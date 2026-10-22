<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personal Data Archive - Kryvex Exchange</title>
    <!-- Include html2pdf library for instant direct PDF file download -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; 
            background: #f1f5f9; 
            color: #0f172a; 
            padding: 24px; 
            font-size: 13px; 
            line-height: 1.5; 
        }
        
        .pdf-container {
            max-width: 900px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            padding: 36px 40px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
        }

        .action-bar {
            max-width: 900px;
            margin: 0 auto 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            text-decoration: none;
        }

        .btn-download {
            background: #7c3aed;
            color: #ffffff;
            box-shadow: 0 4px 14px rgba(124, 58, 237, 0.35);
        }
        .btn-download:hover {
            background: #6d28d9;
        }

        .btn-print {
            background: #ffffff;
            color: #334155;
            border: 1px solid #cbd5e1;
        }
        .btn-print:hover {
            background: #f8fafc;
            border-color: #94a3b8;
        }

        .header { 
            display: flex; 
            justify-content: space-between; 
            align-items: flex-start; 
            padding-bottom: 20px; 
            border-bottom: 3px solid #7c3aed; 
            margin-bottom: 24px; 
        }
        .brand { display: flex; align-items: center; gap: 12px; }
        .brand-logo { 
            width: 44px; 
            height: 44px; 
            background: linear-gradient(135deg, #7c3aed, #4c1d95); 
            border-radius: 12px; 
            color: #fff; 
            font-weight: 900; 
            font-size: 24px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
        }
        .brand-text h1 { font-size: 22px; font-weight: 900; color: #0f172a; letter-spacing: -0.02em; }
        .brand-text p { color: #64748b; font-size: 12px; margin-top: 1px; font-weight: 600; }
        
        .meta { text-align: right; font-size: 12px; color: #475569; }
        .meta strong { color: #0f172a; }
        .doc-badge { 
            display: inline-block; 
            background: #f3e8ff; 
            color: #6b21a8; 
            font-weight: 800; 
            font-size: 10px; 
            text-transform: uppercase; 
            padding: 3px 8px; 
            border-radius: 6px; 
            margin-bottom: 4px; 
        }
        
        .section-box { 
            background: #f8fafc; 
            border: 1px solid #e2e8f0; 
            border-radius: 12px; 
            padding: 18px 20px; 
            margin-bottom: 20px; 
        }
        .section-title { 
            font-size: 13px; 
            font-weight: 800; 
            margin-bottom: 12px; 
            color: #1e293b; 
            text-transform: uppercase; 
            letter-spacing: 0.04em; 
            display: flex; 
            align-items: center; 
            gap: 8px; 
            border-bottom: 1px solid #e2e8f0; 
            padding-bottom: 8px; 
        }
        
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; }
        
        .info-group { margin-bottom: 4px; }
        .info-label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.02em; }
        .info-val { font-size: 13px; font-weight: 700; color: #0f172a; margin-top: 2px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 8px; margin-bottom: 16px; font-size: 12px; }
        th { 
            background: #f1f5f9; 
            color: #334155; 
            text-align: left; 
            padding: 10px 14px; 
            font-size: 11px; 
            text-transform: uppercase; 
            letter-spacing: 0.04em; 
            border-bottom: 2px solid #cbd5e1; 
            font-weight: 800;
        }
        td { padding: 10px 14px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
        tr:nth-child(even) td { background: #fafafa; }
        
        .badge { display: inline-block; padding: 3px 8px; border-radius: 999px; font-size: 10px; font-weight: 800; text-transform: uppercase; }
        .badge.verified { background: #dcfce7; color: #15803d; }
        .badge.pending { background: #fef3c7; color: #b45309; }
        .badge.credit { background: #dcfce7; color: #15803d; font-weight: 800; }
        .badge.debit { background: #fee2e2; color: #b91c1c; font-weight: 800; }
        
        .footer { margin-top: 28px; padding-top: 14px; border-top: 1px solid #e2e8f0; text-align: center; color: #94a3b8; font-size: 11px; }
        
        @media print {
            body { background: #fff; padding: 0; }
            .pdf-container { box-shadow: none; border: none; padding: 10px; max-width: 100%; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

    <!-- ACTION BAR: DIRECT DOWNLOAD PDF & PRINT -->
    <div class="action-bar no-print">
        <div style="display:flex; align-items:center; gap:8px;">
            <a href="{{ url('/settings') }}" class="btn-action btn-print">← Back to Settings</a>
            <span style="font-size:12px; color:#64748b;">(Generated for UID: <strong>{{ $user['uid'] ?? '-' }}</strong>)</span>
        </div>
        <div style="display:flex; align-items:center; gap:10px;">
            <button type="button" class="btn-action btn-print" onclick="window.print()">
                <span>🖨️ Print / Save Dialog</span>
            </button>
            <button type="button" class="btn-action btn-download" id="btnDirectDownloadPdf" onclick="downloadPdfDirectly()">
                <span>📥 Download PDF File</span>
            </button>
        </div>
    </div>

    <!-- MAIN EXPORTABLE PDF CONTAINER -->
    <div class="pdf-container" id="pdfDocumentArea">
        
        <div class="header">
            <div class="brand">
                <div class="brand-logo">K</div>
                <div class="brand-text">
                    <h1>KRYVEX EXCHANGE</h1>
                    <p>Personal Data Archive &amp; Security Record</p>
                </div>
            </div>
            <div class="meta">
                <span class="doc-badge">CONFIDENTIAL USER DATA</span>
                <div>Export Date: <strong>{{ $generated_at }}</strong></div>
                <div>User UID: <strong style="font-family:monospace;">{{ $user['uid'] ?? $user['_id'] ?? '-' }}</strong></div>
            </div>
        </div>

        <!-- SECTION 1: ACCOUNT & PROFILE SUMMARY -->
        <div class="section-box">
            <div class="section-title">👤 Account Profile &amp; Security</div>
            <div class="grid-3">
                <div class="info-group">
                    <div class="info-label">Full Name</div>
                    <div class="info-val">{{ $user['name'] ?? 'Not Specified' }}</div>
                </div>
                <div class="info-group">
                    <div class="info-label">Email Address</div>
                    <div class="info-val">{{ $user['email'] ?? '-' }}</div>
                </div>
                <div class="info-group">
                    <div class="info-label">Mobile Number</div>
                    <div class="info-val">{{ $user['mobile_number'] ?? $user['phone'] ?? 'Not Provided' }}</div>
                </div>
                <div class="info-group">
                    <div class="info-label">KYC Status</div>
                    <div class="info-val">
                        <span class="badge {{ strtolower($user['kyc_status'] ?? '') === 'verified' ? 'verified' : 'pending' }}">
                            {{ strtoupper($user['kyc_status'] ?? 'PENDING') }}
                        </span>
                    </div>
                </div>
                <div class="info-group">
                    <div class="info-label">Active Broker Platform</div>
                    <div class="info-val">{{ strtoupper($user['broker_type'] ?? 'KRYVEX') }}</div>
                </div>
                <div class="info-group">
                    <div class="info-label">Registered Date</div>
                    <div class="info-val">{{ isset($user['created_at']) ? \Carbon\Carbon::parse($user['created_at'])->format('d M Y, h:i A') : '-' }}</div>
                </div>
            </div>
        </div>

        <!-- SECTION 2: BANK & PAYMENT DETAILS -->
        <div class="section-box">
            <div class="section-title">🏦 Linked Bank Account &amp; Payment Details</div>
            <div class="grid-3">
                <div class="info-group">
                    <div class="info-label">Bank Name</div>
                    <div class="info-val">{{ $bankDetails['bank_name'] ?? 'Not Linked' }}</div>
                </div>
                <div class="info-group">
                    <div class="info-label">Account Holder</div>
                    <div class="info-val">{{ $bankDetails['account_holder_name'] ?? 'Not Specified' }}</div>
                </div>
                <div class="info-group">
                    <div class="info-label">Account Number</div>
                    <div class="info-val" style="font-family:monospace;">{{ !empty($bankDetails['account_number']) ? '•••• ' . substr($bankDetails['account_number'], -4) : 'Not Linked' }}</div>
                </div>
                <div class="info-group">
                    <div class="info-label">IFSC Code</div>
                    <div class="info-val" style="font-family:monospace;">{{ $bankDetails['ifsc_code'] ?? '-' }}</div>
                </div>
                <div class="info-group">
                    <div class="info-label">UPI ID</div>
                    <div class="info-val">{{ $bankDetails['upi_id'] ?? '-' }}</div>
                </div>
                <div class="info-group">
                    <div class="info-label">Bank Verification</div>
                    <div class="info-val">
                        <span class="badge {{ !empty($bankDetails['bank_name']) ? 'verified' : 'pending' }}">
                            {{ !empty($bankDetails['bank_name']) ? 'VERIFIED' : 'UNVERIFIED' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 3: WALLET BALANCES -->
        <div class="section-box">
            <div class="section-title">💰 Wallet Balances &amp; Net Equity</div>
            <div class="grid-3">
                <div class="info-group">
                    <div class="info-label">Main Wallet (USD)</div>
                    <div class="info-val" style="font-family:monospace;">${{ number_format((float)($wallet['demo_main_balance'] ?? 0), 2) }}</div>
                </div>
                <div class="info-group">
                    <div class="info-label">Main Wallet (INR)</div>
                    <div class="info-val" style="font-family:monospace;">₹{{ number_format((float)($wallet['demo_main_balance_inr'] ?? 0), 2) }}</div>
                </div>
                <div class="info-group">
                    <div class="info-label">Trading Available (USD)</div>
                    <div class="info-val" style="font-family:monospace;">${{ number_format((float)($wallet['demo_available_balance'] ?? 0), 2) }}</div>
                </div>
                <div class="info-group">
                    <div class="info-label">Margin Locked (USD)</div>
                    <div class="info-val" style="font-family:monospace;">${{ number_format((float)($wallet['demo_locked_balance'] ?? 0), 2) }}</div>
                </div>
                <div class="info-group">
                    <div class="info-label">Unrealized PnL</div>
                    <div class="info-val" style="font-family:monospace;">${{ number_format((float)($wallet['demo_pnl'] ?? 0), 2) }}</div>
                </div>
                <div class="info-group">
                    <div class="info-label">Realized PnL</div>
                    <div class="info-val" style="font-family:monospace;">${{ number_format((float)($wallet['demo_realized_pnl'] ?? 0), 2) }}</div>
                </div>
            </div>
        </div>

        <!-- SECTION 4: RECENT TRANSACTIONS (CLEAN FORMATTED WITHOUT REFERENCE COLUMN) -->
        <div class="section-title" style="margin-top: 14px;">
            <span>📋 Wallet Activity Log</span>
            <span style="font-size:11px; font-weight:600; color:#64748b; text-transform:none; margin-left:auto;">Showing last {{ count($ledgers) }} transactions</span>
        </div>
        <table>
            <thead>
                <tr>
                    <th style="width: 22%;">Date &amp; Time</th>
                    <th style="width: 38%;">Transaction Type</th>
                    <th style="width: 14%;">Direction</th>
                    <th style="width: 14%;">Amount</th>
                    <th style="width: 12%;">Status</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $typeLabels = [
                        'options_realized_pnl' => 'Options Realized PnL',
                        'options_trading_fee' => 'Options Trading Fee',
                        'options_margin_released' => 'Options Margin Released',
                        'options_margin_locked' => 'Options Margin Locked',
                        'futures_realized_pnl' => 'Futures Realized PnL',
                        'futures_trading_fee' => 'Futures Trading Fee',
                        'futures_margin_released' => 'Futures Margin Released',
                        'futures_margin_locked' => 'Futures Margin Locked',
                        'deposit' => 'Fiat/Crypto Deposit',
                        'withdrawal' => 'Withdrawal Request',
                        'transfer' => 'Internal Transfer',
                    ];
                @endphp
                @forelse($ledgers as $item)
                    @php
                        $row = (array)$item;
                        $isINR = ($row['currency'] ?? '') === 'INR' || ($row['wallet_currency'] ?? '') === 'INR';
                        $curSym = $isINR ? '₹' : '$';
                        $amt = $isINR ? ($row['amount_inr'] ?? $row['amount'] ?? 0) : ($row['amount'] ?? 0);
                        $direction = strtolower($row['direction'] ?? 'credit');
                        $rawType = strtolower((string)($row['type'] ?? ''));
                        $cleanType = $typeLabels[$rawType] ?? ucwords(str_replace('_', ' ', $rawType ?: 'Transaction'));
                    @endphp
                    <tr>
                        <td style="white-space: nowrap;">
                            <div style="font-weight: 700; color: #1e293b;">
                                {{ isset($row['created_at']) ? \Carbon\Carbon::parse($row['created_at'])->format('d M Y') : '-' }}
                            </div>
                            <div style="font-size: 11px; color: #64748b; font-weight: 500;">
                                {{ isset($row['created_at']) ? \Carbon\Carbon::parse($row['created_at'])->format('h:i A') : '' }}
                            </div>
                        </td>
                        <td>
                            <strong style="color: #0f172a; font-size: 12.5px;">{{ $cleanType }}</strong>
                        </td>
                        <td>
                            <span class="badge {{ $direction }}">{{ strtoupper($direction) }}</span>
                        </td>
                        <td style="font-family: monospace; font-weight: 700; font-size: 13px; color: {{ $direction === 'credit' ? '#15803d' : '#b91c1c' }}; white-space: nowrap;">
                            {{ $direction === 'credit' ? '+' : '-' }}{{ $curSym }}{{ number_format((float)$amt, 2) }}
                        </td>
                        <td>
                            <span style="font-weight: 600; color: #334155;">{{ ucfirst($row['status'] ?? 'Completed') }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align: center; color: #94a3b8; padding: 24px;">No transaction records found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="footer">
            Kryvex Exchange Official Privacy Record • Generated automatically for User UID: {{ $user['uid'] ?? '-' }} • Confidential Document
        </div>

    </div>

    <script>
        function downloadPdfDirectly() {
            const btn = document.getElementById('btnDirectDownloadPdf');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span>⏳ Generating PDF...</span>';
            }

            const element = document.getElementById('pdfDocumentArea');
            const uid = '{{ $user['uid'] ?? 'user' }}';
            const opt = {
                margin:       [10, 10, 10, 10],
                filename:     'Kryvex_Personal_Data_' + uid + '.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true, letterRendering: true },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            html2pdf().set(opt).from(element).save().then(function() {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<span>📥 Download PDF File</span>';
                }
            }).catch(function(err) {
                console.error('PDF download error:', err);
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<span>📥 Download PDF File</span>';
                }
                window.print();
            });
        }
    </script>
</body>
</html>
