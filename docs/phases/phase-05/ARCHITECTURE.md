# Phase 5 — Payment Infrastructure — Architecture

**Document Status:** DRAFT  
**Created:** 2026-08-15  
**Depends On:** `PDR.md`, Phase 4 architecture

---

## 1. CURRENT STATE (Phase 0–4)

### 1.1 Payment Components

| Component | Location | Role |
|-----------|----------|------|
| `PaymentGatewayInterface` | `app/Contracts/PaymentGatewayInterface.php` | 5-method contract: createCharge, verifyWebhook, refund, getStatus, provisionSubAccount |
| `ManualPayment` | `app/Payments/ManualPayment.php` | Stub — all calls return success |
| `PaymentServiceProvider` | `app/Providers/PaymentServiceProvider.php` | Binds ManualPayment as default |
| `config/payments.php` | config | `default_gateway: manual`, empty Xendit keys |
| `Payment` model | `app/Models/Payment.php` | Fields: method, amount, reference, idempotency_key, status, metadata, refund_amount, refund_status |
| `PaymentService` | `app/Services/PaymentService.php` | createForCheckout, addPayment, refundPayments — no gateway calls |
| `SaleController` | `app/Http/Controllers/SaleController.php` | checkout, addPayment, listPayments — all payments immediate success |
| Frontend `saleService` | `frontend/src/services/sale.ts` | checkout, addPayment, listPayments, listRefunds |

### 1.2 Limitations

- No real gateway integration — all payments are manual/synchronous
- No webhook handling
- No async payment lifecycle
- No settlement tracking
- No cash drawer management
- No platform fee model
- QRIS/card/bank_transfer methods exist in enum but have no gateway backing

---

## 2. TARGET STATE (Phase 5)

### 2.1 Component Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                         Frontend (React)                         │
│                                                                  │
│  ┌──────────┐  ┌──────────────┐  ┌───────────┐  ┌────────────┐ │
│  │ POS Page │  │ QRIS Modal   │  │ Cash      │  │ Payment    │ │
│  │ Checkout │  │ (QR display  │  │ Drawer    │  │ Dashboard  │ │
│  │          │  │  + poll)     │  │ UI        │  │            │ │
│  └────┬─────┘  └──────┬───────┘  └─────┬─────┘  └─────┬──────┘ │
│       │               │                │              │         │
│       └───────────────┴────────────────┴──────────────┘         │
│                              │                                   │
│                    saleService / paymentService                  │
└──────────────────────────────┬──────────────────────────────────┘
                               │ HTTPS (Authorization, X-Store-Id)
                               ▼
┌─────────────────────────────────────────────────────────────────┐
│                    Backend (Laravel API)                         │
│                                                                  │
│  ┌──────────────┐  ┌──────────────────┐  ┌──────────────────┐  │
│  │SaleController│  │PaymentGateway    │  │XenditWebhook     │  │
│  │              │  │Controller        │  │Controller        │  │
│  │ checkout     │  │ createCharge     │  │ (public route)   │  │
│  │ addPayment   │  │ getChargeStatus  │  │ verify token     │  │
│  │ cancel       │  │ refund           │  │ idempotent       │  │
│  └──────┬───────┘  └────────┬─────────┘  └────────┬─────────┘  │
│         │                   │                     │             │
│         ▼                   ▼                     ▼             │
│  ┌──────────────┐  ┌──────────────────┐  ┌──────────────────┐  │
│  │PaymentService│  │PaymentGateway    │  │WebhookProcessor  │  │
│  │              │  │Interface         │  │                  │  │
│  │ createFor    │  │  ┌────────────┐  │  │ process event    │  │
│  │  Checkout    │  │  │ ManualPay  │  │  │ update payment   │  │
│  │ addPayment   │  │  │ (fallback) │  │  │ update sale      │  │
│  │ refundPay    │  │  └────────────┘  │  └──────────────────┘  │
│  │ ments        │  │  ┌────────────┐  │                        │
│  │              │  │  │XenditPay   │  │  ┌──────────────────┐  │
│  └──────┬───────┘  │  │ Gateway    │  │  │SettlementService │  │
│         │          │  └─────┬──────┘  │  │ (scheduled job)  │  │
│         │          └────────┼─────────┘  └──────────────────┘  │
│         │                   │                     │             │
│         ▼                   ▼                     ▼             │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │                    Database (MySQL)                       │   │
│  │  payments · payment_gateway_accounts · payment_webhooks  │   │
│  │  payment_settlements · cash_drawer_sessions · tenants    │   │
│  └──────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
                               │
                               ▼ (HTTPS)
