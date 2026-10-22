<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Api\MarketController;
use App\Http\Controllers\Api\FuturesOrderController;
use App\Http\Controllers\Api\SpotMarketController;
use App\Http\Controllers\Api\SpotOrderController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\OptionMarketController;
use App\Http\Controllers\Api\OptionOrderController;
use App\Http\Controllers\Api\Admin\AdminWalletApiController;
use App\Http\Controllers\Api\AccountStatusController;
use App\Http\Controllers\Api\PlatformStatusController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\KycController;
use App\Http\Controllers\Api\TradingAccountController;
use App\Http\Controllers\Api\BrokerController;
use App\Http\Controllers\Api\SupportController;
use App\Http\Controllers\Admin\AdminMt5Controller;


Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'Kryvex API is running.',
        'app' => config('app.name'),
        'environment' => app()->environment(),
    ]);
});

Route::get('/db-test', function () {
    $id = DB::connection('mongodb')
        ->table('connection_tests')
        ->insertGetId([
            'message' => 'MongoDB Atlas connected successfully.',
            'created_at' => now(),
        ]);

    return response()->json([
        'success' => true,
        'message' => 'MongoDB Atlas connection successful.',
        'inserted_id' => (string) $id,
    ]);
});

// Public platform status used by the frontend maintenance popup.
Route::get('/platform/status', [PlatformStatusController::class, 'show']);

//MMARKET PAGe
Route::get('/markets/futures/tickers', [MarketController::class, 'futuresTickers']);
Route::get('/markets/futures/{symbol}/depth', [MarketController::class, 'futuresDepth']);
Route::get('/markets/futures/{symbol}/trades', [MarketController::class, 'futuresTrades']);
Route::get('/markets/futures/{symbol}/stats', [MarketController::class, 'futuresStats']);
Route::get('/markets/fxempire-news', [MarketController::class, 'fxempireNews']);
Route::get('/markets/upcoming-events', [MarketController::class, 'upcomingEvents']);

Route::post('/futures/order-preview', [FuturesOrderController::class, 'preview'])->middleware(['maintenance.block']);

//option page
Route::get('/options/contracts/{underlying}', [OptionMarketController::class, 'contracts']);
Route::get('/options/chain/{underlying}', [OptionMarketController::class, 'chain']);
Route::get('/options/symbol/{symbol}/depth', [OptionMarketController::class, 'depth']);
Route::get('/options/symbol/{symbol}/trades', [OptionMarketController::class, 'trades']);

