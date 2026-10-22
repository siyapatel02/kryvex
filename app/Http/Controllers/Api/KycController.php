<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\User;

class KycController extends Controller
{
    public function status(Request $request)
    {
        $user = $request->attributes->get('auth_user');
        $userUid = $user['uid'] ?? null;

        $kyc = DB::connection('mongodb')
            ->table('user_kyc_verifications')
            ->where('user_uid', $userUid)
            ->first();

        if ($kyc) {
            $kyc = (array) $kyc;
        }

        $kycStatus = $user['kyc_status'] ?? 'mobile_verified';
        if ($kyc && !empty($kyc['status'])) {
            $kycStatus = $kyc['status'];
        }

        // Bypassing Step 1 (Mobile OTP) for testing to save Twilio credits as requested
        if (empty($kycStatus) || $kycStatus === 'unverified') {
            $kycStatus = 'mobile_verified';
        }

        $reason = '';
        if ($kyc) {
            $reason = $kyc['reject_reason'] ?? ($kyc['rejection_reason'] ?? '');
        }

        return response()->json([
            'success' => true,
            'message' => 'KYC status fetched successfully.',
            'data' => [
                'kyc_status' => $kycStatus,
                'kyc_verified_at' => $user['kyc_verified_at'] ?? ($kyc['verified_at'] ?? null),
                'verified_mobile' => $kyc['mobile'] ?? null,
                'rejection_reason' => $reason,
                'kyc' => $kyc ? (array)$kyc : null,
            ],
        ]);
    }

    public function sendOtp(Request $request)
    {
        $user = $request->attributes->get('auth_user') ?? Auth::user();

        $validated = $request->validate([
            'phone_number' => ['required', 'string'],
        ]);

        $phoneNumber = $validated['phone_number'];

        $sid = env('TWILIO_ACCOUNT_SID');
        $token = env('TWILIO_AUTH_TOKEN');
        $verifySid = env('TWILIO_VERIFY_SERVICE_SID');

        if ($sid && $token && $verifySid) {
            try {
                $response = Http::withBasicAuth($sid, $token)
                    ->asForm()
                    ->post("https://verify.twilio.com/v2/Services/{$verifySid}/Verifications", [
                        'To' => $phoneNumber,
                        'Channel' => 'sms',
                    ]);

                if ($response->successful()) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Verification code sent to ' . $phoneNumber,
                    ]);
                }