┌─────────────────────────────────────────────────────────────────┐
│                    Xendit xenPlatform                            │
│                                                                  │
│  Master Account                                                  │
│  ├── Tenant A Sub-account (for-user-id)                          │
│  ├── Tenant B Sub-account (for-user-id)                          │
│  └── Tenant N Sub-account (for-user-id)                          │
│                                                                  │
│  APIs:                                                           │
│  ├── POST /v2/accounts (provision sub-account)                   │
│  ├── POST /payment_requests (create QRIS/VA/Card charge)         │
│  ├── GET  /payment_requests/{id} (get status)                    │
│  ├── POST /refunds (refund)                                      │
│  ├── GET  /transactions (settlement/report data)                 │
│  └── POST callback → webhook (x-callback-token)                  │
└─────────────────────────────────────────────────────────────────┘
```

### 2.2 New Backend Components

| Component | Location | Responsibility |
|-----------|----------|----------------|
| `XenditPayment` | `app/Payments/XenditPayment.php` | Implements `PaymentGatewayInterface` with real Xendit HTTP calls |
| `XenditWebhookController` | `app/Http/Controllers/XenditWebhookController.php` | Public endpoint, token verify, idempotent processing |
| `PaymentGatewayController` | `app/Http/Controllers/PaymentGatewayController.php` | Gateway account management, charge creation, refund |
| `CashDrawerController` | `app/Http/Controllers/CashDrawerController.php` | Cash drawer session CRUD |
| `PaymentController` | `app/Http/Controllers/PaymentController.php` | Payment dashboard: list, show, summary |
| `WebhookProcessor` | `app/Services/WebhookProcessor.php` | Process verified webhook events → update payment/sale |
| `SettlementService` | `app/Services/SettlementService.php` | Fetch Xendit settlement data, reconciliation |
| `CashDrawerService` | `app/Services/CashDrawerService.php` | Open/close/reconcile cash drawer |
| `SubAccountService` | `app/Services/SubAccountService.php` | Provision Xendit sub-account per tenant |

### 2.3 New Models

| Model | Table | Traits |
|-------|-------|--------|
| `PaymentGatewayAccount` | `payment_gateway_accounts` | BelongsToTenant |
| `PaymentWebhook` | `payment_webhooks` | — (no tenant scope — webhooks may arrive before tenant resolution) |
| `PaymentSettlement` | `payment_settlements` | BelongsToTenant |
| `CashDrawerSession` | `cash_drawer_sessions` | BelongsToTenant |

### 2.4 Modified Components

| Component | Change |
|-----------|--------|
| `Payment` model | Add: gateway_transaction_id, gateway_status, gateway_response, settlement_amount, platform_fee, net_amount, settled_at, expires_at, gateway_account_id |
| `PaymentService` | Enhance: async lifecycle, gateway integration for non-cash methods |
| `PaymentServiceProvider` | Bind XenditPayment when gateway configured, ManualPayment as fallback |
| `config/payments.php` | Add: api_version, base_url, for-user-id support, fee_rule config |
| `SaleController` | checkout: cash stays sync, gateway methods create pending payment |
| `tenants` table | Add: xendit_user_id, xendit_fee_rule_id |

### 2.5 New Frontend Components

| Component | Location | Responsibility |
|-----------|----------|----------------|
| `QRISPaymentModal` | `components/pos/QRISPaymentModal.tsx` | Display QR code, poll status, show success/failure |
| `CashDrawerPage` | `pages/CashDrawerPage.tsx` | Open/close/reconcile drawer |
| `PaymentDashboardPage` | `pages/PaymentDashboardPage.tsx` | Transaction list, settlement status, fee breakdown |
| `paymentService` | `services/payment.ts` | API calls for gateway charge, refund, settlements, cash drawer |

---

## 3. GATEWAY ABSTRACTION LAYER

### 3.1 Interface (unchanged, extended)

```php
interface PaymentGatewayInterface
{
    public function createCharge(array $paymentData): array;
    public function verifyWebhook(string $payload, array $headers): array;
    public function refund(string $gatewayTransactionId, float $amount, string $reason): array;
    public function getStatus(string $gatewayTransactionId): array;
    public function provisionSubAccount(array $tenantInfo): array;
}
```

### 3.2 XenditPayment Implementation

The `XenditPayment` class communicates with Xendit API via HTTP. Key design decisions:

- **Base URL configurable:** `config('payments.gateways.xendit.base_url')` — defaults to `https://api.xendit.co`
- **API versioning:** `config('payments.gateways.xendit.api_version')` — sent as `api-version` header
- **Authentication:** Basic auth with API key (`XENDIT_API_KEY` env)
- **Sub-account routing:** `for-user-id` header from `tenant.xendit_user_id`
- **Fee rules:** `with-fee-rule` header from `tenant.xendit_fee_rule_id`
- **Idempotency:** `Idempotency-Key` header from internal payment reference

