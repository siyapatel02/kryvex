# Kryvex Real Custody Wallet Architecture & Workflow

This document specifies the complete custodial wallet architecture for **Kryvex**, detailing the end-to-end flow for deposits, on-chain confirmations, vault sweeps, and admin-approved withdrawals.

---

## 🏛️ Product Architecture Model

Kryvex is a **Custodial Exchange** (similar to PrimeXBT / Binance) utilizing a double-entry ledger database for instant, zero-gas internal transfers and trading.

```
flowchart LR
    externalUser[User External Wallet] -->|TRC20 USDT| addrB[Unique User HD Address B]
    addrB -->|19 Confirmations| ledger[Kryvex Main Wallet Ledger]
    addrB -.->|DEPOSIT_SWEEP_ENABLED| vaultC[Company Hot Vault C]
    vaultC -->|Admin Approval & Broadcast| externalUser
    ledger -.->|Does Not Auto-Fund| binance[Binance Omnibus House Wallet]
    realOrder[Kryvex Real Spot/Futures Orders] --> binance
```

---

## 🔄 Three Core Integration Phases

### Phase 1 — Deposit & Auto-Credit (Ops + Monitor)

1. **User Address Derivation**:
   - Each registered user receives a deterministic HD deposit address derived from `DEPOSIT_HD_MNEMONIC` at path `m/44'/195'/0'/0/{user_index}` (Tron Coin Type 195).
   - Deposit addresses and their encrypted private keys are indexed in `user_deposit_addresses`.

2. **Blockchain Monitoring**:
   - The daemon `php artisan kryvex:wallet-monitor` queries TronGrid / Tron nodes for incoming TRC-20 transfers matching user deposit addresses.
   - When a transfer reaches **19 on-chain block confirmations**, the system:
     - Atomically increments the user's `real_main_balance` and `crypto_balances.USDT`.
     - Creates a `wallet_ledgers` credit entry.
     - Records the deposit in `wallet_deposits` with `sweep_status = 'pending'`.
   - **Idempotency Guarantee**: Checked via unique `tx_hash` to prevent double-crediting.

### Phase 2 — Vault Sweep (Ops + Config)

1. **Safety Check**:
   - Before executing any sweep, the service derives the Tron address from `WITHDRAW_PRIVATE_KEY` and asserts that it strictly matches `WITHDRAW_FROM_ADDRESS` (Vault C). If there is any mismatch, sweeps are immediately aborted.

2. **Sweep Execution**:
   - When `DEPOSIT_SWEEP_ENABLED=true`, `php artisan kryvex:wallet-sweep` collects funds from user deposit addresses (`Address B`) and transfers them to `Vault C`.
   - **Crucial Rule**: Sweeping funds into the company vault **does NOT modify the user's ledger balance** (the user was already credited in Phase 1).

### Phase 3 — Withdrawals (Ops + Admin Approval)

1. **Immediate Ledger Hold**:
   - When a user submits a withdrawal request, the total amount (net amount + fees) is immediately deducted from the available balance and held in `crypto_pending_balances` / `demo_withdrawal_locked_balance`.
   - Prevents double-spending or concurrent trading while payout is in flight.

2. **Admin Approval & On-Chain Broadcast**:
   - Kryvex admin reviews the withdrawal request in the Admin Panel (`/admin/wallet/withdrawals`).
   - If `WITHDRAW_ONCHAIN_ENABLED=true`, approving the request executes an on-chain smart contract call from `Vault C` to the user's external destination address.
   - The on-chain transaction hash (`on_chain_tx_hash`) is recorded and displayed.

---

## 🔐 Environment Configuration

Set these company custody secrets in `.env`:

```env
# Master HD Mnemonic for deriving unique user deposit addresses (Tron coin type 195)
DEPOSIT_HD_MNEMONIC="twelve word secret mnemonic phrase ..."

# Tron Network: 'mainnet', 'nile', 'shasta'
TRON_NETWORK=mainnet

# Optional TronGrid API Key (prevents rate limiting)
TRONGRID_API_KEY="your-trongrid-api-key"

# TRC-20 USDT Smart Contract Addresses
DEPOSIT_USDT_CONTRACT="TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t"
WITHDRAW_USDT_CONTRACT="TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t"

# Company Hot Vault C (Destination of sweeps & source of payouts)
WITHDRAW_FROM_ADDRESS="T..."
WITHDRAW_PRIVATE_KEY="..."

# Operational Switches
DEPOSIT_ONCHAIN_ENABLED=true
DEPOSIT_SWEEP_ENABLED=false
WITHDRAW_ONCHAIN_ENABLED=false

# Confirmations & Polling
TRON_REQUIRED_CONFIRMATIONS=19
TRON_POLL_INTERVAL_SECONDS=10
```

---

## 🛠️ CLI Commands & Operations

- **Run Deposit Monitor Daemon**:
  ```bash
  php artisan kryvex:wallet-monitor
  ```
- **Run Single Monitor Check**:
  ```bash
  php artisan kryvex:wallet-monitor --once
  ```
- **Sweep Deposit Addresses to Vault C (Dry-run)**:
  ```bash
  php artisan kryvex:wallet-sweep --dry-run
  ```
- **Execute Sweep to Vault C**:
  ```bash
  php artisan kryvex:wallet-sweep
  ```