// SPOT MARKET ROUTES
Route::get('/spot/pairs', [SpotMarketController::class, 'pairs']);
Route::get('/spot/tickers', [SpotMarketController::class, 'tickers']);
Route::get('/spot/{symbol}/depth', [SpotMarketController::class, 'depth']);
Route::get('/spot/{symbol}/trades', [SpotMarketController::class, 'trades']);

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('maintenance.block');
    Route::post('/register/verify', [AuthController::class, 'verifyRegister'])->middleware('maintenance.block');
    // Registration OTP resend: 2 instant resends, then 10 minute cooldown.
    Route::post('/register/resend', [AuthController::class, 'resendRegisterCode'])->middleware('maintenance.block');

    // Forgot password flow: OTP is valid for 120 seconds; resends are unlimited.
    Route::post('/forgot-password/send-code', [AuthController::class, 'sendForgotPasswordCode'])->middleware('maintenance.block');
    // Verify reset OTP before showing the new-password fields on the frontend.
    Route::post('/forgot-password/verify-code', [AuthController::class, 'verifyForgotPasswordCode'])->middleware('maintenance.block');
    Route::post('/forgot-password/reset', [AuthController::class, 'resetForgotPassword'])->middleware('maintenance.block');

    Route::post('/login', [AuthController::class, 'login'])->middleware('maintenance.block');
    Route::post('/login/verify-2fa', [AuthController::class, 'verify2FaLogin'])->middleware('maintenance.block');
    Route::post('/login/resend-2fa', [AuthController::class, 'resend2FaLogin'])->middleware('maintenance.block');
    Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('maintenance.block');

    Route::middleware('auth.jwt')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/toggle-2fa', [AuthController::class, 'toggle2Fa']);
        Route::get('/2fa-status', [AuthController::class, 'get2FaStatus']);
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/mark-read', [NotificationController::class, 'markRead']);
        Route::get('/kyc/status', [KycController::class, 'status']);
        Route::post('/kyc/status', [KycController::class, 'status']);
        Route::post('/kyc/submit', [KycController::class, 'submit'])->middleware('maintenance.block');
        Route::post('/kyc/send-otp', [KycController::class, 'sendOtp'])->middleware('maintenance.block');
        Route::post('/kyc/verify-otp', [KycController::class, 'verifyOtp'])->middleware('maintenance.block');
        Route::post('/kyc/submit-aadhaar', [KycController::class, 'submitAadhaarNumber'])->middleware('maintenance.block');
        Route::post('/kyc/submit-pan', [KycController::class, 'submitPan'])->middleware('maintenance.block');
        Route::post('/kyc/submit-identity', [KycController::class, 'submitIdentity'])->middleware('maintenance.block');
        Route::post('/kyc/check-identity-unique', [KycController::class, 'checkIdentityUnique'])->middleware('maintenance.block');
        Route::post('/kyc/submit-selfie', [KycController::class, 'submitSelfie'])->middleware('maintenance.block');
        Route::post('/kyc/digio/initiate', [KycController::class, 'initiateDigioKyc'])->middleware('maintenance.block');
        Route::post('/kyc/digio/verify', [KycController::class, 'verifyDigioKyc'])->middleware('maintenance.block');
        Route::get('/kyc/digio/check-status', [KycController::class, 'checkDigioStatus']);
        Route::post('/kyc/digio/check-status', [KycController::class, 'checkDigioStatus']);
        Route::post('/kyc/selfie-qr-initiate', [KycController::class, 'initiateSelfieQrSession']);
        Route::get('/kyc/selfie-qr-status', [KycController::class, 'checkSelfieQrStatus']);
        Route::post('/kyc/reset', [KycController::class, 'reset'])->middleware('maintenance.block');
        // Profile password change requires current password.
        Route::post('/change-password', [AuthController::class, 'changePassword'])->middleware('maintenance.block');
        Route::post('/update-profile', [AuthController::class, 'updateProfile'])->middleware('maintenance.block');

        // Support Tickets API
        Route::get('/support/tickets', [SupportController::class, 'index']);
        Route::post('/support/tickets', [SupportController::class, 'store']);
        Route::post('/support/tickets/{id}/reply', [SupportController::class, 'reply']);
        // Base currency selection & currency change request flow
        Route::post('/set-base-currency', [AuthController::class, 'setBaseCurrency']);
        Route::get('/base-currency', [AuthController::class, 'getBaseCurrency']);
        Route::get('/currency-change-status', [AuthController::class, 'getCurrencyChangeStatus']);
        Route::post('/request-currency-change', [AuthController::class, 'requestCurrencyChange'])->middleware('maintenance.block');

        // User Bank Details (under /auth prefix)
        Route::get('/user/bank-details', [AuthController::class, 'getBankDetails']);
        Route::post('/user/bank-details', [AuthController::class, 'saveBankDetails'])->middleware('maintenance.block');

        // Account Deletion Request
        Route::get('/deletion-eligibility', [AuthController::class, 'getDeletionEligibility']);
        Route::post('/request-delete-account', [AuthController::class, 'requestAccountDeletion'])->middleware('maintenance.block');
        Route::get('/delete-account-status', [AuthController::class, 'getAccountDeletionStatus']);
        Route::post('/cancel-delete-account', [AuthController::class, 'cancelAccountDeletion'])->middleware('maintenance.block');
    });
});

// User Bank Details & Data Export (under root /api/user/)
Route::middleware('auth.jwt')->prefix('user')->group(function () {
    Route::get('/bank-details', [AuthController::class, 'getBankDetails']);
    Route::post('/bank-details', [AuthController::class, 'saveBankDetails'])->middleware('maintenance.block');
    Route::get('/personal-data/pdf', [WalletController::class, 'exportPersonalDataPdf']);
});

// User account status API for suspension / block banner on frontend.
Route::middleware('auth.jwt')->get('/account/status', [AccountStatusController::class, 'show']);

// Support Tickets API (accessible via /api/support/tickets)
Route::middleware('auth.jwt')->prefix('support')->group(function () {
    Route::get('/tickets', [SupportController::class, 'index']);
    Route::post('/tickets', [SupportController::class, 'store']);
    Route::post('/tickets/{id}/reply', [SupportController::class, 'reply']);
});

// Trading Sub-Accounts API
Route::middleware('auth.jwt')->prefix('trading-accounts')->group(function () {
    Route::get('/', [TradingAccountController::class, 'index']);
    Route::post('/', [TradingAccountController::class, 'store'])->middleware(['maintenance.block', 'account.active']);
    Route::delete('/{accountUid}', [TradingAccountController::class, 'destroy'])->middleware(['maintenance.block', 'account.active', 'kyc.verified']);
});


