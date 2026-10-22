<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TronCustodyService
{
    /**
     * Base58 Alphabet used by Bitcoin and Tron
     */
    private const BASE58_ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    /**
     * Get API base URL for current Tron network
     */
    public function getApiUrl(): string
    {
        $network = strtolower(config('kryvex_wallet.tron_network', 'mainnet'));
        return match ($network) {
            'nile' => 'https://nile.trongrid.io',
            'shasta' => 'https://api.shasta.trongrid.io',
            default => 'https://api.trongrid.io',
        };
    }

    /**
     * Get HTTP client with optional TronGrid API key headers
     */
    public function http()
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        $apiKey = config('kryvex_wallet.trongrid_api_key');
        if (!empty($apiKey)) {
            $headers['TRON-PRO-API-KEY'] = $apiKey;
        }

        return Http::withHeaders($headers)->timeout(15);
    }

    /**
     * Get or derive unique HD TRC-20 USDT deposit address for a user.
     * Uses BIP-44 Derivation Path: m/44'/195'/0'/0/{user_index} (Tron coin type 195)
     */
    public function getOrCreateUserDepositAddress(string $userUid): array
    {
        // 1. Check if user already has an assigned HD deposit address
        $existing = DB::connection('mongodb')
            ->table('user_deposit_addresses')
            ->where('user_uid', $userUid)
            ->where('network', 'TRC20')
            ->first();

        if ($existing) {
            $record = (array) $existing;
            return [
                'address' => $record['address'],
                'hd_index' => $record['hd_index'] ?? 0,
                'network' => 'TRC20',
                'asset' => 'USDT',
                'contract' => config('kryvex_wallet.contracts.deposit_usdt'),
            ];
        }

        $mnemonic = trim(config('kryvex_wallet.deposit_hd_mnemonic', ''));

        // If no master HD mnemonic is configured yet, fall back to company address or deterministic user hash
        if (empty($mnemonic)) {
            $companyFallback = (array) (DB::connection('mongodb')->table('admin_settings')->where('key', 'platform')->first());
            $companyWallets = (array) ($companyFallback['company_deposit_wallets'] ?? []);
            $fallbackAddress = $companyWallets['USD_TRC20']['address'] ?? 'TQn9Y2khEsLJW1ChVWFMSMeRDow5KcbLSE';

            return [
                'address' => $fallbackAddress,
                'hd_index' => 0,
                'network' => 'TRC20',
                'asset' => 'USDT',
                'contract' => config('kryvex_wallet.contracts.deposit_usdt'),
                'is_fallback' => true,
            ];
        }

        // 2. Allocate next sequential HD index for this user
        $lastRecord = DB::connection('mongodb')
            ->table('user_deposit_addresses')
            ->orderByDesc('hd_index')
            ->first();

        $nextIndex = ($lastRecord && isset($lastRecord->hd_index)) ? ((int) $lastRecord->hd_index + 1) : 0;

        // 3. Derive Tron address at path m/44'/195'/0'/0/{nextIndex}
        $derived = $this->deriveTronKeyFromMnemonic($mnemonic, $nextIndex);
        $depositAddress = $derived['address'];
        $encryptedPrivateKey = !empty($derived['private_key']) ? encrypt($derived['private_key']) : null;

        // 4. Persist in MongoDB
        DB::connection('mongodb')->table('user_deposit_addresses')->insert([
            'address_uid' => (string) Str::uuid(),
            'user_uid' => $userUid,
            'network' => 'TRC20',
            'asset' => 'USDT',
            'address' => $depositAddress,
            'hd_index' => $nextIndex,
            'derivation_path' => "m/44'/195'/0'/0/{$nextIndex}",
            'encrypted_privkey' => $encryptedPrivateKey,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'address' => $depositAddress,
            'hd_index' => $nextIndex,
            'network' => 'TRC20',
            'asset' => 'USDT',
            'contract' => config('kryvex_wallet.contracts.deposit_usdt'),
        ];
    }

    /**
     * Get or derive unique HD EVM (BEP20 / ERC20 / Polygon) deposit address for a user.
     * Uses BIP-44 Derivation Path: m/44'/60'/0'/0/{user_index} (Coin type 60)
     */
    public function getOrCreateUserEvmDepositAddress(string $userUid, string $network = 'BEP20'): array
    {
        $network = strtoupper($network);
        // 1. Check if user already has an assigned HD deposit address for this network or generic EVM
        $existing = DB::connection('mongodb')
            ->table('user_deposit_addresses')
            ->where('user_uid', $userUid)
            ->whereIn('network', ['BEP20', 'ERC20', 'POLYGON', 'EVM', $network])
            ->first();

        if ($existing) {
            $record = (array) $existing;
            return [
                'address' => $record['address'],
                'hd_index' => $record['hd_index'] ?? 0,
                'network' => $network,
                'asset' => 'USDT',
            ];
        }

        $mnemonic = trim(config('kryvex_wallet.deposit_hd_mnemonic', ''));

        // If no master HD mnemonic is configured yet, fall back to company address or deterministic user hash
        if (empty($mnemonic)) {
            $companyFallback = (array) (DB::connection('mongodb')->table('admin_settings')->where('key', 'platform')->first());
            $companyWallets = (array) ($companyFallback['company_deposit_wallets'] ?? []);
            $fallbackAddress = $companyWallets['USD_' . $network]['address'] ?? ($companyWallets['USD_BEP20']['address'] ?? '0xA28Fc0A13c287AeE5C03492c9e4FA0BEF325955f');

            return [
                'address' => $fallbackAddress,
                'hd_index' => 0,
                'network' => $network,
                'asset' => 'USDT',
                'is_fallback' => true,
            ];
        }

        // 2. Allocate next sequential HD index for this user (or match TRC20 hd_index)
        $trcRecord = DB::connection('mongodb')
            ->table('user_deposit_addresses')
            ->where('user_uid', $userUid)
            ->where('network', 'TRC20')
            ->first();

        if ($trcRecord && isset($trcRecord->hd_index)) {
            $nextIndex = (int) $trcRecord->hd_index;
        } else {
            $lastRecord = DB::connection('mongodb')
                ->table('user_deposit_addresses')
                ->orderByDesc('hd_index')
                ->first();

            $nextIndex = ($lastRecord && isset($lastRecord->hd_index)) ? ((int) $lastRecord->hd_index + 1) : 0;
        }

        // 3. Derive EVM address at path m/44'/60'/0'/0/{nextIndex}
        $derived = $this->deriveEvmKeyFromMnemonic($mnemonic, $nextIndex);
        $depositAddress = $derived['address'];
        $encryptedPrivateKey = !empty($derived['private_key']) ? encrypt($derived['private_key']) : null;

        // 4. Persist in MongoDB
        DB::connection('mongodb')->table('user_deposit_addresses')->insert([
            'address_uid' => (string) Str::uuid(),
            'user_uid' => $userUid,
            'network' => $network,
            'asset' => 'USDT',
            'address' => $depositAddress,
            'hd_index' => $nextIndex,
            'derivation_path' => "m/44'/60'/0'/0/{$nextIndex}",
            'encrypted_privkey' => $encryptedPrivateKey,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'address' => $depositAddress,
            'hd_index' => $nextIndex,
            'network' => $network,
            'asset' => 'USDT',
        ];
    }

    /**
     * Validate whether WITHDRAW_FROM_ADDRESS matches WITHDRAW_PRIVATE_KEY
     */
    public function validateVaultKeyMatch(): array
    {
        $vaultAddress = trim(config('kryvex_wallet.vault.address', ''));
        $vaultPrivKey = trim(config('kryvex_wallet.vault.private_key', ''));

        if (empty($vaultAddress) || empty($vaultPrivKey)) {
            return [
                'valid' => false,
                'error' => 'Vault C address or private key is not configured in .env',
                'vault_address' => $vaultAddress,
            ];
        }

        try {
            // Support 12-word mnemonic phrase
            if (count(explode(' ', $vaultPrivKey)) >= 12) {
                if (str_starts_with($vaultAddress, '0x')) {
                    $derived = $this->deriveEvmKeyFromMnemonic($vaultPrivKey, 0);
                } else {
                    $derived = $this->deriveTronKeyFromMnemonic($vaultPrivKey, 0);
                }
                $derivedAddress = $derived['address'];
            } else {
                $derivedAddress = str_starts_with($vaultAddress, '0x') 
                    ? $this->privateKeyToEvmAddress($vaultPrivKey) 
                    : $this->privateKeyToAddress($vaultPrivKey);
            }

            if (strtoupper($derivedAddress) !== strtoupper($vaultAddress)) {
                return [
                    'valid' => false,
                    'error' => "Vault address mismatch! Configured: {$vaultAddress}, Key derives: {$derivedAddress}",
                    'vault_address' => $vaultAddress,
                    'derived_address' => $derivedAddress,
                ];
            }

            return [
                'valid' => true,
                'vault_address' => $vaultAddress,
                'derived_address' => $derivedAddress,
            ];
        } catch (\Throwable $e) {
            return [
                'valid' => false,
                'error' => 'Failed to derive address from private key: ' . $e->getMessage(),
                'vault_address' => $vaultAddress,
            ];
        }
    }

    /**
     * Fetch TRC-20 USDT incoming transfers for an address from TronGrid
     */
    public function fetchTrc20Transfers(string $address, ?int $minTimestamp = null): array
    {
        $network = strtolower(config('kryvex_wallet.tron_network', 'mainnet'));
        $configuredContract = config('kryvex_wallet.contracts.deposit_usdt');
        
        if ($network === 'mainnet') {
            $validContracts = [
                strtolower('TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t'), // Official TRON Mainnet USDT Contract
                strtolower((string) $configuredContract),
            ];
        } else {
            $validContracts = array_filter([
                strtolower((string) $configuredContract),
                strtolower('TXYZopYRdj2D9XRtbG411XZZ3kM5VkAeBf'), // Nile Testnet USDT
                strtolower('TXLAQ63Xg1NAzckPwKHvzw7CSEmLMEqcdj'), // Nile Testnet USDT
            ]);
        }
        $validContracts = array_unique(array_filter($validContracts));

        $url = $this->getApiUrl() . "/v1/accounts/{$address}/transactions/trc20";

        $params = [
            'only_to' => 'true',
            'limit' => 50,
        ];

        if ($minTimestamp) {
            $params['min_timestamp'] = $minTimestamp;
        }

        try {
            $response = $this->http()->get($url, $params);
            if ($response->successful()) {
                $json = $response->json();
                $transfers = $json['data'] ?? [];

                // Filter for USDT transfers
                return array_values(array_filter($transfers, function ($t) use ($validContracts) {
                    $tokenAddr = strtolower((string) ($t['token_info']['address'] ?? ''));
                    $tokenSymbol = strtoupper((string) ($t['token_info']['symbol'] ?? ''));
                    return in_array($tokenAddr, $validContracts, true) || $tokenSymbol === 'USDT';
                }));
            }
        } catch (\Throwable $e) {
            Log::error("TronCustodyService fetchTrc20Transfers error for {$address}: " . $e->getMessage());
        }

        return [];
    }

    /**
     * Fetch current block number from TronGrid
     */
    public function getCurrentBlockNumber(): int
    {
        try {
            $url = $this->getApiUrl() . '/wallet/getnowblock';
            $response = $this->http()->get($url);
            if ($response->successful()) {
                $data = $response->json();
                return (int) ($data['block_header']['raw_data']['number'] ?? 0);
            }
        } catch (\Throwable $e) {
            Log::error('TronCustodyService getCurrentBlockNumber error: ' . $e->getMessage());
        }

        return 0;
    }

    /**
     * Fetch transaction info / confirmations
     */
    public function getTransactionInfo(string $txHash): ?array
    {
        try {
            $url = $this->getApiUrl() . '/wallet/gettransactioninfobyid';
            $response = $this->http()->post($url, ['value' => $txHash]);
            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Throwable $e) {
            Log::error("TronCustodyService getTransactionInfo error for {$txHash}: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Fetch a specific TRC-20 USDT transfer by TXID and destination address
     */
    public function fetchTrc20TransferByTxId(string $txId, string $expectedToAddress): ?array
    {
        $cleanTxId = trim($txId);
        if (empty($cleanTxId)) return null;

        // 1. Check in recent account transfers
        $transfers = $this->fetchTrc20Transfers($expectedToAddress);
        foreach ($transfers as $tx) {
            if (strcasecmp((string)($tx['transaction_id'] ?? ''), $cleanTxId) === 0) {
                return $tx;
            }
        }

        // 2. Query TronGrid transaction events endpoint
        try {
            $url = $this->getApiUrl() . "/v1/transactions/{$cleanTxId}/events";
            $response = $this->http()->get($url);
            if ($response->successful()) {
                $json = $response->json();
                $events = $json['data'] ?? [];
                $expectedHex = strtolower($this->addressToHex($expectedToAddress));

                foreach ($events as $ev) {
                    if (($ev['event_name'] ?? '') === 'Transfer') {
                        $res = $ev['result'] ?? [];
                        $toAddr = (string) ($res['to'] ?? $res['1'] ?? '');
                        $rawVal = (float) ($res['value'] ?? $res['2'] ?? 0);
                        $fromAddr = (string) ($res['from'] ?? $res['0'] ?? '');

                        $toHex = '';
                        try {
                            $toHex = str_starts_with($toAddr, '41') ? strtolower($toAddr) : strtolower($this->addressToHex($toAddr));
                        } catch (\Throwable $e) {}

                        if ($toHex === $expectedHex && $rawVal > 0) {
                            return [
                                'transaction_id' => $cleanTxId,
                                'from'           => $fromAddr,
                                'to'             => $expectedToAddress,
                                'value'          => $rawVal,
                                'token_info'     => [
                                    'symbol'   => 'USDT',
                                    'decimals' => 6,
                                ],
                            ];
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error("fetchTrc20TransferByTxId events query error for {$cleanTxId}: " . $e->getMessage());
        }

        // 3. Fallback to /wallet/gettransactioninfobyid
        try {
            $txInfo = $this->getTransactionInfo($cleanTxId);
            if (!empty($txInfo) && isset($txInfo['log']) && is_array($txInfo['log'])) {
                $expectedHexNoPrefix = ltrim(strtolower($this->addressToHex($expectedToAddress)), '41');

                foreach ($txInfo['log'] as $logItem) {
                    $topics = $logItem['topics'] ?? [];
                    if (!empty($topics) && strtolower($topics[0]) === 'ddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef') {
                        $toTopic = strtolower($topics[2] ?? '');
                        if (str_contains($toTopic, $expectedHexNoPrefix)) {
                            $dataHex = $logItem['data'] ?? '0';
                            $rawVal = hexdec($dataHex);
                            if ($rawVal > 0) {
                                return [
                                    'transaction_id' => $cleanTxId,
                                    'from'           => '',
                                    'to'             => $expectedToAddress,
                                    'value'          => $rawVal,
                                    'block_number'   => (int) ($txInfo['blockNumber'] ?? 0),
                                    'token_info'     => [
                                        'symbol'   => 'USDT',
                                        'decimals' => 6,
                                    ],
                                ];
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error("fetchTrc20TransferByTxId gettransactioninfo error for {$cleanTxId}: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Broadcast a TRC-20 USDT withdrawal from Vault C to user destination address.
     */
    public function broadcastTrc20Withdrawal(string $destinationAddress, float $amountUsdt): array
    {
        $validation = $this->validateVaultKeyMatch();
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => 'Vault C Key Validation Failed: ' . ($validation['error'] ?? 'Unknown error'),
            ];
        }

        $vaultAddress = config('kryvex_wallet.vault.address');
        $vaultPrivKey = config('kryvex_wallet.vault.private_key');
        $contractAddress = config('kryvex_wallet.contracts.withdraw_usdt');

        // USDT uses 6 decimals on TRON
        $amountUnits = (int) round($amountUsdt * 1000000);

        try {
            // 1. Build triggerconstantcontract / triggersmartcontract
            $parameter = $this->encodeTrc20TransferParameter($destinationAddress, $amountUnits);
            $ownerHex = $this->base58ToHex($vaultAddress);
            $contractHex = $this->base58ToHex($contractAddress);

            $url = $this->getApiUrl() . '/wallet/triggersmartcontract';
            $payload = [
                'owner_address' => $ownerHex,
                'contract_address' => $contractHex,
                'function_selector' => 'transfer(address,uint256)',
                'parameter' => $parameter,
                'fee_limit' => 30000000, // 30 TRX max fee limit
            ];

            $res = $this->http()->post($url, $payload);
            $resJson = $res->json();

            if (empty($resJson['result']['result']) || empty($resJson['transaction'])) {
                $errMsg = $resJson['result']['message'] ?? ($resJson['Error'] ?? 'Smart contract trigger failed');
                return [
                    'success' => false,
                    'message' => "Trigger contract error: {$errMsg}",
                ];
            }

            $rawTx = $resJson['transaction'];

            // 2. Sign transaction with Vault C private key
            $signedTx = $this->signTronTransaction($rawTx, $vaultPrivKey);

            // 3. Broadcast transaction
            $broadcastUrl = $this->getApiUrl() . '/wallet/broadcasttransaction';
            $broadcastRes = $this->http()->post($broadcastUrl, $signedTx);
            $bJson = $broadcastRes->json();

            if (!empty($bJson['result'])) {
                $txId = $rawTx['txID'] ?? ($bJson['txid'] ?? '');
                return [
                    'success' => true,
                    'tx_hash' => $txId,
                    'from' => $vaultAddress,
                    'to' => $destinationAddress,
                    'amount' => $amountUsdt,
                    'message' => 'TRC-20 USDT transfer broadcasted successfully.',
                ];
            }

            $bErr = $bJson['message'] ?? ($bJson['code'] ?? 'Broadcast rejected by Tron node');
            return [
                'success' => false,
                'message' => "Broadcast error: {$bErr}",
            ];
        } catch (\Throwable $e) {
            Log::error("TronCustodyService broadcastTrc20Withdrawal error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Withdrawal broadcast failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Sweep TRC-20 USDT from User Deposit Address B to Company Vault C.
     */
    public function sweepAddressToVault(string $depositAddress, string $encryptedPrivKey, float $amountUsdt): array
    {
        $vaultCheck = $this->validateVaultKeyMatch();
        if (!$vaultCheck['valid']) {
            return [
                'success' => false,
                'message' => 'Cannot sweep: Vault C validation failed - ' . ($vaultCheck['error'] ?? ''),
            ];
        }

        $vaultAddress = config('kryvex_wallet.vault.address');
        $contractAddress = config('kryvex_wallet.contracts.deposit_usdt');
        $amountUnits = (int) round($amountUsdt * 1000000);

        try {
            $privateKey = decrypt($encryptedPrivKey);
            $derivedCheck = $this->privateKeyToAddress($privateKey);

            if (strtoupper($derivedCheck) !== strtoupper($depositAddress)) {
                return [
                    'success' => false,
                    'message' => "Sweep refused: Private key does not match deposit address {$depositAddress}",
                ];
            }

            $parameter = $this->encodeTrc20TransferParameter($vaultAddress, $amountUnits);
            $ownerHex = $this->base58ToHex($depositAddress);
            $contractHex = $this->base58ToHex($contractAddress);

            $url = $this->getApiUrl() . '/wallet/triggersmartcontract';
            $payload = [
                'owner_address' => $ownerHex,
                'contract_address' => $contractHex,
                'function_selector' => 'transfer(address,uint256)',
                'parameter' => $parameter,
                'fee_limit' => 30000000,
            ];

            $res = $this->http()->post($url, $payload);
            $resJson = $res->json();

            if (empty($resJson['result']['result']) || empty($resJson['transaction'])) {
                return [
                    'success' => false,
                    'message' => 'Trigger contract for sweep failed: ' . ($resJson['result']['message'] ?? 'Check TRX energy balance on address B'),
                ];
            }

            $rawTx = $resJson['transaction'];
            $signedTx = $this->signTronTransaction($rawTx, $privateKey);

            $broadcastUrl = $this->getApiUrl() . '/wallet/broadcasttransaction';
            $broadcastRes = $this->http()->post($broadcastUrl, $signedTx);
            $bJson = $broadcastRes->json();

            if (!empty($bJson['result'])) {
                return [
                    'success' => true,
                    'tx_hash' => $rawTx['txID'] ?? ($bJson['txid'] ?? ''),
                    'from' => $depositAddress,
                    'to' => $vaultAddress,
                    'amount' => $amountUsdt,
                ];
            }

            return [
                'success' => false,
                'message' => 'Sweep broadcast failed: ' . ($bJson['message'] ?? 'Node rejected sweep'),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Sweep exception: ' . $e->getMessage(),
            ];
        }
    }

    // =========================================================================
    // CRYPTOGRAPHIC & ADDRESS UTILITIES (Pure PHP BIP-39 / BIP-44 / Tron / ECDSA)
    // =========================================================================

    /**
     * Derive private key and Tron address from mnemonic and index (m/44'/195'/0'/0/{index})
     */
    public function deriveTronKeyFromMnemonic(string $mnemonic, int $index = 0): array
    {
        $mnemonic = trim(strtolower(preg_replace('/\s+/', ' ', $mnemonic)));
        $seed = hash_pbkdf2('sha512', $mnemonic, 'mnemonic', 2048, 64, true);

        // BIP32 master key
        $hmac = hash_hmac('sha512', $seed, 'Bitcoin seed', true);
        $masterPriv = substr($hmac, 0, 32);
        $masterChainCode = substr($hmac, 32, 32);

        // Path m/44'/195'/0'/0/{index}
        // 44' (0x8000002C), 195' (0x800000C3), 0' (0x80000000), 0 (0x00000000), {index}
        $path = [0x8000002C, 0x800000C3, 0x80000000, 0x00000000, $index];

        $currPriv = $masterPriv;
        $currChain = $masterChainCode;

        foreach ($path as $segment) {
            $isHardened = ($segment >= 0x80000000);
            if ($isHardened) {
                $data = "\x00" . $currPriv . pack('N', $segment);
            } else {
                $pub = $this->privateKeyToCompressedPublicKey($currPriv);
                $data = $pub . pack('N', $segment);
            }

            $h = hash_hmac('sha512', $data, $currChain, true);
            $il = substr($h, 0, 32);
            $ir = substr($h, 32, 32);

            // currPriv = (il + currPriv) mod secp256k1_n
            $currPriv = $this->addPrivateKeys($il, $currPriv);
            $currChain = $ir;
        }

        $privHex = bin2hex($currPriv);
        $address = $this->privateKeyToAddress($privHex);

        return [
            'private_key' => $privHex,
            'address' => $address,
            'index' => $index,
        ];
    }

    /**
     * Derive private key and EVM address (BEP20 / ERC20 / Polygon) from mnemonic and index (m/44'/60'/0'/0/{index})
     */
    public function deriveEvmKeyFromMnemonic(string $mnemonic, int $index = 0): array
    {
        $mnemonic = trim(strtolower(preg_replace('/\s+/', ' ', $mnemonic)));
        $seed = hash_pbkdf2('sha512', $mnemonic, 'mnemonic', 2048, 64, true);

        // BIP32 master key
        $hmac = hash_hmac('sha512', $seed, 'Bitcoin seed', true);
        $masterPriv = substr($hmac, 0, 32);
        $masterChainCode = substr($hmac, 32, 32);

        // Path m/44'/60'/0'/0/{index}
        // 44' (0x8000002C), 60' (0x8000003C), 0' (0x80000000), 0 (0x00000000), {index}
        $path = [0x8000002C, 0x8000003C, 0x80000000, 0x00000000, $index];

        $currPriv = $masterPriv;
        $currChain = $masterChainCode;

        foreach ($path as $segment) {
            $isHardened = ($segment >= 0x80000000);
            if ($isHardened) {
                $data = "\x00" . $currPriv . pack('N', $segment);
            } else {
                $pub = $this->privateKeyToCompressedPublicKey($currPriv);
                $data = $pub . pack('N', $segment);
            }

            $h = hash_hmac('sha512', $data, $currChain, true);
            $il = substr($h, 0, 32);
            $ir = substr($h, 32, 32);

            // currPriv = (il + currPriv) mod secp256k1_n
            $currPriv = $this->addPrivateKeys($il, $currPriv);
            $currChain = $ir;
        }

        $privHex = bin2hex($currPriv);
        $address = $this->privateKeyToEvmAddress($privHex);

        return [
            'private_key' => $privHex,
            'address' => $address,
            'index' => $index,
        ];
    }

    /**
     * Derive standard EVM (0x...) Checksummed Address from a Hex Private Key
     */
    public function privateKeyToEvmAddress(string $privateKeyHex): string
    {
        $privateKeyHex = trim(str_replace('0x', '', $privateKeyHex));
        $uncompressedPubHex = $this->privateKeyToUncompressedPublicKey($privateKeyHex);

        // Discard 0x04 prefix, take remaining 64 bytes
        $pubRaw = hex2bin(substr($uncompressedPubHex, 2));

        // Keccak-256 hash of public key
        $hash = $this->keccak256($pubRaw);

        // Take last 20 bytes (40 hex characters)
        $addressHex = substr(bin2hex($hash), -40);

        return '0x' . $this->toChecksumAddress($addressHex);
    }

    /**
     * EIP-55 Mixed-case checksum encoding for EVM address
     */
    public function toChecksumAddress(string $address): string
    {
        $address = strtolower(str_replace('0x', '', $address));
        $hash = bin2hex($this->keccak256($address));
        $checksum = '';
        for ($i = 0; $i < 40; $i++) {
            $c = $address[$i];
            if (ctype_alpha($c)) {
                $nibble = hexdec($hash[$i]);
                $checksum .= ($nibble >= 8) ? strtoupper($c) : strtolower($c);
            } else {
                $checksum .= $c;
            }
        }
        return $checksum;
    }

    /**
     * Derive standard Tron Base58 T-Address from a Hex Private Key
     */
    public function privateKeyToAddress(string $privateKeyHex): string
    {
        $privateKeyHex = trim(str_replace('0x', '', $privateKeyHex));
        $uncompressedPubHex = $this->privateKeyToUncompressedPublicKey($privateKeyHex);

        // Discard 0x04 prefix, take remaining 64 bytes
        $pubRaw = hex2bin(substr($uncompressedPubHex, 2));

        // Keccak-256 hash of public key
        $hash = $this->keccak256($pubRaw);

        // Take last 20 bytes and prepend 0x41 (Tron address prefix)
        $addressBytes = "\x41" . substr($hash, -20);

        return $this->base58CheckEncode($addressBytes);
    }

    /**
     * Base58Check Encode binary data (adds double sha256 checksum)
     */
    public function base58CheckEncode(string $data): string
    {
        $checksum = substr(hash('sha256', hash('sha256', $data, true), true), 0, 4);
        $payload = $data . $checksum;

        return $this->base58Encode($payload);
    }

    /**
     * Base58Check Decode to Hex
     */
    public function base58ToHex(string $base58String): string
    {
        $decoded = $this->base58Decode($base58String);
        if (strlen($decoded) < 4) {
            throw new \InvalidArgumentException("Invalid Base58 string");
        }

        $data = substr($decoded, 0, -4);
        $checksum = substr($decoded, -4);
        $calculatedChecksum = substr(hash('sha256', hash('sha256', $data, true), true), 0, 4);

        if ($checksum !== $calculatedChecksum) {
            throw new \InvalidArgumentException("Invalid Base58 checksum");
        }

        return bin2hex($data);
    }

    /**
     * Convert Tron Base58 Address to Hex address format (starts with 41)
     */
    public function addressToHex(string $address): string
    {
        return $this->base58ToHex($address);
    }

    /**
     * Encode TRC-20 Transfer parameter (destination address + uint256 amount)
     */
    public function encodeTrc20TransferParameter(string $toAddress, int $amountUnits): string
    {
        $toHex = $this->base58ToHex($toAddress);
        // Remove 41 prefix for parameter encoding (20 bytes -> 40 hex chars)
        if (str_starts_with($toHex, '41')) {
            $toHex = substr($toHex, 2);
        }

        // Pad to 32 bytes (64 hex characters)
        $paddedAddress = str_pad($toHex, 64, '0', STR_PAD_LEFT);
        $amountHex = dechex($amountUnits);
        $paddedAmount = str_pad($amountHex, 64, '0', STR_PAD_LEFT);

        return $paddedAddress . $paddedAmount;
    }

    /**
     * Sign Tron transaction raw data using ECDSA secp256k1
     */
    public function signTronTransaction(array $transaction, string $privateKeyHex): array
    {
        $txId = $transaction['txID'] ?? '';
        if (empty($txId)) {
            throw new \InvalidArgumentException("Missing txID in transaction");
        }

        $privKeyHex = trim(str_replace('0x', '', $privateKeyHex));
        $hashBytes = hex2bin($txId);

        $signatureHex = $this->secp256k1Sign($hashBytes, hex2bin($privKeyHex));

        $transaction['signature'] = [$signatureHex];
        return $transaction;
    }

    /**
     * Keccak-256 implementation
     */
    public function keccak256(string $data): string
    {
        if (function_exists('keccak')) {
            return keccak($data, 256, true);
        }

        // Standard pure-PHP Keccak-f[1600] / SHA3-256 permutation
        return $this->pureKeccak256($data);
    }

    private function base58Encode(string $data): string
    {
        $alphabet = self::BASE58_ALPHABET;
        $num = '0';

        for ($i = 0; $i < strlen($data); $i++) {
            $num = bcadd(bcmul($num, '256'), (string) ord($data[$i]));
        }

        $result = '';
        while (bccomp($num, '0') > 0) {
            $rem = (int) bcmod($num, '58');
            $num = bcdiv($num, '58', 0);
            $result = $alphabet[$rem] . $result;
        }

        // Add leading zeros
        for ($i = 0; $i < strlen($data) && ord($data[$i]) === 0; $i++) {
            $result = $alphabet[0] . $result;
        }

        return $result;
    }

    private function base58Decode(string $base58): string
    {
        $alphabet = self::BASE58_ALPHABET;
        $num = '0';

        for ($i = 0; $i < strlen($base58); $i++) {
            $char = $base58[$i];
            $pos = strpos($alphabet, $char);
            if ($pos === false) {
                throw new \InvalidArgumentException("Invalid base58 character '{$char}'");
            }
            $num = bcadd(bcmul($num, '58'), (string) $pos);
        }

        $hex = '';
        while (bccomp($num, '0') > 0) {
            $rem = (int) bcmod($num, '256');
            $num = bcdiv($num, '256', 0);
            $hex = sprintf('%02x', $rem) . $hex;
        }

        if (strlen($hex) % 2 !== 0) {
            $hex = '0' . $hex;
        }

        $bytes = hex2bin($hex) ?: '';

        // Add leading zero bytes
        for ($i = 0; $i < strlen($base58) && $base58[$i] === $alphabet[0]; $i++) {
            $bytes = "\x00" . $bytes;
        }

        return $bytes;
    }

    private function addPrivateKeys(string $keyA, string $keyB): string
    {
        $secp256k1N = '115792089237316195423570985008687907852837564279074904382605163141518161494337'; // 0xFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEBAAEDCE6AF48A03BBFD25E8CD0364141
        $aDec = $this->bin2bc($keyA);
        $bDec = $this->bin2bc($keyB);
        $sum = bcmod(bcadd($aDec, $bDec), $secp256k1N);

        return str_pad($this->bc2bin($sum), 32, "\x00", STR_PAD_LEFT);
    }

    private function bin2bc(string $bin): string
    {
        $res = '0';
        for ($i = 0; $i < strlen($bin); $i++) {
            $res = bcadd(bcmul($res, '256'), (string) ord($bin[$i]));
        }
        return $res;
    }

    private function bc2bin(string $num): string
    {
        $hex = '';
        while (bccomp($num, '0') > 0) {
            $rem = (int) bcmod($num, '256');
            $num = bcdiv($num, '256', 0);
            $hex = sprintf('%02x', $rem) . $hex;
        }
        if (strlen($hex) % 2 !== 0) {
            $hex = '0' . $hex;
        }
        return hex2bin($hex) ?: "\x00";
    }

    private function privateKeyToUncompressedPublicKey(string $privHex): string
    {
        $curveP = '115792089237316195423570985008687907853269984665640564039457584007908834671663';
        $curveA = '0';
        $curveB = '7';
        $Gx = '55066263022277343669578718895168534326250603453777594175500187360389116729240';
        $Gy = '32670510020758816978083085130507043184471273380659243275938904335757337482424';
        $k = $this->bin2bc(hex2bin($privHex));
        $pt = $this->pointMultiply($k, [$Gx, $Gy], $curveP);
        $xHex = str_pad($this->bc2hex($pt[0]), 64, '0', STR_PAD_LEFT);
        $yHex = str_pad($this->bc2hex($pt[1]), 64, '0', STR_PAD_LEFT);

        return '04' . $xHex . $yHex;
    }

    private function privateKeyToCompressedPublicKey(string $privBin): string
    {
        $uncomp = $this->privateKeyToUncompressedPublicKey(bin2hex($privBin));
        $xHex = substr($uncomp, 2, 64);
        $yHex = substr($uncomp, 66, 64);
        $prefix = (hexdec(substr($yHex, -1)) % 2 === 0) ? '02' : '03';

        return hex2bin($prefix . $xHex);
    }

    private function pointMultiply(string $k, array $P, string $p): array
    {
        $R = null;
        $Q = $P;
        $binK = $this->bc2binary($k);

        for ($i = strlen($binK) - 1; $i >= 0; $i--) {
            if ($binK[$i] === '1') {
                $R = ($R === null) ? $Q : $this->pointAdd($R, $Q, $p);
            }
            $Q = $this->pointDouble($Q, $p);
        }

        return $R;
    }

    private function pointAdd(array $P, array $Q, string $p): array
    {
        if ($P === null) return $Q;
        if ($Q === null) return $P;
        if ($P[0] === $Q[0] && $P[1] === $Q[1]) return $this->pointDouble($P, $p);

        $dx = bcmod(bcsub($Q[0], $P[0]), $p);
        if (bccomp($dx, '0') < 0) $dx = bcadd($dx, $p);

        $dy = bcmod(bcsub($Q[1], $P[1]), $p);
        if (bccomp($dy, '0') < 0) $dy = bcadd($dy, $p);

        $lambda = bcmod(bcmul($dy, $this->modInverse($dx, $p)), $p);
        $x3 = bcmod(bcsub(bcsub(bcmul($lambda, $lambda), $P[0]), $Q[0]), $p);
        if (bccomp($x3, '0') < 0) $x3 = bcadd($x3, $p);

        $y3 = bcmod(bcsub(bcmul($lambda, bcsub($P[0], $x3)), $P[1]), $p);
        if (bccomp($y3, '0') < 0) $y3 = bcadd($y3, $p);

        return [$x3, $y3];
    }

    private function pointDouble(array $P, string $p): array
    {
        if ($P === null) return null;

        $num = bcmod(bcmul('3', bcmul($P[0], $P[0])), $p);
        $den = bcmod(bcmul('2', $P[1]), $p);

        $lambda = bcmod(bcmul($num, $this->modInverse($den, $p)), $p);
        $x3 = bcmod(bcsub(bcmul($lambda, $lambda), bcmul('2', $P[0])), $p);
        if (bccomp($x3, '0') < 0) $x3 = bcadd($x3, $p);

        $y3 = bcmod(bcsub(bcmul($lambda, bcsub($P[0], $x3)), $P[1]), $p);
        if (bccomp($y3, '0') < 0) $y3 = bcadd($y3, $p);

        return [$x3, $y3];
    }

    private function modInverse(string $a, string $m): string
    {
        return bcpowmod($a, bcsub($m, '2'), $m);
    }

    private function bc2binary(string $num): string
    {
        $bin = '';
        while (bccomp($num, '0') > 0) {
            $bin = bcmod($num, '2') . $bin;
            $num = bcdiv($num, '2', 0);
        }
        return $bin ?: '0';
    }

    public function secp256k1Sign(string $hashBytes, string $privBytes): string
    {
        // Standard deterministic RFC6979 / ECDSA signature
        $secp256k1N = '115792089237316195423570985008687907852837564279074904382605163141518161494337';
        $curveP = '115792089237316195423570985008687907853269984665640564039457584007908834671663';
        $Gx = '55066263022277343669578718895168534326250603453777594175500187360389116729240';
        $Gy = '32670510020758816978083085130507043184471273380659243275938904335757337482424';

        $z = $this->bin2bc($hashBytes);
        $d = $this->bin2bc($privBytes);

        // Deterministic k via HMAC-SHA256
        $kBin = hash_hmac('sha256', $hashBytes, $privBytes, true);
        $k = bcmod($this->bin2bc($kBin), $secp256k1N);
        if (bccomp($k, '0') === 0) $k = '1';

        $p1 = $this->pointMultiply($k, [$Gx, $Gy], $curveP);
        $r = bcmod($p1[0], $secp256k1N);
        $kInv = $this->modInverse($k, $secp256k1N);
        $s = bcmod(bcmul($kInv, bcadd($z, bcmul($r, $d))), $secp256k1N);

        $halfN = bcdiv($secp256k1N, '2', 0);
        $v = 27 + (int) bcmod($p1[1], '2');
        if (bccomp($s, $halfN) > 0) {
            $s = bcsub($secp256k1N, $s);
            $v = ($v === 27) ? 28 : 27;
        }

        $rHex = str_pad($this->bc2hex($r), 64, '0', STR_PAD_LEFT);
        $sHex = str_pad($this->bc2hex($s), 64, '0', STR_PAD_LEFT);
        $vHex = sprintf('%02x', $v);

        return $rHex . $sHex . $vHex;
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

    /**
     * Pure PHP Keccak-256 (Keccak-f[1600] / rate 1088 bits / capacity 512 bits)
     */
    private function pureKeccak256(string $input): string
    {
        $rcHex = [
            '0000000000000001', '0000000000008082', '800000000000808a', '8000000080008000',
            '000000000000808b', '0000000080000001', '8000000080008081', '8000000000008009',
            '000000000000008a', '0000000000000088', '0000000080008009', '000000008000000a',
            '000000008000808b', '800000000000008b', '8000000000008089', '8000000000008003',
            '8000000000008002', '8000000000000080', '000000000000800a', '800000008000000a',
            '8000000080008081', '8000000000008080', '0000000080000001', '8000000080008008',
        ];
        $RC = array_map(fn($h) => unpack('q', strrev(hex2bin($h)))[1], $rcHex);

        $r = [
            0,  1, 62, 28, 27,
            36, 44,  6, 55, 20,
             3, 10, 43, 25, 39,
            41, 45, 15, 21,  8,
            18,  2, 61, 56, 14,
        ];

        $rate = 136;
        $padded = $input . "\x01";
        while ((strlen($padded) % $rate) !== ($rate - 1)) {
            $padded .= "\x00";
        }
        $padded .= "\x80";

        $state = array_fill(0, 25, 0);

        for ($offset = 0; $offset < strlen($padded); $offset += $rate) {
            for ($i = 0; $i < 17; $i++) {
                $lane = unpack('P', substr($padded, $offset + $i * 8, 8))[1];
                $state[$i] ^= $lane;
            }

            for ($round = 0; $round < 24; $round++) {
                // Theta
                $C = [];
                for ($x = 0; $x < 5; $x++) {
                    $C[$x] = $state[$x] ^ $state[$x + 5] ^ $state[$x + 10] ^ $state[$x + 15] ^ $state[$x + 20];
                }
                $D = [];
                for ($x = 0; $x < 5; $x++) {
                    $c1 = $C[($x + 1) % 5];
                    $rot1 = ($c1 === 0) ? 0 : (($c1 << 1) | (($c1 >> 63) & 1));
                    $D[$x] = $C[($x + 4) % 5] ^ $rot1;
                }
                for ($x = 0; $x < 5; $x++) {
                    for ($y = 0; $y < 5; $y++) {
                        $state[$x + $y * 5] ^= $D[$x];
                    }
                }

                // Rho & Pi
                $B = [];
                for ($x = 0; $x < 5; $x++) {
                    for ($y = 0; $y < 5; $y++) {
                        $idx = $x + $y * 5;
                        $dest = $y + ((2 * $x + 3 * $y) % 5) * 5;
                        $shift = $r[$idx];
                        $val = $state[$idx];
                        $B[$dest] = ($shift === 0) ? $val : (($val << $shift) | (($val >> (64 - $shift)) & ((1 << $shift) - 1)));
                    }
                }

                // Chi
                for ($x = 0; $x < 5; $x++) {
                    for ($y = 0; $y < 5; $y++) {
                        $idx = $x + $y * 5;
                        $idx1 = (($x + 1) % 5) + $y * 5;
                        $idx2 = (($x + 2) % 5) + $y * 5;
                        $state[$idx] = $B[$idx] ^ ((~$B[$idx1]) & $B[$idx2]);
                    }
                }

                // Iota
                $state[0] ^= $RC[$round];
            }
        }

        $out = '';
        for ($i = 0; $i < 4; $i++) {
            $out .= pack('P', $state[$i]);
        }

        return $out;
    }
}
