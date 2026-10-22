<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AdminNotificationController extends Controller
{
    public function index()
    {
        $users = DB::connection('mongodb')
            ->table('users')
            ->orderBy('name')
            ->limit(500)
            ->get();

        $notifications = DB::connection('mongodb')
            ->table('user_notifications')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        $templates = self::templates();

        return view('admin.notifications.index', compact('users', 'notifications', 'templates'));
    }

    public function send(Request $request)
    {
        $validated = $request->validate([
            'target' => ['required', 'string'],
            'category' => ['nullable', 'string', 'max:30'],
            'template_key' => ['nullable', 'string', 'max:80'],
            'type' => ['required', 'string', 'max:30'],
            'title' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:1500'],
            'email_subject' => ['nullable', 'string', 'max:160'],
            'email_title' => ['nullable', 'string', 'max:160'],
            'email_body' => ['nullable', 'string', 'max:4000'],
            'send_email' => ['nullable'],
        ]);

        $users = $this->targetUsers($validated['target']);
        $sendEmail = $request->boolean('send_email');
        $emailSent = 0;
        $emailFailed = 0;

        foreach ($users as $user) {
            $result = self::createForUser(
                (string) ($user->uid ?? ''),
                $validated['type'],
                $validated['title'],
                $validated['message'],
                [
                    'source' => 'admin_manual',
                    'admin' => session('admin_email') ?? 'admin',
                    'send_email' => $sendEmail,
                    'category' => $validated['category'] ?? self::categoryForType($validated['type']),
                    'template_key' => $validated['template_key'] ?? 'custom',
                    'email_subject' => $validated['email_subject'] ?? $validated['title'],
                    'email_title' => $validated['email_title'] ?? $validated['title'],
                    'email_body' => $validated['email_body'] ?? $validated['message'],
                ],
                $sendEmail,
                $user
            );

            if ($sendEmail) {
                $result ? $emailSent++ : $emailFailed++;
            }
        }

        $message = 'Notification sent successfully.';

        if ($sendEmail) {
            $message .= ' Email sent: ' . $emailSent . '.';

            if ($emailFailed > 0) {
                $message .= ' Email failed: ' . $emailFailed . '. Check Laravel logs.';
            }
        }

        return back()->with('success', $message);
    }

    public function saveTemplate(Request $request)
    {
        $validated = $request->validate([
            'template_key' => ['required', 'string', 'max:80'],
            'category' => ['required', 'string', 'max:30'],
            'type' => ['required', 'string', 'max:30'],
            'label' => ['required', 'string', 'max:120'],
            'title' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:1500'],
            'email_subject' => ['required', 'string', 'max:160'],
            'email_title' => ['required', 'string', 'max:160'],
            'email_body' => ['required', 'string', 'max:4000'],
        ]);

        DB::connection('mongodb')
            ->table('notification_templates')
            ->updateOrInsert(
                ['template_key' => $validated['template_key']],
                [
                    ...$validated,
                    'is_custom' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

        return back()->with('success', 'Notification template saved successfully.');
    }

    /**
     * Create notification for one user. If $sendEmail is true, direct email is sent immediately.
     * This method is used by admin wallet actions, account status actions and platform setting changes.
     */
    public static function createForUser(
        string $userUid,
        string $type,
        string $title,
        string $message,
        array $meta = [],
        bool $sendEmail = false,
        object|array|null $user = null
    ): bool {
        if ($userUid === '') {
            return false;
        }

        $normalizedType = self::normalizeType($type);

        DB::connection('mongodb')->table('user_notifications')->insert([
            'notification_uid' => (string) Str::uuid(),
            'user_uid' => $userUid,
            'type' => $normalizedType,
            'title' => $title,
            'message' => $message,
            'meta' => $meta,
            'email_requested' => $sendEmail,
            'email_sent_at' => null,
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (! $sendEmail) {
            return true;
        }

        return self::sendEmailToUser($userUid, $title, $message, $user, $normalizedType, $meta);
    }

    public static function createAndEmailForUser(string $userUid, string $type, string $title, string $message, array $meta = []): bool
    {
        return self::createForUser($userUid, $type, $title, $message, $meta, true);
    }

    public static function createForAllUsers(string $type, string $title, string $message, array $meta = [], bool $sendEmail = true, int $limit = 5000): array
    {
        $users = DB::connection('mongodb')
            ->table('users')
            ->limit($limit)
            ->get();

        $created = 0;
        $emailSent = 0;
        $emailFailed = 0;

        foreach ($users as $user) {
            $created++;

            $result = self::createForUser(
                (string) ($user->uid ?? ''),
                $type,
                $title,
                $message,
                $meta,
                $sendEmail,
                $user
            );

            if ($sendEmail) {
                $result ? $emailSent++ : $emailFailed++;
            }
        }

        return compact('created', 'emailSent', 'emailFailed');
    }

    public static function sendEmailToUser(
        string $userUid,
        string $title,
        string $message,
        object|array|null $user = null,
        string $type = 'inform',
        array $meta = []
    ): bool {
        $user = $user ?: DB::connection('mongodb')
            ->table('users')
            ->where('uid', $userUid)
            ->first();

        if (! $user) {
            return false;
        }

        $email = is_array($user) ? ($user['email'] ?? null) : ($user->email ?? null);
        $name = is_array($user) ? ($user['name'] ?? 'User') : ($user->name ?? 'User');

        if (empty($email)) {
            return false;
        }

        $templateKey = (string) ($meta['template_key'] ?? self::templateKeyForType($type));
        $template = self::template($templateKey);

        $subject = self::replaceVars((string) ($meta['email_subject'] ?? $template['email_subject'] ?? $title), $user, $meta);
        $emailTitle = self::replaceVars((string) ($meta['email_title'] ?? $template['email_title'] ?? $title), $user, $meta);
        $emailBody = self::replaceVars((string) ($meta['email_body'] ?? $template['email_body'] ?? $message), $user, [
            ...$meta,
            'message' => $message,
        ]);

        $html = self::renderEmailCard($name, $emailTitle, $emailBody, $type, $meta);

        try {
            Mail::html($html, function ($mail) use ($email, $subject) {
                $mail->to($email)->subject($subject);
            });

            DB::connection('mongodb')
                ->table('user_notifications')
                ->where('user_uid', $userUid)
                ->where('title', $title)
                ->where('message', $message)
                ->orderByDesc('created_at')
                ->limit(1)
                ->update([
                    'email_sent_at' => now(),
                    'updated_at' => now(),
                ]);

            return true;
        } catch (\Throwable $error) {
            Log::error('Kryvex notification email failed', [
                'user_uid' => $userUid,
                'email' => $email,
                'title' => $title,
                'error' => $error->getMessage(),
            ]);

            return false;
        }
    }

    public static function normalizeType(string $type): string
    {
        $type = strtolower(trim($type));

        return match ($type) {
            'info', 'update', 'maintenance', 'account' => $type === 'maintenance' ? 'maintenance' : 'inform',
            'approval', 'approved', 'withdrawal', 'active' => 'success',
            'rejection', 'rejected', 'blocked', 'block', 'error', 'danger' => 'alert',
            'suspended', 'suspend', 'pending' => 'warning',
            default => in_array($type, ['inform', 'success', 'warning', 'alert'], true) ? $type : 'inform',
        };
    }

    public static function categoryForType(string $type): string
    {
        $normalized = self::normalizeType($type);

        return match ($normalized) {
            'success' => 'success',
            'alert' => 'alert',
            'warning', 'maintenance' => 'warning',
            default => 'inform',
        };
    }

    public static function templateKeyForType(string $type): string
    {
        $type = strtolower($type);

        return match (true) {
            str_contains($type, 'maintenance') => 'maintenance_warning',
            str_contains($type, 'withdraw') && (str_contains($type, 'reject') || str_contains($type, 'rejection')) => 'withdraw_rejected',
            str_contains($type, 'withdraw') && (str_contains($type, 'approve') || str_contains($type, 'approval')) => 'withdraw_approved',
            str_contains($type, 'reject'), str_contains($type, 'rejection'), str_contains($type, 'block'), str_contains($type, 'alert') => 'account_alert',
            str_contains($type, 'approve'), str_contains($type, 'approval'), str_contains($type, 'success'), str_contains($type, 'active') => 'success_update',
            str_contains($type, 'suspend'), str_contains($type, 'warning') => 'account_warning',
            default => 'platform_inform',
        };
    }

    public static function template(string $templateKey): array
    {
        $templates = self::templates();

        return $templates[$templateKey] ?? $templates['platform_inform'];
    }

    public static function templates(): array
    {
        $defaults = self::defaultTemplates();

        try {
            $customTemplates = DB::connection('mongodb')
                ->table('notification_templates')
                ->get();

            foreach ($customTemplates as $template) {
                $key = (string) ($template->template_key ?? '');
                if ($key === '') {
                    continue;
                }

                $defaults[$key] = [
                    'template_key' => $key,
                    'category' => $template->category ?? 'inform',
                    'type' => $template->type ?? 'inform',
                    'label' => $template->label ?? Str::headline($key),
                    'title' => $template->title ?? '',
                    'message' => $template->message ?? '',
                    'email_subject' => $template->email_subject ?? '',
                    'email_title' => $template->email_title ?? '',
                    'email_body' => $template->email_body ?? '',
                    'is_custom' => true,
                ];
            }
        } catch (\Throwable $error) {
            // Keep admin page available even if template collection is empty or unavailable.
        }

        return $defaults;
    }

    private static function defaultTemplates(): array
    {
        return [
            'platform_inform' => [
                'template_key' => 'platform_inform',
                'category' => 'inform',
                'type' => 'inform',
                'label' => 'Platform Update',
                'title' => 'Platform Update',
                'message' => 'A new Kryvex update is available for your account.',
                'email_subject' => 'Kryvex Platform Update',
                'email_title' => 'Platform Update',
                'email_body' => 'Hello {{user}},\n\n{{message}}\n\nOpen your Kryvex account to view more details.',
            ],
            'maintenance_warning' => [
                'template_key' => 'maintenance_warning',
                'category' => 'warning',
                'type' => 'maintenance',
                'label' => 'Maintenance Notice',
                'title' => 'Maintenance Notice',
                'message' => 'Kryvex maintenance mode has been updated. Some services may be temporarily unavailable.',
                'email_subject' => 'Kryvex Maintenance Notice',
                'email_title' => 'Maintenance Notice',
                'email_body' => 'Hello {{user}},\n\nKryvex maintenance mode has been updated.\n\nStatus: {{status}}\nTime: {{date}} {{time}}\n\nWe will keep you updated inside your Kryvex notification center.',
            ],
            'account_warning' => [
                'template_key' => 'account_warning',
                'category' => 'warning',
                'type' => 'warning',
                'label' => 'Account Warning / Suspended',
                'title' => 'Account Status Warning',
                'message' => 'Your Kryvex account status has been updated. Please review your account notifications.',
                'email_subject' => 'Kryvex Account Status Warning',
                'email_title' => 'Account Status Warning',
                'email_body' => 'Hello {{user}},\n\nYour account status has been updated.\n\nStatus: {{status}}\nReason: {{reason}}\n\nPlease contact support if you need help.',
            ],
            'account_alert' => [
                'template_key' => 'account_alert',
                'category' => 'alert',
                'type' => 'alert',
                'label' => 'Alert / Rejected / Blocked',
                'title' => 'Important Account Alert',
                'message' => 'An important account action has been applied to your Kryvex account.',
                'email_subject' => 'Important Kryvex Account Alert',
                'email_title' => 'Important Account Alert',
                'email_body' => 'Hello {{user}},\n\nAn important account action has been applied.\n\nStatus: {{status}}\nReason: {{reason}}\nAmount: {{amount}}\n\nPlease check your Kryvex account for details.',
            ],
            'success_update' => [
                'template_key' => 'success_update',
                'category' => 'success',
                'type' => 'success',
                'label' => 'Success / Approved',
                'title' => 'Request Completed Successfully',
                'message' => 'Your Kryvex request has been completed successfully.',
                'email_subject' => 'Kryvex Request Completed',
                'email_title' => 'Request Completed Successfully',
                'email_body' => 'Hello {{user}},\n\nYour request has been completed successfully.\n\nStatus: {{status}}\nAmount: {{amount}}\nTime: {{date}} {{time}}\n\nThank you for using Kryvex.',
            ],
            'withdraw_approved' => [
                'template_key' => 'withdraw_approved',
                'category' => 'success',
                'type' => 'success',
                'label' => 'Withdraw Approved',
                'title' => 'Withdrawal Approved',
                'message' => 'Your withdrawal request has been approved.',
                'email_subject' => 'Kryvex Withdrawal Approved',
                'email_title' => 'Withdrawal Approved',
                'email_body' => 'Hello {{user}},\n\nYour withdrawal request has been approved.\n\nAmount: {{amount}}\nStatus: Approved\nTime: {{date}} {{time}}\n\nThe final amount will be processed according to platform rules.',
            ],
            'withdraw_rejected' => [
                'template_key' => 'withdraw_rejected',
                'category' => 'alert',
                'type' => 'alert',
                'label' => 'Withdraw Rejected',
                'title' => 'Withdrawal Rejected',
                'message' => 'Your withdrawal request has been rejected.',
                'email_subject' => 'Kryvex Withdrawal Rejected',
                'email_title' => 'Withdrawal Rejected',
                'email_body' => 'Hello {{user}},\n\nYour withdrawal request has been rejected.\n\nAmount: {{amount}}\nReason: {{reason}}\nTime: {{date}} {{time}}\n\nPlease review your wallet notifications for more information.',
            ],
        ];
    }

    private static function replaceVars(string $text, object|array|null $user = null, array $meta = []): string
    {
        $name = is_array($user) ? ($user['name'] ?? 'User') : ($user->name ?? 'User');
        $email = is_array($user) ? ($user['email'] ?? '') : ($user->email ?? '');

        $vars = [
            '{{user}}' => $name,
            '{{email}}' => $email,
            '{{amount}}' => (string) ($meta['amount'] ?? $meta['withdraw_amount'] ?? '--'),
            '{{status}}' => (string) ($meta['status'] ?? $meta['action'] ?? '--'),
            '{{coin}}' => (string) ($meta['coin'] ?? 'USDT'),
            '{{wallet}}' => (string) ($meta['wallet'] ?? 'Kryvex Wallet'),
            '{{date}}' => now()->format('d M Y'),
            '{{time}}' => now()->format('h:i A'),
            '{{reason}}' => (string) ($meta['reason'] ?? $meta['admin_note'] ?? '--'),
            '{{platform}}' => config('app.name', 'Kryvex'),
            '{{balance}}' => (string) ($meta['balance'] ?? '--'),
            '{{ticket}}' => (string) ($meta['ticket'] ?? $meta['withdrawal_uid'] ?? '--'),
            '{{message}}' => (string) ($meta['message'] ?? ''),
        ];

        return str_replace(array_keys($vars), array_values($vars), $text);
    }

    private static function renderEmailCard(string $name, string $title, string $body, string $type, array $meta = []): string
    {
        $category = self::categoryForType($type);
        $accent = match ($category) {
            'inform' => '#3b82f6',
            'information' => '#3b82f6',
            'success' => '#22c55e',
            'alert' => '#ef4444',
            default => '#f59e0b',
        };

        $badge = strtoupper($category === 'inform' ? 'INFO' : $category);
        $bodyHtml = nl2br(e($body));
        $titleHtml = e($title);
        $nameHtml = e($name);
        $appName = e(config('app.name', 'Kryvex'));

        return <<<HTML
<!doctype html>
<html>
<body style="margin:0;background:#07080b;padding:28px;font-family:Arial,Helvetica,sans-serif;color:#f8fafc;">
  <div style="max-width:640px;margin:0 auto;background:#10131b;border:1px solid rgba(255,255,255,.10);border-radius:24px;overflow:hidden;box-shadow:0 30px 90px rgba(0,0,0,.45);">
    <div style="padding:22px 26px;border-bottom:1px solid rgba(255,255,255,.08);background:linear-gradient(135deg,rgba(245,158,11,.18),rgba(255,255,255,.02));">
      <div style="display:inline-block;background:#f5c542;color:#07080b;font-weight:900;padding:8px 12px;border-radius:12px;letter-spacing:.04em;">{$appName}</div>
      <span style="float:right;display:inline-block;border:1px solid {$accent};color:{$accent};padding:7px 10px;border-radius:999px;font-size:11px;font-weight:900;">{$badge}</span>
    </div>
    <div style="padding:28px 26px;">
      <p style="margin:0 0 8px;color:#94a3b8;font-size:14px;">Hello {$nameHtml},</p>
      <h1 style="margin:0 0 16px;color:#ffffff;font-size:24px;line-height:1.25;">{$titleHtml}</h1>
      <div style="border-left:4px solid {$accent};background:rgba(255,255,255,.035);border-radius:14px;padding:16px 18px;color:#dbe3ee;font-size:15px;line-height:1.65;">{$bodyHtml}</div>
      <a href="/wallet" style="display:inline-block;margin-top:22px;background:#f5c542;color:#07080b;text-decoration:none;font-weight:900;padding:12px 18px;border-radius:14px;">Open Kryvex</a>
      <p style="margin:24px 0 0;color:#94a3b8;font-size:13px;line-height:1.6;">Regards,<br>{$appName} Team</p>
    </div>
  </div>
</body>
</html>
HTML;
    }

    private function targetUsers(string $target)
    {
        $query = DB::connection('mongodb')->table('users');

        if ($target !== 'all') {
            $query->where('uid', $target);
        }

        return $query->limit(1000)->get();
    }
}
