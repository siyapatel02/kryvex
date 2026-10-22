<?php

namespace App\Console\Commands;

use App\Services\TronCustodyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class KryvexWalletMonitorCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'kryvex:wallet-monitor {--once : Run a single check cycle and exit}';

    /**
     * The console command description.
     */
    protected $description = 'Monitor TRON network for incoming TRC-20 USDT deposits, check 19 confirmations, and credit Kryvex Main Ledger';

    public function handle(TronCustodyService $tronService): int
    {
        $this->info('Starting Kryvex Custody TRC-20 USDT Deposit Monitor...');
        $runOnce = $this->option('once');
        $pollInterval = (int) config('kryvex_wallet.confirmations.poll_interval_seconds', 10);
        $reqConfirmations = (int) config('kryvex_wallet.confirmations.required_blocks', 19);

        do {
            if (!config('kryvex_wallet.switches.deposit_onchain_enabled', true)) {
                $this->warn('[PAUSED] DEPOSIT_ONCHAIN_ENABLED is false. Skipping cycle.');
                if ($runOnce) break;
                sleep($pollInterval);
                continue;
            }

            try {
                $this->processDepositCycle($tronService, $reqConfirmations);
            } catch (\Throwable $e) {
                $this->error('Error during monitor cycle: ' . $e->getMessage());
                Log::error('KryvexWalletMonitor error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            }

            if ($runOnce) break;
            sleep($pollInterval);
        } while (true);

        return Command::SUCCESS;
    }

    private function processDepositCycle(TronCustodyService $tronService, int $reqConfirmations): void
    {
        $currentBlock = $tronService->getCurrentBlockNumber();
        if ($currentBlock <= 0) {
            $this->warn('Could not fetch current block height from Tron node. Skipping cycle.');
            return;
        }

        // Get all user deposit addresses registered in the database
        $addresses = DB::connection('mongodb')
            ->table('user_deposit_addresses')
            ->where('network', 'TRC20')
            ->get();

        if ($addresses->isEmpty()) {
            $this->line('No user deposit addresses found to monitor.');
            return;
        }

        foreach ($addresses as $row) {
            $userUid = (string) ($row->user_uid ?? '');
            $address = (string) ($row->address ?? '');

            if (empty($userUid) || empty($address)) {
                continue;
            }

            // Fetch recent TRC20 transfers for this address
            $transfers = $tronService->fetchTrc20Transfers($address);

            foreach ($transfers as $tx) {
                $txId = $tx['transaction_id'] ?? '';
                $toAddr = $tx['to'] ?? '';
                $fromAddr = $tx['from'] ?? '';
                $rawAmount = (float) ($tx['value'] ?? 0);
                $blockTimestamp = (int) ($tx['block_timestamp'] ?? 0);

                if (empty($txId) || $rawAmount <= 0) {
                    continue;
                }

                // Check if already credited in wallet_deposits
                $existingDeposit = DB::connection('mongodb')
                    ->table('wallet_deposits')
                    ->where('tx_hash', $txId)
                    ->first();

                if ($existingDeposit) {
                    continue; // Already processed and credited
                }

                // Get transaction details for block number and confirmation check
                $txInfo = $tronService->getTransactionInfo($txId);
                $txBlockNumber = (int) ($txInfo['blockNumber'] ?? 0);

                if ($txBlockNumber <= 0) {
                    $this->line("Transaction {$txId} found in mempool, waiting for block inclusion...");
                    continue;
                }

                $confirmations = max(0, $currentBlock - $txBlockNumber + 1);

                if ($confirmations < $reqConfirmations) {
                    $this->line("Deposit {$txId} for {$address}: {$confirmations}/{$reqConfirmations} confirmations. Pending...");
                    continue;
                }

                // Confirmations satisfied! Calculate USDT amount (6 decimals)
                $amountUsdt = round($rawAmount / 1000000, 6);

                // ATOMIC LEDGER CREDIT
                $reference = 'DEP-TRON-' . strtoupper(substr(md5($txId), 0, 10));

                // 1. Credit the live account named on the user's deposit request, else the Main Account
                $walletService = app(\App\Services\DemoWalletService::class);
                $depositRequest = DB::connection('mongodb')->table('crypto_deposit_requests')
                    ->where('user_uid', $userUid)
                    ->where('tx_hash', $txId)
                    ->first();
                try {
                    $creditAccountUid = $walletService->resolveLiveAccountUid($userUid, $depositRequest->account_uid ?? null);
                } catch (\Illuminate\Validation\ValidationException $e) {
                    $creditAccountUid = $walletService->resolveLiveAccountUid($userUid, null);
                }
                $credit = $walletService->adjustLiveCrypto($userUid, $creditAccountUid, 'USDT', $amountUsdt);
                $newRealBalance = $credit['balance'];

                // 2. Insert into wallet_ledgers
                DB::connection('mongodb')
                    ->table('wallet_ledgers')
                    ->insert([
                        'ledger_uid' => (string) Str::uuid(),
                        'user_uid' => $userUid,
                        'account_uid' => $credit['account_uid'],
                        'currency' => 'USDT',
                        'wallet_currency' => 'USD',
                        'type' => 'crypto_deposit',
                        'category' => 'wallet_deposit',
                        'direction' => 'credit',
                        'amount' => $amountUsdt,
                        'balance_after' => $newRealBalance,
                        'status' => 'completed',
                        'reference' => $reference,
                        'note' => "On-chain TRC-20 USDT deposit: {$amountUsdt} USDT confirmed ({$confirmations} confs). TxID: {$txId}",
                        'meta' => [
                            'tx_hash' => $txId,
                            'from_address' => $fromAddr,
                            'to_address' => $toAddr,
                            'confirmations' => $confirmations,
                            'network' => 'TRC20',
                            'asset' => 'USDT',
                            'block_number' => $txBlockNumber,
                        ],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                // 3. Record in wallet_deposits for idempotency & sweep tracking
                DB::connection('mongodb')
                    ->table('wallet_deposits')
                    ->insert([
                        'deposit_uid' => (string) Str::uuid(),
                        'user_uid' => $userUid,
                        'asset' => 'USDT',
                        'network' => 'TRC20',
                        'amount' => $amountUsdt,
                        'tx_hash' => $txId,
                        'from_address' => $fromAddr,
                        'to_address' => $toAddr,
                        'block_number' => $txBlockNumber,
                        'confirmations' => $confirmations,
                        'status' => 'completed',
                        'sweep_status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                $this->info("✅ [CREDITED] User: {$userUid} | +{$amountUsdt} USDT | Confs: {$confirmations} | Tx: {$txId}");
                Log::info("Kryvex Deposit Credited: User {$userUid}, +{$amountUsdt} USDT, Tx {$txId}");
            }
        }
    }
}
