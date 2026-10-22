@extends('admin.layouts.app')

@section('title', 'Settings | Kryvex Admin')

@section('content')
@php
    $exchangeName = $settings->exchange_name ?? 'Kryvex Exchange';
    $supportEmail = $settings->support_email ?? 'support@kryvex.com';
    $supportPhone = $settings->support_phone ?? '';

    $minimumWalletBalance = $settings->minimum_wallet_balance ?? 2000;
    $minimumWithdrawAmount = $settings->minimum_withdraw_amount ?? 10;
    $maximumWithdrawAmount = $settings->maximum_withdraw_amount ?? 100000;

    $withdrawalFeeType = $settings->withdrawal_fee_type ?? 'percentage';
    $withdrawalFeeValue = $settings->withdrawal_fee_value ?? 1;
    $platformChargeType = $settings->platform_charge_type ?? 'fixed';
    $platformChargeValue = $settings->platform_charge_value ?? 2;

    $makerFee = $settings->maker_fee ?? 0.0002;
    $takerFee = $settings->taker_fee ?? 0.0005;
    $futuresTradingFeeRate = $settings->futures_trading_fee_rate ?? $takerFee;
    $optionsTradingFeeRate = $settings->options_trading_fee_rate ?? $takerFee;
    $maxLeverage = $settings->max_leverage ?? 100;

    $usdtInrRate = $settings->usdt_inr_rate ?? 85;
    $inrBankDetails = (array) ($settings->inr_bank_details ?? []);

    $companyWallets = (array) ($settings->company_deposit_wallets ?? []);
    $walletRules = [
        'USD_TRC20' => ['label' => 'USD TRC20', 'starts_with' => 'T', 'min' => 34, 'max' => 34, 'default' => 'TQn9Y2khEsLJW1ChVWFMSMeRDow5KcbLSE', 'minimum' => 10],
        'USD_ERC20' => ['label' => 'USD ERC20', 'starts_with' => '0x', 'min' => 42, 'max' => 42, 'default' => '0x742d35Cc6634C0532925a3b844Bc454e4438f44e', 'minimum' => 25],
        'USD_BEP20' => ['label' => 'USD BEP20', 'starts_with' => '0x', 'min' => 42, 'max' => 42, 'default' => '0x8ba1f109551bD432803012645Ac136ddd64DBA72', 'minimum' => 10],
        'BTC' => ['label' => 'Bitcoin (BTC)', 'starts_with' => '1 / 3 / bc1', 'min' => 26, 'max' => 62, 'default' => 'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0w1h', 'minimum' => 50],
        'ETH' => ['label' => 'Ethereum (ETH)', 'starts_with' => '0x', 'min' => 42, 'max' => 42, 'default' => '0x742d35Cc6634C0532925a3b844Bc454e4438f44e', 'minimum' => 25],
        'SOL' => ['label' => 'Solana (SOL)', 'starts_with' => 'Base58', 'min' => 30, 'max' => 44, 'default' => '7EqQdEULxWcraVx3mXKK2TbPgYfvKwC5s1vZMjTLxK3', 'minimum' => 0.01],
        'USDC' => ['label' => 'USDC (ERC20)', 'starts_with' => '0x', 'min' => 42, 'max' => 42, 'default' => '0x8ba1f109551bD432803012645Ac136ddd64DBA72', 'minimum' => 10],
    ];
@endphp

