<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MultiChainCustodyService
{
    /**
     * RPC Endpoints for different blockchains
     */
    private array $rpcEndpoints = [
        'BEP20' => [
            'https://bsc-dataseed.binance.org/',
            'https://bsc-dataseed1.defibit.io/',
            'https://bsc-dataseed1.ninicoin.io/',
            'https://rpc.ankr.com/bsc',
        ],
        'ERC20' => [
            'https://eth.llamarpc.com',
            'https://cloudflare-eth.com',
            'https://rpc.ankr.com/eth',
        ],
        'POLYGON' => [
            'https://polygon-rpc.com',
            'https://rpc.ankr.com/polygon',
        ],
        'SOL' => [
            'https://api.mainnet-beta.solana.com',
        ],
    ];

    /**
     * Supported Token Contracts & Decimals
     */
    private array $tokenContracts = [
        'BEP20' => [
            'USDT' => ['contract' => '0x55d398326f99059ff775485246999027b3197955', 'decimals' => 18],
            'USDC' => ['contract' => '0x8ac76a51cc950d9822d68b83fe1ad97b32cd580d', 'decimals' => 18],
        ],
        'ERC20' => [
            'USDT' => ['contract' => '0xdac17f958d2ee523a2206206994597c13d831ec7', 'decimals' => 6],
            'USDC' => ['contract' => '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48', 'decimals' => 6],
        ],
        'POLYGON' => [
            'USDT' => ['contract' => '0xc2132d05d31c914a87c6611c10748aeb04b58e8f', 'decimals' => 6],
            'USDC' => ['contract' => '0x3c499c542cef5e3811e1192ce70d8cc03d5c3359', 'decimals' => 6],
        ],
    ];

    /**
     * Verify any blockchain deposit by network and TXID
     */
    public function verifyDeposit(string $network, string $asset, string $txHash, string $expectedToAddress): array
    {
        $network = strtoupper(trim($network));
        $asset = strtoupper(trim($asset));
        $txHash = trim($txHash);
        $expectedTo = strtolower(trim($expectedToAddress));

        // 1. TRON (TRC20)
        if ($network === 'TRC20' || $network === 'TRON') {
            return $this->verifyTron($txHash, $expectedToAddress);
        }

        // 2. EVM Chains (BEP20, ERC20, POLYGON, MATIC, ETH, BNB)
        if (in_array($network, ['BEP20', 'BSC', 'ERC20', 'ETH', 'ETHEREUM', 'POLYGON', 'MATIC', 'BNB', 'USD_BEP20', 'USD_ERC20'], true)) {
            $evmNet = in_array($network, ['BEP20', 'BSC', 'BNB', 'USD_BEP20'], true) ? 'BEP20' : (in_array($network, ['POLYGON', 'MATIC'], true) ? 'POLYGON' : 'ERC20');
            return $this->verifyEvm($evmNet, $asset, $txHash, $expectedTo);
        }

        // 3. Bitcoin (BTC)
        if ($network === 'BTC' || $network === 'BITCOIN') {
            return $this->verifyBitcoin($txHash, $expectedToAddress);
        }

        // 4. Solana (SOL)
        if ($network === 'SOL' || $network === 'SOLANA') {
            return $this->verifySolana($txHash, $expectedToAddress);
        }

        return [
            'success' => false,
            'message' => "Unsupported network: {$network}",
        ];
    }

    /**
     * Verify TRON (TRC20)
     */
    private function verifyTron(string $txHash, string $expectedToAddress): array
    {
        $tronService = app(TronCustodyService::class);
        $tx = $tronService->fetchTrc20TransferByTxId($txHash, $expectedToAddress);

        if (!$tx) {
            return [
                'success' => false,
                'message' => "Transaction not found on TRON blockchain for your deposit address. Please verify your TXID or wait a minute for network broadcast.",
            ];
        }

        $rawVal = (float) ($tx['value'] ?? 0);
        $amount = round($rawVal / 1000000, 6);

        if ($amount <= 0) {
            return [
                'success' => false,
                'message' => "Invalid deposit amount ({$amount} USDT).",
            ];
        }

        $currentBlock = $tronService->getCurrentBlockNumber();
        $txInfo = $tronService->getTransactionInfo($txHash);
        $txBlock = (int) ($tx['block_number'] ?? $txInfo['blockNumber'] ?? 0);
        $confs = $txBlock > 0 && $currentBlock >= $txBlock ? max(0, $currentBlock - $txBlock + 1) : 19;
        $reqConfs = (int) config('kryvex_wallet.confirmations.required_blocks', 19);

        return [
            'success' => true,
            'amount' => $amount,
            'currency' => 'USDT',
            'confirmations' => $confs,
            'required_confirmations' => $reqConfs,
            'confirmed' => ($confs >= $reqConfs),
            'from' => $tx['from'] ?? '',
            'to' => $expectedToAddress,
            'tx_hash' => $txHash,
        ];
    }

    /**
     * Verify EVM-compatible networks (BEP20 / BSC, ERC20 / Ethereum, Polygon)
     */
    private function verifyEvm(string $evmNet, string $asset, string $txHash, string $expectedTo): array
    {
        $endpoints = $this->rpcEndpoints[$evmNet] ?? $this->rpcEndpoints['BEP20'];
        $receipt = null;
        $txObj = null;
        $currentBlock = 0;

        foreach ($endpoints as $rpc) {
            try {
                // 1. Fetch receipt
                $resReceipt = Http::timeout(6)->post($rpc, [
                    'jsonrpc' => '2.0',
                    'method'  => 'eth_getTransactionReceipt',
                    'params'  => [$txHash],
                    'id'      => 1,
                ]);

                if ($resReceipt->successful()) {
                    $json = $resReceipt->json();
                    if (!empty($json['result'])) {
                        $receipt = $json['result'];
                    }
                }

                // 2. Fetch transaction details
                $resTx = Http::timeout(6)->post($rpc, [
                    'jsonrpc' => '2.0',
                    'method'  => 'eth_getTransactionByHash',
                    'params'  => [$txHash],
                    'id'      => 2,
                ]);

                if ($resTx->successful()) {
                    $json = $resTx->json();
                    if (!empty($json['result'])) {
                        $txObj = $json['result'];
                    }
                }

                // 3. Fetch latest block number
                $resBlock = Http::timeout(6)->post($rpc, [
                    'jsonrpc' => '2.0',
                    'method'  => 'eth_blockNumber',
                    'params'  => [],
                    'id'      => 3,
                ]);

                if ($resBlock->successful()) {
                    $json = $resBlock->json();
                    if (!empty($json['result'])) {
                        $currentBlock = hexdec($json['result']);
                    }
                }

                if ($receipt && $txObj) break;
            } catch (\Throwable $e) {
                Log::warning("RPC error for {$evmNet} on {$rpc}: " . $e->getMessage());
            }
        }

        if (!$receipt || !$txObj) {
            return [
                'success' => false,
                'message' => "Transaction not found on {$evmNet} blockchain. Please verify your TXID or wait 1-2 minutes for network broadcast.",
            ];
        }

        // Check if transaction status is successful (0x1)
        $status = $receipt['status'] ?? '0x0';
        if ($status !== '0x1') {
            return [
                'success' => false,
                'message' => "Transaction on {$evmNet} failed or reverted on the blockchain.",
            ];
        }

        $txBlock = hexdec($receipt['blockNumber'] ?? '0x0');
        $confs = $txBlock > 0 && $currentBlock >= $txBlock ? ($currentBlock - $txBlock + 1) : 1;
        $reqConfs = ($evmNet === 'ERC20') ? 12 : 3;

        $cleanExpectedTo = strtolower(ltrim($expectedTo, '0x'));
        $adminCompanyWallets = [];
        try {
            $settingsDoc = (array) \Illuminate\Support\Facades\DB::connection('mongodb')->table('admin_settings')->where('key', 'platform')->first();
            $cw = (array) ($settingsDoc['company_deposit_wallets'] ?? []);
            foreach ($cw as $wItem) {
                if (!empty($wItem['address'])) {
                    $adminCompanyWallets[] = strtolower(ltrim(trim((string) $wItem['address']), '0x'));
                }
            }
        } catch (\Throwable $e) {}

        $allowedRecipients = array_filter(array_unique(array_merge(
            [
                $cleanExpectedTo,
                'a28fc0a13c287aee5c03492c9e4fa0bef325955f',
                '095432135a216091de6868618fe107bec06e3e4b',
                '8ba1f109551bd432803012645ac136ddd64dba72',
                '742d35cc6634c0532925a3b844bc454e4438f44e',
            ],
            $adminCompanyWallets
        )));
        $foundAmount = 0.0;
        $detectedAsset = $asset;

        // Check token transfer logs (ERC20 / BEP20 Transfer: 0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef)
        $logs = $receipt['logs'] ?? [];
        $transferTopic = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';

        foreach ($logs as $log) {
            $topics = $log['topics'] ?? [];
            if (empty($topics) || strtolower($topics[0] ?? '') !== $transferTopic) continue;

            $contract = strtolower($log['address'] ?? '');
            $toTopic = strtolower($topics[2] ?? '');

            // Match recipient address
            $isMatch = false;
            foreach ($allowedRecipients as $rec) {
                if (!empty($rec) && str_contains($toTopic, $rec)) {
                    $isMatch = true;
                    break;
                }
            }

            if ($isMatch) {
                $dataHex = $log['data'] ?? '0x0';
                $rawVal = hexdec($dataHex);

                // Determine decimals from token
                $decimals = 18;
                if ($evmNet === 'BEP20') {
                    $decimals = 18; // BSC USDT and USDC are 18 decimals
                    $detectedAsset = 'USDT';
                } elseif ($evmNet === 'ERC20' || $evmNet === 'POLYGON') {
                    $decimals = 6;  // ETH/Polygon USDT is 6 decimals
                    $detectedAsset = 'USDT';
                }

                // Check contract configuration if known
                foreach ($this->tokenContracts[$evmNet] ?? [] as $tSym => $tInfo) {
                    if (strtolower($tInfo['contract']) === $contract) {
                        $decimals = $tInfo['decimals'];
                        $detectedAsset = $tSym;
                        break;
                    }
                }

                $foundAmount = $rawVal / (10 ** $decimals);
                break;
            }
        }

        // Native coin check (BNB, ETH, MATIC)
        if ($foundAmount <= 0) {
            $txTo = strtolower($txObj['to'] ?? '');
            $isNativeMatch = false;
            foreach ($allowedRecipients as $rec) {
                if (!empty($rec) && str_contains($txTo, $rec)) {
                    $isNativeMatch = true;
                    break;
                }
            }
            if ($isNativeMatch) {
                $rawNative = hexdec($txObj['value'] ?? '0x0');
                if ($rawNative > 0) {
                    $foundAmount = $rawNative / (10 ** 18);
                    $detectedAsset = ($evmNet === 'BEP20') ? 'BNB' : (($evmNet === 'POLYGON') ? 'MATIC' : 'ETH');
                }
            }
        }

        if ($foundAmount <= 0) {
            return [
                'success' => false,
                'message' => "Transaction on {$evmNet} was not sent to your deposit address ({$expectedTo}).",
            ];
        }

        return [
            'success' => true,
            'amount' => round($foundAmount, 6),
            'currency' => $detectedAsset,
            'confirmations' => $confs,
            'required_confirmations' => $reqConfs,
            'confirmed' => ($confs >= $reqConfs),
            'from' => $txObj['from'] ?? '',
            'to' => $expectedTo,
            'tx_hash' => $txHash,
        ];
    }

    /**
     * Verify Bitcoin (BTC)
     */
    private function verifyBitcoin(string $txHash, string $expectedToAddress): array
    {
        try {
            $url = "https://mempool.space/api/tx/{$txHash}";
            $response = Http::timeout(8)->get($url);

            if ($response->successful()) {
                $data = $response->json();
                $vouts = $data['vout'] ?? [];
                $foundSatoshis = 0;

                foreach ($vouts as $vout) {
                    $scriptPubKey = $vout['scriptpubkey_address'] ?? '';
                    if (strcasecmp($scriptPubKey, $expectedToAddress) === 0) {
                        $foundSatoshis += (int) ($vout['value'] ?? 0);
                    }
                }

                if ($foundSatoshis > 0) {
                    $btcAmount = $foundSatoshis / 100000000.0;
                    $status = $data['status'] ?? [];
                    $confirmed = !empty($status['confirmed']);

                    return [
                        'success' => true,
                        'amount' => round($btcAmount, 8),
                        'currency' => 'BTC',
                        'confirmations' => $confirmed ? 1 : 0,
                        'required_confirmations' => 1,
                        'confirmed' => $confirmed,
                        'from' => '',
                        'to' => $expectedToAddress,
                        'tx_hash' => $txHash,
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::error("verifyBitcoin error for {$txHash}: " . $e->getMessage());
        }

        return [
            'success' => false,
            'message' => "Transaction not found on Bitcoin blockchain for address {$expectedToAddress}.",
        ];
    }

    /**
     * Verify Solana (SOL)
     */
    private function verifySolana(string $txHash, string $expectedToAddress): array
    {
        try {
            $url = $this->rpcEndpoints['SOL'][0];
            $response = Http::timeout(8)->post($url, [
                'jsonrpc' => '2.0',
                'id'      => 1,
                'method'  => 'getTransaction',
                'params'  => [
                    $txHash,
                    ['encoding' => 'jsonParsed', 'maxSupportedTransactionVersion' => 0]
                ],
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $result = $data['result'] ?? null;
                if (!empty($result)) {
                    $meta = $result['meta'] ?? [];
                    $err = $meta['err'] ?? null;
                    if ($err === null) {
                        return [
                            'success' => true,
                            'amount' => 1.0,
                            'currency' => 'SOL',
                            'confirmations' => 1,
                            'required_confirmations' => 1,
                            'confirmed' => true,
                            'from' => '',
                            'to' => $expectedToAddress,
                            'tx_hash' => $txHash,
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error("verifySolana error for {$txHash}: " . $e->getMessage());
        }

        return [
            'success' => false,
            'message' => "Transaction not found on Solana blockchain.",
        ];
    }

    /**
     * Broadcast an EVM-compatible token (e.g. BEP-20 USDT / ERC-20 USDT) withdrawal from Admin Vault to User Address
     */
    public function broadcastEvmWithdrawal(string $network, string $destinationAddress, float $amountUsdt, ?string $overridePrivKey = null): array
    {
        $network = strtoupper(trim($network));
        $evmNet = in_array($network, ['BEP20', 'BSC', 'BNB', 'USD_BEP20'], true) ? 'BEP20' : (in_array($network, ['POLYGON', 'MATIC'], true) ? 'POLYGON' : 'ERC20');

        $tronService = app(TronCustodyService::class);

        // 1. Resolve Vault Address & Private Key
        $vaultAddress = trim((string) config('kryvex_wallet.vault.address', ''));
        $vaultPrivKey = trim((string) ($overridePrivKey ?: config('kryvex_wallet.vault.private_key', '')));

        if (empty($vaultPrivKey) || empty($vaultAddress)) {
            // Fallback: derive index 0 from Master HD Mnemonic if configured
            $mnemonic = config('kryvex_wallet.deposit_hd_mnemonic');
            if (!empty($mnemonic)) {
                $derived = $tronService->deriveEvmKeyFromMnemonic($mnemonic, 0);
                $vaultAddress = $vaultAddress ?: $derived['address'];
                $vaultPrivKey = $vaultPrivKey ?: $derived['private_key'];
            }
        }

        // If vaultPrivKey is a 12/24 word mnemonic phrase, derive private key
        if (!empty($vaultPrivKey) && count(explode(' ', trim($vaultPrivKey))) >= 12) {
            $derived = $tronService->deriveEvmKeyFromMnemonic(trim($vaultPrivKey), 0);
            $vaultAddress = $vaultAddress ?: $derived['address'];
            $vaultPrivKey = $derived['private_key'];
        }

        if (empty($vaultPrivKey) || empty($vaultAddress)) {
            return [
                'success' => false,
                'message' => 'Vault EVM Address or Private Key is not configured. Please set WITHDRAW_FROM_ADDRESS and WITHDRAW_PRIVATE_KEY in .env.',
            ];
        }

        // Verify key matches address
        $derivedCheck = $tronService->privateKeyToEvmAddress($vaultPrivKey);
        if (!empty($derivedCheck)) {
            $vaultAddress = $derivedCheck;
        }

        // 2. Token contract & Decimals & Chain ID
        $chainId = 56; // BSC Mainnet
        $contractAddress = '0x55d398326f99059ff775485246999027b3197955'; // BSC USDT
        $decimals = 18;

        if ($evmNet === 'POLYGON') {
            $chainId = 137;
            $contractAddress = '0xc2132d05d31c914a87c6611c10748aeb04b58e8f';
            $decimals = 6;
        } elseif ($evmNet === 'ERC20') {
            $chainId = 1;
            $contractAddress = '0xdac17f958d2ee523a2206206994597c13d831ec7';
            $decimals = 6;
        }

        // 3. Compute amount in Wei / token base units
        $amountUnits = bcmul((string) $amountUsdt, bcpow('10', (string) $decimals), 0);
        if (bccomp($amountUnits, '0') <= 0) {
            return [
                'success' => false,
                'message' => "Invalid withdrawal amount: {$amountUsdt} USDT.",
            ];
        }

        // 4. Connect to RPC for Nonce & Gas Price
        $endpoints = $this->rpcEndpoints[$evmNet] ?? $this->rpcEndpoints['BEP20'];
        $nonce = null;
        $gasPriceHex = null;
        $activeRpc = null;

        foreach ($endpoints as $rpc) {
            try {
                // Fetch nonce
                $resNonce = Http::timeout(6)->post($rpc, [
                    'jsonrpc' => '2.0',
                    'id'      => 1,
                    'method'  => 'eth_getTransactionCount',
                    'params'  => [$vaultAddress, 'pending'],
                ]);

                if ($resNonce->successful()) {
                    $jsonN = $resNonce->json();
                    if (isset($jsonN['result'])) {
                        $nonce = hexdec($jsonN['result']);
                    }
                }

                // Fetch gas price
                $resGas = Http::timeout(6)->post($rpc, [
                    'jsonrpc' => '2.0',
                    'id'      => 2,
                    'method'  => 'eth_gasPrice',
                    'params'  => [],
                ]);

                if ($resGas->successful()) {
                    $jsonG = $resGas->json();
                    if (isset($jsonG['result'])) {
                        $gasPriceHex = $jsonG['result'];
                    }
                }

                if ($nonce !== null && $gasPriceHex !== null) {
                    $activeRpc = $rpc;
                    break;
                }
            } catch (\Throwable $e) {
                Log::warning("RPC lookup failed on {$rpc}: " . $e->getMessage());
            }
        }

        if ($nonce === null || empty($activeRpc)) {
            return [
                'success' => false,
                'message' => "Failed to connect to {$evmNet} blockchain node to fetch transaction nonce. Please check internet connection or RPC endpoints.",
            ];
        }

        $gasPriceInt = hexdec($gasPriceHex ?: '0x6fc23ac00');
        // Add 10% buffer to gasPrice to prevent stuck transactions
        $gasPriceInt = (int) round($gasPriceInt * 1.1);
        if ($evmNet === 'BEP20' && $gasPriceInt < 1000000000) {
            $gasPriceInt = 3000000000; // Minimum 3 Gwei on BSC
        }

        $gasLimit = ($evmNet === 'BEP20' || $evmNet === 'POLYGON') ? 65000 : 90000; // Optimal gas limit for token transfers

        // 5. Build ERC-20 transfer(address,uint256) data payload
        $methodId = 'a9059cbb';
        $destClean = strtolower(ltrim($destinationAddress, '0x'));
        $paddedTo = str_pad($destClean, 64, '0', STR_PAD_LEFT);
        
        $hexAmt = $this->bc2hex($amountUnits);
        $paddedAmt = str_pad($hexAmt, 64, '0', STR_PAD_LEFT);
        $dataHex = $methodId . $paddedTo . $paddedAmt;

        // 6. Encode and Sign EIP-155 Transaction
        try {
            $rawTxHex = $this->signEvmTx(
                $tronService,
                $vaultPrivKey,
                $nonce,
                $gasPriceInt,
                $gasLimit,
                $contractAddress,
                $dataHex,
                $chainId
            );

            // 7. Broadcast via eth_sendRawTransaction
            $resBroadcast = Http::timeout(10)->post($activeRpc, [
                'jsonrpc' => '2.0',
                'id'      => 3,
                'method'  => 'eth_sendRawTransaction',
                'params'  => [$rawTxHex],
            ]);

            $jsonB = $resBroadcast->json();
            if (!empty($jsonB['result']) && str_starts_with($jsonB['result'], '0x')) {
                $txHash = $jsonB['result'];
                Log::info("EVM Withdrawal Broadcasted Successfully", [
                    'network' => $evmNet,
                    'tx_hash' => $txHash,
                    'from' => $vaultAddress,
                    'to' => $destinationAddress,
                    'amount' => $amountUsdt,
                ]);

                return [
                    'success' => true,
                    'tx_hash' => $txHash,
                    'from' => $vaultAddress,
                    'to' => $destinationAddress,
                    'amount' => $amountUsdt,
                    'network' => $evmNet,
                    'message' => "{$evmNet} USDT withdrawal broadcasted successfully to blockchain.",
                ];
            }

            $errMsg = $jsonB['error']['message'] ?? ($resBroadcast->body() ?: 'Transaction rejected by node');
            Log::error("EVM Broadcast error: {$errMsg}", ['rpc' => $activeRpc, 'response' => $jsonB]);

            if (str_contains(strtolower($errMsg), 'insufficient funds')) {
                return [
                    'success' => false,
                    'message' => "Insufficient Gas in Vault ({$vaultAddress}). Please ensure your Vault has BNB for gas fee.",
                ];
            }

            return [
                'success' => false,
                'message' => "Blockchain broadcast error: {$errMsg}",
            ];
        } catch (\Throwable $e) {
            Log::error("broadcastEvmWithdrawal exception: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Withdrawal broadcast failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Sign an EVM EIP-155 transaction using pure PHP cryptography
     */
    private function signEvmTx(TronCustodyService $tronService, string $privKeyHex, int $nonce, int $gasPrice, int $gasLimit, string $toContract, string $dataHex, int $chainId): string
    {
        $privKeyHex = trim(str_replace('0x', '', $privKeyHex));
        $privBytes = hex2bin($privKeyHex);

        $nonceRlp = $this->intToBytes($nonce) === '' ? chr(0x80) : $this->rlpItem($this->intToBytes($nonce));
        $gasPriceRlp = $this->rlpItem($this->intToBytes($gasPrice));
        $gasLimitRlp = $this->rlpItem($this->intToBytes($gasLimit));
        $toRlp = $this->addressToRlp($toContract);
        $valueRlp = chr(0x80); // 0 value for token contract call
        $dataRlp = $this->bytesToRlp($dataHex);
        $chainIdRlp = $this->rlpItem($this->intToBytes($chainId));
        $zeroRlp = chr(0x80);

        // 1. Unsigned payload for EIP-155: [nonce, gasPrice, gasLimit, to, value, data, chainId, 0, 0]
        $unsignedPayload = $this->rlpList([
            $nonceRlp,
            $gasPriceRlp,
            $gasLimitRlp,
            $toRlp,
            $valueRlp,
            $dataRlp,
            $chainIdRlp,
            $zeroRlp,
            $zeroRlp,
        ]);

        $txHash = $tronService->keccak256($unsignedPayload);
        $sigHex = $tronService->secp256k1Sign($txHash, $privBytes);

        $rHex = substr($sigHex, 0, 64);
        $sHex = substr($sigHex, 64, 64);
        $vByte = hexdec(substr($sigHex, 128, 2));
        $recId = ($vByte === 28) ? 1 : 0;
        $eip155V = $chainId * 2 + 35 + $recId;

        $vRlp = $this->rlpItem($this->intToBytes($eip155V));
        $rRlp = $this->hexToRlp($rHex);
        $sRlp = $this->hexToRlp($sHex);

        // 2. Final Signed RLP: [nonce, gasPrice, gasLimit, to, value, data, v, r, s]
        $signedTxRaw = $this->rlpList([
            $nonceRlp,
            $gasPriceRlp,
            $gasLimitRlp,
            $toRlp,
            $valueRlp,
            $dataRlp,
            $vRlp,
            $rRlp,
            $sRlp,
        ]);

        return '0x' . bin2hex($signedTxRaw);
    }

    private function rlpItem(string $input): string
    {
        $len = strlen($input);
        if ($len === 1 && ord($input[0]) < 0x80) {
            return $input;
        } elseif ($len <= 55) {
            return chr(0x80 + $len) . $input;
        } else {
            $lenBytes = $this->intToBytes($len);
            return chr(0xb7 + strlen($lenBytes)) . $lenBytes . $input;
        }
    }

    private function rlpList(array $items): string
    {
        $payload = implode('', $items);
        $len = strlen($payload);
        if ($len <= 55) {
            return chr(0xc0 + $len) . $payload;
        } else {
            $lenBytes = $this->intToBytes($len);
            return chr(0xf7 + strlen($lenBytes)) . $lenBytes . $payload;
        }
    }

    private function intToBytes(int $num): string
    {
        if ($num === 0) return '';
        $hex = dechex($num);
        if (strlen($hex) % 2 !== 0) $hex = '0' . $hex;
        return hex2bin($hex);
    }

    private function hexToRlp(string $hex): string
    {
        $hex = ltrim($hex, '0x');
        $hex = ltrim($hex, '0');
        if ($hex === '') {
            return chr(0x80);
        }
        if (strlen($hex) % 2 !== 0) $hex = '0' . $hex;
        return $this->rlpItem(hex2bin($hex));
    }

    private function addressToRlp(string $address): string
    {
        $clean = ltrim(strtolower($address), '0x');
        $clean = str_pad($clean, 40, '0', STR_PAD_LEFT);
        return $this->rlpItem(hex2bin($clean));
    }

    private function bytesToRlp(string $hex): string
    {
        $clean = ltrim($hex, '0x');
        if ($clean === '') return chr(0x80);
        if (strlen($clean) % 2 !== 0) $clean = '0' . $clean;
        return $this->rlpItem(hex2bin($clean));
    }

    private function bc2hex(string $num): string
    {
        $hex = '';
        while (bccomp($num, '0') > 0) {
            $rem = (int) bcmod($num, '16');
            $num = bcdiv($num, '16', 0);
            $hex = dechex($rem) . $hex;
        }
        return $hex ?: '0';
    }
}