### 3.3 Payment Method Routing

```
PaymentService::createForCheckout()
├── method = cash → ManualPayment (immediate success)
├── method = qris → XenditPayment::createCharge (async, QR string returned)
├── method = card → XenditPayment::createCharge (async, 3DS redirect)
├── method = bank_transfer → XenditPayment::createCharge (async, VA number)
└── fallback → ManualPayment
```

### 3.4 Gateway Selection Logic

```php
// PaymentServiceProvider
$this->app->bind(PaymentGatewayInterface::class, function ($app) {
    $gateway = config('payments.default_gateway', 'manual');
    return match ($gateway) {
        'xendit' => new XenditPayment(
            config('payments.gateways.xendit.api_key'),
            config('payments.gateways.xendit.base_url'),
            config('payments.gateways.xendit.api_version'),
        ),
        default => new ManualPayment(),
    };
});
```

---

## 4. WEBHOOK ARCHITECTURE

### 4.1 Endpoint

```
POST /api/v1/webhooks/xendit
```

- **No auth middleware** — public endpoint
- **Token verification** — `x-callback-token` header compared with `XENDIT_WEBHOOK_TOKEN`
- **Idempotency** — `payment_webhooks(gateway, event_id)` unique constraint
- **Payload logging** — all webhooks stored in `payment_webhooks` table

### 4.2 Processing Pipeline

```
1. Receive POST → XenditWebhookController::handle()
2. Extract x-callback-token from headers
3. Compare with config('payments.gateways.xendit.webhook_token')
4. If mismatch → 401 Unauthorized
5. Parse payload → extract event_id, event_type
6. Check payment_webhooks for existing (gateway, event_id) → if exists, return 200 (idempotent)
7. Store webhook in payment_webhooks (verified=true)
8. Dispatch WebhookProcessor::process()
9. Return 200 OK (async processing)
```

### 4.3 WebhookProcessor

```
WebhookProcessor::process(webhook)
├── event_type = "payment.capture" (SUCCEEDED)
│   ├── Find Payment by gateway_transaction_id
│   ├── Lock Payment row
│   ├── Update: status=success, gateway_status=SUCCEEDED
│   ├── Update Sale: payment_status=paid (or partial)
│   ├── Trigger: inventory deduction, loyalty points
│   └── Mark webhook processed=true
├── event_type = "payment.failure" (FAILED)
│   ├── Find Payment by gateway_transaction_id
│   ├── Update: status=failed, gateway_status=FAILED
│   └── Mark webhook processed=true
├── event_type = "payment.authorization" (AUTHORIZED)
│   ├── Find Payment by gateway_transaction_id
│   ├── Update: gateway_status=AUTHORIZED (awaiting capture)
│   └── Mark webhook processed=true
└── event_type = "account.activated" (xenPlatform)
    ├── Find PaymentGatewayAccount by gateway_account_id
    ├── Update: status=active, kyc_status=passed
    └── Mark webhook processed=true
```

---

## 5. SETTLEMENT ARCHITECTURE

### 5.1 Data-Driven Approach

Settlement tracking is based on Xendit transaction/report data, not a fixed T+2 assumption:

1. **Scheduled job** (`php artisan settlements:sync`) runs daily
2. For each tenant with active gateway account:
   - Fetch transactions from Xendit `/transactions` endpoint
   - Match with internal `payments` by `gateway_transaction_id`
   - Create/update `payment_settlements` records
   - Update `payments.settled_at`, `settlement_amount`, `platform_fee`, `net_amount`
3. **Reconciliation report** compares:
   - Internal payment totals vs Xendit settlement totals
   - Discrepancies flagged for manual review

### 5.2 Reconciliation Service

```
SettlementService::reconcile(tenantId, dateRange)
├── Fetch internal payments (gateway, succeeded, in date range)
├── Fetch Xendit settlement data for same period
├── Match by gateway_transaction_id
├── Categories:
│   ├── MATCHED (amount matches)
│   ├── AMOUNT_MISMATCH (settlement ≠ payment amount)
│   ├── MISSING_SETTLEMENT (payment succeeded but no settlement)
│   └── MISSING_PAYMENT (settlement exists but no internal payment)
└── Return reconciliation report
```

---

## 6. CASH DRAWER ARCHITECTURE

### 6.1 Session Lifecycle