<div class="settings-page" data-settings-page>
    <section class="settings-hero">
        <div>
            <p class="settings-eyebrow">Admin</p>
            <h1>Exchange Settings</h1>
            <span>Configure platform rules. Saved settings apply immediately.</span>
        </div>

        <a href="{{ route('admin.settings.index') }}" class="settings-reload-btn">Reload</a>
    </section>

    <form method="POST" action="{{ route('admin.settings.update') }}" class="settings-form">
        @csrf

        <div class="settings-layout">
            {{-- Settings navigation tabs. JS only switches visible panels; one form still saves every field. --}}
            <aside class="settings-tabs" aria-label="Settings sections">
                <button type="button" class="settings-tab is-active" data-settings-tab="general">
                    <span>General</span>
                    <small>Brand & support</small>
                </button>

                <button type="button" class="settings-tab" data-settings-tab="trading">
                    <span>Trading</span>
                    <small>Fees & leverage</small>
                </button>

                <button type="button" class="settings-tab" data-settings-tab="wallet">
                    <span>Wallet & Withdrawals</span>
                    <small>Limits & charges</small>
                </button>

                <button type="button" class="settings-tab" data-settings-tab="access">
                    <span>Access Controls</span>
                    <small>Login & modules</small>
                </button>

                <button type="button" class="settings-tab" data-settings-tab="binance">
                    <span>Binance API</span>
                    <small>Keys & Testnet</small>
                </button>

                <button type="button" class="settings-tab" data-settings-tab="razorpay">
                    <span>Razorpay PG</span>
                    <small>INR Gateway</small>
                </button>

                <button type="button" class="settings-tab" data-settings-tab="bybit">
                    <span>Bybit API</span>
                    <small>Keys & Environment</small>
                </button>

                <button type="button" class="settings-tab" data-settings-tab="metaapi">
                    <span>MetaTrader 5 (MT5)</span>
                    <small>MetaApi Cloud API</small>
                </button>

                <button type="button" class="settings-tab" data-settings-tab="advanced">
                    <span>Advanced</span>
                    <small>Maintenance</small>
                </button>
            </aside>

            <section class="settings-panels">
                <div class="settings-panel is-active" data-settings-panel="general">
                    <div class="settings-card">
                        <div class="settings-card-head">
                            <div>
                                <h2>General</h2>
                                <p>Basic exchange identity and support information.</p>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Exchange Name</label>
                            <input type="text" name="exchange_name" value="{{ $exchangeName }}" maxlength="120" placeholder="Kryvex Exchange">
                            <small>Shown in platform preview and future email templates.</small>
                        </div>

                        <div class="form-group">
                            <label>Support Email</label>
                            <input type="email" name="support_email" value="{{ $supportEmail }}" maxlength="150" placeholder="support@kryvex.com">
                        </div>

                        <div class="form-group">
                            <label>Support Phone</label>
                            <input type="text" name="support_phone" value="{{ $supportPhone }}" maxlength="40" placeholder="Optional support number">
                        </div>
                    </div>
                </div>

                <div class="settings-panel" data-settings-panel="trading">
                    <div class="settings-card">
                        <div class="settings-card-head">
                            <div>
                                <h2>Trading Rules</h2>
                                <p>Control trading fees, leverage and trading modules.</p>
                            </div>
                        </div>

                        <div class="settings-subtitle" style="margin-top: 0;">Trading fee charged on open and close</div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Futures Trading Fee Rate</label>
                                <input type="number" step="0.000001" name="futures_trading_fee_rate" value="{{ $futuresTradingFeeRate }}" min="0" required>
                                <small>Charged once when a futures trade opens and again when it closes. Example: 0.0005 means 0.05%.</small>
                            </div>

                            <div class="form-group">
                                <label>Options Trading Fee Rate</label>
                                <input type="number" step="0.000001" name="options_trading_fee_rate" value="{{ $optionsTradingFeeRate }}" min="0" required>
                                <small>Charged once when an options trade opens and again when it closes.</small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Max Leverage</label>
                            <input type="number" name="max_leverage" value="{{ $maxLeverage }}" min="1" max="200" required>
                        </div>

                        <div class="settings-subtitle" style="margin-top: 24px; color: #4f46e5; font-weight: 700;">Trading Plan Spread Engine (Plan-Based)</div>
                        <p style="font-size: 0.85rem; color: #64748b; margin-top: -6px; margin-bottom: 16px;">Configure custom spread rates for Cent, Basic, Standard, and Advanced trading plans across Kryvex Exchange and MT5.</p>

                        <!-- Cent Plan Spread -->
                        <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 10px; padding: 14px; margin-bottom: 12px;">
                            <strong style="color: #38bdf8; display: block; margin-bottom: 10px; font-size: 0.9rem;">1. Cent Plan Spread (MT5 Micro Cent)</strong>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Cent Spread Status</label>
                                    <label class="setting-switch single-switch">
                                        <input type="checkbox" name="plan_spread_cent_enabled" {{ !isset($settings->plan_spread_cent_enabled) || !empty($settings->plan_spread_cent_enabled) ? 'checked' : '' }}>
                                        <span>Enabled</span>
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label>Spread Type</label>
                                    <select name="plan_spread_cent_type">
                                        <option value="percentage" {{ ($settings->plan_spread_cent_type ?? 'percentage') === 'percentage' ? 'selected' : '' }}>Percentage %</option>
                                        <option value="fixed" {{ ($settings->plan_spread_cent_type ?? 'percentage') === 'fixed' ? 'selected' : '' }}>Fixed USDT ($)</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Spread Value</label>
                                    <input type="number" step="0.0001" name="plan_spread_cent_value" value="{{ $settings->plan_spread_cent_value ?? 0.08 }}" min="0" placeholder="0.08">
                                    <small>Default: 0.08%</small>
                                </div>
                            </div>
                        </div>

                        <!-- Basic Plan Spread -->
                        <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 10px; padding: 14px; margin-bottom: 12px;">
                            <strong style="color: #f4c542; display: block; margin-bottom: 10px; font-size: 0.9rem;">1. Basic Plan Spread</strong>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Basic Spread Status</label>
                                    <label class="setting-switch single-switch">
                                        <input type="checkbox" name="plan_spread_basic_enabled" {{ !isset($settings->plan_spread_basic_enabled) || !empty($settings->plan_spread_basic_enabled) ? 'checked' : '' }}>
                                        <span>Enabled</span>
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label>Spread Type</label>
                                    <select name="plan_spread_basic_type">
                                        <option value="percentage" {{ ($settings->plan_spread_basic_type ?? 'percentage') === 'percentage' ? 'selected' : '' }}>Percentage %</option>
                                        <option value="fixed" {{ ($settings->plan_spread_basic_type ?? 'percentage') === 'fixed' ? 'selected' : '' }}>Fixed USDT ($)</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Spread Value</label>
                                    <input type="number" step="0.0001" name="plan_spread_basic_value" value="{{ $settings->plan_spread_basic_value ?? 0.05 }}" min="0" placeholder="0.05">
                                    <small>Default: 0.05%</small>
                                </div>
                            </div>
                        </div>

                        <!-- Standard Plan Spread -->
                        <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 10px; padding: 14px; margin-bottom: 12px;">
                            <strong style="color: #3b82f6; display: block; margin-bottom: 10px; font-size: 0.9rem;">2. Standard Plan Spread</strong>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Standard Spread Status</label>
                                    <label class="setting-switch single-switch">
                                        <input type="checkbox" name="plan_spread_standard_enabled" {{ !isset($settings->plan_spread_standard_enabled) || !empty($settings->plan_spread_standard_enabled) ? 'checked' : '' }}>
                                        <span>Enabled</span>
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label>Spread Type</label>
                                    <select name="plan_spread_standard_type">
                                        <option value="percentage" {{ ($settings->plan_spread_standard_type ?? 'percentage') === 'percentage' ? 'selected' : '' }}>Percentage %</option>
                                        <option value="fixed" {{ ($settings->plan_spread_standard_type ?? 'percentage') === 'fixed' ? 'selected' : '' }}>Fixed USDT ($)</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Spread Value</label>
                                    <input type="number" step="0.0001" name="plan_spread_standard_value" value="{{ $settings->plan_spread_standard_value ?? 0.03 }}" min="0" placeholder="0.03">
                                    <small>Default: 0.03%</small>
                                </div>
                            </div>
                        </div>

                        <!-- Advanced Plan Spread -->
                        <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 10px; padding: 14px; margin-bottom: 16px;">
                            <strong style="color: #10b981; display: block; margin-bottom: 10px; font-size: 0.9rem;">3. Advanced Plan Spread</strong>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Advanced Spread Status</label>
                                    <label class="setting-switch single-switch">
                                        <input type="checkbox" name="plan_spread_advanced_enabled" {{ !isset($settings->plan_spread_advanced_enabled) || !empty($settings->plan_spread_advanced_enabled) ? 'checked' : '' }}>
                                        <span>Enabled</span>
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label>Spread Type</label>
                                    <select name="plan_spread_advanced_type">
                                        <option value="percentage" {{ ($settings->plan_spread_advanced_type ?? 'percentage') === 'percentage' ? 'selected' : '' }}>Percentage %</option>
                                        <option value="fixed" {{ ($settings->plan_spread_advanced_type ?? 'percentage') === 'fixed' ? 'selected' : '' }}>Fixed USDT ($)</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Spread Value</label>
                                    <input type="number" step="0.0001" name="plan_spread_advanced_value" value="{{ $settings->plan_spread_advanced_value ?? 0.01 }}" min="0" placeholder="0.01">
                                    <small>Default: 0.01%</small>
                                </div>
                            </div>
                        </div>

                        <div class="switch-grid">
                            <label class="setting-switch">
                                <input type="checkbox" name="trading_enabled" {{ !empty($settings->trading_enabled) ? 'checked' : '' }}>
                                <span>Trading Enabled</span>
                            </label>

                            <label class="setting-switch">
                                <input type="checkbox" name="futures_enabled" {{ !empty($settings->futures_enabled) ? 'checked' : '' }}>
                                <span>Futures Enabled</span>
                            </label>

                            <label class="setting-switch">
                                <input type="checkbox" name="options_enabled" {{ !empty($settings->options_enabled) ? 'checked' : '' }}>
                                <span>Options Enabled</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="settings-panel" data-settings-panel="wallet">
                    <div class="settings-card">
                        <div class="settings-card-head">
                            <div>
                                <h2>Wallet & Withdrawal Rules</h2>
                                <p>Set reserve limits, withdrawal limits, withdrawal fee and platform charge.</p>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Minimum Wallet Balance ($)</label>
                                <input type="number" step="0.01" name="minimum_wallet_balance" value="{{ $minimumWalletBalance }}" required>
                                <small>Applied only while users have open trades.</small>
                            </div>

                            <div class="form-group">
                                <label>Minimum Withdraw Amount ($)</label>
                                <input type="number" step="0.01" name="minimum_withdraw_amount" value="{{ $minimumWithdrawAmount }}" min="0">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Maximum Withdraw Amount ($)</label>
                                <input type="number" step="0.01" name="maximum_withdraw_amount" value="{{ $maximumWithdrawAmount }}" min="0">
                            </div>

                            <div class="form-group">
                                <label>Withdrawal Status</label>
                                <label class="setting-switch single-switch">
                                    <input type="checkbox" name="withdrawal_enabled" {{ !empty($settings->withdrawal_enabled) ? 'checked' : '' }}>
                                    <span>Withdrawal Enabled</span>
                                </label>
                            </div>
                        </div>

                        <div class="settings-subtitle">Charges deducted from requested withdrawal amount</div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Withdrawal Fee Type</label>
                                <select name="withdrawal_fee_type" data-charge-input>
                                    <option value="fixed" {{ $withdrawalFeeType === 'fixed' ? 'selected' : '' }}>Fixed $</option>
                                    <option value="percentage" {{ $withdrawalFeeType === 'percentage' ? 'selected' : '' }}>Percentage %</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Withdrawal Fee Value</label>
                                <input type="number" step="0.01" name="withdrawal_fee_value" value="{{ $withdrawalFeeValue }}" min="0" data-charge-input>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Platform Charge Type</label>
                                <select name="platform_charge_type" data-charge-input>
                                    <option value="fixed" {{ $platformChargeType === 'fixed' ? 'selected' : '' }}>Fixed $</option>
                                    <option value="percentage" {{ $platformChargeType === 'percentage' ? 'selected' : '' }}>Percentage %</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Platform Charge Value</label>
                                <input type="number" step="0.01" name="platform_charge_value" value="{{ $platformChargeValue }}" min="0" data-charge-input>
                            </div>
                        </div>

                        <div class="charge-preview-box">
                            <strong>Example withdrawal calculation</strong>
                            <p>For a $100 request, user receives <b id="withdrawChargePreview">--</b> after fees.</p>
                            <small>Requested amount is deducted from wallet. If admin rejects the request, full requested amount is returned.</small>
                        </div>

                        <div class="settings-subtitle">🇮🇳 INR Currency & Bank Deposit Settings</div>

                        <div style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.25); padding: 12px 16px; border-radius: 12px; margin-bottom: 18px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                            <div>
                                <strong style="color: #10b981; font-size: 14px; display: block;">⚡ Currently Active Platform Rates</strong>
                                <span style="color: #94a3b8; font-size: 12px;">These rates are live on all user wallet transfers, deposits, and conversions.</span>
                            </div>
                            <div style="display: flex; gap: 10px; font-family: monospace; font-weight: 800; font-size: 13px;">
                                <span style="background: rgba(6, 78, 59, 0.8); color: #6ee7b7; padding: 6px 12px; border-radius: 8px; border: 1px solid rgba(16, 185, 129, 0.4);">BUY: ₹{{ number_format((float)($settings->usdt_inr_buy_rate ?? ($settings->usdt_inr_rate ?? 95.00)), 2) }}/USD</span>
                                <span style="background: rgba(76, 29, 149, 0.8); color: #c4b5fd; padding: 6px 12px; border-radius: 8px; border: 1px solid rgba(139, 92, 246, 0.4);">SELL: ₹{{ number_format((float)($settings->usdt_inr_sell_rate ?? 94.50), 2) }}/USD</span>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Buy Rate (1 USD = ₹ Rate)</label>
                                <input type="number" step="0.01" name="usdt_inr_buy_rate" value="{{ $settings->usdt_inr_buy_rate ?? ($settings->usdt_inr_rate ?? 95.00) }}" min="1" placeholder="e.g. 95.00" required>
                                <small>Used when converting INR to USD ($1 USD = ₹ Buy Rate).</small>
                            </div>
                            <div class="form-group">
                                <label>Sell Rate (1 USD = ₹ Rate)</label>
                                <input type="number" step="0.01" name="usdt_inr_sell_rate" value="{{ $settings->usdt_inr_sell_rate ?? 94.50 }}" min="1" placeholder="e.g. 94.50" required>
                                <small>Used when converting USD to INR ($1 USD = ₹ Sell Rate).</small>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Bank Name</label>
                                <input type="text" name="inr_bank_details[bank_name]" value="{{ $settings->inr_bank_details['bank_name'] ?? ($inrBankDetails['bank_name'] ?? '') }}" placeholder="e.g. HDFC Bank">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Account Holder Name</label>
                                <input type="text" name="inr_bank_details[account_name]" value="{{ $inrBankDetails['account_name'] ?? '' }}" placeholder="e.g. Kryvex Technologies">
                            </div>
                            <div class="form-group">
                                <label>Account Number</label>
                                <input type="text" name="inr_bank_details[account_number]" value="{{ $inrBankDetails['account_number'] ?? '' }}" placeholder="e.g. 50200012345678">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>IFSC Code</label>
                                <input type="text" name="inr_bank_details[ifsc]" value="{{ $inrBankDetails['ifsc'] ?? '' }}" placeholder="e.g. HDFC0001234">
                            </div>
                            <div class="form-group">
                                <label>UPI ID</label>
                                <input type="text" name="inr_bank_details[upi_id]" value="{{ $inrBankDetails['upi_id'] ?? '' }}" placeholder="e.g. kryvex@paytm">
                            </div>
                        </div>

                        <div class="settings-subtitle">Company Deposit Wallet Addresses</div>
                        <div class="company-wallet-settings-grid">
                            @foreach($walletRules as $network => $rule)
                                @php
                                    $walletRow = (array) ($companyWallets[$network] ?? []);
                                    $addressValue = array_key_exists('address', $walletRow) ? $walletRow['address'] : ($rule['default'] ?? '');
                                    $minimumValue = $walletRow['minimum_amount'] ?? $rule['minimum'];
                                @endphp
                                <div class="company-wallet-setting-card">
                                    <div class="wallet-network-title">
                                        <strong>{{ $rule['label'] }}</strong>
                                        <span>Starts with {{ $rule['starts_with'] }} • {{ $rule['min'] }}{{ $rule['min'] === $rule['max'] ? '' : '-' . $rule['max'] }} chars</span>
                                    </div>
                                    <div class="form-group">
                                        <label>Company Deposit Address</label>
                                        <input type="text" name="company_wallets[{{ $network }}][address]" value="{{ $addressValue }}" placeholder="Leave blank if not set yet">
                                        <small>User side will show warning if left blank.</small>
                                    </div>
                                    <div class="form-group">
                                        <label>Minimum Deposit Amount ($)</label>
                                        <input type="number" step="0.01" name="company_wallets[{{ $network }}][minimum_amount]" value="{{ $minimumValue }}" min="0">
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="settings-panel" data-settings-panel="access">
                    <div class="settings-card">
                        <div class="settings-card-head">
                            <div>
                                <h2>Access Controls</h2>
                                <p>Control authentication options and user access modules.</p>
                            </div>
                        </div>

                        <div class="switch-grid">
                            <label class="setting-switch">
                                <input type="checkbox" name="google_login_enabled" {{ !empty($settings->google_login_enabled) ? 'checked' : '' }}>
                                <span>Google Login Enabled</span>
                            </label>

                            <label class="setting-switch">
                                <input type="checkbox" name="email_otp_enabled" {{ !empty($settings->email_otp_enabled) ? 'checked' : '' }}>
                                <span>Email OTP Verification Enabled</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="settings-panel" data-settings-panel="binance">
                    <div class="settings-card">
                        <div class="settings-card-head">
                            <div>
                                <h2>Binance API Configuration</h2>
                                <p>Connect Binance Futures Exchange for live prices, orderbook depth, and order execution.</p>
                            </div>
                        </div>

                        <div class="settings-grid">
                            <div class="form-group full-width">
                                <label>Active Primary Provider</label>
                                <select name="active_exchange_provider" class="form-control" style="background:#111; color:#fff; padding:10px; border-radius:6px; width:100%; border:1px solid #333;">
                                    <option value="binance" {{ ($settings->active_exchange_provider ?? 'binance') === 'binance' ? 'selected' : '' }}>Binance (Active)</option>
                                    <option value="bybit" {{ ($settings->active_exchange_provider ?? 'binance') === 'bybit' ? 'selected' : '' }}>Bybit (Active)</option>
                                </select>
                            </div>

                            <label class="setting-switch full-width">
                                <input type="checkbox" name="binance_enabled" {{ !empty($settings->binance_enabled) ? 'checked' : '' }}>
                                <span>Enable Binance Integration</span>
                            </label>

                            <div class="form-group full-width">
                                <label>Environment Mode</label>
                                <select name="binance_environment" class="form-control" style="background:#111; color:#fff; padding:10px; border-radius:6px; width:100%; border:1px solid #333;">
                                    <option value="testnet" {{ ($settings->binance_environment ?? 'testnet') === 'testnet' ? 'selected' : '' }}>Testnet (Demo Trading / testnet.binancefuture.com)</option>
                                    <option value="mainnet" {{ ($settings->binance_environment ?? 'testnet') === 'mainnet' ? 'selected' : '' }}>Mainnet (Live Trading / fapi.binance.com)</option>
                                </select>
                            </div>

                            <div class="form-group full-width">
                                <label>Binance API Key</label>
                                <input type="text" name="binance_api_key" value="{{ $settings->binance_api_key ?? '' }}" placeholder="Enter Binance API Key" class="form-control" autocomplete="off" style="background:#111; color:#fff; padding:10px; border-radius:6px; width:100%; border:1px solid #333;">
                            </div>

                            <div class="form-group full-width">
                                <label>Binance API Secret</label>
                                <input type="password" name="binance_api_secret" value="{{ $settings->binance_api_secret ?? '' }}" placeholder="Enter Binance API Secret" class="form-control" autocomplete="off" style="background:#111; color:#fff; padding:10px; border-radius:6px; width:100%; border:1px solid #333;">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="settings-panel" data-settings-panel="razorpay">
                    <div class="settings-card">
                        <div class="settings-card-head">
                            <div>
                                <h2>Razorpay Payment Gateway (INR)</h2>
                                <p>Enable Razorpay for Instant INR Deposits via UPI, NetBanking, and Cards.</p>
                            </div>
                        </div>

                        <div class="settings-grid">
                            <label class="setting-switch full-width">
                                <input type="checkbox" name="razorpay_enabled" {{ !empty($settings->razorpay_enabled) ? 'checked' : '' }}>
                                <span>Enable Razorpay Gateway</span>
                            </label>

                            <div class="form-group full-width">
                                <label>Environment Mode</label>
                                <select name="razorpay_environment" class="form-control" style="background:#111; color:#fff; padding:10px; border-radius:6px; width:100%; border:1px solid #333;">
                                    <option value="test" {{ ($settings->razorpay_environment ?? 'test') === 'test' ? 'selected' : '' }}>Test Mode (rzp_test_...)</option>
                                    <option value="live" {{ ($settings->razorpay_environment ?? 'test') === 'live' ? 'selected' : '' }}>Live Mode (rzp_live_...)</option>
                                </select>
                            </div>

                            <div class="form-group full-width">
                                <label>Razorpay Key ID</label>
                                <input type="text" name="razorpay_key_id" value="{{ $settings->razorpay_key_id ?? '' }}" placeholder="Enter Razorpay Key ID (e.g. rzp_test_...)" class="form-control" autocomplete="off" style="background:#111; color:#fff; padding:10px; border-radius:6px; width:100%; border:1px solid #333;">
                            </div>

                            <div class="form-group full-width">
                                <label>Razorpay Key Secret</label>
                                <input type="password" name="razorpay_key_secret" value="{{ $settings->razorpay_key_secret ?? '' }}" placeholder="Enter Razorpay Key Secret" class="form-control" autocomplete="off" style="background:#111; color:#fff; padding:10px; border-radius:6px; width:100%; border:1px solid #333;">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="settings-panel" data-settings-panel="bybit">
                    <div class="settings-card">
                        <div class="settings-card-head">
                            <div>
                                <h2>Bybit API Configuration</h2>
                                <p>Connect Bybit Exchange for live prices, orderbook depth, and order execution.</p>
                            </div>
                        </div>

                        <div class="settings-grid">
                            <label class="setting-switch full-width">
                                <input type="checkbox" name="bybit_enabled" {{ !empty($settings->bybit_enabled) ? 'checked' : '' }}>
                                <span>Enable Bybit Integration</span>
                            </label>

                            <div class="form-group full-width">
                                <label>Environment Mode</label>
                                <select name="bybit_environment" class="form-control" style="background:#111; color:#fff; padding:10px; border-radius:6px; width:100%; border:1px solid #333;">
                                    <option value="testnet" {{ ($settings->bybit_environment ?? 'mainnet') === 'testnet' ? 'selected' : '' }}>Testnet (Demo Trading / Sandbox)</option>
                                    <option value="mainnet" {{ ($settings->bybit_environment ?? 'mainnet') === 'mainnet' ? 'selected' : '' }}>Mainnet (Live Trading / Production)</option>
                                </select>
                            </div>

                            <div class="form-group full-width">
                                <label>Bybit API Key</label>
                                <input type="text" name="bybit_api_key" value="{{ $settings->bybit_api_key ?? '' }}" placeholder="Enter Bybit API Key" class="form-control" autocomplete="off" style="background:#111; color:#fff; padding:10px; border-radius:6px; width:100%; border:1px solid #333;">
                            </div>

                            <div class="form-group full-width">
                                <label>Bybit API Secret</label>
                                <input type="password" name="bybit_api_secret" value="{{ $settings->bybit_api_secret ?? '' }}" placeholder="Enter Bybit API Secret" class="form-control" autocomplete="off" style="background:#111; color:#fff; padding:10px; border-radius:6px; width:100%; border:1px solid #333;">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="settings-panel" data-settings-panel="metaapi">
                    <div class="settings-card">
                        <div class="settings-card-head">
                            <div>
                                <h2>MetaTrader 5 (MT5) Cloud API</h2>
                                <p>Connect MetaApi.cloud for real-time MT5 account deployment, balance sync, and automated trade execution.</p>
                            </div>
                        </div>

                        <div class="settings-grid">
                            <label class="setting-switch full-width">
                                <input type="checkbox" name="metaapi_enabled" {{ !empty($settings->metaapi_enabled) ? 'checked' : '' }}>
                                <span>Enable MetaApi (MT5 Integration)</span>
                            </label>

                            <div class="form-group full-width">
                                <label>MetaApi Access Token</label>
                                <input type="password" id="metaapi_token_input" name="metaapi_token" value="{{ $settings->metaapi_token ?? '' }}" placeholder="Enter MetaApi Access Token (e.g. eyJhbGciOi...)" class="form-control" autocomplete="off" style="background:#111; color:#fff; padding:10px; border-radius:6px; width:100%; border:1px solid #333;">
                                <small style="color:#888; margin-top:4px; display:block;">Generated from MetaApi Cloud Dashboard &gt; API Access.</small>
                            </div>

                            <div class="form-group full-width">
                                <label>Master / Default Account ID</label>
                                <input type="text" id="metaapi_account_id_input" name="metaapi_account_id" value="{{ $settings->metaapi_account_id ?? '' }}" placeholder="Enter Deployed MT5 Account ID (UUID)" class="form-control" autocomplete="off" style="background:#111; color:#fff; padding:10px; border-radius:6px; width:100%; border:1px solid #333;">
                                <small style="color:#888; margin-top:4px; display:block;">The Account ID of your deployed MT5 Demo or Live account in MetaApi.</small>
                            </div>

                            <div class="form-group full-width" style="margin-top:10px;">
                                <button type="button" id="testMetaApiBtn" class="btn btn-secondary" style="background:#2563eb; color:#fff; padding:9px 18px; border-radius:6px; border:none; cursor:pointer; font-weight:600;">
                                    Test MT5 Connection
                                </button>
                                <span id="metaApiTestResult" style="margin-left:12px; font-size:14px;"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="settings-panel" data-settings-panel="advanced">
                    <div class="settings-card danger-card">
                        <div class="settings-card-head">
                            <div>
                                <h2>Platform Status</h2>
                                <p>Enable maintenance mode for view-only user access.</p>
                            </div>
                        </div>

                        <label class="setting-switch maintenance-switch">
                            <input type="checkbox" name="maintenance_mode" {{ !empty($settings->maintenance_mode) ? 'checked' : '' }}>
                            <span>Maintenance Mode</span>
                        </label>

                        <div class="maintenance-note">
                            When enabled, users can view public pages but login, trading, wallet and password actions are blocked.
                        </div>
                    </div>
                </div>
            </section>

            <aside class="settings-preview-card">
                <div class="preview-status-dot"></div>
                <span>Platform Preview</span>
                <h3 id="settingsPreviewName">{{ $exchangeName }}</h3>
                <p id="settingsPreviewEmail">{{ $supportEmail }}</p>

                <div class="preview-list">
                    <div>
                        <strong>Trading</strong>
                        <small>{{ !empty($settings->trading_enabled) ? 'Enabled' : 'Disabled' }} • {{ $maxLeverage }}x max • Futures {{ $futuresTradingFeeRate }} / Options {{ $optionsTradingFeeRate }}</small>
                    </div>
                    <div>
                        <strong>Withdrawals</strong>
                        <small>Min ${{ number_format((float) $minimumWithdrawAmount, 2) }} • Fee {{ $withdrawalFeeType === 'percentage' ? $withdrawalFeeValue . '%' : '$' . number_format((float) $withdrawalFeeValue, 2) }}</small>
                    </div>
                    <div>
                        <strong>Platform Charge</strong>
                        <small>{{ $platformChargeType === 'percentage' ? $platformChargeValue . '%' : '$' . number_format((float) $platformChargeValue, 2) }}</small>
                    </div>
                    <div>
                        <strong>Maintenance</strong>
                        <small>{{ !empty($settings->maintenance_mode) ? 'Enabled' : 'Disabled' }}</small>
                    </div>
                </div>
            </aside>
        </div>

        <div class="settings-actions">
            <button type="submit">Save Settings</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const testBtn = document.getElementById('testMetaApiBtn');
    const resultSpan = document.getElementById('metaApiTestResult');
    const accountInput = document.getElementById('metaapi_account_id_input');

    if (testBtn && resultSpan) {
        testBtn.addEventListener('click', async function () {
            const accountId = accountInput ? accountInput.value.trim() : '';
            testBtn.disabled = true;
            testBtn.textContent = 'Testing...';
            resultSpan.style.color = '#eab308';
            resultSpan.textContent = 'Connecting to MetaApi Cloud...';

            try {
                const res = await fetch('{{ route("admin.settings.test-metaapi") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ account_id: accountId })
                });

                const data = await res.json();
                if (data.success) {
                    const info = data.data || {};
                    resultSpan.style.color = '#22c55e';
                    resultSpan.textContent = `Connected! Balance: $${info.balance ?? 0} ${info.currency ?? 'USD'} | Equity: $${info.equity ?? 0} | Server: ${info.server ?? 'MT5'}`;
                } else {
                    resultSpan.style.color = '#ef4444';
                    resultSpan.textContent = `Failed: ${data.message || 'Could not connect'}`;
                }
            } catch (err) {
                resultSpan.style.color = '#ef4444';
                resultSpan.textContent = `Error: ${err.message || 'Network error'}`;
            } finally {
                testBtn.disabled = false;
                testBtn.textContent = 'Test MT5 Connection';
            }
        });
    }
});
</script>
@endsection