Route::middleware('auth.jwt')->prefix('wallet')->group(function () {
    Route::get('/', [WalletController::class, 'show']);
    Route::get('/ledger', [WalletController::class, 'ledger']);
    Route::get('/ledger/export/csv', [WalletController::class, 'exportLedgerCsv']);
    Route::get('/ledger/export/pdf', [WalletController::class, 'exportLedgerPdf']);
    Route::get('/withdrawals', [WalletController::class, 'withdrawals']);

    Route::get('/live', [WalletController::class, 'live']);

    Route::post('/demo-credit', [WalletController::class, 'demoCredit'])
        ->middleware(['maintenance.block', 'account.active', 'kyc.verified']);

    Route::post('/crypto-exchange', [WalletController::class, 'cryptoExchange'])
        ->middleware(['maintenance.block', 'account.active', 'kyc.verified']);

    Route::post('/withdraw', [WalletController::class, 'withdraw'])
        ->middleware(['maintenance.block', 'account.active', 'kyc.verified']);

    Route::post('/withdrawal-address', [WalletController::class, 'saveWithdrawalAddress'])
        ->middleware(['maintenance.block', 'account.active']);

    Route::post('/transfer', [WalletController::class, 'transfer'])
        ->middleware(['throttle:60,1', 'maintenance.block', 'account.active']);

    Route::post('/account-transfer', [WalletController::class, 'accountTransfer'])
        ->middleware(['throttle:60,1', 'maintenance.block', 'account.active']);

    Route::get('/inr-rate', [WalletController::class, 'inrRate']);
    Route::get('/deposit-status', [WalletController::class, 'checkDepositStatus'])->middleware('throttle:60,1');
    Route::post('/inr-deposit', [WalletController::class, 'inrDeposit'])->middleware(['throttle:30,1', 'maintenance.block', 'account.active']);
    Route::post('/crypto-deposit', [WalletController::class, 'cryptoDeposit'])->middleware(['throttle:60,1', 'maintenance.block', 'account.active']);
    Route::post('/inr-withdraw', [WalletController::class, 'inrWithdraw'])->middleware(['throttle:30,1', 'maintenance.block', 'account.active']);

    Route::post('/razorpay/create-order', [WalletController::class, 'createRazorpayOrder'])->middleware(['throttle:30,1', 'maintenance.block', 'account.active']);
    Route::post('/razorpay/verify', [WalletController::class, 'verifyRazorpayPayment'])->middleware(['throttle:30,1', 'maintenance.block', 'account.active']);

    Route::get('/internal-address-info', [WalletController::class, 'getInternalAddressInfo']);
    Route::post('/internal-transfer', [WalletController::class, 'executeInternalTransfer'])
        ->middleware(['maintenance.block', 'account.active', 'kyc.verified', 'throttle:60,1']);

    Route::delete('/demo-reset', [WalletController::class, 'resetDemoData'])
        ->middleware(['maintenance.block', 'account.active']);
});


Route::middleware('auth.jwt')->prefix('options')->group(function () {
    Route::get('/orders', [OptionOrderController::class, 'orders']);
    Route::get('/positions', [OptionOrderController::class, 'positions']);
    Route::get('/trades', [OptionOrderController::class, 'trades']);

    Route::post('/demo-orders', [OptionOrderController::class, 'placeDemo'])
        ->middleware(['maintenance.block', 'account.active', 'kyc.verified']);

    Route::post('/orders/{orderUid}/cancel', [OptionOrderController::class, 'cancelDemoOrder'])
        ->middleware(['maintenance.block', 'account.active']);

    Route::post('/positions/{positionUid}/close', [OptionOrderController::class, 'closeDemoPosition'])
        ->middleware(['maintenance.block', 'account.active']);

    Route::post('/positions/close-all', [OptionOrderController::class, 'closeAllDemoPositions'])
        ->middleware(['maintenance.block', 'account.active']);
});

