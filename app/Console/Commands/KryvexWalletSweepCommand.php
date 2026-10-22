<?php

namespace App\Console\Commands;

use App\Services\TronCustodyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KryvexWalletSweepCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'kryvex:wallet-sweep {--dry-run : Check sweeps without executing}';

    /**
     * The console command description.
     */
    protected $description = 'Sweep confirmed TRC-20 USDT deposits from Address B to Company Vault C';

    public function handle(TronCustodyService $tronService): int
    {
        $this->info('Starting Kryvex Custody Multi-Chain Vault Sweep process...');
        $isDryRun = $this->option('dry-run');

        if (!config('kryvex_wallet.switches.deposit_sweep_enabled', false)) {
            $this->warn('[DISABLED] DEPOSIT_SWEEP_ENABLED is false. Set DEPOSIT_SWEEP_ENABLED=true in .env to enable sweeps.');
        }

        // Fetch completed deposits pending sweep across all networks
        $pendingSweeps = DB::connection('mongodb')
            ->table('wallet_deposits')
            ->where('status', 'completed')
            ->where(function ($q) {
                $q->where('sweep_status', 'pending')
                  ->orWhereNull('sweep_status');
            })
            ->get();

        if ($pendingSweeps->isEmpty()) {
            $this->info('No pending sweeps found.');
            return Command::SUCCESS;
        }

        $this->info("Found {$pendingSweeps->count()} pending sweep(s)...");

        $settingsDoc = (array) DB::connection('mongodb')->table('admin_settings')->where('key', 'platform')->first();
        $companyWallets = (array) ($settingsDoc['company_deposit_wallets'] ?? []);

        foreach ($pendingSweeps as $deposit) {
            $depositId = (string) ($deposit->_id ?? $deposit->id ?? '');
            $userUid = (string) ($deposit->user_uid ?? '');
            $address = (string) ($deposit->to_address ?? '');
            $network = strtoupper((string) ($deposit->network ?? 'TRC20'));
            $amount = (float) ($deposit->amount ?? 0);

            if (empty($address) || $amount <= 0) {
                continue;
            }

            // Retrieve encrypted private key for this user deposit address
            $addrRecord = DB::connection('mongodb')
                ->table('user_deposit_addresses')
                ->where('address', $address)
                ->first();

            if (!$addrRecord || empty($addrRecord->encrypted_privkey)) {
                $this->warn("Skipping {$address} ({$network}): No private key stored.");
                continue;
            }

            $destVault = $companyWallets['USD_' . $network]['address'] ?? ($companyWallets['USD_TRC20']['address'] ?? config('kryvex_wallet.vault.address'));

            if ($isDryRun) {
                $this->line("[DRY-RUN] Would sweep {$amount} {$network} USDT from {$address} to {$destVault}");
                continue;
            }

            $this->line("Sweeping {$amount} USDT from {$address} ➔ {$destVault} ({$network})...");

            if ($network === 'TRC20' || $network === 'TRON') {
                $res = $tronService->sweepAddressToVault($address, (string) $addrRecord->encrypted_privkey, $amount);
                if ($res['success']) {
                    $sweepTx = $res['tx_hash'] ?? '';
                    DB::connection('mongodb')->table('wallet_deposits')->where('_id', $depositId)->update([
                        'sweep_status' => 'completed',
                        'sweep_tx_hash' => $sweepTx,
                        'swept_to_vault' => $destVault,
                        'swept_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $this->info("✅ [SWEPT] {$amount} USDT swept! TxID: {$sweepTx}");
                } else {
                    $this->error("❌ [SWEEP FAILED] {$address}: " . ($res['message'] ?? 'Check TRX Energy on Address'));
                }
            } else {
                // EVM (BEP20 / ERC20)
                $this->line("ℹ️ EVM ({$network}) Custody Address: {$address} holds {$amount} USDT. Destination: {$destVault}");
                DB::connection('mongodb')->table('wallet_deposits')->where('_id', $depositId)->update([
                    'sweep_status' => 'ready_for_sweep',
                    'swept_to_vault' => $destVault,
                    'updated_at' => now(),
                ]);
            }
        }

        return Command::SUCCESS;
    }
}
