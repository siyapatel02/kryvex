<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\UTCDateTime;

class AccountStatusService
{
    /**
     * Sync and return the current account state for one user.
     *
     * What this service controls:
     * - Scheduled suspension grace time.
     * - Scheduled block grace time.
     * - Automatic suspension activation after grace time.
     * - Automatic suspension clear after duration ends.
     * - Frontend timing payload for live countdown banners.
     */
    public static function syncByUid(?string $userUid): ?array
    {
        if (!$userUid) {
            return null;
        }

        $user = DB::connection('mongodb')
            ->table('users')
            ->where('uid', $userUid)
            ->first();

        if (!$user) {
            $user = DB::connection('mongodb')
                ->table('users')
                ->where('_id', $userUid)
                ->first();
        }

        if (!$user) {
            return null;
        }

        $now = now(config('app.timezone'));
        $userArr = (array) $user;
        $userUidForUpdate = $userArr['uid'] ?? $userUid;

        $status = $userArr['status'] ?? 'active';
        $delStatus = $userArr['deletion_status'] ?? '';
        $suspensionStatus = $userArr['suspension_status'] ?? null;
        $blockStatus = $userArr['block_status'] ?? null;

        $suspensionEffectiveAt = self::toCarbon($userArr['suspension_effective_at'] ?? null);
        $suspensionUntil = self::toCarbon($userArr['suspension_until'] ?? null);
        $blockEffectiveAt = self::toCarbon($userArr['block_effective_at'] ?? null);

        if ($status === 'deleted' || $delStatus === 'approved') {
            return [
                'state' => 'deleted',
                'status' => 'deleted',
                'reason' => 'Account closed / deleted.',
                'message' => 'Your account has been closed/deleted by administrator.',
            ];
        }

        if ($delStatus === 'pending') {
            $requestedAt = self::toCarbon($userArr['deletion_requested_at'] ?? null);
            return [
                'state' => 'deletion_pending',
                'status' => 'deletion_pending',
                'reason' => $userArr['deletion_reason'] ?? 'Account deletion request submitted.',
                'message' => 'Your account deletion request has been submitted and is currently pending review by administrator.',
                'effective_at' => $requestedAt?->toIso8601String(),
                'deletion_requested_at' => $requestedAt?->toIso8601String(),
            ];
        }

        $query = DB::connection('mongodb')
            ->table('users')
            ->where('uid', $userUidForUpdate);

        /**
         * Scheduled block reached.
         * User had grace time to make changes; now account becomes blocked.
         */
        if ($blockStatus === 'scheduled' && $blockEffectiveAt && $now->greaterThanOrEqualTo($blockEffectiveAt)) {
            $query->update([
                'status' => 'blocked',
                'block_status' => 'active',
                'blocked_at' => $now,
                'updated_at' => $now,
            ]);

            $status = 'blocked';
            $blockStatus = 'active';
        }

        /**
         * Scheduled suspension reached.
         * User was active during grace time; now account becomes suspended.
         */
        if ($suspensionStatus === 'scheduled' && $suspensionEffectiveAt && $now->greaterThanOrEqualTo($suspensionEffectiveAt)) {
            // If the full suspension window already expired, clear it immediately.
            if ($suspensionUntil && $now->greaterThanOrEqualTo($suspensionUntil)) {
                self::clearSuspension($userUidForUpdate);

                return self::activePayload('Suspension completed. Account is active.');
            }

            $query->update([
                'status' => 'suspended',
                'suspension_status' => 'active',
                'suspended_at' => $now,
                'updated_at' => $now,
            ]);

            $status = 'suspended';
            $suspensionStatus = 'active';
        }

        /**
         * Active suspension expired.
         * User gets automatic access again without admin manual action.
         */
        if (($status === 'suspended' || $suspensionStatus === 'active') && $suspensionUntil && $now->greaterThanOrEqualTo($suspensionUntil)) {
            self::clearSuspension($userUidForUpdate);

            return self::activePayload('Suspension completed. Account is active.');
        }

        /**
         * Scheduled block warning state.
         * User is still active, but frontend shows countdown and warning banner.
         */
        if ($blockStatus === 'scheduled') {
            return array_merge([
                'state' => 'block_scheduled',
                'status' => 'active',
                'reason' => $user->block_reason ?? $user->blocked_reason ?? 'Account block scheduled by admin.',
                'message' => 'Your account is scheduled to be blocked.',
            ], self::buildTimingPayload($blockEffectiveAt, null));
        }

        /**
         * Final blocked state.
         * No automatic expiry is used for block; admin must activate manually.
         */
        if ($status === 'blocked' || $blockStatus === 'active') {
            return array_merge([
                'state' => 'blocked',
                'status' => 'blocked',
                'reason' => $user->block_reason ?? $user->blocked_reason ?? 'Account blocked by admin.',
                'message' => 'Your account has been blocked.',
            ], self::buildTimingPayload($blockEffectiveAt, null));
        }

        /**
         * Scheduled suspension warning state.
         * User is still active and can close trades during grace time.
         */
        if ($suspensionStatus === 'scheduled') {
            return array_merge([
                'state' => 'suspension_scheduled',
                'status' => 'active',
                'reason' => $user->suspension_reason ?? 'No reason provided.',
                'message' => 'Your account is scheduled for suspension.',
            ], self::buildTimingPayload($suspensionEffectiveAt, $suspensionUntil));
        }

        /**
         * Active suspended state.
         * Wallet/trading write actions are blocked until suspension_until.
         */
        if ($status === 'suspended' || $suspensionStatus === 'active') {
            return array_merge([
                'state' => 'suspended',
                'status' => 'suspended',
                'reason' => $user->suspension_reason ?? 'No reason provided.',
                'message' => 'Your account is suspended.',
            ], self::buildTimingPayload($suspensionEffectiveAt, $suspensionUntil));
        }

        return self::activePayload('Account is active.');
    }

