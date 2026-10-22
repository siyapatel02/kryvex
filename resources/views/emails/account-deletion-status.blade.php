<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $action === 'approved' ? 'Account Deletion Request Approved' : 'Account Deletion Request Update' }}</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #0b0e14;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #e2e8f0;
        }
        .email-wrapper {
            max-width: 560px;
            margin: 30px auto;
            background: #121824;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 32px 28px;
            box-sizing: border-box;
        }
        .brand-header {
            font-size: 13px;
            font-weight: 800;
            color: #f59e0b;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        h1 {
            font-size: 22px;
            font-weight: 800;
            color: #ffffff;
            margin: 0 0 16px 0;
            line-height: 1.3;
        }
        .badge {
            display: inline-block;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 6px;
            margin-bottom: 14px;
            letter-spacing: 0.5px;
        }
        .badge-danger {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        .badge-success {
            background: rgba(34, 197, 94, 0.15);
            color: #4ade80;
            border: 1px solid rgba(34, 197, 94, 0.3);
        }
        .intro-text {
            font-size: 14px;
            color: #94a3b8;
            line-height: 1.6;
            margin-bottom: 22px;
        }
        .status-card {
            background: #0d111a;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }
        .status-item {
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .status-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        .status-item:first-child {
            padding-top: 0;
        }
        .item-label {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .item-value {
            font-size: 13px;
            font-weight: 700;
            color: #ffffff;
            text-align: right;
        }
        .notice-box {
            padding: 14px 16px;
            border-radius: 10px;
            font-size: 13px;
            line-height: 1.5;
            margin-bottom: 24px;
        }
        .notice-approved {
            background: rgba(239, 68, 68, 0.08);
            border: 1px solid rgba(239, 68, 68, 0.25);
            color: #fca5a5;
        }
        .notice-rejected {
            background: rgba(34, 197, 94, 0.08);
            border: 1px solid rgba(34, 197, 94, 0.25);
            color: #86efac;
        }
        .btn-action {
            display: inline-block;
            background: #3b82f6;
            color: #ffffff !important;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            padding: 11px 22px;
            border-radius: 8px;
            margin-bottom: 24px;
        }
        .footer-note {
            font-size: 12px;
            color: #64748b;
            line-height: 1.6;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            padding-top: 20px;
        }
        .footer-note a {
            color: #f59e0b;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="brand-header">
            <span>⚡ KRYVEX EXCHANGE</span>
        </div>

        @if($action === 'approved')
            <span class="badge badge-danger">Account Closed</span>
            <h1>Account Deletion Request Approved</h1>
            <p class="intro-text">
                Hello <strong>{{ $userName }}</strong>,<br><br>
                We are writing to inform you that your request to delete your Kryvex account has been <strong>approved</strong> by our administration team.
            </p>

            <div class="status-card">
                <div class="status-item">
                    <span class="item-label">Account Holder</span>
                    <span class="item-value">{{ $userName }}</span>
                </div>
                <div class="status-item">
                    <span class="item-label">Email Address</span>
                    <span class="item-value">{{ $userEmail }}</span>
                </div>
                @if($clientId)
                <div class="status-item">
                    <span class="item-label">Client ID</span>
                    <span class="item-value">{{ $clientId }}</span>
                </div>
                @endif
                <div class="status-item">
                    <span class="item-label">Processed Date</span>
                    <span class="item-value">{{ $processedAt }}</span>
                </div>
                <div class="status-item">
                    <span class="item-label">Account Status</span>
                    <span class="item-value" style="color: #ef4444;">Deactivated / Deleted</span>
                </div>
            </div>

            <div class="notice-box notice-approved">
                ⚠️ <strong>Important:</strong> Your login access and trading permissions have been disabled in accordance with your request. All active sessions have been terminated.
            </div>

            <p class="intro-text" style="font-size: 13px;">
                Thank you for trading with Kryvex. If you believe this action was taken in error or if you did not submit this request, please contact our support desk immediately.
            </p>
        @else
            <span class="badge badge-success">Request Rejected / Account Active</span>
            <h1>Account Deletion Request Update</h1>
            <p class="intro-text">
                Hello <strong>{{ $userName }}</strong>,<br><br>
                Your recent request to delete your Kryvex account has been <strong>reviewed and rejected</strong> by our administration team. Your account remains <strong>active</strong> with full access.
            </p>

            <div class="status-card">
                <div class="status-item">
                    <span class="item-label">Account Holder</span>
                    <span class="item-value">{{ $userName }}</span>
                </div>
                <div class="status-item">
                    <span class="item-label">Email Address</span>
                    <span class="item-value">{{ $userEmail }}</span>
                </div>
                @if($clientId)
                <div class="status-item">
                    <span class="item-label">Client ID</span>
                    <span class="item-value">{{ $clientId }}</span>
                </div>
                @endif
                <div class="status-item">
                    <span class="item-label">Processed Date</span>
                    <span class="item-value">{{ $processedAt }}</span>
                </div>
                <div class="status-item">
                    <span class="item-label">Account Status</span>
                    <span class="item-value" style="color: #22c55e;">Active</span>
                </div>
                @if($reason)
                <div class="status-item">
                    <span class="item-label">Admin Reason</span>
                    <span class="item-value" style="color: #facc15;">{{ $reason }}</span>
                </div>
                @endif
            </div>

            <div class="notice-box notice-rejected">
                ✅ <strong>Account Restored:</strong> All read-only restrictions have been lifted. You can now deposit, withdraw, and trade normally.
            </div>

            <div style="text-align: left; margin: 20px 0;">
                <a href="{{ url('/login') }}" class="btn-action">Log In to Your Account</a>
            </div>
        @endif

        <div class="footer-note">
            This is an automated security notice from Kryvex Exchange.<br>
            If you need assistance, please reply to this email or visit our <a href="{{ url('/') }}">Support Center</a>.
        </div>
    </div>
</body>
</html>