Route::middleware('auth.jwt')->prefix('futures')->group(function () {
    Route::get('/orders', [FuturesOrderController::class, 'orders']);
    Route::get('/positions', [FuturesOrderController::class, 'positions']);
    Route::get('/trades', [FuturesOrderController::class, 'trades']);
    Route::get('/realized-pnl', [FuturesOrderController::class, 'realizedPnl']);

    Route::post('/demo-orders', [FuturesOrderController::class, 'placeDemo'])
        ->middleware(['maintenance.block', 'account.active', 'kyc.verified']);

    Route::post('/orders', [FuturesOrderController::class, 'placeLive'])
        ->middleware(['maintenance.block', 'account.active', 'kyc.verified']);

    Route::post('/orders/process-demo-fills', [FuturesOrderController::class, 'processDemoFills'])
        ->middleware(['maintenance.block', 'account.active']);

    Route::post('/positions/{positionUid}/close', [FuturesOrderController::class, 'closeDemoPosition'])
        ->middleware(['maintenance.block', 'account.active']);

    Route::post('/positions/{positionUid}/tp-sl', [FuturesOrderController::class, 'updatePositionTpSl'])
        ->middleware(['maintenance.block', 'account.active']);

    Route::post('/positions/close-all', [FuturesOrderController::class, 'closeAllDemoPositions'])
        ->middleware(['maintenance.block', 'account.active']);

    Route::post('/orders/{orderUid}/cancel', [FuturesOrderController::class, 'cancelDemoOrder'])
        ->middleware(['maintenance.block', 'account.active']);
});

Route::middleware('auth.jwt')->prefix('spot')->group(function () {
    Route::get('/balances', [SpotOrderController::class, 'balances']);
    Route::post('/orders', [SpotOrderController::class, 'place'])
        ->middleware(['maintenance.block', 'account.active', 'kyc.verified']);
    Route::get('/orders/open', [SpotOrderController::class, 'openOrders']);
    Route::get('/orders/history', [SpotOrderController::class, 'orderHistory']);
    Route::get('/trades/history', [SpotOrderController::class, 'tradeHistory']);
    Route::post('/orders/{orderUid}/cancel', [SpotOrderController::class, 'cancel'])
        ->middleware(['maintenance.block', 'account.active']);
    Route::post('/orders/process-fills', [SpotOrderController::class, 'processFills'])
        ->middleware(['maintenance.block', 'account.active']);
});

Route::get('/markets/debug-external', function () {
    $tests = [];

    $urls = [
        'binance' => 'https://fapi.binance.com/fapi/v1/ticker/24hr',
        'bybit' => 'https://api.bybit.com/v5/market/tickers?category=linear',
        'coingecko' => 'https://api.coingecko.com/api/v3/ping',
    ];

    foreach ($urls as $name => $url) {
        try {
            $verifySsl = filter_var(env('MARKET_API_SSL_VERIFY', true), FILTER_VALIDATE_BOOLEAN);

            $response = Http::timeout(10)
                ->withOptions(['verify' => $verifySsl])
                ->get($url);

            $tests[$name] = [
                'ok' => $response->successful(),
                'status' => $response->status(),
                'body_preview' => substr($response->body(), 0, 300),
            ];
        } catch (\Throwable $exception) {
            $tests[$name] = [
                'ok' => false,
                'error' => $exception->getMessage(),
            ];
        }
    }

    return response()->json([
        'success' => true,
        'php_version' => PHP_VERSION,
        'curl_loaded' => extension_loaded('curl'),
        'openssl_loaded' => extension_loaded('openssl'),
        'tests' => $tests,
    ]);
});

Route::get('/kyc/mobile-session/{token}', [KycController::class, 'getMobileSelfieSession']);
Route::post('/kyc/mobile-selfie-upload', [KycController::class, 'uploadMobileSelfie']);

//ADMin
Route::prefix('admin/wallet')->group(function () {
    Route::get('/overview', [AdminWalletApiController::class, 'overview']);
    Route::get('/withdrawals', [AdminWalletApiController::class, 'withdrawals']);
    Route::get('/ledger', [AdminWalletApiController::class, 'ledger']);
});

Route::middleware('auth.jwt')->group(function () {
    Route::get('/broker/status', [BrokerController::class, 'status']);
    Route::post('/broker/select', [BrokerController::class, 'select']);
    Route::get('/broker/mt5-plans', [BrokerController::class, 'plans']);
    Route::post('/broker/mt5-request', [BrokerController::class, 'requestPlan']);
    Route::post('/broker/mt5-credentials', [BrokerController::class, 'saveCredentials']);
    Route::post('/broker/dismiss-popup', [BrokerController::class, 'dismissPopup']);
    Route::post('/broker/dismiss-app-popup', [BrokerController::class, 'dismissAppPopup']);
    Route::post('/broker/dismiss-downgrade-warning', [BrokerController::class, 'dismissDowngradeWarning']);
});
