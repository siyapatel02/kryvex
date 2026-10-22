<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your MetaTrader 5 Account</title>
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
        }
        .brand-header {
            font-size: 13px;
            font-weight: 700;
            color: #facc15;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }
        h1 {
            font-size: 24px;
            font-weight: 800;
            color: #ffffff;
            margin: 0 0 16px 0;
        }
        .intro-text {
            font-size: 14px;
            color: #94a3b8;
            line-height: 1.5;
            margin-bottom: 24px;
        }
        .credentials-card {
            background: #0d111a;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }
        .cred-item {
            padding: 12px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }
        .cred-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        .cred-item:first-child {
            padding-top: 0;
        }
        .cred-label {
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .cred-val {
            font-size: 18px;
            font-weight: 800;
            color: #ffffff;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        }
        .cred-meta {
            margin-top: 14px;
            font-size: 13px;
            color: #94a3b8;
            font-weight: 600;
        }
        .cred-meta strong {
            color: #facc15;
        }
        .instructions {
            font-size: 13px;
            color: #94a3b8;
            line-height: 1.5;
            margin-bottom: 24px;
        }
        .btn-download {
            display: inline-block;
            background: #2563eb;
            color: #ffffff;
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
            padding: 12px 24px;
            border-radius: 8px;
            margin-bottom: 24px;
        }
        .footer-note {
            font-size: 12px;
            color: #64748b;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            padding-top: 16px;
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="brand-header">Kryvex Exchange</div>
        <h1>Your MetaTrader 5 account</h1>
        
        <p class="intro-text">
            Hi <strong>{{ $userName }}</strong>, your MT5 trading account is ready. Use the details below to log in.
        </p>

        <div class="credentials-card">
            <div class="cred-item">
                <div class="cred-label">Login (ID)</div>
                <div class="cred-val">{{ $login }}</div>
            </div>
            
            <div class="cred-item">
                <div class="cred-label">Password</div>
                <div class="cred-val">{{ $password }}</div>
            </div>

            <div class="cred-item">
                <div class="cred-label">Server</div>
                <div class="cred-val">{{ $server }}</div>
            </div>

            <div class="cred-meta">
                Account: <strong>{{ $planName }}</strong> &nbsp;·&nbsp; Currency: <strong>{{ $currency }}</strong>
            </div>
        </div>

        <p class="instructions">
            Download MetaTrader 5, then open <strong>File → Login to Trade Account</strong> and enter the details above.
        </p>

        <a href="https://www.metatrader5.com/en/download" class="btn-download" target="_blank">
            Download MetaTrader 5
        </a>

        <div class="footer-note">
            Keep this email private. Do not share your password with anyone.
        </div>
    </div>
</body>
</html>
