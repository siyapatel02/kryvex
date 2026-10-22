<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\PageController;
use App\Http\Controllers\Web\GoogleAuthController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminWalletController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminPositionController;
use App\Http\Controllers\Admin\AdminReportController;
use App\Http\Controllers\Admin\AdminSettingController;
use App\Http\Controllers\Admin\AdminAuditLogController;
use App\Http\Controllers\Admin\AdminNotificationController;
use App\Http\Controllers\Admin\AdminKycController;
use App\Http\Controllers\Admin\AdminCurrencyChangeController;
use App\Http\Controllers\Admin\AdminMt5Controller;
use App\Http\Controllers\Admin\AdminSupportController;

Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/markets', [PageController::class, 'markets'])->name('markets');
Route::get('/learn', [PageController::class, 'learn'])->name('learn');
Route::get('/calendar', [PageController::class, 'calendar'])->name('calendar');
Route::get('/economic-calendar', [PageController::class, 'calendar'])->name('calendar.economic');
Route::get('/research', [PageController::class, 'research'])->name('research');
Route::get('/research/{id}', [PageController::class, 'research'])->name('research.detail');
Route::get('/market-research', [PageController::class, 'research'])->name('research.market');
Route::get('/news', [PageController::class, 'news'])->name('news');
Route::get('/news/{id}', [PageController::class, 'news'])->name('news.detail');
Route::get('/market-news', [PageController::class, 'news'])->name('news.market');

Route::get('/futures', [PageController::class, 'futures'])->name('futures');
Route::get('/futures/{symbol}', [PageController::class, 'futures'])->name('futures.symbol');

Route::get('/spot', [PageController::class, 'spot'])->name('spot');
Route::get('/spot/{symbol}', [PageController::class, 'spot'])->name('spot.symbol');
Route::get('/trade', [PageController::class, 'spot'])->name('trade');
Route::get('/trade/{symbol}', [PageController::class, 'spot'])->name('trade.symbol');

Route::get('/login', [PageController::class, 'login'])->name('login');
Route::get('/register', [PageController::class, 'register'])->name('register');
Route::get('/options/{symbol?}', [PageController::class, 'options'])->name('options');
Route::get('/settings', [PageController::class, 'settings'])->name('settings');
Route::get('/dashboard', function() { return redirect('/settings'); })->name('dashboard');
Route::get('/wallet', [PageController::class, 'wallet'])->name('wallet');
Route::get('/wallet/deposit', [PageController::class, 'wallet'])->name('wallet.deposit');
Route::get('/wallet/withdraw', [PageController::class, 'wallet'])->name('wallet.withdraw');
Route::get('/wallet/exchange', [PageController::class, 'wallet'])->name('wallet.exchange');
Route::get('/wallet/history', [PageController::class, 'wallet'])->name('wallet.history');
Route::get('/deposit', [PageController::class, 'wallet'])->name('deposit');
Route::get('/withdraw', [PageController::class, 'wallet'])->name('withdraw');
Route::get('/exchange', [PageController::class, 'wallet'])->name('exchange');
Route::get('/portfolio', [PageController::class, 'portfolio'])->name('portfolio');
Route::get('/why-kryvex', function() { return redirect('/wallet?guide=why-kryvex'); })->name('why.kryvex');
Route::get('/why-nexus', function() { return redirect('/wallet?guide=why-kryvex'); });
Route::get('/invite', [PageController::class, 'invite'])->name('invite');
Route::get('/referral', function() { return redirect('/invite'); })->name('referral');
Route::get('/terms', [PageController::class, 'terms'])->name('terms');
Route::get('/terms-of-service', [PageController::class, 'terms'])->name('terms.service');
Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/privacy-policy', [PageController::class, 'privacy'])->name('privacy.policy');
Route::get('/user/personal-data/pdf', [\App\Http\Controllers\Api\WalletController::class, 'exportPersonalDataPdf'])->name('user.personal-data.pdf');
Route::get('/wallet/ledger/export/csv', [\App\Http\Controllers\Api\WalletController::class, 'exportLedgerCsv'])->name('wallet.ledger.export.csv');
Route::get('/wallet/ledger/export/pdf', [\App\Http\Controllers\Api\WalletController::class, 'exportLedgerPdf'])->name('wallet.ledger.export.pdf');
Route::get('/kyc', [PageController::class, 'kyc'])->name('kyc');
Route::get('/kyc/mobile/{token}', [PageController::class, 'mobileSelfie'])->name('kyc.mobile');

