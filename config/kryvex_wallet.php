<?php

return [
    // Minimum reserve requirement while open trading exposure exists
    'demo_min_available_balance' => 2000,

    // Auto starter balance disabled (manual demo-credit)
    'demo_initial_credit' => 0,

    // Trading fees
    'demo_trading_fee' => 0.0004,
    'demo_options_trading_fee' => 0.0005,

    // -------------------------------------------------------------
    // Kryvex Custodial Real Wallet Configuration
    // -------------------------------------------------------------

    // Master HD Mnemonic for deriving unique user TRC20 deposit addresses
    // Derivation path: m/44'/195'/0'/0/{user_index} (Tron coin type 195)
    'deposit_hd_mnemonic' => env('DEPOSIT_HD_MNEMONIC', ''),

    // Tron Network: 'mainnet', 'nile', 'shasta'
    'tron_network' => env('TRON_NETWORK', 'mainnet'),

    // TronGrid API Key (Optional, avoids rate limits)
    'trongrid_api_key' => env('TRONGRID_API_KEY', ''),

    // Official TRC-20 USDT smart contract addresses
    'contracts' => [
        'deposit_usdt' => env('DEPOSIT_USDT_CONTRACT', 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t'),
        'withdraw_usdt' => env('WITHDRAW_USDT_CONTRACT', 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t'),
    ],

    // Company Hot Vault C (Payout & Sweep Destination)
    'vault' => [
        'address' => env('WITHDRAW_FROM_ADDRESS', ''),
        'private_key' => env('WITHDRAW_PRIVATE_KEY', ''),
    ],

    // Operational Feature Switches
    'switches' => [
        'deposit_onchain_enabled' => env('DEPOSIT_ONCHAIN_ENABLED', true),
        'deposit_sweep_enabled' => env('DEPOSIT_SWEEP_ENABLED', false),
        'withdraw_onchain_enabled' => env('WITHDRAW_ONCHAIN_ENABLED', false),
    ],

    // Confirmation & Timing Settings
    'confirmations' => [
        'required_blocks' => (int) env('TRON_REQUIRED_CONFIRMATIONS', 19),
        'poll_interval_seconds' => (int) env('TRON_POLL_INTERVAL_SECONDS', 10),
    ],
];