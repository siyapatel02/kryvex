<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RazorpayService
{
    protected string $keyId;
    protected string $keySecret;
    protected string $environment;
    protected bool $enabled;

    public function __construct()
    {
        $settings = null;
        try {
            $settings = DB::connection('mongodb')
                ->table('admin_settings')
                ->where('key', 'platform')
                ->first();
        } catch (\Throwable $e) {
            $settings = null;
        }

        $this->keyId = $settings->razorpay_key_id ?? config('services.razorpay.key_id', env('RAZORPAY_KEY_ID', ''));
        $this->keySecret = $settings->razorpay_key_secret ?? config('services.razorpay.key_secret', env('RAZORPAY_KEY_SECRET', ''));
        $this->environment = strtolower($settings->razorpay_environment ?? config('services.razorpay.environment', env('RAZORPAY_ENV', 'test')));
        $this->enabled = (bool) ($settings->razorpay_enabled ?? env('RAZORPAY_ENABLED', true));
    }

    public function isEnabled(): bool
    {
        return $this->enabled && !empty($this->keyId) && !empty($this->keySecret);
    }

    public function getKeyId(): string
    {
        return $this->keyId;
    }

    /**
     * Create Razorpay Order via REST API.
     */
    public function createOrder(float $amountInInr, string $receiptUid, array $notes = []): array
    {
        if (!$this->isEnabled()) {
            return [
                'success' => false,
                'message' => 'Razorpay Payment Gateway is not configured or enabled in Admin Settings.',
            ];
        }

        try {
            $amountInPaise = (int) round($amountInInr * 100);

            $payload = [
                'amount' => $amountInPaise,
                'currency' => 'INR',
                'receipt' => $receiptUid,
                'notes' => $notes,
            ];

            $response = Http::timeout(10)
                ->withBasicAuth($this->keyId, $this->keySecret)
                ->post('https://api.razorpay.com/v1/orders', $payload);

            $json = $response->json();

            if ($response->successful() && isset($json['id'])) {
                return [
                    'success' => true,
                    'order_id' => $json['id'],
                    'amount' => $amountInInr,
                    'amount_paise' => $amountInPaise,
                    'currency' => 'INR',
                    'key_id' => $this->keyId,
                    'raw' => $json,
                ];
            }

            return [
                'success' => false,
                'message' => $json['error']['description'] ?? 'Failed to create Razorpay Order.',
                'raw' => $json,
            ];
        } catch (\Throwable $e) {
            Log::error('RazorpayService createOrder Exception: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify HMAC SHA256 Signature of Payment Callback.
     */
    public function verifySignature(string $razorpayOrderId, string $razorpayPaymentId, string $razorpaySignature): bool
    {
        if (empty($this->keySecret)) {
            return false;
        }

        $expectedSignature = hash_hmac(
            'sha256',
            $razorpayOrderId . '|' . $razorpayPaymentId,
            $this->keySecret
        );

        return hash_equals($expectedSignature, $razorpaySignature);
    }
}