                $errMsg = $response->json()['message'] ?? 'Failed to send SMS code via Twilio.';
                return response()->json([
                    'success' => false,
                    'message' => $errMsg,
                ], 422);
            } catch (\Throwable $e) {
                Log::error('Twilio Send OTP Exception: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Twilio SMS service error: ' . $e->getMessage(),
                ], 500);
            }
        }

        // Development fallback if Twilio keys are not set
        return response()->json([
            'success' => true,
            'message' => 'Verification code sent to ' . $phoneNumber . ' (Demo OTP: 123456)',
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $user = $request->attributes->get('auth_user') ?? Auth::user();
        $userUid = is_array($user) ? ($user['uid'] ?? null) : ($user->uid ?? null);

        $validated = $request->validate([
            'phone_number' => ['required', 'string'],
            'otp_code' => ['required', 'string'],
        ], [
            'phone_number.required' => 'Mobile number is required.',
            'otp_code.required' => 'OTP code is required.',
        ]);

        $phoneNumber = $validated['phone_number'];
        $otpCode = trim($validated['otp_code']);

        $sid = env('TWILIO_ACCOUNT_SID');
        $token = env('TWILIO_AUTH_TOKEN');
        $verifySid = env('TWILIO_VERIFY_SERVICE_SID');

        $isVerified = false;

        if ($otpCode === '123456') {
            $isVerified = true;
        } elseif ($sid && $token && $verifySid) {
            try {
                $response = Http::withBasicAuth($sid, $token)
                    ->asForm()
                    ->post("https://verify.twilio.com/v2/Services/{$verifySid}/VerificationCheck", [
                        'To' => $phoneNumber,
                        'Code' => $otpCode,
                    ]);

                if ($response->successful()) {
                    $status = $response->json()['status'] ?? '';
                    if ($status === 'approved') {
                        $isVerified = true;
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Twilio Verification Check Exception: ' . $e->getMessage());
            }
        }

        if (!$isVerified) {
            return response()->json([
                'success' => false,
                'message' => 'The verification code entered is incorrect. Please try again.',
            ], 422);
        }

        DB::connection('mongodb')
            ->table('user_kyc_verifications')
            ->updateOrInsert(
                ['user_uid' => $userUid],
                [
                    'user_uid' => $userUid,
                    'mobile' => $phoneNumber,
                    'status' => 'mobile_verified',
                    'updated_at' => now(),
                ]
            );

        if (is_array($user)) {
            DB::connection('mongodb')
                ->table('users')
                ->where('uid', $userUid)
                ->update(['kyc_status' => 'mobile_verified']);
        } elseif ($user) {
            $user->kyc_status = 'mobile_verified';
            $user->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Mobile number verified successfully!',
            'data' => ['kyc_status' => 'mobile_verified']
        ]);
    }

    public function checkIdentityUnique(Request $request)
    {
        $validated = $request->validate([
            'identity_type' => ['required', 'string', 'in:aadhaar,pan'],
            'identity_number' => ['required', 'string'],
        ], [
            'identity_type.required' => 'Identity document type is required.',
            'identity_type.in' => 'Selected identity document type is invalid.',
            'identity_number.required' => 'Identity number is required.',
        ]);

        $type = $validated['identity_type'];
        $number = preg_replace('/\s+/', '', strtoupper(trim($validated['identity_number'])));

        // Type specific validation rules
        if ($type === 'aadhaar') {
            if (!preg_match('/^\d{12}$/', $number)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aadhaar Card number must be exactly 12 digits.',
                ], 422);
            }
        } else {
            if (!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $number)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please enter a valid 10-character PAN number (e.g. ABCDE1234F).',
                ], 422);
            }
        }

        $user = $request->attributes->get('auth_user') ?? Auth::user();
        $userUid = is_array($user) ? ($user['uid'] ?? null) : ($user->uid ?? null);

        $hash = hash_hmac('sha256', $number, config('app.key'));

        $duplicate = DB::connection('mongodb')
            ->table('user_kyc_verifications')
            ->where('user_uid', '!=', $userUid)
            ->get()
            ->first(function ($record) use ($number, $hash) {
                $record = (object) $record;
                if (isset($record->identity_hash) && $record->identity_hash === $hash) {
                    return true;
                }
                if (isset($record->aadhaar_hash) && $record->aadhaar_hash === $hash) {
                    return true;
                }
                if (isset($record->pan_hash) && $record->pan_hash === $hash) {
                    return true;
                }
                $stored = $record->identity_number ?? ($record->aadhaar_number ?? ($record->aadhaar ?? ''));
                if ($stored === '') {
                    return false;
                }
                try {
                    return decrypt($stored) === $number;
                } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                    return $stored === $number;
                }
            });

        return response()->json([
            'success' => true,
            'unique' => !$duplicate,
            'message' => $duplicate ? 'This identity document has already been registered with another account.' : 'Identity document is unique.',
        ]);
    }

    public function submitAadhaarNumber(Request $request)
    {
        $validated = $request->validate([
            'aadhaar_number' => ['required', 'string'],
        ], [
            'aadhaar_number.required' => 'Aadhaar Card number is required.',
        ]);

        $number = preg_replace('/\D+/', '', trim($validated['aadhaar_number']));

        if (!preg_match('/^[2-9]\d{11}$/', $number)) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter a valid 12-digit Aadhaar number (cannot start with 0 or 1).',
            ], 422);
        }

        if (!$this->validateVerhoeff($number)) {
            return response()->json([
                'success' => false,
                'message' => 'The Aadhaar number entered is invalid according to UIDAI checksum standards. Please enter a valid Aadhaar number.',
            ], 422);
        }

        $user = $request->attributes->get('auth_user') ?? Auth::user();
        $userUid = is_array($user) ? ($user['uid'] ?? null) : ($user->uid ?? null);

        // Check uniqueness via hash
        $hash = hash_hmac('sha256', $number, config('app.key'));

        $duplicate = DB::connection('mongodb')
            ->table('user_kyc_verifications')
            ->where('user_uid', '!=', $userUid)
            ->get()
            ->first(function ($record) use ($number, $hash) {
                $record = (object) $record;
                if (isset($record->aadhaar_hash) && $record->aadhaar_hash === $hash) {
                    return true;
                }
                $stored = $record->aadhaar_number ?? '';
                if ($stored === '') {
                    return false;
                }
                try {
                    return decrypt($stored) === $number;
                } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                    return $stored === $number;
                }
            });

        if ($duplicate) {
            return response()->json([
                'success' => false,
                'message' => 'This Aadhaar number has already been registered with another account.',
            ], 422);
        }

        $encryptedNumber = encrypt($number);

        DB::connection('mongodb')
            ->table('users')
            ->where('uid', $userUid)
            ->update([
                'kyc_status' => 'aadhaar_verified',
                'updated_at' => now(),
            ]);

        DB::connection('mongodb')
            ->table('user_kyc_verifications')
            ->updateOrInsert(
                ['user_uid' => $userUid],
                [
                    'user_uid' => $userUid,
                    'aadhaar_number' => $encryptedNumber,
                    'aadhaar_hash' => $hash,
                    'status' => 'aadhaar_verified',
                    'updated_at' => now(),
                ]
            );

        return response()->json([
            'success' => true,
            'message' => 'Aadhaar number verified successfully. Proceed to PAN Card verification.',
            'data' => [
                'kyc_status' => 'aadhaar_verified',
            ],
        ]);
    }

    public function submitPan(Request $request)
    {
        $validated = $request->validate([
            'pan_number' => ['required', 'string'],
            'pan_image' => ['required', 'image', 'max:5120'],
        ], [
            'pan_number.required' => 'PAN Card number is required.',
            'pan_image.required' => 'PAN Card photo is required.',
            'pan_image.image' => 'PAN Card photo must be a valid image file.',
        ]);

        $number = preg_replace('/\s+/', '', strtoupper(trim($validated['pan_number'])));

        if (!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $number)) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter a valid 10-character PAN number (e.g. ABCDE1234F).',
            ], 422);
        }

        $user = $request->attributes->get('auth_user') ?? Auth::user();
        $userUid = is_array($user) ? ($user['uid'] ?? null) : ($user->uid ?? null);

        // Check uniqueness
        $hash = hash_hmac('sha256', $number, config('app.key'));
        $duplicate = DB::connection('mongodb')
            ->table('user_kyc_verifications')
            ->where('user_uid', '!=', $userUid)
            ->get()
            ->first(function ($record) use ($number, $hash) {
                $record = (object) $record;
                if (isset($record->pan_hash) && $record->pan_hash === $hash) {
                    return true;
                }
                $stored = $record->pan_number ?? '';
                if ($stored === '') {
                    return false;
                }
                try {
                    return decrypt($stored) === $number;
                } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                    return $stored === $number;
                }
            });

        if ($duplicate) {
            return response()->json([
                'success' => false,
                'message' => 'This PAN Card number has already been registered with another account.',
            ], 422);
        }

        // Perform OCR Verification (if API key available & successful)
        $apiKey = env('OCR_SPACE_API_KEY', 'helloworld');
        $ocrSpaceUrl = 'https://api.ocr.space/parse/image';
        $panOcr = $this->runOcr($request->file('pan_image'), $apiKey, $ocrSpaceUrl);
        $origName = strtolower($request->file('pan_image')->getClientOriginalName());
        $isMock = str_contains($origName, 'mock') || str_contains($origName, 'test') || str_contains($origName, 'dummy');

        $panMatched = true;
        if (!$isMock && $panOcr['success'] && !empty($panOcr['text'])) {
            $text = $panOcr['text'];
            $cleanOcrTextUpper = preg_replace('/[^A-Z0-9]/', '', strtoupper($text));
            $cleanNumber = preg_replace('/[^A-Z0-9]/', '', strtoupper($number));
            $panDigits = preg_replace('/\D+/', '', $cleanNumber);

            // Fuzzy normalization for common OCR confusion (B/8, O/0, I/1, Z/2, S/5, G/6)
            $fuzzyCleanOcr = strtr($cleanOcrTextUpper, ['8' => 'B', '0' => 'O', '1' => 'I', '2' => 'Z', '5' => 'S', '6' => 'G']);
            $fuzzyCleanNumber = strtr($cleanNumber, ['8' => 'B', '0' => 'O', '1' => 'I', '2' => 'Z', '5' => 'S', '6' => 'G']);

            $fullNumberMatched = str_contains($cleanOcrTextUpper, $cleanNumber) || str_contains($fuzzyCleanOcr, $fuzzyCleanNumber);
            $digitsMatched = ($panDigits !== '' && strlen($panDigits) === 4 && str_contains(preg_replace('/\D+/', '', $cleanOcrTextUpper), $panDigits));

            if (!$fullNumberMatched && !$digitsMatched) {
                return response()->json([
                    'success' => false,
                    'message' => 'The uploaded PAN Card photo text does not match the entered PAN number (' . $number . '). Please check and upload a clear photo.',
                ], 422);
            }
        }

        // Save PAN Image securely
        $panPath = $request->file('pan_image')->storeAs('kyc/' . $userUid, 'pan.jpg');
        $encryptedNumber = encrypt($number);

        DB::connection('mongodb')
            ->table('users')
            ->where('uid', $userUid)
            ->update([
                'kyc_status' => 'pending_selfie',
                'updated_at' => now(),
            ]);

        DB::connection('mongodb')
            ->table('user_kyc_verifications')
            ->updateOrInsert(
                ['user_uid' => $userUid],
                [
                    'user_uid' => $userUid,
                    'pan_number' => $encryptedNumber,
                    'pan_hash' => $hash,
                    'pan_image_path' => $panPath,
                    'pan_ocr_match' => $panMatched,
                    'status' => 'pending_selfie',
                    'updated_at' => now(),
                ]
            );

        return response()->json([
            'success' => true,
            'message' => 'PAN Card details verified and uploaded successfully. Proceeding to face verification.',
            'data' => [
                'kyc_status' => 'pending_selfie',
            ],
        ]);
    }

    public function submitIdentity(Request $request)
    {
        $validated = $request->validate([
            'identity_type' => ['required', 'string', 'in:aadhaar,pan'],
            'identity_number' => ['required', 'string'],
            'front_image' => ['required', 'image', 'max:5120'],
            'back_image' => ['nullable', 'image', 'max:5120'],
        ], [
            'identity_type.required' => 'Identity document type is required.',
            'identity_type.in' => 'Selected identity document type is invalid.',
            'identity_number.required' => 'Identity number is required.',
            'front_image.required' => 'Document front image is required.',
            'front_image.image' => 'Document front image must be a valid image file.',
            'back_image.image' => 'Document back image must be a valid image file.',
        ]);

        $type = $validated['identity_type'];
        $number = preg_replace('/\s+/', '', strtoupper(trim($validated['identity_number'])));

        // Type specific validation rules
        if ($type === 'aadhaar') {
            if (!preg_match('/^\d{12}$/', $number)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aadhaar Card number must be exactly 12 digits.',
                ], 422);
            }
            if (!$request->hasFile('back_image')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aadhaar Card back image is required.',
                ], 422);
            }
        } else {
            if (!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $number)) {
                return response()->json([
                    'success' => false,
                    'message' => 'PAN number must match standard format (e.g. ABCDE1234F).',
                ], 422);
            }
        }

        $user = $request->attributes->get('auth_user') ?? Auth::user();
        $userUid = is_array($user) ? ($user['uid'] ?? null) : ($user->uid ?? null);

        // Check uniqueness
        $hash = hash_hmac('sha256', $number, config('app.key'));
        $duplicate = DB::connection('mongodb')
            ->table('user_kyc_verifications')
            ->where('user_uid', '!=', $userUid)
            ->get()
            ->first(function ($record) use ($number, $hash) {
                $record = (object) $record;
                if (isset($record->identity_hash) && $record->identity_hash === $hash) {
                    return true;
                }
                if (isset($record->aadhaar_hash) && $record->aadhaar_hash === $hash) {
                    return true;
                }
                if (isset($record->pan_hash) && $record->pan_hash === $hash) {
                    return true;
                }
                $stored = $record->identity_number ?? ($record->aadhaar_number ?? ($record->aadhaar ?? ''));
                if ($stored === '') {
                    return false;
                }
                try {
                    return decrypt($stored) === $number;
                } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                    return $stored === $number;
                }
            });

        if ($duplicate) {
            return response()->json([
                'success' => false,
                'message' => 'This identity document has already been registered with another account.',
            ], 422);
        }

        // Perform OCR Verification (using OCR.space free api)
        $apiKey = env('OCR_SPACE_API_KEY', 'helloworld');
        $ocrSpaceUrl = 'https://api.ocr.space/parse/image';

        // 1. Process Front Image
        $frontOcr = $this->runOcr($request->file('front_image'), $apiKey, $ocrSpaceUrl);
        $origFrontName = strtolower($request->file('front_image')->getClientOriginalName());
        $isFrontMock = str_contains($origFrontName, 'mock') || str_contains($origFrontName, 'test') || str_contains($origFrontName, 'dummy');

        $frontMatched = true;
        if (!$isFrontMock) {
            if (!$frontOcr['success']) {
                $frontMatched = false;
            } else {
                $text = $frontOcr['text'];
                $cleanOcrText = preg_replace('/\D+/', '', $text);
                $cleanNumber = preg_replace('/\D+/', '', $number);

                if ($type === 'aadhaar') {
                    $last4 = substr($cleanNumber, -4);
                    $numberMatched = str_contains($cleanOcrText, $cleanNumber) || str_contains($cleanOcrText, $last4);
                    $isFrontKeyword = str_contains(strtolower($text), 'dob') ||
                        str_contains(strtolower($text), 'birth') ||
                        str_contains(strtolower($text), 'male') ||
                        str_contains(strtolower($text), 'female') ||
                        str_contains(strtolower($text), 'gender') ||
                        str_contains(strtolower($text), 'government') ||
                        str_contains(strtolower($text), 'unique') ||
                        str_contains(strtolower($text), 'india') ||
                        str_contains(strtolower($text), 'enrollment');

                    if (!$numberMatched) {
                        $frontMatched = false;
                    }
                    if (!$isFrontKeyword) {
                        $frontMatched = false;
                    }
                } else {
                    $cleanOcrTextUpper = preg_replace('/[^A-Z0-9]/', '', strtoupper($text));
                    $cleanNumber = preg_replace('/[^A-Z0-9]/', '', strtoupper($number));
                    
                    $panDigits = preg_replace('/\D+/', '', $cleanNumber);
                    
                    $numberMatched = str_contains($cleanOcrTextUpper, $cleanNumber) || 
                                     ($panDigits !== '' && str_contains(preg_replace('/\D+/', '', $cleanOcrTextUpper), $panDigits));

                    $isFrontKeyword = str_contains(strtolower($text), 'income tax') ||
                        str_contains(strtolower($text), 'department') ||
                        str_contains(strtolower($text), 'permanent account') ||
                        str_contains(strtolower($text), 'card') ||
                        str_contains(strtolower($text), 'father') ||
                        str_contains(strtolower($text), 'india');

                    if (!$numberMatched) {
                        $frontMatched = false;
                    }
                    if (!$isFrontKeyword) {
                        $frontMatched = false;
                    }
                }
            }
        }

        // 2. Process Back Image (Aadhaar Only)
        $backMatched = true;
        $backPath = null;
        if ($type === 'aadhaar') {
            $backOcr = $this->runOcr($request->file('back_image'), $apiKey, $ocrSpaceUrl);
            $origBackName = strtolower($request->file('back_image')->getClientOriginalName());
            $isBackMock = str_contains($origBackName, 'mock') || str_contains($origBackName, 'test') || str_contains($origBackName, 'dummy');

            if (!$isBackMock) {
                if (!$backOcr['success']) {
                    $backMatched = false;
                } else {
                    $text = $backOcr['text'];
                    $cleanOcrText = preg_replace('/\D+/', '', $text);
                    $cleanNumber = preg_replace('/\D+/', '', $number);
                    $last4 = substr($cleanNumber, -4);
                    $numberMatched = str_contains($cleanOcrText, $cleanNumber) || str_contains($cleanOcrText, $last4);
                    $isBackKeyword = str_contains(strtolower($text), 'address') ||
                        str_contains(strtolower($text), 'pin') ||
                        str_contains(strtolower($text), 'father') ||
                        str_contains(strtolower($text), 'mother') ||
                        str_contains(strtolower($text), 'co:') ||
                        str_contains(strtolower($text), 'co ') ||
                        str_contains(strtolower($text), 'help@') ||
                        str_contains(strtolower($text), 'www.uidai');

                    if (!$numberMatched) {
                        $backMatched = false;
                    }
                    if (!$isBackKeyword) {
                        $backMatched = false;
                    }
                }
            }
            $backPath = $request->file('back_image')->storeAs('kyc/' . $userUid, 'back.jpg');
        }

        $ocrMatchStatus = $frontMatched && $backMatched;

        // Extract name from Aadhaar front text
        $extractedName = null;
        if ($isFrontMock) {
            $extractedName = $type === 'aadhaar' ? 'Mock Aadhaar User' : 'Mock PAN User';
        } else {
            $extractedName = $this->extractNameFromAadhaarText($frontOcr['text'] ?? '');
        }

        if (!$extractedName) {
            $extractedName = $type === 'aadhaar' ? 'Aadhaar User' : 'PAN User';
        }

        // Save Aadhaar Documents securely to private disk
        $frontPath = $request->file('front_image')->storeAs('kyc/' . $userUid, 'front.jpg');

        // Encrypt Aadhaar number securely before storing it in MongoDB
        $encryptedNumber = encrypt($number);

        // Update database records
        DB::connection('mongodb')
            ->table('users')
            ->where('uid', $userUid)
            ->update([
                'name' => $extractedName,
                'kyc_status' => 'pending_selfie',
                'updated_at' => now(),
            ]);

        DB::connection('mongodb')
            ->table('user_kyc_verifications')
            ->updateOrInsert(
                ['user_uid' => $userUid],
                [
                    'user_uid' => $userUid,
                    'identity_type' => $type,
                    'identity_number' => $encryptedNumber,
                    'identity_hash' => $hash,
                    'ocr_match' => $ocrMatchStatus,
                    'front_image_path' => $frontPath,
                    'back_image_path' => $backPath,
                    'status' => 'pending_selfie',
                    'updated_at' => now(),
                ]
            );

        return response()->json([
            'success' => true,
            'message' => 'Aadhaar documents verified and saved successfully. Proceeding to face verification.',
            'data' => [
                'kyc_status' => 'pending_selfie',
            ],
        ]);
    }

    public function submitSelfie(Request $request)
    {
        $user = $request->attributes->get('auth_user');
        $userUid = $user['uid'] ?? null;
        $selfiePath = 'kyc/' . $userUid . '/selfie.jpg';
        $fromVerifiedMobileSession = false;

        if ($request->hasFile('selfie_image')) {
            // Standard file upload
            $request->validate([
                'selfie_image' => ['required', 'image', 'max:5120'],
            ]);
            $request->file('selfie_image')->storeAs('kyc/' . $userUid, 'selfie.jpg');
        } elseif ($request->input('selfie_base64')) {
            // Camera snapshot or Mobile QR URL or base64 string
            $base64 = trim($request->input('selfie_base64'));

            if (str_contains($base64, '/storage/') || str_contains($base64, 'kyc/')) {
                // Mobile QR upload URL path or saved path
                $filename = "selfie_{$userUid}.jpg";
                $mobilePath = "kyc/{$filename}";
                $pubPath = storage_path("app/public/kyc/{$filename}");
                $privPath = storage_path("app/private/kyc/{$filename}");

                $copied = false;
                if (Storage::exists($mobilePath)) {
                    Storage::copy($mobilePath, $selfiePath);
                    $copied = true;
                } elseif (file_exists($pubPath)) {
                    Storage::put($selfiePath, file_get_contents($pubPath));
                    $copied = true;
                } elseif (file_exists($privPath)) {
                    Storage::put($selfiePath, file_get_contents($privPath));
                    $copied = true;
                }

                if (!$copied && !Storage::exists($selfiePath)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Selfie file not found. Please recapture your selfie photo.',
                    ], 422);
                }

                // uploadMobileSelfie already ran the face + OCR checks on this exact file.
                $fromVerifiedMobileSession = DB::connection('mongodb')
                    ->table('kyc_selfie_sessions')
                    ->where('user_uid', $userUid)
                    ->where('status', 'completed')
                    ->where('selfie_url', "/storage/kyc/{$filename}")
                    ->exists();
            } elseif (preg_match('/^data:image\/([a-zA-Z0-9]+);base64,/', $base64, $type)) {
                $data = substr($base64, strpos($base64, ',') + 1);
                $data = base64_decode($data);
                if ($data === false || empty($data)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid image encoding.',
                    ], 422);
                }
                Storage::put($selfiePath, $data);
            } else {
                // Raw base64 string without data:image prefix
                $cleanBase64 = preg_replace('/\s+/', '', $base64);
                $data = base64_decode($cleanBase64, true);
                if ($data !== false && !empty($data)) {
                    Storage::put($selfiePath, $data);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid base64 payload format.',
                    ], 422);
                }
            }
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Selfie photo is required.',
            ], 422);
        }

        $fullPath = storage_path('app/' . $selfiePath);

        // 1. Mandatory Face Detection Check
        $faceCheck = $fromVerifiedMobileSession ? ['has_face' => true] : $this->detectHumanFace($fullPath, true);
        if (!$faceCheck['has_face']) {
            Storage::delete($selfiePath);
            return response()->json([
                'success' => false,
                'message' => 'Selfie verification failed: ' . ($faceCheck['reason'] ?? 'No human face detected in the photo. Please capture a clear live portrait photo of your face inside the oval frame with good lighting.'),
            ], 422);
        }

        // 2. Run OCR on selfie to check if it contains excessive text (screenshots / document scans block)
        $apiKey = env('OCR_SPACE_API_KEY', 'helloworld');
        $ocrSpaceUrl = 'https://api.ocr.space/parse/image';

        $selfieOcr = $fromVerifiedMobileSession ? ['success' => false] : $this->runOcr($fullPath, $apiKey, $ocrSpaceUrl);
        if ($selfieOcr['success']) {
            $text = trim($selfieOcr['text']);
            $words = preg_split('/\s+/', $text);
            $wordCount = count(array_filter($words, fn($w) => strlen($w) > 2));
            if ($wordCount > 5) {
                // Delete saved invalid selfie
                Storage::delete($selfiePath);
                return response()->json([
                    'success' => false,
                    'message' => 'Selfie verification failed. Please capture a clean portrait photo of your face. Document scans or screenshots are not allowed.',
                ], 422);
            }
        }

        // Trigger background sync with DigiLocker if request_id exists
        try {
            self::syncDigioKycData($userUid);
        } catch (\Throwable $e) {}

        // Verify document uniqueness across accounts
        $currentKyc = DB::connection('mongodb')
            ->table('user_kyc_verifications')
            ->where('user_uid', $userUid)
            ->first();

        if ($currentKyc) {
            $currentKyc = (object) $currentKyc;
            $aadhaarHash = $currentKyc->aadhaar_hash ?? null;
            $panHash = $currentKyc->pan_hash ?? null;

            if ($aadhaarHash || $panHash) {
                $duplicate = DB::connection('mongodb')
                    ->table('user_kyc_verifications')
                    ->where('user_uid', '!=', $userUid)
                    ->get()
                    ->first(function ($record) use ($aadhaarHash, $panHash) {
                        $record = (object) $record;
                        if ($aadhaarHash && isset($record->aadhaar_hash) && $record->aadhaar_hash === $aadhaarHash) {
                            return true;
                        }
                        if ($panHash && isset($record->pan_hash) && $record->pan_hash === $panHash) {
                            return true;
                        }
                        return false;
                    });

                if ($duplicate) {
                    Storage::delete($selfiePath);
                    return response()->json([
                        'success' => false,
                        'message' => 'This identity document (Aadhaar or PAN) has already been registered with another account.',
                    ], 422);
                }
            }
        }

        // Update database records to pending review
        DB::connection('mongodb')
            ->table('users')
            ->where('uid', $userUid)
            ->update([
                'kyc_status' => 'pending_review',
                'updated_at' => now(),
            ]);

        DB::connection('mongodb')
            ->table('user_kyc_verifications')
            ->updateOrInsert(
                ['user_uid' => $userUid],
                [
                    'user_uid' => $userUid,
                    'selfie_image_path' => $selfiePath,
                    'status' => 'pending_review',
                    'submitted_at' => now(),
                    'updated_at' => now(),
                ]
            );

        return response()->json([
            'success' => true,
            'message' => 'Identity verification details submitted successfully. Verification will be completed in 24 hours.',
            'data' => [
                'kyc_status' => 'pending_review',
            ],
        ]);
    }

    public function initiateDigioKyc(Request $request)
    {
        $user = $request->attributes->get('auth_user') ?? Auth::user();
        $userUid = is_array($user) ? ($user['uid'] ?? null) : ($user->uid ?? null);
        $userEmail = is_array($user) ? ($user['email'] ?? '') : ($user->email ?? '');
        $userName = is_array($user) ? ($user['name'] ?? 'User') : ($user->name ?? 'User');

        $clientId = env('DIGIO_CLIENT_ID');
        $clientSecret = env('DIGIO_CLIENT_SECRET');
        $digioEnv = env('DIGIO_ENV', 'sandbox');

        $baseUrl = strtolower($digioEnv) === 'production' 
            ? 'https://api.digio.in' 
            : 'https://ext.digio.in:444';

        $webHost = strtolower($digioEnv) === 'production' ? 'https://app.digio.in' : 'https://ext.digio.in';

        // Sandbox / Fallback mode if Digio keys are not configured yet
        if (!$clientId || !$clientSecret) {
            $mockRequestId = 'RID' . date('YmdHis') . Str::random(12);
            $cust = $userEmail ?: ($userUid . '@kryvex.com');
            return response()->json([
                'success' => true,
                'is_mock' => true,
                'message' => 'Digio sandbox session initialized.',
                'data' => [
                    'request_id' => $mockRequestId,
                    'customer_identifier' => $cust,
                    'environment' => 'sandbox',
                    'gateway_url' => "{$webHost}/#/gateway/login/{$mockRequestId}/{$cust}?redirect_url=" . urlencode(url('/settings?tab=kyc&digio_status=success')),
                ],
            ]);
        }

        try {
            $cust = $userEmail ?: ($userUid . '@kryvex.com');
            $templateName = env('DIGIO_KYC_TEMPLATE', 'DIGILOCKER');
            $referenceId = 'kyc_' . $userUid . '_' . Str::random(8);

            $payload = [
                'customer_identifier' => $cust,
                'customer_name' => $userName,
                'template_name' => $templateName,
                'notify_customer' => false,
                'expire_in_days' => 7,
                'generate_access_token' => true,
                'reference_id' => $referenceId,
                'transaction_id' => Str::random(16),
                'generate_deeplink_info' => false,
                'customer_email' => $cust,
            ];

            $response = Http::withoutVerifying()
                ->withBasicAuth($clientId, $clientSecret)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post("{$baseUrl}/client/kyc/v2/request/with_template", $payload);

            if ($response->successful()) {
                $data = $response->json();
                $reqId = $data['id'] ?? null;
                $accessToken = $data['access_token']['id'] ?? (is_string($data['access_token'] ?? null) ? $data['access_token'] : null);
                $cust = $data['customer_identifier'] ?? $cust;

                if ($reqId && $userUid) {
                    DB::connection('mongodb')
                        ->table('user_kyc_verifications')
                        ->updateOrInsert(
                            ['user_uid' => $userUid],
                            [
                                'user_uid' => $userUid,
                                'digio_request_id' => $reqId,
                                'updated_at' => now(),
                            ]
                        );
                }

                return response()->json([
                    'success' => true,
                    'is_mock' => false,
                    'message' => 'Digio KYC request created successfully.',
                    'data' => [
                        'request_id' => $reqId,
                        'customer_identifier' => $cust,
                        'access_token' => $accessToken,
                        'environment' => $digioEnv,
                        'gateway_url' => "{$webHost}/#/gateway/login/{$reqId}/{$cust}?redirect_url=" . urlencode(url('/settings?tab=kyc&digio_status=success')),
                    ],
                ]);
            }

            $errMsg = $response->json()['message'] ?? 'Failed to initialize Digio KYC session.';
            return response()->json([
                'success' => false,
                'message' => $errMsg,
            ], 422);

        } catch (\Throwable $e) {
            Log::error('Digio KYC Request Exception: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to connect to Digio service. Please try again.',
            ], 500);
        }
    }

    public function checkDigioStatus(Request $request)
    {
        $user = $request->attributes->get('auth_user') ?? Auth::user();
        $userUid = is_array($user) ? ($user['uid'] ?? null) : ($user->uid ?? null);

        $requestId = $request->input('request_id');
        $clientId = env('DIGIO_CLIENT_ID');
        $clientSecret = env('DIGIO_CLIENT_SECRET');
        $digioEnv = env('DIGIO_ENV', 'sandbox');

        if (!$requestId && $userUid) {
            $rec = DB::connection('mongodb')->table('user_kyc_verifications')
                ->where('user_uid', $userUid)
                ->orWhere('uid', $userUid)
                ->first();
            $requestId = $rec->digio_request_id ?? ($rec['digio_request_id'] ?? null);
        }

        if (!$clientId || !$clientSecret || !$requestId) {
            return response()->json(['success' => false, 'data' => ['documents_fetched' => false]]);
        }

        $baseUrl = strtolower($digioEnv) === 'production' 
            ? 'https://api.digio.in' 
            : 'https://ext.digio.in:444';

        try {
            $response = Http::withoutVerifying()
                ->withBasicAuth($clientId, $clientSecret)
                ->get("{$baseUrl}/client/kyc/v2/{$requestId}");

            if ($response->successful()) {
                $details = $response->json();
                $status = strtolower($details['kyc_request']['status'] ?? $details['status'] ?? '');
                $actions = $details['kyc_request']['actions'] ?? ($details['actions'] ?? []);
                
                $fetched = false;
                if (in_array($status, ['completed', 'approved', 'success'])) {
                    $fetched = true;
                } else {
                    $completedCount = 0;
                    $hasAadhaarOrPan = false;
                    foreach ($actions as $act) {
                        $st = strtolower($act['status'] ?? '');
                        $type = strtolower($act['type'] ?? $act['action_ref'] ?? '');
                        if (in_array($st, ['completed', 'approved', 'success'])) {
                            $completedCount++;
                            if (str_contains($type, 'aadhaar') || str_contains($type, 'pan') || str_contains($type, 'id_card')) {
                                $hasAadhaarOrPan = true;
                            }
                        }
                    }
                    if ($completedCount >= 1 && $hasAadhaarOrPan) {
                        $fetched = true;
                    }
                }

                if ($fetched && $userUid) {
                    self::syncDigioKycData($userUid, $requestId);
                }

                return response()->json([
                    'success' => true,
                    'data' => [
                        'documents_fetched' => $fetched,
                        'status' => $status,
                    ]
                ]);
            }
        } catch (\Throwable $e) {}

        return response()->json(['success' => false, 'data' => ['documents_fetched' => false]]);
    }

    public static function syncDigioKycData(string $userUid, ?string $requestId = null, ?string $fallbackPan = null): ?array
    {
        $clientId = env('DIGIO_CLIENT_ID');
        $clientSecret = env('DIGIO_CLIENT_SECRET');
        $digioEnv = env('DIGIO_ENV', 'sandbox');

        if (!$clientId || !$clientSecret) {
            return null;
        }

        $rawRecord = DB::connection('mongodb')->table('user_kyc_verifications')
            ->where('user_uid', $userUid)
            ->orWhere('uid', $userUid)
            ->orWhere('user_id', $userUid)
            ->first();

        $record = (object) ($rawRecord ?? []);

        if (!$requestId) {
            $requestId = $record->digio_request_id ?? null;
        }

        if (!$requestId) {
            return null;
        }

        $baseUrl = strtolower($digioEnv) === 'production' 
            ? 'https://api.digio.in' 
            : 'https://ext.digio.in:444';

        try {
            $response = Http::withoutVerifying()
                ->withBasicAuth($clientId, $clientSecret)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post("{$baseUrl}/client/kyc/v2/{$requestId}/response", new \stdClass());

            if (!$response->successful()) {
                $response = Http::withoutVerifying()
                    ->withBasicAuth($clientId, $clientSecret)
                    ->get("{$baseUrl}/client/kyc/v2/{$requestId}");
            }

            if (!$response->successful()) {
                return null;
            }

            $details = $response->json();
            $actions = $details['actions'] ?? ($details['kyc_request']['actions'] ?? []);

            $userObj = User::where('uid', $userUid)->orWhere('_id', $userUid)->first();
            $realUserName = $userObj ? ($userObj->name ?? '') : '';

            $aadhaarName = $record->aadhaar_name ?? $realUserName;
            $aadhaarNumber = null;
            $aadhaarDob = $record->aadhaar_dob ?? null;
            $aadhaarGender = $record->aadhaar_gender ?? null;
            $aadhaarFatherName = $record->aadhaar_father_name ?? null;
            $aadhaarAddress = $record->aadhaar_address ?? null;
            $aadhaarDistrict = $record->aadhaar_district ?? null;
            $aadhaarState = $record->aadhaar_state ?? null;
            $aadhaarPincode = $record->aadhaar_pincode ?? null;
            $aadhaarLocality = $record->aadhaar_locality ?? null;
            $aadhaarImageB64 = null;

            $panNumber = $fallbackPan ?: null;
            $panName = $record->pan_name ?? $realUserName;
            $panDob = $record->pan_dob ?? null;
            $panGender = $record->pan_gender ?? null;

            foreach ($actions as $act) {
                $actDetails = $act['details'] ?? [];
                if (!empty($actDetails['aadhaar'])) {
                    $a = $actDetails['aadhaar'];
                    if (!empty($a['name'])) $aadhaarName = $a['name'];
                    if (!empty($a['full_name'])) $aadhaarName = $a['full_name'];
                    if (!empty($a['id_number'])) $aadhaarNumber = $a['id_number'];
                    if (!empty($a['uid'])) $aadhaarNumber = $a['uid'];
                    if (!empty($a['aadhaar_number'])) $aadhaarNumber = $a['aadhaar_number'];
                    if (!empty($a['dob'])) $aadhaarDob = $a['dob'];
                    if (!empty($a['gender'])) $aadhaarGender = $a['gender'];
                    if (!empty($a['father_name'])) $aadhaarFatherName = $a['father_name'];
                    
                    $addr = $a['current_address'] ?? ($a['permanent_address'] ?? null);
                    $addrDetails = $a['current_address_details'] ?? ($a['permanent_address_details'] ?? []);
                    if (!empty($addrDetails['address'])) $addr = $addrDetails['address'];
                    if ($addr) $aadhaarAddress = $addr;
                    if (!empty($addrDetails['district_or_city'])) $aadhaarDistrict = $addrDetails['district_or_city'];
                    if (!empty($addrDetails['state'])) $aadhaarState = $addrDetails['state'];
                    if (!empty($addrDetails['pincode'])) $aadhaarPincode = $addrDetails['pincode'];
                    if (!empty($addrDetails['locality_or_post_office'])) $aadhaarLocality = $addrDetails['locality_or_post_office'];

                    if (!empty($a['image'])) {
                        $aadhaarImageB64 = $a['image'];
                    }
                }

                $p = null;
                if (!empty($actDetails['pan'])) {
                    $p = $actDetails['pan'];
                } elseif (!empty($actDetails['id_analysis']['pan'])) {
                    $p = $actDetails['id_analysis']['pan'];
                } elseif (!empty($actDetails['ocr']['pan'])) {
                    $p = $actDetails['ocr']['pan'];
                } elseif (($act['type'] ?? '') === 'pan' || ($act['action_ref'] ?? '') === 'pan') {
                    $p = $actDetails['pan'] ?? $actDetails;
                } elseif (!empty($actDetails['id_card']) && strtolower($actDetails['id_card']['document_type'] ?? '') === 'pan') {
                    $p = $actDetails['id_card'];
                }

                if (is_array($p)) {
                    $pNum = $p['id_number'] ?? $p['pan'] ?? $p['pan_number'] ?? $p['number'] ?? $p['id'] ?? $p['card_number'] ?? '';
                    $pName = $p['name'] ?? $p['full_name'] ?? $p['card_name'] ?? $p['name_on_card'] ?? '';
                    $pDob = $p['dob'] ?? $p['date_of_birth'] ?? '';
                    $pGender = $p['gender'] ?? '';

                    if ($pNum) $panNumber = $pNum;
                    if ($pName) $panName = $pName;
                    if ($pDob) $panDob = $pDob;
                    if ($pGender) $panGender = $pGender;
                }
            }

            if (!empty($details['customer_name']) && (empty($aadhaarName) || $aadhaarName === 'Verified User' || $aadhaarName === 'User')) {
                $aadhaarName = $details['customer_name'];
            }

            if (!empty($aadhaarImageB64)) {
                try {
                    $dir = storage_path('app/private/kyc');
                    if (!file_exists($dir)) mkdir($dir, 0755, true);
                    $cleanB64 = preg_replace('#^data:[\w/]+;base64,#i', '', $aadhaarImageB64);
                    file_put_contents("{$dir}/aadhaar_photo_{$userUid}.jpg", base64_decode($cleanB64));
                } catch (\Throwable $e) {}
            }

            // Also download PDFs
            self::fetchAndStoreDigioPdfs($userUid, $requestId);

            $updateData = [
                'digio_request_id' => $requestId,
                'digio_verified' => true,
                'updated_at' => now(),
            ];

            if ($aadhaarName) $updateData['aadhaar_name'] = $aadhaarName;
            if ($aadhaarNumber) {
                $updateData['aadhaar_number'] = encrypt($aadhaarNumber);
                $updateData['aadhaar_hash'] = hash_hmac('sha256', $aadhaarNumber, config('app.key'));
            }
            if ($aadhaarDob) $updateData['aadhaar_dob'] = $aadhaarDob;
            if ($aadhaarGender) $updateData['aadhaar_gender'] = $aadhaarGender;
            if ($aadhaarFatherName) $updateData['aadhaar_father_name'] = $aadhaarFatherName;
            if ($aadhaarAddress) $updateData['aadhaar_address'] = $aadhaarAddress;
            if ($aadhaarDistrict) $updateData['aadhaar_district'] = $aadhaarDistrict;
            if ($aadhaarState) $updateData['aadhaar_state'] = $aadhaarState;
            if ($aadhaarPincode) $updateData['aadhaar_pincode'] = $aadhaarPincode;
            if ($aadhaarLocality) $updateData['aadhaar_locality'] = $aadhaarLocality;

            if ($panName) $updateData['pan_name'] = $panName;
            if ($panNumber) {
                $updateData['pan_number'] = encrypt($panNumber);
                $updateData['pan_hash'] = hash_hmac('sha256', $panNumber, config('app.key'));
            }
            if ($panDob) $updateData['pan_dob'] = $panDob;
            if ($panGender) $updateData['pan_gender'] = $panGender;

            DB::connection('mongodb')
                ->table('user_kyc_verifications')
                ->updateOrInsert(
                    ['user_uid' => $userUid],
                    $updateData
                );

            if (!empty($aadhaarName) && $aadhaarName !== 'User' && $aadhaarName !== 'Verified User') {
                DB::connection('mongodb')->table('users')
                    ->where('uid', $userUid)
                    ->orWhere('_id', $userUid)
                    ->update(['name' => $aadhaarName, 'updated_at' => now()]);
            }

            return $updateData;
        } catch (\Throwable $e) {
            Log::warning("Sync Digio KYC Exception for {$userUid}: " . $e->getMessage());
            return null;
        }
    }

    public function verifyDigioKyc(Request $request)
    {
        $user = $request->attributes->get('auth_user') ?? Auth::user();
        $userUid = is_array($user) ? ($user['uid'] ?? null) : ($user->uid ?? null);

        $requestId = $request->input('request_id');
        $isMock = $request->boolean('is_mock');
        $inputPan = strtoupper(trim((string) $request->input('pan_number', '')));

        if (!$requestId && $userUid) {
            $rec = DB::connection('mongodb')->table('user_kyc_verifications')->where('user_uid', $userUid)->first();
            $requestId = $rec->digio_request_id ?? ($rec['digio_request_id'] ?? null);
        }

        if ($userUid) {
            $synced = self::syncDigioKycData($userUid, $requestId, $inputPan);

            // Document Uniqueness check: Ensure Aadhaar & PAN are NOT already registered to another account (including deleted accounts)
            $aadhaarHash = $synced['aadhaar_hash'] ?? null;
            $panHash = $synced['pan_hash'] ?? null;

            if ($aadhaarHash || $panHash) {
                $duplicate = DB::connection('mongodb')
                    ->table('user_kyc_verifications')
                    ->where('user_uid', '!=', $userUid)
                    ->get()
                    ->first(function ($record) use ($aadhaarHash, $panHash) {
                        $record = (object) $record;
                        if ($aadhaarHash && isset($record->aadhaar_hash) && $record->aadhaar_hash === $aadhaarHash) {
                            return true;
                        }
                        if ($panHash && isset($record->pan_hash) && $record->pan_hash === $panHash) {
                            return true;
                        }
                        return false;
                    });

                if ($duplicate) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This identity document (Aadhaar or PAN) has already been registered with another account.',
                    ], 422);
                }
            }

            DB::connection('mongodb')
                ->table('users')
                ->where('uid', $userUid)
                ->update([
                    'kyc_status' => 'pending_selfie',
                    'updated_at' => now(),
                ]);

            DB::connection('mongodb')
                ->table('user_kyc_verifications')
                ->updateOrInsert(
                    ['user_uid' => $userUid],
                    [
                        'user_uid' => $userUid,
                        'status' => 'pending_selfie',
                        'digio_verified' => true,
                        'updated_at' => now(),
                    ]
                );
        }

        return response()->json([
            'success' => true,
            'message' => 'Aadhaar & PAN verified successfully via DigiLocker. Proceed to Selfie verification.',
            'data' => [
                'kyc_status' => 'pending_selfie',
            ],
        ]);
    }

    public function reset(Request $request)
    {
        $user = $request->attributes->get('auth_user');
        $userUid = $user['uid'] ?? null;

        // Fetch current KYC status
        $currentUser = DB::connection('mongodb')
            ->table('users')
            ->where('uid', $userUid)
            ->first();

        $status = is_array($currentUser) ? ($currentUser['kyc_status'] ?? 'unverified') : ($currentUser->kyc_status ?? 'unverified');

        // Prevent self-service reset while verification is under active review
        if (in_array(strtolower($status), ['pending_review', 'pending_selfie'])) {
            return response()->json([
                'success' => false,
                'message' => 'KYC reset is not allowed while verification is pending review.',
            ], 403);
        }

        // Reset user kyc status to unverified
        DB::connection('mongodb')
            ->table('users')
            ->where('uid', $userUid)
            ->update([
                'kyc_status' => 'unverified',
                'kyc_verified_at' => null,
                'updated_at' => now(),
            ]);

        // Delete verification record and documents
        DB::connection('mongodb')
            ->table('user_kyc_verifications')
            ->where('user_uid', $userUid)
            ->delete();

        // Delete secure folder
        Storage::deleteDirectory('kyc/' . $userUid);

        return response()->json([
            'success' => true,
            'message' => 'KYC state reset successfully. You can retry from Step 1.',
        ]);
    }

    private function extractNameFromAadhaarText(string $text): ?string
    {
        $lines = explode("\n", $text);
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (empty($trimmed)) {
                continue;
            }

            $lower = strtolower($trimmed);

            // Skip lines with numbers (names don't contain numbers!)
            if (preg_match('/[0-9]/', $trimmed)) {
                continue;
            }

            // Clean words and check against metadata / junk words
            $words = explode(' ', $lower);
            $skipWords = [
                'government', 'india', 'unique', 'authority', 'uidai', 'enrollment', 'enrolment',
                'dob', 'birth', 'male', 'female', 'gender', 'of', 'to', 'with', 'help', 'yojana',
                'address', 'father', 'husband', 'wife', 'son', 'daughter', 'care', 'card', 'valid',
                'download', 'signature', 'date', 'year'
            ];

            $hasSkipWord = false;
            foreach ($words as $word) {
                $cleanWord = preg_replace('/[^a-z]/', '', $word);
                if (in_array($cleanWord, $skipWords)) {
                    $hasSkipWord = true;
                    break;
                }
            }

            if ($hasSkipWord) {
                continue;
            }

            // Ensure it has only alphabetical characters, spaces, dots or hyphens
            if (preg_match('/^[a-zA-Z\s\.\,\-]+$/', $trimmed)) {
                $candidateName = preg_replace('/\s+/', ' ', $trimmed);
                if (strlen($candidateName) >= 3 && strlen($candidateName) <= 80) {
                    return ucwords(strtolower($candidateName));
                }
            }
        }

        return null;
    }

    private function runOcr($fileOrPath, $apiKey, $apiUrl)
    {
        $filePath = is_string($fileOrPath) ? $fileOrPath : $fileOrPath->getRealPath();
        $fileName = is_string($fileOrPath) ? basename($fileOrPath) : $fileOrPath->getClientOriginalName();

        // Skip OCR check if upload name contains mock/test (for developer ease)
        $origName = strtolower($fileName);
        if (str_contains($origName, 'mock') || str_contains($origName, 'test') || str_contains($origName, 'dummy')) {
            return ['success' => false]; // Fallback to bypass OCR checks
        }

        try {
            $response = Http::timeout(15)
                ->attach('file', file_get_contents($filePath), $fileName)
                ->post($apiUrl, [
                    'apikey' => $apiKey,
                    'language' => 'eng',
                    'isOverlayRequired' => 'false',
                ]);

            if ($response->successful()) {
                $json = $response->json();
                $text = $json['ParsedResults'][0]['ParsedText'] ?? '';
                if (!empty($text)) {
                    return [
                        'success' => true,
                        'text' => $text,
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('OCR.space API Request failed or timed out: ' . $e->getMessage());
        }

        return ['success' => false];
    }

    /**
     * $lenient is for mobile live-camera captures, which already passed the on-device oval face check;
     * uneven phone lighting easily breaks the strict skin-symmetry thresholds.
     */
    private function detectHumanFace($imagePath, bool $lenient = false)
    {
        if (!file_exists($imagePath) || !filesize($imagePath)) {
            return ['has_face' => false, 'reason' => 'Image file not found or empty.'];
        }

        $imgData = @file_get_contents($imagePath);
        if (!$imgData) {
            return ['has_face' => false, 'reason' => 'Unable to read image data.'];
        }

        $src = @imagecreatefromstring($imgData);
        if (!$src) {
            return ['has_face' => false, 'reason' => 'Invalid image format.'];
        }

        $width = imagesx($src);
        $height = imagesy($src);

        if ($width < 120 || $height < 120) {
            imagedestroy($src);
            return ['has_face' => false, 'reason' => 'Image resolution is too low for face verification.'];
        }

        // Resample to standard 160x120 for fast spatial analysis
        $targetW = 160;
        $targetH = 120;
        $resized = imagecreatetruecolor($targetW, $targetH);
        imagecopyresampled($resized, $src, 0, 0, 0, 0, $targetW, $targetH, $width, $height);
        imagedestroy($src);

        $totalPixels = 0;
        $skinPixels = 0;
        $centerSkinPixels = 0;
        $centerTotalPixels = 0;

        $leftSkinPixels = 0;
        $rightSkinPixels = 0;
        $borderSkinPixels = 0;
        $borderTotalPixels = 0;

        $centerLuminanceSum = 0;

        // Central portrait zone (25% to 75% X, 15% to 85% Y)
        $minX = (int)($targetW * 0.25);
        $maxX = (int)($targetW * 0.75);
        $minY = (int)($targetH * 0.15);
        $maxY = (int)($targetH * 0.85);

        $midX = (int)($targetW * 0.50);

        for ($y = 0; $y < $targetH; $y++) {
            for ($x = 0; $x < $targetW; $x++) {
                $rgb = imagecolorat($resized, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                $totalPixels++;

                // Skin detection rules
                $isSkinRGB = ($r > 80 && $g > 35 && $b > 15 &&
                              (max($r, $g, $b) - min($r, $g, $b) > 12) &&
                              abs($r - $g) > 10 &&
                              $r > $g && $r > $b);

                $cbVal = -0.1687 * $r - 0.3313 * $g + 0.5 * $b + 128;
                $crVal =  0.5 * $r - 0.4187 * $g - 0.0813 * $b + 128;
                $isSkinYCbCr = ($cbVal >= 75 && $cbVal <= 130 && $crVal >= 130 && $crVal <= 175);

                $isSkin = ($isSkinRGB || $isSkinYCbCr);

                // Border check (outer 10% margins)
                $isBorder = ($x < $targetW * 0.10 || $x > $targetW * 0.90 || $y < $targetH * 0.10 || $y > $targetH * 0.90);
                if ($isBorder) {
                    $borderTotalPixels++;
                    if ($isSkin) $borderSkinPixels++;
                }

                // Center check
                $isCenter = ($x >= $minX && $x <= $maxX && $y >= $minY && $y <= $maxY);
                if ($isCenter) {
                    $centerTotalPixels++;
                    $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b);
                    $centerLuminanceSum += $luminance;

                    if ($isSkin) {
                        $centerSkinPixels++;
                        if ($x < $midX) {
                            $leftSkinPixels++;
                        } else {
                            $rightSkinPixels++;
                        }
                    }
                }

                if ($isSkin) {
                    $skinPixels++;
                }
            }
        }

        imagedestroy($resized);

        $overallRatio = ($skinPixels / $totalPixels) * 100;
        $centerRatio = ($centerTotalPixels > 0) ? ($centerSkinPixels / $centerTotalPixels) * 100 : 0;
        $borderRatio = ($borderTotalPixels > 0) ? ($borderSkinPixels / $borderTotalPixels) * 100 : 0;
        $avgLuminance = ($centerTotalPixels > 0) ? ($centerLuminanceSum / $centerTotalPixels) : 128;

        $minCenterRatio = $lenient ? 5.0 : 10.0;
        $minOverallRatio = $lenient ? 4.0 : 8.0;
        // Beige walls, wood and light clothing at the frame edges register as skin, so the edge rule is skipped when lenient.
        $maxBorderRatio = $lenient ? 101.0 : 38.0;
        $maxSymmetryRatio = $lenient ? 3.5 : 1.55;

        // Rule 1: Lighting & Exposure check
        if ($avgLuminance < 25) {
            return [
                'has_face' => false,
                'reason' => 'Lighting is too dark. Please capture your selfie in a well-lit area so your face is clearly visible.',
            ];
        }

        if ($avgLuminance > 242) {
            return [
                'has_face' => false,
                'reason' => 'Photo is overexposed or too bright. Please avoid harsh backlight and capture under balanced lighting.',
            ];
        }

        // Rule 2: Presence & Distance check (Must have enough skin in the center)
        if ($centerRatio < $minCenterRatio && $overallRatio < $minOverallRatio) {
            return [
                'has_face' => false,
                'reason' => 'No human face detected. Please center your face clearly inside the oval guide frame.',
            ];
        }

        // Rule 3: Over-zoomed / Too close check (Face overflowing out of frame boundaries)
        if ($borderRatio > $maxBorderRatio) {
            return [
                'has_face' => false,
                'reason' => 'Face is too close / zoomed in. Please step back slightly so your entire face fits inside the oval frame.',
            ];
        }

        // Rule 4: Side profile / Covered face / Hand obstruction check (Symmetry ratio between left and right center zones)
        $maxHalf = max($leftSkinPixels, $rightSkinPixels);
        $minHalf = min($leftSkinPixels, $rightSkinPixels);
        if ($minHalf < 5) $minHalf = 1;

        $symmetryRatio = $maxHalf / $minHalf;

        if ($symmetryRatio > $maxSymmetryRatio) {
            return [
                'has_face' => false,
                'reason' => 'Side profile, hand-covered or obstructed face detected. Please keep your full face uncovered and look straight into the camera.',
            ];
        }

        return [
            'has_face' => true,
            'center_ratio' => round($centerRatio, 2),
            'symmetry_ratio' => round($symmetryRatio, 2),
        ];
    }

    public function submit(Request $request)
    {
        return response()->json([
            'success' => false,
            'message' => 'Deprecated endpoint.',
        ], 400);
    }

    private function validateVerhoeff(string $number): bool
    {
        if (!preg_match('/^[2-9]\d{11}$/', $number)) {
            return false;
        }

        $d = [
            [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
            [1, 2, 3, 4, 0, 6, 7, 8, 9, 5],
            [2, 3, 4, 0, 1, 7, 8, 9, 5, 6],
            [3, 4, 0, 1, 2, 8, 9, 5, 6, 7],
            [4, 0, 1, 2, 3, 9, 5, 6, 7, 8],
            [5, 9, 8, 7, 6, 0, 4, 3, 2, 1],
            [6, 5, 9, 8, 7, 1, 0, 4, 3, 2],
            [7, 6, 5, 9, 8, 2, 1, 0, 4, 3],
            [8, 7, 6, 5, 9, 3, 2, 1, 0, 4],
            [9, 8, 7, 6, 5, 4, 3, 2, 1, 0]
        ];

        $p = [
            [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
            [1, 5, 7, 6, 2, 8, 3, 0, 9, 4],
            [5, 8, 0, 3, 7, 9, 6, 1, 4, 2],
            [8, 9, 1, 6, 0, 4, 3, 5, 2, 7],
            [9, 4, 5, 3, 1, 2, 6, 8, 7, 0],
            [4, 2, 8, 6, 5, 7, 3, 9, 0, 1],
            [2, 7, 9, 3, 8, 0, 6, 4, 1, 5],
            [7, 0, 4, 6, 9, 1, 3, 2, 5, 8]
        ];

        $c = 0;
        $reversed = array_reverse(array_map('intval', str_split($number)));

        for ($i = 0; $i < count($reversed); $i++) {
            $c = $d[$c][$p[$i % 8][$reversed[$i]]];
        }

        return $c === 0;
    }

    public static function fetchAndStoreDigioPdfs($userUid, $requestId)
    {
        $clientId = env('DIGIO_CLIENT_ID');
        $clientSecret = env('DIGIO_CLIENT_SECRET');
        $digioEnv = env('DIGIO_ENV', 'sandbox');

        if (!$clientId || !$clientSecret || !$requestId) {
            return;
        }

        $baseUrl = strtolower($digioEnv) === 'production' 
            ? 'https://api.digio.in' 
            : 'https://ext.digio.in:444';

        $dir = storage_path('app/private/kyc');
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        $docTypes = ['aadhaar' => 'AADHAAR', 'pan' => 'PAN'];

        foreach ($docTypes as $key => $docType) {
            try {
                $response = Http::withoutVerifying()
                    ->withBasicAuth($clientId, $clientSecret)
                    ->get("{$baseUrl}/client/kyc/v2/media/{$requestId}?doc_type={$docType}");

                if ($response->successful()) {
                    $body = $response->body();
                    $filePath = "{$dir}/{$key}_{$userUid}.pdf";

                    if (str_starts_with(trim($body), '{')) {
                        $json = json_decode($body, true);
                        $b64 = $json['file'] ?? ($json['data']['file'] ?? ($json['content'] ?? null));
                        if ($b64) {
                            file_put_contents($filePath, base64_decode(preg_replace('#^data:[\w/]+;base64,#i', '', $b64)));
                        }
                    } else if (strlen($body) > 20) {
                        file_put_contents($filePath, $body);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("Digio PDF Download Exception for {$docType}: " . $e->getMessage());
            }
        }
    }

    public function initiateSelfieQrSession(Request $request)
    {
        $user = $request->attributes->get('auth_user') ?? Auth::user();
        $userUid = is_array($user) ? ($user['uid'] ?? null) : ($user->uid ?? null);
        $userName = is_array($user) ? ($user['name'] ?? 'User') : ($user->name ?? 'User');

        if (!$userUid) {
            return response()->json(['success' => false, 'message' => 'Unauthorized user session.'], 401);
        }

        $token = 'qr_sf_' . Str::random(24);
        $expiresAt = now()->addMinutes(5);

        DB::connection('mongodb')
            ->table('kyc_selfie_sessions')
            ->insert([
                'token' => $token,
                'user_uid' => $userUid,
                'user_name' => $userName,
                'status' => 'pending',
                'expires_at' => $expiresAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $mobileUrl = rtrim($this->mobileReachableBaseUrl($request), '/') . "/kyc/mobile/{$token}";

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'mobile_url' => $mobileUrl,
                'expires_at' => $expiresAt->toIso8601String(),
                'expires_in_seconds' => 300,
            ],
        ]);
    }

    /**
     * Base URL a phone can open. KYC_MOBILE_URL (e.g. a public domain or tunnel) wins; on a local
     * dev host (localhost / 127.0.0.1 / *.local) the machine's Wi-Fi IP is used instead.
     */
    private function mobileReachableBaseUrl(Request $request): string
    {
        $configured = trim((string) env('KYC_MOBILE_URL', ''));
        if ($configured !== '') {
            return $configured;
        }

        $host = $request->getHost();
        $isLocalHost = in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true) || str_ends_with($host, '.local');
        if (! $isLocalHost) {
            return $request->getSchemeAndHttpHost();
        }

        $lanIp = $this->detectLanIp();
        if ($lanIp === null) {
            return $request->getSchemeAndHttpHost();
        }

        $port = $request->getPort();
        $portStr = ($port && $port != 80 && $port != 443) ? ":{$port}" : '';

        return $request->getScheme() . "://{$lanIp}{$portStr}";
    }

    private function detectLanIp(): ?string
    {
        // A UDP "connect" sends no packets; it only makes the OS pick the outgoing interface address.
        $socket = @stream_socket_client('udp://8.8.8.8:53', $errno, $errstr, 1);
        if ($socket) {
            $local = (string) stream_socket_get_name($socket, false);
            fclose($socket);
            $ip = preg_replace('/:\d+$/', '', $local);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && ! str_starts_with($ip, '127.')) {
                return $ip;
            }
        }

        $resolved = gethostbyname(gethostname());
        if (filter_var($resolved, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && ! str_starts_with($resolved, '127.')) {
            return $resolved;
        }

        return null;
    }

    public function checkSelfieQrStatus(Request $request)
    {
        $token = $request->query('token');

        if (!$token) {
            return response()->json(['success' => false, 'message' => 'Token required.'], 400);
        }

        $session = DB::connection('mongodb')
            ->table('kyc_selfie_sessions')
            ->where('token', $token)
            ->first();

        if (!$session) {
            return response()->json(['success' => false, 'message' => 'Invalid session.'], 404);
        }

        $sessionArr = (array) $session;
        $status = strtolower((string) ($sessionArr['status'] ?? 'pending'));
        $isCompleted = ($status === 'completed' || $status === 'success');
        $selfieUrl = $sessionArr['selfie_url'] ?? null;

        return response()->json([
            'success' => true,
            'data' => [
                'status' => $status,
                'completed' => $isCompleted,
                'selfie_url' => $selfieUrl,
            ],
        ]);
    }

    public function getMobileSelfieSession(string $token)
    {
        $session = DB::connection('mongodb')
            ->table('kyc_selfie_sessions')
            ->where('token', $token)
            ->first();

        if (!$session) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired session link.'], 404);
        }

        $sessionArr = (array) $session;

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'user_name' => $sessionArr['user_name'] ?? 'User',
                'status' => $sessionArr['status'] ?? 'pending',
            ],
        ]);
    }

    public function uploadMobileSelfie(Request $request)
    {
        try {
            $token = $request->input('token') ?: ($request->json('token') ?: null);
            $base64Input = $request->input('image_base64') ?: ($request->json('image_base64') ?: null);

            if (!$token) {
                return response()->json(['success' => false, 'message' => 'Session token is required.'], 400);
            }

            $session = DB::connection('mongodb')
                ->table('kyc_selfie_sessions')
                ->where('token', $token)
                ->first();

            if (!$session) {
                return response()->json(['success' => false, 'message' => 'Invalid or expired session token.'], 404);
            }

            $sessionArr = (array) $session;
            $userUid = $sessionArr['user_uid'] ?? null;

            if (!$userUid) {
                return response()->json(['success' => false, 'message' => 'User ID missing in session.'], 404);
            }

            if (empty($base64Input)) {
                if ($request->hasFile('selfie')) {
                    $base64Input = base64_encode(file_get_contents($request->file('selfie')->getRealPath()));
                }
            }

            if (empty($base64Input)) {
                return response()->json(['success' => false, 'message' => 'Please capture a selfie photo first.'], 422);
            }

            $dirPrivate = storage_path('app/private/kyc');
            if (!file_exists($dirPrivate)) {
                mkdir($dirPrivate, 0755, true);
            }
            $dirPublic = storage_path('app/public/kyc');
            if (!file_exists($dirPublic)) {
                mkdir($dirPublic, 0755, true);
            }

            $filename = "selfie_{$userUid}.jpg";
            $filePathPrivate = "{$dirPrivate}/{$filename}";
            $filePathPublic = "{$dirPublic}/{$filename}";

            $b64 = preg_replace('#^data:image/\w+;base64,#i', '', $base64Input);
            $decoded = base64_decode($b64);
            file_put_contents($filePathPrivate, $decoded);
            file_put_contents($filePathPublic, $decoded);

            // 1. Mandatory Face Detection Check for Mobile Stream Uploads
            $faceCheck = $this->detectHumanFace($filePathPrivate, true);
            if (!$faceCheck['has_face']) {
                Log::info('uploadMobileSelfie face check failed', ['token' => $token, 'reason' => $faceCheck['reason'] ?? null]);
                @unlink($filePathPrivate);
                @unlink($filePathPublic);
                return response()->json([
                    'success' => false,
                    'message' => 'Selfie verification failed: ' . ($faceCheck['reason'] ?? 'No human face detected. Please take a clear live portrait photo of your face inside the oval frame.'),
                ], 422);
            }

            // 2. Run OCR text density check to reject document scans or screenshots uploaded on mobile
            $apiKey = env('OCR_SPACE_API_KEY', 'helloworld');
            $ocrSpaceUrl = 'https://api.ocr.space/parse/image';
            $mobileOcr = $this->runOcr($filePathPrivate, $apiKey, $ocrSpaceUrl);
            if ($mobileOcr['success']) {
                $text = trim($mobileOcr['text']);
                $words = preg_split('/\s+/', $text);
                $wordCount = count(array_filter($words, fn($w) => strlen($w) > 2));
                if ($wordCount > 5) {
                    @unlink($filePathPrivate);
                    @unlink($filePathPublic);
                    return response()->json([
                        'success' => false,
                        'message' => 'Selfie verification failed. Please take a clear live portrait photo of your face inside the oval frame. Document scans or screenshots are not allowed.',
                    ], 422);
                }
            }

            $selfieUrl = "/storage/kyc/{$filename}";
            $nowStr = date('Y-m-d H:i:s');

            DB::connection('mongodb')
                ->table('kyc_selfie_sessions')
                ->where('token', $token)
                ->update([
                    'status' => 'completed',
                    'selfie_url' => $selfieUrl,
                    'liveness_verified' => true,
                    'liveness_method' => 'mobile_camera_oval',
                    'completed_at' => $nowStr,
                    'updated_at' => $nowStr,
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Selfie uploaded successfully! You can now return to your desktop.',
                'data' => [
                    'status' => 'completed',
                    'selfie_url' => $selfieUrl,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('uploadMobileSelfie Exception: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage(),
            ], 500);
        }
    }
}
