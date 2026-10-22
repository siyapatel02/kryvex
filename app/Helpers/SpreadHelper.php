<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;

class SpreadHelper
{
    /**
     * Get active spread settings from admin_settings for a given plan or user.
     */
    public static function getSettings(?string $userUid = null, ?string $planKey = null): array
    {
        try {
            $settings = DB::connection('mongodb')
                ->table('admin_settings')
                ->where('key', 'platform')
                ->first();

            $settingsArr = $settings ? (array) $settings : [];

            // Determine effective plan key (basic, standard, advanced)
            $effectivePlan = 'basic';

            if (!empty($planKey)) {
                $effectivePlan = strtolower(trim($planKey));
            } elseif (!empty($userUid)) {
                $user = DB::connection('mongodb')
                    ->table('users')
                    ->where('_id', $userUid)
                    ->first();

                if (!$user) {
                    $user = DB::connection('mongodb')
                        ->table('users')
                        ->where('uid', $userUid)
                        ->first();
                }

                $userArr = $user ? (array) $user : [];
                $userPlan = $userArr['kryvex_plan'] ?? ($userArr['mt5_plan'] ?? null);

                if (!empty($userPlan)) {
                    $effectivePlan = strtolower(trim($userPlan));
                }
            }

            if (!in_array($effectivePlan, ['cent', 'basic', 'standard', 'advanced'], true)) {
                $effectivePlan = 'basic';
            }

            // Plan-specific default fallback spread values:
            // Cent: 0.08%, Basic: 0.05%, Standard: 0.03%, Advanced: 0.01%
            $defaultValues = [
                'cent'     => 0.08,
                'basic'    => 0.05,
                'standard' => 0.03,
                'advanced' => 0.01,
            ];

            $enabledKey = "plan_spread_{$effectivePlan}_enabled";
            $typeKey    = "plan_spread_{$effectivePlan}_type";
            $valueKey   = "plan_spread_{$effectivePlan}_value";

            $enabled = isset($settingsArr[$enabledKey]) ? (bool) $settingsArr[$enabledKey] : true;
            $type    = (string) ($settingsArr[$typeKey] ?? 'percentage');
            $value   = (isset($settingsArr[$valueKey]) && $settingsArr[$valueKey] !== '' && $settingsArr[$valueKey] !== null)
                ? (float) $settingsArr[$valueKey]
                : (float) ($defaultValues[$effectivePlan] ?? 0.05);

            return [
                'enabled' => $enabled,
                'type'    => $type,
                'value'   => $value,
                'plan'    => $effectivePlan,
            ];
        } catch (\Throwable $e) {
            return [
                'enabled' => true,
                'type'    => 'percentage',
                'value'   => 0.05,
                'plan'    => 'basic',
            ];
        }
    }

    /**
     * Calculate spread-adjusted Buy (Ask) and Sell (Bid) prices based on market LP price.
     */
    public static function calculatePrices(float $marketPrice, ?string $userUid = null, ?string $planKey = null): array
    {
        $settings = self::getSettings($userUid, $planKey);

        if ($marketPrice <= 0 || !$settings['enabled'] || $settings['value'] <= 0) {
            return [
                'market_price'  => $marketPrice,
                'spread_amount' => 0.0,
                'spread_type'   => $settings['type'],
                'spread_value'  => $settings['value'],
                'plan'          => $settings['plan'],
                'buy_price'     => $marketPrice,
                'sell_price'    => $marketPrice,
            ];
        }

        $spreadAmount = 0.0;
        $type = strtolower(trim((string) ($settings['type'] ?? 'percentage')));

        if (in_array($type, ['percentage', 'percent', '%'], true)) {
            $spreadAmount = ($marketPrice * (float) $settings['value']) / 100.0;
        } else {
            // Fixed USDT amount set by Admin
            $spreadAmount = (float) $settings['value'];
        }

        $spreadAmount = round($spreadAmount, 8);
        $buyPrice = round($marketPrice + $spreadAmount, 8);
        $sellPrice = round(max(0.00000001, $marketPrice - $spreadAmount), 8);

        return [
            'market_price'  => $marketPrice,
            'spread_amount' => $spreadAmount,
            'spread_type'   => $settings['type'],
            'spread_value'  => $settings['value'],
            'plan'          => $settings['plan'],
            'buy_price'     => $buyPrice,
            'sell_price'    => $sellPrice,
        ];
    }

    /**
     * Calculate execution price for a specific order side ('buy' or 'sell').
     */
    public static function calculateExecutionPrice(float $marketPrice, string $side, ?string $userUid = null, ?string $planKey = null): float
    {
        $prices = self::calculatePrices($marketPrice, $userUid, $planKey);
        return strtolower($side) === 'buy' ? $prices['buy_price'] : $prices['sell_price'];
    }

    /**
     * Record spread earnings as Admin Revenue in USDT in database.
     */
    public static function recordSpreadRevenue(
        string $userUid,
        string $orderUid,
        string $symbol,
        string $side,
        float $marketPrice,
        float $executionPrice,
        float $spreadAmount,
        float $quantity,
        ?string $planKey = null
    ): bool {
        try {
            if ($quantity <= 0) {
                return false;
            }

            $actualSpreadPerUnit = abs($executionPrice - $marketPrice);
            if ($actualSpreadPerUnit <= 0 && $spreadAmount > 0) {
                $actualSpreadPerUnit = $spreadAmount;
            }

            if ($actualSpreadPerUnit <= 0) {
                return false;
            }

            $revenueUsdt = round($actualSpreadPerUnit * $quantity, 2);

            if ($revenueUsdt <= 0) {
                return false;
            }

            $settings = self::getSettings($userUid, $planKey);

            DB::connection('mongodb')
                ->table('admin_revenues')
                ->insert([
                    'user_uid'        => $userUid,
                    'order_uid'       => $orderUid,
                    'type'            => 'futures_spread_revenue',
                    'category'        => 'spread',
                    'plan'            => $settings['plan'],
                    'symbol'          => strtoupper($symbol),
                    'side'            => strtolower($side),
                    'market_price'    => round($marketPrice, 8),
                    'execution_price' => round($executionPrice, 8),
                    'spread_amount'   => round($actualSpreadPerUnit, 8),
                    'quantity'        => round($quantity, 8),
                    'amount'          => $revenueUsdt,
                    'currency'        => 'USDT',
                    'status'          => 'completed',
                    'description'     => "Futures Spread revenue ({$settings['plan']} plan) from {$side} order on {$symbol}",
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);

            DB::connection('mongodb')
                ->table('platform_revenue_ledgers')
                ->insert([
                    'revenue_uid' => (string) \Illuminate\Support\Str::uuid(),
                    'source'      => 'futures_spread',
                    'type'        => 'futures_spread_revenue',
                    'category'    => 'spread',
                    'market'      => 'futures',
                    'plan'        => $settings['plan'],
                    'user_uid'    => $userUid,
                    'order_uid'   => $orderUid,
                    'symbol'      => strtoupper($symbol),
                    'amount'      => $revenueUsdt,
                    'currency'    => 'USDT',
                    'status'      => 'completed',
                    'description' => "Futures Spread revenue ({$settings['plan']} plan) from {$side} order on {$symbol}",
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);

            return true;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Spread revenue logging exception: " . $e->getMessage());
            return false;
        }
    }
}