```
OPEN → CLOSED → RECONCILED
```

- **Open:** Cashier starts shift, counts opening cash
- **Closed:** Cashier ends shift, counts closing cash, system calculates expected
- **Reconciled:** Manager reviews difference, approves/rejects

### 6.2 Expected Amount Calculation

```
expected_amount = opening_amount
                + SUM(cash payments received during session)
                - SUM(cash refunds during session)
                - SUM(cash payouts/expenses during session)
```

---

## 7. DATA FLOW SUMMARY

### 7.1 QRIS Payment (end-to-end)

```
POS Frontend
  │ POST /sales/{id}/gateway-charge { method: "qris", amount: 50000 }
  ▼
PaymentGatewayController
  │ → PaymentService::createGatewayCharge()
  │   → XenditPayment::createCharge()
  │     → POST {base_url}/payment_requests
  │       Headers: Authorization, for-user-id, with-fee-rule, Idempotency-Key, api-version
  │       Body: { reference_id, type: "PAY", country: "ID", currency: "IDR",
  │               request_amount, channel_code: "QRIS",
  │               payment_method: { type: "QR_CODE", qr_code: { channel_code: "QRIS" } } }
  │     ← Response: { payment_request_id, status: "REQUIRES_ACTION",
  │                    actions: [{ type: "QR_DISPLAY", value: qr_string }] }
  │   → Save Payment: status=pending, gateway_transaction_id, metadata={qr_string}
  │ ← Return: { payment_id, gateway_transaction_id, qr_string, status: "pending" }
  ▼
POS Frontend
  │ Display QR code in QRISPaymentModal
  │ Poll: GET /sales/{id}/gateway-charge/{chargeId} every 3s
  ▼
Customer scans QR → pays via e-wallet/banking app
  ▼
Xendit
  │ POST /api/v1/webhooks/xendit
  │   Headers: x-callback-token
  │   Body: { event: "payment.capture", data: { status: "SUCCEEDED", ... } }
  ▼
XenditWebhookController
  │ → Verify token
  │ → Check idempotency
  │ → Store webhook
  │ → WebhookProcessor::process()
  │   → Find Payment by gateway_transaction_id
  │   → Lock + update: status=success, gateway_status=SUCCEEDED
  │   → Update Sale: payment_status=paid
  │ ← Return 200 OK
  ▼
POS Frontend (next poll)
  │ GET /sales/{id}/gateway-charge/{chargeId}
  │ ← Response: { status: "success" }
  │ → Show success screen → print receipt
```

### 7.2 Cash Payment (with cash drawer)

```
POS Frontend
  │ POST /sales/checkout { payments: [{ method: "cash", amount: 50000 }] }
  ▼
SaleController::checkout()
  │ → SaleService::checkout()
  │   → PaymentService::createForCheckout()
  │     → method=cash → immediate status=success
  │ → Return Sale (201)
  ▼
Cash Drawer (automatic)
  │ → CashDrawerService::recordCashPayment()
  │   → Add to active session's running total
  ▼
End of shift
  │ POST /cash-drawer/{id}/close { closing_amount: 75000 }
  │ → CashDrawerService::close()
  │   → Calculate expected_amount
  │   → Record difference
  ▼
Manager review
  │ GET /cash-drawer/{id}
  │ → Review difference → approve/reject
```

---

## 8. CONFIGURATION

### 8.1 Updated `config/payments.php`

```php
return [
    'default_gateway' => env('PAYMENT_GATEWAY', 'manual'),

    'gateways' => [
        'manual' => [
            'driver' => 'manual',
        ],
        'xendit' => [
            'driver' => 'xendit',
            'api_key' => env('XENDIT_API_KEY', ''),
            'webhook_token' => env('XENDIT_WEBHOOK_TOKEN', ''),
            'base_url' => env('XENDIT_BASE_URL', 'https://api.xendit.co'),
            'api_version' => env('XENDIT_API_VERSION', '2024-11-11'),
        ],
    ],

    'settlement' => [
        'sync_enabled' => env('XENDIT_SETTLEMENT_SYNC', false),
        'sync_schedule' => env('XENDIT_SETTLEMENT_SCHEDULE', 'daily'),
    ],
];
```

### 8.2 Environment Variables

```env
PAYMENT_GATEWAY=xendit
XENDIT_API_KEY=xnd_development_xxxxx
XENDIT_WEBHOOK_TOKEN=xxxxx
XENDIT_BASE_URL=https://api.xendit.co
XENDIT_API_VERSION=2024-11-11
XENDIT_SETTLEMENT_SYNC=true
```

---

*End of Phase 5 Architecture*
