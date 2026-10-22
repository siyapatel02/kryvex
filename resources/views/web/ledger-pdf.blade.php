<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction Ledger Statement - Kryvex Exchange</title>
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
            max-width: 960px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            padding: 36px 40px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
        }

        .action-bar {
            max-width: 960px;
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
        
        .section-title { 
            font-size: 14px; 
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
            <button type="button" class="btn-action btn-download" id="btnDirectDownloadLedgerPdf" onclick="downloadLedgerPdfDirectly()">
                <span>📥 Download PDF File</span>
            </button>
        </div>
    </div>

    <!-- MAIN EXPORTABLE PDF CONTAINER -->
    <div class="pdf-container" id="ledgerPdfDocumentArea">
        
        <div class="header">
            <div class="brand">
                <div class="brand-logo">K</div>
                <div class="brand-text">
                    <h1>KRYVEX EXCHANGE</h1>
                    <p>Official Transaction Ledger Statement</p>
                </div>
            </div>
            <div class="meta">
                <span class="doc-badge">OFFICIAL FINANCIAL STATEMENT</span>
                <div>User: <strong>{{ $user['email'] ?? $user['name'] ?? 'User' }}</strong></div>
                <div>User UID: <strong style="font-family:monospace;">{{ $user['uid'] ?? '-' }}</strong></div>
                <div>Currency Filter: <strong>{{ strtoupper($currency ?? 'ALL') }}</strong></div>
                <div>Generated Date: <strong>{{ $generated_at }}</strong></div>
            </div>
        </div>

        <div class="section-title">
            <span>📋 Account Transaction Records</span>
            <span style="font-size:11px; font-weight:600; color:#64748b; text-transform:none; margin-left:auto;">Total Records: {{ count($rows) }}</span>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 20%;">Date &amp; Time</th>
                    <th style="width: 32%;">Transaction Type</th>
                    <th style="width: 12%;">Direction</th>
                    <th style="width: 10%;">Currency</th>
                    <th style="width: 14%;">Amount</th>
                    <th style="width: 12%;">Balance After</th>
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
                        'inr_deposit' => 'INR Bank Deposit',
                        'inr_withdrawal_request' => 'INR Withdrawal Request',
                        'demo_credit' => 'Demo Balance Credit',
                    ];
                @endphp
                @forelse($rows as $row)
                    @php
                        $item = (array) $row;
                        $isINR = ($item['currency'] ?? '') === 'INR' || ($item['wallet_currency'] ?? '') === 'INR' || !empty($item['meta']['amount_inr'] ?? null) || str_contains(strtolower($item['type'] ?? ''), 'inr');
                        $curSymbol = $isINR ? '₹' : '$';
                        $amt = $isINR ? ($item['meta']['amount_inr'] ?? $item['amount_inr'] ?? $item['amount'] ?? 0) : ($item['amount'] ?? 0);
                        $direction = strtolower($item['direction'] ?? 'credit');
                        $rawType = strtolower((string)($item['type'] ?? ''));
                        $cleanType = $typeLabels[$rawType] ?? ucwords(str_replace('_', ' ', $rawType ?: 'Transaction'));
                    @endphp
                    <tr>
                        <td style="white-space: nowrap;">
                            <div style="font-weight: 700; color: #1e293b;">
                                {{ isset($item['created_at']) ? \Carbon\Carbon::parse($item['created_at'])->format('d M Y') : '-' }}
                            </div>
                            <div style="font-size: 11px; color: #64748b; font-weight: 500;">
                                {{ isset($item['created_at']) ? \Carbon\Carbon::parse($item['created_at'])->format('h:i A') : '' }}
                            </div>
                        </td>
                        <td>
                            <strong style="color: #0f172a; font-size: 12.5px;">{{ $cleanType }}</strong>
                        </td>
                        <td>
                            <span class="badge {{ $direction }}">{{ strtoupper($direction) }}</span>
                        </td>
                        <td>
                            <strong style="color: #334155; font-size: 11.5px;">{{ $isINR ? 'INR' : 'USDT' }}</strong>
                        </td>
                        <td style="font-family: monospace; font-weight: 700; font-size: 13px; color: {{ $direction === 'credit' ? '#15803d' : '#b91c1c' }}; white-space: nowrap;">
                            {{ $direction === 'credit' ? '+' : '-' }}{{ $curSymbol }}{{ number_format((float)$amt, 2) }}
                        </td>
                        <td style="font-family: monospace; font-weight: 600; color: #334155; white-space: nowrap;">
                            ${{ number_format((float)($item['balance_after'] ?? 0), 2) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align: center; color: #94a3b8; padding: 24px;">No ledger transactions recorded.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="footer">
            Kryvex Exchange Official Ledger Statement • Generated for User UID: {{ $user['uid'] ?? '-' }} • Computer-generated document
        </div>

    </div>

    <script>
        function downloadLedgerPdfDirectly() {
            const btn = document.getElementById('btnDirectDownloadLedgerPdf');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span>⏳ Generating PDF...</span>';
            }

            const element = document.getElementById('ledgerPdfDocumentArea');
            const uid = '{{ $user['uid'] ?? 'user' }}';
            const opt = {
                margin:       [10, 10, 10, 10],
                filename:     'Kryvex_Transactions_Ledger_' + uid + '.pdf',
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
