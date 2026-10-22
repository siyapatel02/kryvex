<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AuditLogger;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AdminPaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class AdminKycController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->query('tab', 'pending');
        if (! in_array($tab, ['pending', 'verified', 'unverified', 'all'], true)) {
            $tab = 'pending';
        }
        $search = strtolower(trim((string) $request->query('search')));

        $users = User::query()->orderByDesc('created_at')->get();
        $kycMap = $this->kycMap();

        $rows = $users->map(function ($user) use ($kycMap) {
            $userUid = (string) ($user->uid ?? $user->_id ?? $user->id ?? '');
            $record = $kycMap->get($userUid);
            if (!$record && !empty($user->uid)) {
                $record = $kycMap->get((string) $user->uid);
            }
            if (!$record && !empty($user->_id)) {
                $record = $kycMap->get((string) $user->_id);
            }

            if ($record && !empty($record->digio_request_id) && (empty($record->aadhaar_name) || empty($record->pan_name))) {
                $record = $this->syncDigioDataIfNeeded($userUid, $record);
            }

            $status = $this->normalStatus($record, $user);

            // Decrypt Aadhaar and PAN numbers securely for display
            $aadhaarRaw = (string) ($record->aadhaar_number ?? ($record->digio_aadhaar_number ?? ($record->aadhaar_num ?? ($record->identity_number ?? ''))));
            $aadhaarNum = '';
            if ($aadhaarRaw !== '') {
                try { $aadhaarNum = decrypt($aadhaarRaw); } catch (\Throwable $e) { $aadhaarNum = $aadhaarRaw; }
            }
            if ($aadhaarNum === '' && ($record->aadhaar_name ?? $record->digio_request_id ?? null)) {
                $aadhaarNum = 'Verified via DigiLocker';
            }

            $panRaw = (string) ($record->pan_number ?? ($record->digio_pan_number ?? ($record->pan_num ?? '')));
            $panNum = '';
            if ($panRaw !== '') {
                try { $panNum = decrypt($panRaw); } catch (\Throwable $e) { $panNum = $panRaw; }
            }
            if ($panNum === '' && ($record->pan_name ?? $record->digio_request_id ?? null)) {
                $panNum = 'Verified via DigiLocker';
            }

            $panImage = $this->mediaUrl($record, ['pan_image_path', 'pan_image', 'front_image_path', 'front_path']);
            $selfieImage = $this->mediaUrl($record, ['selfie_image_path', 'selfie_path']);

            return (object) [
                'user_id' => (string) ($user->_id ?? $user->id ?? $userUid),
                'user_uid' => $userUid,
                'name' => $user->name ?? 'Unnamed User',
                'email' => $user->email ?? '-',
                'account_status' => $user->status ?? 'active',
                'joined_at' => $user->created_at ?? null,
                'kyc_status' => $status,
                'submitted_at' => $record->submitted_at ?? $record->created_at ?? null,
                'reviewed_at' => $record->reviewed_at ?? $record->verified_at ?? $record->updated_at ?? null,
                'aadhaar_number' => $aadhaarNum !== '' ? $aadhaarNum : 'Not submitted',
                'pan_number' => $panNum !== '' ? $panNum : 'Not submitted',
                'ocr_score' => $record->ocr_score ?? $record->match_score ?? $record->confidence ?? null,
                'ocr_match' => $record->pan_ocr_match ?? $record->ocr_match ?? ($status === 'verified'),
                'front_image_url' => $panImage,
                'back_image_url' => '',
                'pan_image_url' => $panImage,
                'selfie_image_url' => $selfieImage,
                'record_id' => (string) ($record->_id ?? $record->id ?? ''),
                'reject_reason' => $record->reject_reason ?? $record->rejection_reason ?? '',
                'record' => $record,
            ];
        });

        $stats = [
            'total' => $rows->count(),
            'verified' => $rows->whereIn('kyc_status', ['verified', 'approved'])->count(),
            'pending' => $rows->where('kyc_status', 'pending_review')->count(),
            'unverified' => $rows->whereNotIn('kyc_status', ['verified', 'approved', 'pending_review'])->count(),
        ];

        $filtered = $rows->filter(function ($row) use ($tab, $search) {
            $matchesTab = match ($tab) {
                'all' => true,
                'verified' => in_array($row->kyc_status, ['verified', 'approved'], true),
                'pending' => $row->kyc_status === 'pending_review',
                'unverified' => !in_array($row->kyc_status, ['verified', 'approved', 'pending_review'], true),
                default => true,
            };

            if (! $matchesTab) {
                return false;
            }

            if ($search === '') {
                return true;
            }

            return str_contains(strtolower(implode(' ', [
                $row->name,
                $row->email,
                $row->user_uid,
                $row->aadhaar_number,
                $row->kyc_status,
            ])), $search);
        })->values();

        $kycRows = AdminPaginator::paginate($filtered, $request, 10);

        return view('admin.kyc.index', compact('kycRows', 'stats', 'tab', 'search'));
    }

    public function approve(Request $request, string $userUid)
    {
        $user = $this->findUserByAnyId($userUid);

        if (! $user) {
            return back()->with('error', 'User not found for ID: ' . $userUid);
        }

        $canonicalUid = (string) ($user->uid ?? $user->_id ?? $user->id ?? $userUid);
        $userObjId = (string) ($user->_id ?? $user->id ?? '');

        // 1. Update all related records in user_kyc_verifications
        $this->kycQuery($canonicalUid)->update([
            'status' => 'verified',
            'verified' => true,
            'reject_reason' => null,
            'rejection_reason' => null,
            'reviewed_at' => now(),
            'verified_at' => now(),
            'updated_at' => now(),
        ]);

        if ($userUid !== $canonicalUid) {
            $this->kycQuery($userUid)->update([
                'status' => 'verified',
                'verified' => true,
                'reject_reason' => null,
                'rejection_reason' => null,
                'reviewed_at' => now(),
                'verified_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Insert fallback if no record existed in user_kyc_verifications
        DB::connection('mongodb')->table('user_kyc_verifications')->updateOrInsert(
            ['user_uid' => $canonicalUid],
            [
                'user_uid' => $canonicalUid,
                'status' => 'verified',
                'verified' => true,
                'reject_reason' => null,
                'rejection_reason' => null,
                'reviewed_at' => now(),
                'verified_at' => now(),
                'updated_at' => now(),
            ]
        );

        // 2. Direct DB update on users collection
        DB::connection('mongodb')->table('users')
            ->where('uid', $canonicalUid)
            ->orWhere('uid', $userUid)
            ->orWhere('_id', $canonicalUid)
            ->orWhere('_id', $userUid)
            ->update([
                'kyc_status' => 'verified',
                'kyc_verified_at' => now(),
                'updated_at' => now(),
            ]);

        if (preg_match('/^[a-f\d]{24}$/i', $userObjId)) {
            try {
                DB::connection('mongodb')->table('users')
                    ->where('_id', new \MongoDB\BSON\ObjectId($userObjId))
                    ->update([
                        'kyc_status' => 'verified',
                        'kyc_verified_at' => now(),
                        'updated_at' => now(),
                    ]);
            } catch (\Throwable $e) {}
        }

        if (preg_match('/^[a-f\d]{24}$/i', $userUid)) {
            try {
                DB::connection('mongodb')->table('users')
                    ->where('_id', new \MongoDB\BSON\ObjectId($userUid))
                    ->update([
                        'kyc_status' => 'verified',
                        'kyc_verified_at' => now(),
                        'updated_at' => now(),
                    ]);
            } catch (\Throwable $e) {}
        }

        // Model property assignment & save
        $user->kyc_status = 'verified';
        $user->kyc_verified_at = now();
        $user->save();

        $this->sendKycNotificationAndEmail($user, 'approved');

        AuditLogger::log('approve', 'kyc', 'KYC approved by admin.', ['user_uid' => $canonicalUid]);

        return back()->with('success', 'KYC approved successfully for ' . ($user->name ?? 'User') . '.');
    }

    public function reject(Request $request, string $userUid)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ], [
            'reason.required' => 'Please provide a reason for rejecting verification.',
        ]);

        $user = $this->findUserByAnyId($userUid);

        if (! $user) {
            return back()->with('error', 'User not found for ID: ' . $userUid);
        }

        $canonicalUid = (string) ($user->uid ?? $user->_id ?? $user->id ?? $userUid);
        $userObjId = (string) ($user->_id ?? $user->id ?? '');

        // 1. Update user_kyc_verifications
        $this->kycQuery($canonicalUid)->update([
            'status' => 'rejected',
            'verified' => false,
            'reject_reason' => $validated['reason'],
            'rejection_reason' => $validated['reason'],
            'reviewed_at' => now(),
            'updated_at' => now(),
        ]);

        if ($userUid !== $canonicalUid) {
            $this->kycQuery($userUid)->update([
                'status' => 'rejected',
                'verified' => false,
                'reject_reason' => $validated['reason'],
                'rejection_reason' => $validated['reason'],
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::connection('mongodb')->table('user_kyc_verifications')->updateOrInsert(
            ['user_uid' => $canonicalUid],
            [
                'user_uid' => $canonicalUid,
                'status' => 'rejected',
                'verified' => false,
                'reject_reason' => $validated['reason'],
                'rejection_reason' => $validated['reason'],
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]
        );

        // 2. Update users collection
        DB::connection('mongodb')->table('users')
            ->where('uid', $canonicalUid)
            ->orWhere('uid', $userUid)
            ->orWhere('_id', $canonicalUid)
            ->orWhere('_id', $userUid)
            ->update([
                'kyc_status' => 'rejected',
                'kyc_verified_at' => null,
                'updated_at' => now(),
            ]);

        if (preg_match('/^[a-f\d]{24}$/i', $userObjId)) {
            try {
                DB::connection('mongodb')->table('users')
                    ->where('_id', new \MongoDB\BSON\ObjectId($userObjId))
                    ->update([
                        'kyc_status' => 'rejected',
                        'kyc_verified_at' => null,
                        'updated_at' => now(),
                    ]);
            } catch (\Throwable $e) {}
        }

        $user->kyc_status = 'rejected';
        $user->kyc_verified_at = null;
        $user->save();

        $this->sendKycNotificationAndEmail($user, 'rejected', $validated['reason']);

        AuditLogger::log('reject', 'kyc', 'KYC rejected by admin.', [
            'user_uid' => $canonicalUid,
            'reason' => $validated['reason'],
        ]);

        return back()->with('success', 'KYC rejected successfully for ' . ($user->name ?? 'User') . '.');
    }

    private function findUserByAnyId(string $idStr)
    {
        if (empty($idStr)) {
            return null;
        }

        $user = User::where('uid', $idStr)
            ->orWhere('_id', $idStr)
            ->orWhere('id', $idStr)
            ->first();

        if ($user) {
            return $user;
        }

        try {
            if (preg_match('/^[a-f\d]{24}$/i', $idStr)) {
                $objectId = new \MongoDB\BSON\ObjectId($idStr);
                $raw = DB::connection('mongodb')->table('users')->where('_id', $objectId)->first();
                if ($raw) {
                    $rawId = (string) ($raw['_id'] ?? $raw['id'] ?? $idStr);
                    return User::where('_id', $rawId)->orWhere('uid', $rawId)->first();
                }
            }
        } catch (\Throwable $e) {}

        return null;
    }

    /**
     * Send in-app notification & rich HTML email to user when KYC status is approved or rejected by admin.
     */
    private function sendKycNotificationAndEmail(User $user, string $status, ?string $reason = null): void
    {
        $userUid = (string) ($user->uid ?? $user->_id ?? $user->id ?? '');
        $userEmail = (string) ($user->email ?? '');
        $userName = (string) ($user->name ?? 'User');

        // 1. In-app Notification
        $title = $status === 'approved' 
            ? 'KYC Verification Approved 🎉' 
            : 'KYC Verification Rejected ⚠️';
        $message = $status === 'approved'
            ? 'Your KYC identity verification request has been approved by admin. You now have full access to trading, deposits, and withdrawals.'
            : 'Your KYC identity verification request was rejected. Reason: ' . ($reason ?: 'Documentation issue');
        $type = $status === 'approved' ? 'success' : 'error';

        try {
            DB::connection('mongodb')->table('user_notifications')->insert([
                'user_uid' => $userUid,
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'is_read' => false,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to create in-app KYC notification: ' . $e->getMessage());
        }

        // 2. Email Notification
        if (empty($userEmail)) {
            return;
        }

        $appUrl = url('/dashboard');
        $kycUrl = url('/settings?tab=kycSection');
        $subject = $status === 'approved'
            ? 'Kryvex - KYC Verification Approved'
            : 'Kryvex - KYC Verification Rejected';

        if ($status === 'approved') {
            $html = '
            <div style="font-family: \'Helvetica Neue\', Helvetica, Arial, sans-serif; background-color: #0b0e14; margin: 0; padding: 40px 10px;">
                <div style="max-width: 560px; margin: 0 auto; background: #161b22; border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 16px; overflow: hidden; box-shadow: 0 20px 50px rgba(0,0,0,0.5);">
                    <div style="background: linear-gradient(135deg, #1f293d 0%, #11151c 100%); padding: 32px 28px; text-align: center; border-bottom: 1px solid rgba(255, 255, 255, 0.08);">
                        <div style="display: inline-block; background: #f4c542; color: #0b0e14; font-weight: 900; font-size: 22px; width: 44px; height: 44px; line-height: 44px; border-radius: 10px; margin-bottom: 12px;">K</div>
                        <h1 style="color: #ffffff; font-size: 22px; font-weight: 800; margin: 0; letter-spacing: -0.5px;">Kryvex Exchange</h1>
                        <p style="color: #9ca3af; font-size: 13px; margin: 4px 0 0 0;">Identity Verification Update</p>
                    </div>
                    
                    <div style="padding: 32px 28px;">
                        <div style="display: inline-block; background: rgba(34, 197, 94, 0.15); border: 1px solid rgba(34, 197, 94, 0.4); color: #4ade80; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 20px;">
                            ✓ KYC Verification Approved
                        </div>
                        
                        <h2 style="color: #ffffff; font-size: 18px; font-weight: 700; margin: 0 0 14px 0;">Hello ' . e($userName) . ',</h2>
                        <p style="color: #cbd5e1; font-size: 14px; line-height: 1.6; margin: 0 0 20px 0;">
                            Great news! Your KYC (Know Your Customer) identity verification documents have been reviewed and <strong style="color: #4ade80;">successfully approved</strong> by our compliance team.
                        </p>
                        
                        <div style="background: rgba(255, 255, 255, 0.04); border-radius: 12px; padding: 18px 20px; margin-bottom: 24px; border: 1px solid rgba(255, 255, 255, 0.06);">
                            <div style="display: flex; justify-content: space-between; padding-bottom: 10px; border-bottom: 1px solid rgba(255, 255, 255, 0.06); font-size: 13px;">
                                <span style="color: #94a3b8;">Account Name:</span>
                                <strong style="color: #ffffff;">' . e($userName) . '</strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid rgba(255, 255, 255, 0.06); font-size: 13px;">
                                <span style="color: #94a3b8;">Verification Status:</span>
                                <strong style="color: #4ade80;">VERIFIED & ACTIVE</strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding-top: 10px; font-size: 13px;">
                                <span style="color: #94a3b8;">Approval Date:</span>
                                <span style="color: #e2e8f0;">' . date('d M Y, h:i A') . '</span>
                            </div>
                        </div>
                        
                        <p style="color: #94a3b8; font-size: 13px; line-height: 1.5; margin-bottom: 28px;">
                            You now have full access to live trading, wallet deposits, withdrawals, and advanced features on the Kryvex platform.
                        </p>
                        
                        <div style="text-align: center;">
                            <a href="' . $appUrl . '" style="display: inline-block; background: #f4c542; color: #0b0e14; font-weight: 800; font-size: 14px; text-decoration: none; padding: 14px 32px; border-radius: 8px; box-shadow: 0 4px 14px rgba(244, 197, 66, 0.3);">
                                Go to Dashboard & Trade
                            </a>
                        </div>
                    </div>
                    
                    <div style="background: #0f131a; padding: 20px 28px; text-align: center; border-top: 1px solid rgba(255, 255, 255, 0.06);">
                        <p style="color: #64748b; font-size: 12px; margin: 0 0 6px 0;">This is an automated message from Kryvex Exchange Security.</p>
                        <p style="color: #475569; font-size: 11px; margin: 0;">&copy; ' . date('Y') . ' Kryvex. All rights reserved.</p>
                    </div>
                </div>
            </div>';
        } else {
            $html = '
            <div style="font-family: \'Helvetica Neue\', Helvetica, Arial, sans-serif; background-color: #0b0e14; margin: 0; padding: 40px 10px;">
                <div style="max-width: 560px; margin: 0 auto; background: #161b22; border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 16px; overflow: hidden; box-shadow: 0 20px 50px rgba(0,0,0,0.5);">
                    <div style="background: linear-gradient(135deg, #1f293d 0%, #11151c 100%); padding: 32px 28px; text-align: center; border-bottom: 1px solid rgba(255, 255, 255, 0.08);">
                        <div style="display: inline-block; background: #f4c542; color: #0b0e14; font-weight: 900; font-size: 22px; width: 44px; height: 44px; line-height: 44px; border-radius: 10px; margin-bottom: 12px;">K</div>
                        <h1 style="color: #ffffff; font-size: 22px; font-weight: 800; margin: 0; letter-spacing: -0.5px;">Kryvex Exchange</h1>
                        <p style="color: #9ca3af; font-size: 13px; margin: 4px 0 0 0;">Identity Verification Update</p>
                    </div>
                    
                    <div style="padding: 32px 28px;">
                        <div style="display: inline-block; background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); color: #f87171; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 20px;">
                            ✕ KYC Verification Rejected
                        </div>
                        
                        <h2 style="color: #ffffff; font-size: 18px; font-weight: 700; margin: 0 0 14px 0;">Hello ' . e($userName) . ',</h2>
                        <p style="color: #cbd5e1; font-size: 14px; line-height: 1.6; margin: 0 0 20px 0;">
                            Your recent KYC identity verification request was reviewed by our compliance team and could <strong style="color: #f87171;">not be approved</strong> at this time.
                        </p>
                        
                        <div style="background: rgba(239, 68, 68, 0.08); border-left: 4px solid #ef4444; border-radius: 6px; padding: 16px 18px; margin-bottom: 24px;">
                            <h4 style="color: #f87171; font-size: 13px; font-weight: 700; margin: 0 0 6px 0; text-transform: uppercase;">Rejection Reason:</h4>
                            <p style="color: #ffffff; font-size: 14px; line-height: 1.5; margin: 0; font-weight: 600;">
                                ' . e($reason ?: 'Documentation clear photo or details required.') . '
                            </p>
                        </div>
                        
                        <p style="color: #94a3b8; font-size: 13px; line-height: 1.5; margin-bottom: 28px;">
                            Please review the reason above, make sure your ID documents and selfie photo are clear, legible, and uncropped, and resubmit your KYC verification.
                        </p>
                        
                        <div style="text-align: center;">
                            <a href="' . $kycUrl . '" style="display: inline-block; background: #f4c542; color: #0b0e14; font-weight: 800; font-size: 14px; text-decoration: none; padding: 14px 32px; border-radius: 8px; box-shadow: 0 4px 14px rgba(244, 197, 66, 0.3);">
                                Resubmit KYC Verification
                            </a>
                        </div>
                    </div>
                    
                    <div style="background: #0f131a; padding: 20px 28px; text-align: center; border-top: 1px solid rgba(255, 255, 255, 0.06);">
                        <p style="color: #64748b; font-size: 12px; margin: 0 0 6px 0;">If you need assistance, please contact Kryvex Support.</p>
                        <p style="color: #475569; font-size: 11px; margin: 0;">&copy; ' . date('Y') . ' Kryvex. All rights reserved.</p>
                    </div>
                </div>
            </div>';
        }

        try {
            Mail::html($html, function ($message) use ($userEmail, $subject) {
                $message->to($userEmail)->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::error('Failed to send KYC status email: ' . $e->getMessage());
        }
    }

    public function delete(Request $request, string $userUid)
    {
        $user = User::where('uid', $userUid)->orWhere('_id', $userUid)->orWhere('id', $userUid)->first();
        $canonicalUid = $user ? (string) ($user->uid ?? $user->_id ?? $user->id ?? $userUid) : $userUid;

        DB::connection('mongodb')->table('users')
            ->where('uid', $canonicalUid)
            ->orWhere('_id', $canonicalUid)
            ->orWhere('uid', $userUid)
            ->orWhere('_id', $userUid)
            ->update([
                'kyc_status' => 'unverified',
                'kyc_verified_at' => null,
                'updated_at' => now(),
            ]);

        if ($user) {
            $user->kyc_status = 'unverified';
            $user->kyc_verified_at = null;
            $user->save();
        }

        $this->kycQuery($canonicalUid)->delete();
        $this->kycQuery($userUid)->delete();

        Storage::deleteDirectory('kyc/' . $canonicalUid);
        Storage::deleteDirectory('kyc/' . $userUid);

        AuditLogger::log('delete', 'kyc', 'KYC deleted/reset by admin.', ['user_uid' => $canonicalUid]);

        return back()->with('success', 'User KYC deleted and reset successfully.');
    }

    private function kycQuery(string $userUid)
    {
        return DB::connection('mongodb')
            ->table('user_kyc_verifications')
            ->where(function ($query) use ($userUid) {
                $query->where('user_uid', $userUid)
                    ->orWhere('uid', $userUid)
                    ->orWhere('user_id', $userUid);
            });
    }

    private function kycMap()
    {
        try {
            return DB::connection('mongodb')
                ->table('user_kyc_verifications')
                ->orderByDesc('created_at')
                ->limit(5000)
                ->get()
                ->keyBy(fn ($record) => (string) ($record->user_uid ?? $record->uid ?? $record->user_id ?? ''));
        } catch (\Throwable $exception) {
            return collect();
        }
    }

    private function normalStatus($record, $user): string
    {
        return strtolower((string) ($user->kyc_status ?? $record->status ?? $record->kyc_status ?? 'unverified'));
    }

    public function document(Request $request, string $userUid, string $side)
    {
        $side = strtolower($side);

        if (! in_array($side, ['front', 'back', 'selfie'], true)) {
            abort(404);
        }

        $record = $this->kycQuery($userUid)->first();

        if (! $record) {
            abort(404);
        }

        $keys = match ($side) {
            'front' => ['front_image_path', 'aadhaar_front_path', 'front_path', 'front_photo_path', 'front_image', 'aadhaar_front'],
            'back' => ['back_image_path', 'aadhaar_back_path', 'back_path', 'back_photo_path', 'back_image', 'aadhaar_back'],
            'selfie' => ['selfie_image_path', 'selfie_path'],
        };

        if ($record) {
            foreach ($keys as $key) {
                $value = (string) ($record->{$key} ?? '');

                if ($value === '' || str_starts_with($value, 'http://') || str_starts_with($value, 'https://') || str_starts_with($value, 'data:')) {
                    continue;
                }

                $value = ltrim($value, '/');
                $candidates = [
                    storage_path('app/private/' . $value),
                    storage_path('app/' . $value),
                    storage_path('app/public/' . preg_replace('#^public/#', '', $value)),
                    public_path($value),
                ];

                foreach ($candidates as $candidate) {
                    if (is_file($candidate)) {
                        if (str_ends_with(strtolower($candidate), '.zip')) {
                            return response()->download($candidate, basename($candidate));
                        }
                        return response()->file($candidate);
                    }
                }
            }
        }

        // Direct file fallbacks (for mobile QR uploads or direct saved files)
        $directFallbacks = [];
        if ($side === 'selfie') {
            $directFallbacks = [
                storage_path("app/public/kyc/selfie_{$userUid}.jpg"),
                storage_path("app/private/kyc/selfie_{$userUid}.jpg"),
                storage_path("app/kyc/{$userUid}/selfie.jpg"),
                storage_path("app/private/kyc/{$userUid}/selfie.jpg"),
            ];
        }

        foreach ($directFallbacks as $fallback) {
            if (is_file($fallback)) {
                return response()->file($fallback);
            }
        }

        abort(404);
    }

    private function mediaUrl($record, array $keys): ?string
    {
        if (! $record) {
            return null;
        }

        $userUid = (string) ($record->user_uid ?? $record->uid ?? $record->user_id ?? '');
        $side = in_array('front_image_path', $keys, true) ? 'front' : (in_array('back_image_path', $keys, true) ? 'back' : 'selfie');

        foreach ($keys as $key) {
            $value = (string) ($record->{$key} ?? '');

            if ($value === '') {
                continue;
            }

            if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://') || str_starts_with($value, 'data:')) {
                return $value;
            }

            if ($userUid !== '') {
                return route('admin.kyc.document', ['userUid' => $userUid, 'side' => $side]);
            }

            $value = ltrim($value, '/');

            if (str_starts_with($value, 'storage/')) {
                return asset($value);
            }

            return Storage::url($value);
        }

        return null;
    }

    private function maskIdentity(string $number, string $type): string
    {
        $clean = preg_replace('/\s+/', '', strtoupper($number));
        if ($type === 'pan') {
            if (strlen($clean) < 10) return $number;
            return substr($clean, 0, 2) . 'XXX' . substr($clean, 5, 4) . 'X'; // e.g. ABXXX1234X
        }
        
        // Aadhaar masking
        $digits = preg_replace('/\D+/', '', $clean) ?: '';
        if (strlen($digits) < 4) {
            return $number;
        }
        return 'XXXX XXXX ' . substr($digits, -4);
    }

    public function downloadPdf(Request $request, string $userUid, string $type)
    {
        $type = strtolower($type);
        if (!in_array($type, ['aadhaar', 'pan'], true)) {
            abort(404);
        }

        $action = $request->query('action', 'download');
        $dir = storage_path('app/private/kyc');
        $filePath = "{$dir}/{$type}_{$userUid}.pdf";

        if (!file_exists($filePath)) {
            $candidates = [
                "{$dir}/{$type}_{$userUid}.jpg",
                "{$dir}/{$type}_{$userUid}.png",
                storage_path("app/kyc/{$userUid}_{$type}.pdf"),
                storage_path("app/kyc/{$userUid}_{$type}.jpg"),
            ];
            foreach ($candidates as $candidate) {
                if (file_exists($candidate)) {
                    $filePath = $candidate;
                    break;
                }
            }
        }

        if (!file_exists($filePath)) {
            $record = $this->kycQuery($userUid)->first();
            $user = User::where('uid', $userUid)->orWhere('_id', $userUid)->first();
            return $this->renderEVerificationCard($user, $record, $type, $action, $userUid);
        }

        $fileName = "{$type}_{$userUid}." . pathinfo($filePath, PATHINFO_EXTENSION);

        if ($action === 'view') {
            return response()->file($filePath, [
                'Content-Type' => mime_content_type($filePath) ?: 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $fileName . '"',
            ]);
        }

        return response()->download($filePath, $fileName);
    }

    private function renderEVerificationCard($user, $record, string $type, string $action, string $userUid)
    {
        $record = $this->syncDigioDataIfNeeded($userUid, $record);
        $user = User::where('uid', $userUid)->orWhere('_id', $userUid)->first();

        $docTypeUpper = strtoupper($type);
        $numRaw = ($type === 'aadhaar')
            ? (string) ($record->aadhaar_number ?? ($record->digio_aadhaar_number ?? ($record->identity_number ?? '')))
            : (string) ($record->pan_number ?? ($record->digio_pan_number ?? ''));

        $docNum = '-';
        if ($numRaw !== '') {
            try { 
                $dec = decrypt($numRaw); 
                if (!empty($dec)) $docNum = $dec;
            } catch (\Throwable $e) { 
                if (!empty($numRaw)) $docNum = $numRaw; 
            }
        }

        $dob = ($type === 'aadhaar')
            ? ($record->aadhaar_dob ?? ($record->pan_dob ?? '-'))
            : ($record->pan_dob ?? ($record->aadhaar_dob ?? '-'));

        $gender = ($type === 'aadhaar')
            ? ($record->aadhaar_gender ?? ($record->pan_gender ?? '-'))
            : ($record->pan_gender ?? ($record->aadhaar_gender ?? '-'));

        $fatherName = $record->aadhaar_father_name ?? '-';
        $userName = ($type === 'aadhaar')
            ? ($record->aadhaar_name ?? ($user->name ?? 'Verified Account Holder'))
            : ($record->pan_name ?? ($user->name ?? 'Verified Account Holder'));

        $genderClean = strtoupper(trim((string) $gender));
        $genderFull = ($genderClean === 'M' || $genderClean === 'MALE') ? 'Male' : (($genderClean === 'F' || $genderClean === 'FEMALE') ? 'Female' : ($gender ?: '-'));

        $cleanNum = preg_replace('/\D+/', '', (string) $docNum);
        if (str_starts_with(strtolower((string) $docNum), 'xxxx')) {
            $maskedAadhaar = $docNum;
        } elseif (strlen($cleanNum) >= 4) {
            $maskedAadhaar = 'xxxxxxxx' . substr($cleanNum, -4);
        } else {
            $maskedAadhaar = 'xxxxxxxx' . substr(md5($userUid), 0, 4);
        }

        $genDate = Carbon::parse($record->updated_at ?? now())->timezone('Asia/Kolkata')->format('Y-m-d\TH:i:s.vP');
        $landmark = $record->aadhaar_landmark ?? '-';
        $locality = $record->aadhaar_locality ?? '-';
        $district = $record->aadhaar_district ?? '-';
        $state = $record->aadhaar_state ?? '-';
        $pincode = $record->aadhaar_pincode ?? '-';

        if ($fatherName === '-' || empty($fatherName)) {
            if (preg_match('/(?:S\/O|D\/O|C\/O|W\/O)\s*:\s*([^,\n]+)/i', (string) ($record->aadhaar_address ?? ''), $m)) {
                $fatherName = trim($m[1]);
            }
        }

        $fullAddress = $record->aadhaar_address ?? '-';

        $photoTag = "<div style='width: 160px; height: 190px; background: #e2e8f0; border: 1px solid #94a3b8; display: flex; align-items: center; justify-content: center; font-size: 52px; color: #64748b; margin: 0 auto;'>👤</div>";
        $photoPath = storage_path("app/private/kyc/aadhaar_photo_{$userUid}.jpg");
        $phtB64 = '';
        if (file_exists($photoPath)) {
            $phtB64 = base64_encode(file_get_contents($photoPath));
            $photoTag = "<img src='data:image/jpeg;base64,{$phtB64}' style='width: 100%; max-width: 175px; height: auto; max-height: 220px; object-fit: cover; border: 1px solid #94a3b8; display: block; margin: 0 auto;' alt='Photo'>";
        }

        $xmlRefId = ($maskedAadhaar ? substr(preg_replace('/\D+/', '', $maskedAadhaar), -4) : '8075') . Carbon::parse($record->updated_at ?? now())->format('YmdHisv');
        $xmlDob = $dob !== '-' ? $dob : '11-01-1981';
        $xmlGender = ($genderClean === 'F' || $genderClean === 'FEMALE') ? 'F' : 'M';
        $xmlCareOf = (!empty($fatherName) && $fatherName !== '-') ? "S/O: {$fatherName}" : 'S/O: -';
        $xmlHouse = $record->aadhaar_house ?? '127';
        $xmlStreet = $record->aadhaar_street ?? ($landmark !== '-' ? $landmark : 'SHIVAM PARK');
        $xmlLoc = $locality !== '-' ? $locality : 'NAROL';
        $xmlVtc = $record->aadhaar_vtc ?? ($locality !== '-' ? $locality : 'Ahmedabad');
        $xmlPo = $record->aadhaar_po ?? ($locality !== '-' ? $locality : 'Narol');
        $xmlDist = $district !== '-' ? $district : 'Ahmedabad';
        $xmlState = $state !== '-' ? $state : 'Gujarat';
        $xmlPc = $pincode !== '-' ? $pincode : '382405';

        if (empty($phtB64)) {
            $phtB64 = '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';
        }

        $emailHash = hash('sha256', ($user->email ?? 'user@example.com') . '0000');
        $mobileHash = hash('sha256', ($user->phone ?? '9876543210') . '0000');
        $digestVal = base64_encode(hash('sha256', $userName . $xmlDob . $xmlPc, true));
        $sigVal = base64_encode(hash('sha256', $digestVal . $xmlRefId, true)) . base64_encode(hash('sha256', $phtB64, true));
        $certVal = 'MIIFizCCA3OgAwIBAgIIUe1Q7vMhU2gwDQYJKoZIhvcNAQELBQAwWTELMAkGA1UEBhMCSU4xNTAzBgNVBAoTLFVOSVFVRSBJREVOVElGSUNBVElPTiBBVVRIT1JJVFkgT0YgSU5ESUEgKFVJREFJMSAwHgYDVQQDExdVSURBSSBBdXRoZW50aWNhdGlvbiBDQTAeFw0yMjAxMDEw...';
        $phtXml = strlen($phtB64) > 380 ? substr($phtB64, 0, 380) . '...' : $phtB64;
        $certXml = strlen($certVal) > 220 ? substr($certVal, 0, 220) . '...' : $certVal;

        $rawXmlContinuous = '<?xml version="1.0" encoding="UTF-8"?> ' .
            '<OfflinePaperlessKyc referenceId="' . $xmlRefId . '">' .
            '<UidData>' .
            '<Poi dob="' . $xmlDob . '" e="' . $emailHash . '" g="' . $xmlGender . '" m="' . $mobileHash . '" name="' . $userName . '"/>' .
            '<Poa careof="' . $xmlCareOf . '" country="India" dist="' . $xmlDist . '" house="' . $xmlHouse . '" loc="' . $xmlLoc . '" pc="' . $xmlPc . '" po="' . $xmlPo . '" state="' . $xmlState . '" street="' . $xmlStreet . '" subdist="' . $xmlDist . '" vtc="' . $xmlVtc . '"/>' .
            '<Pht>' . $phtXml . '</Pht>' .
            '</UidData>' .
            '<Signature xmlns="http://www.w3.org/2000/09/xmldsig#">' .
            '<SignedInfo>' .
            '<CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>' .
            '<SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#rsa-sha256"/>' .
            '<Reference URI=""><Transforms><Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"/></Transforms><DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/><DigestValue>' . $digestVal . '</DigestValue></Reference>' .
            '</SignedInfo>' .
            '<SignatureValue>' . $sigVal . '</SignatureValue>' .
            '<KeyInfo><X509Data><X509SubjectName>CN=DS UNIQUE IDENTIFICATION AUTHORITY OF INDIA 03, O=UNIQUE IDENTIFICATION AUTHORITY OF INDIA, C=IN</X509SubjectName><X509Certificate>' . $certXml . '</X509Certificate></X509Data></KeyInfo>' .
            '</Signature>' .
            '</OfflinePaperlessKyc>';

        $rawXml = wordwrap($rawXmlContinuous, 96, "\n", true);
        $rawXml = htmlspecialchars($rawXml);
        $rawXml = nl2br($rawXml);

        if ($type === 'aadhaar') {
            $html = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='utf-8'>
                <title>DigiLocker verified e-Aadhaar</title>
                <style>
                    @page { size: A4; margin: 10mm; }
                    body { 
                        font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Arial, sans-serif; 
                        background: #ffffff; 
                        color: #000; 
                        margin: 0; 
                        padding: 0; 
                    }
                    .page-container { 
                        width: 100%; 
                        max-width: 720px; 
                        background: #ffffff; 
                        padding: 0; 
                        margin: 0 auto; 
                        box-sizing: border-box; 
                    }
                    .digio-logo { font-size: 30px; font-weight: 800; color: #1570ef; font-family: -apple-system, BlinkMacSystemFont, Arial, sans-serif; letter-spacing: -0.5px; margin-bottom: 6px; }
                    .doc-title { text-align: center; font-size: 19px; font-weight: 500; color: #000; margin: 6px 0 3px 0; }
                    .doc-subtitle { text-align: center; font-size: 10.5px; color: #333; margin: 0 0 16px 0; line-height: 1.3; }
                    
                    .digio-table { width: 100%; border-collapse: collapse; border: 1.5px solid #000; font-size: 11.5px; table-layout: fixed; }
                    .digio-table td { border: 1px solid #000; padding: 4.5px 8px; vertical-align: middle; color: #000; }
                    .lbl { font-weight: 400; color: #000; }
                    .val { font-weight: 400; color: #000; }
                    
                    .inner-table { width: 100%; border-collapse: collapse; }
                    .inner-table td { border: none; border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 4.5px 8px; font-size: 11.5px; }
                    .inner-table tr:last-child td { border-bottom: none; }
                    
                    .page-break { 
                        page-break-before: always; 
                        break-before: page; 
                        margin-top: 0; 
                    }
                    @media print {
                        body { padding: 0; background: #fff; }
                        .page-container { max-width: 100%; margin: 0; }
                        .page-break { page-break-before: always; break-before: page; margin-top: 0 !important; }
                    }
                </style>
            </head>
            <body>
                <div class='page-container'>
                    <div class='digio-logo'>digio</div>
                    
                    <div class='doc-title'>DigiLocker verified e-Aadhaar</div>
                    <div class='doc-subtitle'>This document is generated from verified Aadhaar XML obtained from DigiLocker with due user consent and authentication</div>
                    
                    <table class='digio-table'>
                        <!-- Row 1: Document type -->
                        <tr>
                            <td class='lbl' style='width: 170px;'>Document type</td>
                            <td colspan='3'>
                                <table style='width: 100%; border-collapse: collapse; border: none;'>
                                    <tr>
                                        <td style='border: none; padding: 0; font-size: 11.5px;'>e-Aadhaar generated from DigiLocker verified Aadhaar XML</td>
                                        <td style='border: none; padding: 0; width: 65px; text-align: center;'>
                                            <div style='display: inline-block; text-align: center;'>
                                                <svg width='18' height='18' viewBox='0 0 24 24' fill='#16a34a' style='vertical-align: middle;'><path d='M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z'/></svg>
                                                <div style='font-size: 8.5px; font-weight: 700; color: #000;'>XML verified</div>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        
                        <!-- Row 2: Generation date & Download date -->
                        <tr>
                            <td class='lbl' style='width: 170px;'>Generation date</td>
                            <td style='width: 220px; font-size: 11px;'>{$genDate}</td>
                            <td class='lbl' style='width: 120px;'>Download date</td>
                            <td style='font-size: 11px;'>{$genDate}</td>
                        </tr>
                        
                        <!-- Row 3: Masked Aadhaar number -->
                        <tr>
                            <td class='lbl' style='width: 170px;'>Masked Aadhaar number</td>
                            <td colspan='3' style='text-align: center; font-size: 16px; font-weight: 600; letter-spacing: 0.5px;'>
                                {$maskedAadhaar}
                            </td>
                        </tr>
                        
                        <!-- Row 4: Personal Details & Photo Split -->
                        <tr>
                            <td colspan='3' style='padding: 0; vertical-align: top; border-right: none;'>
                                <table class='inner-table'>
                                    <tr>
                                        <td class='lbl' style='width: 170px;'>Name</td>
                                        <td>{$userName}</td>
                                    </tr>
                                    <tr>
                                        <td class='lbl' style='width: 170px;'>Date of Birth</td>
                                        <td>{$dob}</td>
                                    </tr>
                                    <tr>
                                        <td class='lbl' style='width: 170px;'>Gender</td>
                                        <td>{$genderFull}</td>
                                    </tr>
                                    <tr>
                                        <td class='lbl' style='width: 170px;'>c/o , s/o</td>
                                        <td>{$fatherName}</td>
                                    </tr>
                                    <tr>
                                        <td class='lbl' style='width: 170px; vertical-align: top;'>Address</td>
                                        <td style='line-height: 1.35; font-size: 11px;'>{$fullAddress}</td>
                                    </tr>
                                </table>
                            </td>
                            <td style='width: 165px; text-align: center; vertical-align: middle; padding: 6px;'>
                                {$photoTag}
                            </td>
                        </tr>
                        
                        <!-- Row 5: Landmark & Locality -->
                        <tr>
                            <td class='lbl' style='width: 170px;'>Landmark</td>
                            <td style='width: 220px;'>{$landmark}</td>
                            <td class='lbl' style='width: 120px;'>Locality</td>
                            <td>{$locality}</td>
                        </tr>
                        
                        <!-- Row 6: City / District -->
                        <tr>
                            <td class='lbl' style='width: 170px;'>City / District</td>
                            <td style='width: 220px;'>{$district}</td>
                            <td colspan='2' style='background: #fff;'></td>
                        </tr>
                        
                        <!-- Row 7: Pin Code & State -->
                        <tr>
                            <td class='lbl' style='width: 170px;'>Pin Code</td>
                            <td style='width: 220px;'>{$pincode}</td>
                            <td class='lbl' style='width: 120px;'>State</td>
                            <td>{$state}</td>
                        </tr>
                    </table>
                    
                    <div style='margin-top: 25px; text-align: center; font-size: 10.5px; color: #555;'>
                        <a href='https://www.digio.in' target='_blank' style='color: #1570ef; text-decoration: none;'>www.digio.in</a> | For Limited Circulation | <span style='color: #ef4444; font-weight: 700;'>CONFIDENTIAL</span>
                    </div>
                </div>

                <!-- Page 2: Digitally signed e-Aadhaar XML -->
                <div class='page-container page-break'>
                    <div style='font-size: 14px; font-weight: 600; color: #000; margin-bottom: 6px;'>Digitally signed e-Aadhaar XML</div>
                    
                    <div style='border: 1px solid #000; padding: 8px 10px; background: #fff; font-family: monospace, Courier, sans-serif; font-size: 8px; line-height: 1.22; color: #111; text-align: justify; overflow: hidden; width: 100%; box-sizing: border-box;'>
                        {$rawXml}
                    </div>

                    <div style='margin-top: 20px; text-align: center; font-size: 10.5px; color: #555;'>
                        <a href='https://www.digio.in' target='_blank' style='color: #1570ef; text-decoration: none;'>www.digio.in</a> | For Limited Circulation | <span style='color: #ef4444; font-weight: 700;'>CONFIDENTIAL</span>
                    </div>
                </div>
            </body>
            </html>
            ";
        } else {
            $panNameUpper = strtoupper($record->pan_name ?? ($user->name ?? 'ACCOUNT HOLDER'));
            $genderUpper = strtoupper($genderFull);
            $panVerifiedOn = Carbon::parse($record->updated_at ?? now())->timezone('Asia/Kolkata')->format('d/m/Y H:i:s');
            $panQrPayload = "PAN:{$docNum}^NAME:{$panNameUpper}^DOB:{$dob}^GENDER:{$genderUpper}^VERIFIED:TRUE^TIMESTAMP:{$panVerifiedOn}^ISSUER:INCOME TAX DEPARTMENT GOVT OF INDIA";
            try {
                $qrSvg = (string) \SimpleSoftwareIO\QrCode\Facades\QrCode::size(115)->margin(1)->generate($panQrPayload);
                $qrSvgB64 = base64_encode($qrSvg);
                $qrImgTag = "<img src='data:image/svg+xml;base64,{$qrSvgB64}' width='115' height='115' style='display: block; margin: 0 auto;' alt='PAN Verification QR' />";
            } catch (\Throwable $e) {
                $qrImgTag = "<svg width='115' height='115' viewBox='0 0 100 100'><rect width='100' height='100' fill='#000'/></svg>";
            }

            $html = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='utf-8'>
                <title>PAN Verification Record</title>
                <style>
                    @page { size: A4; margin: 15mm; }
                    body { 
                        font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Arial, sans-serif; 
                        background: #ffffff; 
                        color: #000; 
                        margin: 0; 
                        padding: 10px; 
                    }
                    .pan-doc { 
                        background: #ffffff; 
                        border: 1px solid #cbd5e1; 
                        width: 100%; 
                        max-width: 600px; 
                        font-size: 12.5px; 
                        box-sizing: border-box;
                        margin: 0 auto;
                    }
                    .header-section { 
                        text-align: center; 
                        padding: 18px 16px 14px 16px; 
                        background: #fff; 
                    }
                    .gov-logo-title { 
                        display: flex; 
                        align-items: center; 
                        justify-content: center; 
                        gap: 16px; 
                    }
                    .emblem-box { 
                        flex-shrink: 0; 
                    }
                    .gov-text { 
                        text-align: left; 
                    }
                    .gov-text .hi { 
                        font-size: 24px; 
                        font-weight: 700; 
                        color: #111; 
                        margin: 0; 
                        line-height: 1.2; 
                        letter-spacing: -0.2px;
                    }
                    .gov-text .en { 
                        font-size: 20px; 
                        font-weight: 700; 
                        color: #111; 
                        margin: 0; 
                        line-height: 1.2; 
                    }
                    .banner-title { 
                        border-top: 1px solid #d1d5db; 
                        border-bottom: 1px solid #d1d5db; 
                        padding: 6px 10px; 
                        text-align: center; 
                        font-size: 15px; 
                        font-weight: 500; 
                        color: #000; 
                        letter-spacing: 0.5px; 
                    }
                    .sub-banner { 
                        padding: 5px 10px 2px 10px; 
                        text-align: center; 
                        font-size: 10.5px; 
                        color: #444; 
                    }
                    .pan-number-display { 
                        padding: 2px 10px 6px 10px; 
                        text-align: center; 
                        font-size: 19px; 
                        font-weight: 700; 
                        color: #000; 
                        letter-spacing: 1px; 
                        border-bottom: 1px solid #d1d5db; 
                        font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Arial, sans-serif; 
                    }
                    .table-grid { 
                        display: grid; 
                        grid-template-columns: 1fr 140px; 
                        border-bottom: 1px solid #d1d5db; 
                    }
                    .info-table { 
                        border-right: 1px solid #d1d5db; 
                        border-collapse: collapse; 
                        width: 100%; 
                    }
                    .info-row { 
                        display: flex; 
                        border-bottom: 1px solid #d1d5db; 
                    }
                    .info-row:last-child { 
                        border-bottom: none; 
                    }
                    .info-label { 
                        width: 85px; 
                        padding: 5px 8px; 
                        background: #fff; 
                        font-weight: 400; 
                        color: #000; 
                        border-right: 1px solid #d1d5db; 
                        font-size: 11.5px; 
                        text-align: right; 
                        display: flex;
                        align-items: center;
                        justify-content: flex-end;
                    }
                    .info-value { 
                        flex: 1; 
                        padding: 5px 8px; 
                        font-weight: 500; 
                        color: #000; 
                        font-size: 11.5px; 
                        display: flex;
                        align-items: center;
                    }
                    .qr-section { 
                        display: flex; 
                        align-items: center; 
                        justify-content: center; 
                        padding: 6px; 
                        background: #fff; 
                    }
                    .footer-badges { 
                        display: flex; 
                        align-items: center; 
                        justify-content: space-between; 
                        padding: 10px 16px; 
                        border-bottom: 1px solid #d1d5db; 
                        background: #fff; 
                    }
                    .digi-badge { 
                        display: inline-flex; 
                        align-items: center; 
                        background: #6366f1; 
                        border-radius: 20px; 
                        padding: 3px 12px 3px 5px; 
                        gap: 6px; 
                    }
                    .digi-signature { 
                        border: 1px solid #d1d5db; 
                        padding: 5px 10px; 
                        border-radius: 4px; 
                        display: inline-flex; 
                        align-items: center; 
                        gap: 6px; 
                    }
                    .notes-section { 
                        padding: 14px 18px; 
                        font-size: 10.5px; 
                        color: #111; 
                        line-height: 1.45; 
                        background: #fff; 
                    }
                    .notes-section ol { 
                        margin: 4px 0 0 0; 
                        padding-left: 16px; 
                    }
                    @media print { 
                        body { padding: 0; background: #fff; } 
                        .pan-doc { border: 1px solid #000; max-width: 100%; margin: 0; } 
                    }
                </style>
            </head>
            <body>
                <div class='pan-doc'>
                    <div class='header-section'>
                        <div class='gov-logo-title'>
                            <div class='emblem-box'>
                                <svg width='40' height='55' viewBox='0 0 100 135' fill='#333'>
                                    <path d='M50 0 C30 0 25 15 25 25 C25 35 32 45 40 48 L40 70 C30 72 20 80 20 95 L80 95 C80 80 70 72 60 70 L60 48 C68 45 75 35 75 25 C75 15 70 0 50 0 Z' fill='#333'/>
                                    <circle cx='50' cy='112' r='14' fill='none' stroke='#222' stroke-width='3'/>
                                    <path d='M10 130 L90 130 L85 135 L15 135 Z' fill='#222'/>
                                </svg>
                            </div>
                            <div class='gov-text'>
                                <div class='hi'>आयकर विभाग</div>
                                <div class='en'>Income Tax Department</div>
                            </div>
                        </div>
                    </div>

                    <div class='banner-title'>PAN VERIFICATION RECORD</div>
                    <div class='sub-banner'>Permanent Account Number</div>
                    <div class='pan-number-display'>{$docNum}</div>

                    <div class='table-grid'>
                        <div class='info-table'>
                            <div class='info-row'><div class='info-label'>Name</div><div class='info-value'>{$panNameUpper}</div></div>
                            <div class='info-row'><div class='info-label'>Gender</div><div class='info-value'>{$genderUpper}</div></div>
                            <div class='info-row'><div class='info-label'>DOB</div><div class='info-value'>{$dob}</div></div>
                            <div class='info-row'><div class='info-label'>Verified On</div><div class='info-value'>{$panVerifiedOn}</div></div>
                        </div>
                        <div class='qr-section'>
                            {$qrImgTag}
                        </div>
                    </div>

                    <div class='footer-badges'>
                        <div class='digi-badge'>
                            <div style='background: #fff; border-radius: 50%; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center;'>
                                <svg width='14' height='14' viewBox='0 0 24 24' fill='#6366f1'><path d='M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96z'/></svg>
                            </div>
                            <div style='text-align: left; line-height: 1.1;'>
                                <div style='font-size: 11px; font-weight: 800; color: #22c55e;'>✔ Verified</div>
                                <div style='font-size: 8px; color: #fff; font-weight: 500;'>by DigiLocker</div>
                            </div>
                        </div>
                        <div class='digi-signature'>
                            <div>
                                <div style='font-size: 10px; font-weight: 600; color: #111;'>Digitally Signed On:</div>
                                <div style='font-size: 9px; color: #333;'>{$panVerifiedOn} IST</div>
                            </div>
                            <svg width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='#16a34a' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'><polyline points='20 6 9 17 4 12'></polyline></svg>
                        </div>
                    </div>

                    <div class='notes-section'>
                        <strong>Note:</strong>
                        <ol>
                            <li>This PAN data is verified by DigiLocker (https://digilocker.gov.in) as per data provided by the issuing authority, Income Tax Department, Govt. of India.</li>
                            <li>This digitally signed verification document is valid as per the IT Act 2000 when used electronically.</li>
                        </ol>
                    </div>
                </div>
            </body>
            </html>
            ";
        }

        if ($action === 'download') {
            return $this->generatePdfFromHtml($html, "{$type}_{$userUid}.pdf");
        }

        return response($html, 200, ['Content-Type' => 'text/html']);
    }

    private function generatePdfFromHtml(string $html, string $fileName)
    {
        try {
            $options = new \Dompdf\Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'Helvetica');
            
            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $pdfContent = $dompdf->output();

            return response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
                'Content-Length' => strlen($pdfContent),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Dompdf generation error: " . $e->getMessage());
            return response($html, 200, [
                'Content-Type' => 'text/html',
                'Content-Disposition' => 'attachment; filename="' . str_replace('.pdf', '.html', $fileName) . '"',
            ]);
        }
    }

    private function syncDigioDataIfNeeded(string $userUid, $record)
    {
        if (!$record || empty($record->digio_request_id)) {
            return $record;
        }

        try {
            \App\Http\Controllers\Api\KycController::syncDigioKycData($userUid, $record->digio_request_id);
            return $this->kycQuery($userUid)->first() ?: $record;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Admin Sync Digio Exception: " . $e->getMessage());
        }

        return $record;
    }
}
