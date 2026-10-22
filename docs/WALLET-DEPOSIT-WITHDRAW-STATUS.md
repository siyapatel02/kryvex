# Kryvex Custody Wallet Status & Safety Matrix

Status report for Kryvex Custodial Real Wallet implementation (Phases 1, 2, and 3).

---

## 📊 Status Matrix

| Component | Status | Implementation File | Details |
| :--- | :--- | :--- | :--- |
| **HD Mnemonic Derivation** | ✅ Operational | `app/Services/TronCustodyService.php` | Derives Tron Coin Type 195 (`m/44'/195'/0'/0/{index}`) with Base58Check and Keccak-256. |
| **Unique User Deposit Address B** | ✅ Operational | `app/Http/Controllers/Api/WalletController.php` | Assigns and returns user's unique HD TRC-20 deposit address in `/api/wallet`. |
| **On-Chain Deposit Monitor** | ✅ Operational | `app/Console/Commands/KryvexWalletMonitorCommand.php` | Polls TronGrid, enforces 19 confirmations, atomically credits ledger, records deposit with unique tx_hash. |
| **Vault Key Matching Check** | ✅ Operational | `app/Services/TronCustodyService.php` | Validates `WITHDRAW_FROM_ADDRESS` strictly equals key-derived address from `WITHDRAW_PRIVATE_KEY`. |
| **Sweep Address B ➔ Vault C** | ✅ Operational | `app/Console/Commands/KryvexWalletSweepCommand.php` | Moves funds to Vault C when `DEPOSIT_SWEEP_ENABLED=true` without altering user ledger balance. |
| **Instant Ledger Hold on Withdraw** | ✅ Operational | `app/Http/Controllers/Api/WalletController.php` | Deducts from available balance and adds to locked balance immediately upon request submission. |
| **Admin Payout Broadcast** | ✅ Operational | `app/Http/Controllers/Admin/AdminWalletController.php` | Broadcasts TRC-20 USDT payout from Vault C upon admin approval when `WITHDRAW_ONCHAIN_ENABLED=true`. |
| **Binance House Wallet Isolation** | ✅ Isolated | Core Ledger Architecture | User deposits do not auto-fund Binance; spot/futures hedging remains isolated. |
| **Razorpay INR Rail** | ✅ Isolated | `RazorpayService.php` | INR fiat rail operates independently of crypto custody rails. |

---

## 🔒 Security Hardening Guarantees

1. **No Double Credits**:
   - Every deposit transaction is uniquely keyed by `tx_hash` in `wallet_deposits`. Duplicate blockchain notifications are discarded.
2. **Double-Spend Prevention (Withdrawals in Flight)**:
   - When a user submits a withdrawal, the funds are immediately locked. If the user creates orders, opens positions, or requests another withdrawal, the locked funds cannot be accessed.
3. **Vault Mismatch Safeguard**:
   - If someone accidentally configures a mismatched Vault C address and private key, both Sweeps and Withdrawals immediately fail-safe and refuse to broadcast.
4. **Idempotent Sweep**:
   - The user's ledger balance is only credited on deposit confirmation. Sweeps from Address B to Vault C do not mutate user ledger balances.