Route::get('/storage/private/kyc/{filename}', function ($filename) {
    $path = storage_path('app/private/kyc/' . $filename);
    if (!file_exists($path)) {
        $path = storage_path('app/public/kyc/' . $filename);
    }
    if (!file_exists($path)) {
        abort(404);
    }
    return response()->file($path, ['Content-Type' => 'image/jpeg']);
});

Route::get('/storage/kyc/{filename}', function ($filename) {
    $path = storage_path('app/public/kyc/' . $filename);
    if (!file_exists($path)) {
        $path = storage_path('app/private/kyc/' . $filename);
    }
    if (!file_exists($path)) {
        abort(404);
    }
    return response()->file($path, ['Content-Type' => 'image/jpeg']);
});

Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AdminAuthController::class, 'login'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'loginSubmit'])->name('login.submit');
    Route::get('/register', [AdminAuthController::class, 'register'])->name('register');
    Route::get('/forgot-password', [AdminAuthController::class, 'forgotPassword'])->name('forgot-password');

    // Admin AJAX Auth Endpoints
    Route::post('/api/login', [AdminAuthController::class, 'apiLogin'])->name('api.login');
    Route::post('/api/register', [AdminAuthController::class, 'apiRegister'])->name('api.register');
    Route::post('/api/register/verify', [AdminAuthController::class, 'apiVerifyRegister'])->name('api.register.verify');
    Route::post('/api/register/resend', [AdminAuthController::class, 'apiResendRegisterCode'])->name('api.register.resend');
    Route::post('/api/forgot-password/send-code', [AdminAuthController::class, 'apiSendForgotPasswordCode'])->name('api.forgot.send-code');
    Route::post('/api/forgot-password/verify-code', [AdminAuthController::class, 'apiVerifyForgotPasswordCode'])->name('api.forgot.verify-code');
    Route::post('/api/forgot-password/reset', [AdminAuthController::class, 'apiResetForgotPassword'])->name('api.forgot.reset');

    Route::middleware('admin.auth')->group(function () {
        Route::get('/', [AdminAuthController::class, 'dashboard'])->name('dashboard');
        Route::get('/dashboard', [AdminAuthController::class, 'dashboard'])->name('dashboard');
        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/users/deletion-requests', [AdminUserController::class, 'deletionRequests'])->name('users.deletion-requests');
        Route::post('/users/{id}/approve-deletion', [AdminUserController::class, 'approveDeletion'])->name('users.approve-deletion');
        Route::post('/users/{id}/reject-deletion', [AdminUserController::class, 'rejectDeletion'])->name('users.reject-deletion');
        Route::get('/users/pending-deposits', [AdminUserController::class, 'pendingDeposits'])->name('users.pending-deposits');
        Route::get('/users/pending-kyc', [AdminUserController::class, 'pendingKyc'])->name('users.pending-kyc');
        Route::post('/users/send-deposit-reminder/{userUid?}', [AdminUserController::class, 'sendDepositReminder'])->name('users.send-deposit-reminder');
        Route::post('/users/send-kyc-reminder/{userUid?}', [AdminUserController::class, 'sendKycReminder'])->name('users.send-kyc-reminder');
        Route::get('/users/{id}', [AdminUserController::class, 'show'])->name('users.show');
        Route::patch('/users/{id}/status', [AdminUserController::class, 'updateStatus'])->name('users.status');
        Route::post('/users/{id}/currency/change', [AdminUserController::class, 'changeCurrency'])->name('users.currency.change');
        Route::post('/users/{id}/currency/reset', [AdminUserController::class, 'resetCurrency'])->name('users.currency.reset');
        Route::post('/users/{id}/currency/lock', [AdminUserController::class, 'toggleCurrencyLock'])->name('users.currency.lock');
        Route::get('/users/{id}/export/csv', [AdminUserController::class, 'exportCsv'])->name('users.export.csv');
        Route::get('/users/{id}/export/pdf', [AdminUserController::class, 'exportPdf'])->name('users.export.pdf');

        Route::get('/kyc', [AdminKycController::class, 'index'])->name('kyc.index');
        Route::get('/kyc/{userUid}/document/{side}', [AdminKycController::class, 'document'])->name('kyc.document');
        Route::get('/kyc/{userUid}/download/{type}', [AdminKycController::class, 'downloadPdf'])->name('kyc.download.pdf');
        Route::post('/kyc/{userUid}/approve', [AdminKycController::class, 'approve'])->name('kyc.approve');
        Route::post('/kyc/{userUid}/reject', [AdminKycController::class, 'reject'])->name('kyc.reject');
        Route::delete('/kyc/{userUid}/delete', [AdminKycController::class, 'delete'])->name('kyc.delete');

        Route::get('/wallet', [AdminWalletController::class, 'index'])->name('wallet.index');
        Route::get('/wallet/deposits', [AdminWalletController::class, 'deposits'])->name('wallet.deposits');
        Route::get('/wallet/withdrawals', [AdminWalletController::class, 'withdrawals'])->name('wallet.withdrawals');
        Route::get('/wallet/internal-transfers', [AdminWalletController::class, 'internalTransfers'])->name('wallet.internal_transfers');
        Route::get('/wallet/ledger', [AdminWalletController::class, 'ledger'])->name('wallet.ledger');
        Route::post('/wallet/revenue/withdraw', [AdminWalletController::class, 'withdrawRevenue'])->name('wallet.revenue.withdraw');
        Route::post('/wallet/revenue/transfer', [AdminWalletController::class, 'transferRevenue'])->name('wallet.revenue.transfer');

        Route::patch('/wallet/withdrawals/{id}/approve', [AdminWalletController::class, 'approveWithdrawal'])->name('wallet.withdrawals.approve');
        Route::patch('/wallet/withdrawals/{id}/reject', [AdminWalletController::class, 'rejectWithdrawal'])->name('wallet.withdrawals.reject');

        Route::get('/currency-requests', [AdminCurrencyChangeController::class, 'index'])->name('currency_requests.index');
        Route::post('/currency-requests/{id}/approve', [AdminCurrencyChangeController::class, 'approve'])->name('currency_requests.approve');
        Route::post('/currency-requests/{id}/reject', [AdminCurrencyChangeController::class, 'reject'])->name('currency_requests.reject');

        Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/futures', [AdminOrderController::class, 'futures'])->name('orders.futures');
        Route::get('/orders/options', [AdminOrderController::class, 'options'])->name('orders.options');

        Route::get('/positions', [AdminPositionController::class, 'index'])->name('positions.index');
        Route::get('/positions/open', [AdminPositionController::class, 'open'])->name('positions.open');
        Route::get('/positions/closed', [AdminPositionController::class, 'closed'])->name('positions.closed');
        Route::post('/positions/force-stop', [AdminPositionController::class, 'forceStop'])->name('positions.force_stop');



        Route::get('/reports', [AdminReportController::class, 'index'])->name('reports.index');

        Route::get('/reports/{type}', [AdminReportController::class, 'show'])
            ->name('reports.show');

        Route::get('/reports/{type}/export/csv', [AdminReportController::class, 'exportCsv'])
            ->name('reports.export.csv');

        Route::get('/settings', [AdminSettingController::class, 'index'])->name('settings.index');
        Route::post('/settings', [AdminSettingController::class, 'update'])->name('settings.update');
        Route::post('/settings/test-metaapi', [AdminSettingController::class, 'testMetaApiConnection'])->name('settings.test-metaapi');

        Route::get('/notifications', [AdminNotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications', [AdminNotificationController::class, 'send'])->name('notifications.send');
        Route::post('/notifications/templates', [AdminNotificationController::class, 'saveTemplate'])->name('notifications.templates.save');

        Route::get('/audit-logs', [AdminAuditLogController::class, 'index'])->name('audit.index');

        Route::get('/audit-logs/export/csv', [AdminAuditLogController::class, 'exportCsv'])
            ->name('audit.export.csv');

        Route::get('/mt5-requests', [AdminMt5Controller::class, 'index'])->name('mt5-requests.index');
        Route::post('/users/{uid}/assign-mt5', [AdminMt5Controller::class, 'assignCredentials'])->name('users.assign-mt5');

        Route::get('/support', [AdminSupportController::class, 'index'])->name('support.index');
        Route::get('/api/support/tickets', [AdminSupportController::class, 'getTickets'])->name('support.api.tickets');
        Route::get('/api/support/tickets/{id}', [AdminSupportController::class, 'show'])->name('support.api.show');
        Route::post('/api/support/tickets/{id}/reply', [AdminSupportController::class, 'reply'])->name('support.api.reply');
        Route::post('/api/support/tickets/{id}/close', [AdminSupportController::class, 'closeTicket'])->name('support.api.close');
            
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
    });
});
Route::get('/support', function() { return redirect('/settings?tab=support'); })->name('support');