    /**
     * Clear all scheduled/active restriction fields and set the user active.
     * This is used when admin clicks Activate or when suspension expires.
     */
    public static function clearSuspension(string $userUid): void
    {
        DB::connection('mongodb')
            ->table('users')
            ->where('uid', $userUid)
            ->update([
                'status' => 'active',
                'suspension_status' => null,
                'suspension_reason' => null,
                'suspension_effective_at' => null,
                'suspension_until' => null,
                'suspension_duration_minutes' => null,
                'suspended_at' => null,
                'block_status' => null,
                'block_reason' => null,
                'blocked_reason' => null,
                'block_effective_at' => null,
                'blocked_at' => null,
                'updated_at' => now(config('app.timezone')),
            ]);
    }

    /**
     * Common response for active accounts.
     */
    private static function activePayload(string $message): array
    {
        return [
            'state' => 'active',
            'status' => 'active',
            'reason' => null,
            'effective_at' => null,
            'until' => null,
            'server_now' => now(config('app.timezone'))->toIso8601String(),
            'grace_remaining_seconds' => 0,
            'suspended_remaining_seconds' => 0,
            'duration_minutes' => 0,
            'message' => $message,
        ];
    }

    /**
     * Convert MongoDB / Laravel / string date values to app timezone Carbon.
     */
    private static function toCarbon($value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        if ($value instanceof UTCDateTime) {
            return Carbon::instance($value->toDateTime())->setTimezone(config('app.timezone'));
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->setTimezone(config('app.timezone'));
        }

        return Carbon::parse($value)->setTimezone(config('app.timezone'));
    }

    /**
     * Return ISO dates + countdown values for the frontend banner.
     * Frontend uses the ISO dates to update countdown every second without waiting for API polling.
     */
    private static function buildTimingPayload(?Carbon $effectiveAt, ?Carbon $until): array
    {
        $now = now(config('app.timezone'));

        $graceRemainingSeconds = 0;
        $suspendedRemainingSeconds = 0;
        $durationMinutes = 0;

        if ($effectiveAt && $now->lt($effectiveAt)) {
            $graceRemainingSeconds = $now->diffInSeconds($effectiveAt);
        }

        if ($until && $now->lt($until)) {
            $suspendedRemainingSeconds = $now->diffInSeconds($until);
        }

        if ($effectiveAt && $until) {
            $durationMinutes = $effectiveAt->diffInMinutes($until);
        }

        return [
            'server_now' => $now->toIso8601String(),
            'effective_at' => $effectiveAt?->toIso8601String(),
            'until' => $until?->toIso8601String(),
            'grace_remaining_seconds' => $graceRemainingSeconds,
            'suspended_remaining_seconds' => $suspendedRemainingSeconds,
            'duration_minutes' => $durationMinutes,
        ];
    }
}
