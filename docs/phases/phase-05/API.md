# Phase 5 — Payment Infrastructure — API Specification

**Document Status:** DRAFT  
**Created:** 2026-08-15  
**Depends On:** `PDR.md`, `ARCHITECTURE.md`, `FLOW.md`

---

## 1. BASE URL

All endpoints are prefixed with `/api/v1`.

---

## 2. PUBLIC WEBHOOK ENDPOINT

### 2.1 Receive Xendit Webhook

**URL:** `POST /webhooks/xendit`

**Auth:** None — public endpoint. Verified via `x-callback-token` header.

**Headers:**

| Header | Description |
|--------|-------------|
| `x-callback-token` | Xendit webhook verification token |
| `Content-Type` | `application/json` |

**Request Body (example — `payment.capture`):**

```json
{
  "event": "payment.capture",
  "business_id": "6094fa76c2fd53701b8e079c",
  "created": "2026-08-15T10:30:00.000Z",
  "data": {
    "payment_id": "py-1fdaf346-dd2e-4b6c-b938-124c7167a822",
    "business_id": "6094fa76c2fd53701b8e079c",
    "status": "SUCCEEDED",
    "payment_request_id": "pr-1fdaf346-dd2e-4b6c-b938-124c7167a822",
    "request_amount": "50000",
    "customer_id": null,
    "channel_code": "QRIS",
    "country": "ID",
    "currency": "IDR",
    "reference_id": "SALE-20260815-0001",
    "description": "POS payment",
    "type": "SINGLE_PAYMENT",
    "created": "2026-08-15T10:30:00.000Z",
    "updated": "2026-08-15T10:30:00.000Z"
  }
}
```

**Response:**

| Status | Body | Meaning |
|--------|------|---------|
| 200 | `{ "message": "OK" }` | Webhook received (verified or idempotent) |
| 401 | `{ "message": "Unauthorized" }` | Invalid `x-callback-token` |
| 422 | `{ "message": "Invalid payload" }` | Missing required fields |

**Idempotency:**
- Webhooks are identified by `event_id` derived from `business_id + data.payment_id + event`
- Duplicate `event_id` returns 200 immediately without re-processing

---

## 3. PAYMENT GATEWAY ACCOUNT ENDPOINTS

### 3.1 Get Tenant Gateway Account

**URL:** `GET /payment-gateway/account`

**Auth:** `auth:sanctum` + `permission:payments.view`

**Response 200:**

```json
{
  "data": {
    "id": 1,
    "gateway": "xendit",
    "status": "active",
    "kyc_status": "passed",
    "gateway_account_id": "usr_xxxxx",
    "capabilities": ["qris", "virtual_account", "cards"],
    "activated_at": "2026-08-15T10:00:00.000000Z",
    "created_at": "2026-08-15T09:00:00.000000Z",
    "updated_at": "2026-08-15T10:00:00.000000Z"
  }
}
```

---

### 3.2 Provision Xendit Sub-account

**URL:** `POST /payment-gateway/provision`

**Auth:** `auth:sanctum` + `permission:payments.gateway_config`

**Request Body:**

```json
{
  "business_name": "Tenant A",
  "business_email": "owner@tenanta.com",
  "business_type": "restaurant"
}
```

**Response 201:**

```json
{
  "data": {
    "id": 1,
    "gateway": "xendit",
    "status": "pending",
    "kyc_status": "none",
    "gateway_account_id": "usr_xxxxx",
    "message": "Sub-account provisioned. Awaiting KYC/capability activation."
  }
}
```

**Response 422:**

```json
{ "message": "Gateway account already provisioned" }
```

---

### 3.3 List Settlements

**URL:** `GET /payment-gateway/settlements`

**Auth:** `auth:sanctum` + `permission:payments.reconcile`

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `date_from` | date | no | Filter start date |
| `date_to` | date | no | Filter end date |
| `page` | int | no | Page number (default 1) |
| `per_page` | int | no | Items per page (default 20, max 100) |

**Response 200:**

```json
{
  "data": [
    {
      "id": 1,
      "settlement_id": "settle_xxxxx",
      "gross_amount": "100000.00",
      "platform_fee": "2000.00",
      "net_amount": "98000.00",
      "settled_at": "2026-08-16T02:00:00.000000Z",
      "status": "settled",
      "payment_id": 42
    }
  ],
  "current_page": 1,
  "last_page": 1,
  "per_page": 20,
  "total": 1
}
```

---

### 3.4 Run Reconciliation

**URL:** `POST /payment-gateway/reconcile`

**Auth:** `auth:sanctum` + `permission:payments.reconcile`

**Request Body:**

```json
{
  "date_from": "2026-08-15",
  "date_to": "2026-08-15"
}
```

**Response 200:**

```json
{
  "data": {
    "period": { "from": "2026-08-15", "to": "2026-08-15" },
    "internal_total": "100000.00",
    "xendit_total": "100000.00",
    "matched_count": 5,
    "mismatched_count": 0,
    "missing_settlement_count": 0,
    "missing_payment_count": 0,
    "mismatches": []
  }
}
```

---

## 4. GATEWAY CHARGE ENDPOINTS

### 4.1 Create Gateway Charge

**URL:** `POST /sales/{id}/gateway-charge`

**Auth:** `auth:sanctum` + `permission:payments.manage`

**Route Parameter:** `id` (int) — Sale ID

**Request Body:**

```json
{
  "method": "qris",
  "amount": 50000,
  "idempotency_key": "sale-123-qris-20260815-001"
}
```

**Allowed methods:** `qris`, `card`, `bank_transfer` (Virtual Account)

**Response 201 — QRIS:**

```json
{
  "data": {
    "id": 42,
    "sale_id": 123,
    "payment_method": "qris",
    "amount": "50000.00",
    "gateway_transaction_id": "pr-xxxxx",
    "gateway_status": "REQUIRES_ACTION",
    "status": "pending",
    "qr_string": "00020101021126580014ID.X...",
    "expires_at": "2026-08-15T10:45:00.000000Z",
    "metadata": {
      "qr_string": "00020101021126580014ID.X...",
      "actions": [
        { "type": "QR_DISPLAY", "value": "000201..." }
      ]
    },
    "created_at": "2026-08-15T10:30:00.000000Z",
    "updated_at": "2026-08-15T10:30:00.000000Z"
  }
}
```

**Response 201 — Virtual Account:**

```json
{
  "data": {
    "id": 43,
    "sale_id": 123,
    "payment_method": "bank_transfer",
    "amount": "50000.00",
    "gateway_transaction_id": "pr-xxxxx",
    "gateway_status": "REQUIRES_ACTION",
    "status": "pending",
    "metadata": {
      "virtual_account_number": "8812345678",
      "bank_code": "MANDIRI"
    },
    "created_at": "2026-08-15T10:30:00.000000Z",
    "updated_at": "2026-08-15T10:30:00.000000Z"
  }
}
```

**Response 422:**

```json
{ "message": "Gateway not active for tenant" }
```

---

### 4.2 Get Gateway Charge Status

**URL:** `GET /sales/{id}/gateway-charge/{chargeId}`

**Auth:** `auth:sanctum` + `permission:payments.view`

**Route Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | int | Sale ID |
| `chargeId` | int | Payment ID |

**Response 200:**

```json
{
  "data": {
    "id": 42,
    "sale_id": 123,
    "payment_method": "qris",
    "amount": "50000.00",
    "gateway_transaction_id": "pr-xxxxx",
    "gateway_status": "SUCCEEDED",
    "status": "success",
    "metadata": {
      "qr_string": "00020101021126580014ID.X..."
    },
    "created_at": "2026-08-15T10:30:00.000000Z",
    "updated_at": "2026-08-15T10:30:05.000000Z"
  }
}
```

---

## 5. PAYMENT REFUND ENDPOINT

### 5.1 Refund Payment

**URL:** `POST /payments/{id}/refund`

**Auth:** `auth:sanctum` + `permission:payments.refund`

**Request Body:**

```json
{
  "amount": 25000,
  "reason": "Customer request"
}
```

**Response 201:**

```json
{
  "data": {
    "refund_id": "rfd-xxxxx",
    "status": "success",
    "amount": "25000.00",
    "payment_id": 42,
    "payment_refund_status": "partial"
  }
}
```

**Response 422:**

```json
{ "message": "Refund amount exceeds payment amount" }
```

---

## 6. PAYMENT DASHBOARD ENDPOINTS

### 6.1 List Payments

**URL:** `GET /payments`

**Auth:** `auth:sanctum` + `permission:payments.view`

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `page` | int | Page number |
| `per_page` | int | Items per page |
| `method` | string | Filter by `cash`, `qris`, `card`, `bank_transfer` |
| `status` | string | Filter by `success`, `pending`, `failed`, `refunded` |
| `date_from` | date | Start date |
| `date_to` | date | End date |
| `store_id` | int | Filter by store |
| `sale_id` | int | Filter by sale |

**Response 200:**

```json
{
  "data": [
    {
      "id": 42,
      "sale_id": 123,
      "payment_method": "qris",
      "amount": "50000.00",
      "status": "success",
      "gateway_transaction_id": "pr-xxxxx",
      "gateway_status": "SUCCEEDED",
      "platform_fee": "1000.00",
      "net_amount": "49000.00",
      "settled_at": "2026-08-16T02:00:00.000000Z",
      "payment_date": "2026-08-15T10:30:00.000000Z"
    }
  ],
  "current_page": 1,
  "last_page": 1,
  "per_page": 20,
  "total": 1
}
```

---

### 6.2 Show Payment

**URL:** `GET /payments/{id}`

**Auth:** `auth:sanctum` + `permission:payments.view`

**Response 200:**

```json
{
  "data": {
    "id": 42,
    "sale_id": 123,
    "payment_method": "qris",
    "amount": "50000.00",
    "status": "success",
    "gateway_transaction_id": "pr-xxxxx",
    "gateway_status": "SUCCEEDED",
    "gateway_response": { ... },
    "metadata": { "qr_string": "..." },
    "platform_fee": "1000.00",
    "net_amount": "49000.00",
    "settled_at": "2026-08-16T02:00:00.000000Z",
    "payment_date": "2026-08-15T10:30:00.000000Z"
  }
}
```

---

### 6.3 Payment Summary

**URL:** `GET /payments/summary`

**Auth:** `auth:sanctum` + `permission:payments.view`

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `date_from` | date | Start date |
| `date_to` | date | End date |
| `store_id` | int | Filter by store |

**Response 200:**

```json
{
  "data": {
    "total_payments": 10,
    "total_amount": "500000.00",
    "success_amount": "480000.00",
    "pending_amount": "20000.00",
    "failed_amount": "0.00",
    "refunded_amount": "0.00",
    "total_fees": "10000.00",
    "net_settled": "470000.00",
    "by_method": {
      "cash": { "count": 4, "amount": "200000.00" },
      "qris": { "count": 6, "amount": "300000.00" }
    }
  }
}
```

---

## 7. CASH DRAWER ENDPOINTS

### 7.1 List Cash Drawer Sessions

**URL:** `GET /cash-drawer/sessions`

**Auth:** `auth:sanctum` + `permission:payments.cash_drawer`

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `store_id` | int | Filter by store |
| `status` | string | Filter by `open`, `closed`, `reconciled` |
| `page` | int | Page number |
| `per_page` | int | Items per page |

**Response 200:**

```json
{
  "data": [
    {
      "id": 1,
      "store_id": 2,
      "user_id": 3,
      "opening_amount": "100000.00",
      "closing_amount": "350000.00",
      "expected_amount": "345000.00",
      "difference": "5000.00",
      "status": "closed",
      "opened_at": "2026-08-15T08:00:00.000000Z",
      "closed_at": "2026-08-15T16:00:00.000000Z"
    }
  ],
  "current_page": 1,
  "last_page": 1,
  "per_page": 20,
  "total": 1
}
```

---

### 7.2 Open Cash Drawer

**URL:** `POST /cash-drawer/open`

**Auth:** `auth:sanctum` + `permission:payments.cash_drawer`

**Request Body:**

```json
{
  "store_id": 2,
  "opening_amount": 100000,
  "notes": "Morning shift"
}
```

**Response 201:**

```json
{
  "data": {
    "id": 1,
    "store_id": 2,
    "user_id": 3,
    "opening_amount": "100000.00",
    "status": "open",
    "opened_at": "2026-08-15T08:00:00.000000Z"
  }
}
```

**Response 422:**

```json
{ "message": "Another session is already open for this store" }
```

---

### 7.3 Close Cash Drawer

**URL:** `POST /cash-drawer/{id}/close`

**Auth:** `auth:sanctum` + `permission:payments.cash_drawer`

**Request Body:**

```json
{
  "closing_amount": 350000,
  "notes": "End of shift"
}
```

**Response 200:**

```json
{
  "data": {
    "id": 1,
    "opening_amount": "100000.00",
    "closing_amount": "350000.00",
    "expected_amount": "345000.00",
    "difference": "5000.00",
    "status": "closed",
    "closed_at": "2026-08-15T16:00:00.000000Z"
  }
}
```

---

### 7.4 Get Cash Drawer Detail

**URL:** `GET /cash-drawer/{id}`

**Auth:** `auth:sanctum` + `permission:payments.cash_drawer`

**Response 200:**

```json
{
  "data": {
    "id": 1,
    "store_id": 2,
    "user_id": 3,
    "opening_amount": "100000.00",
    "closing_amount": "350000.00",
    "expected_amount": "345000.00",
    "difference": "5000.00",
    "status": "closed",
    "opened_at": "2026-08-15T08:00:00.000000Z",
    "closed_at": "2026-08-15T16:00:00.000000Z",
    "cash_payments": [
      { "sale_id": 123, "amount": "100000.00", "payment_date": "..." }
    ],
    "cash_refunds": []
  }
}
```

---

## 8. EXISTING MODIFIED ENDPOINTS

### 8.1 `POST /sales/checkout` (Modified)

The `payments` array in checkout now supports gateway methods with async lifecycle.

**Request Body:**

```json
{
  "store_id": 2,
  "customer_id": 5,
  "items": [
    { "product_id": 1, "variant_id": null, "quantity": 2 }
  ],
  "payments": [
    { "payment_method": "qris", "amount": 50000, "idempotency_key": "..." }
  ],
  "discount": 0,
  "tax": 0,
  "notes": ""
}
```

**Behavior:**

- `cash` → immediate success, sale `payment_status: paid`
- `qris` / `card` / `bank_transfer` → pending, sale `payment_status: unpaid` until webhook confirms

**Response 201 (qris):**

```json
{
  "data": {
    "id": 123,
    "sale_number": "SALE-20260815-0001",
    "status": "completed",
    "payment_status": "unpaid",
    "total": "50000.00",
    "paid_amount": "0.00",
    "payments": [
      {
        "id": 42,
        "payment_method": "qris",
        "amount": "50000.00",
        "status": "pending",
        "gateway_status": "REQUIRES_ACTION",
        "qr_string": "00020101021126580014ID.X..."
      }
    ]
  }
}
```

---

## 9. FRONTEND SERVICE CALLS

### 9.1 New `paymentService` (`frontend/src/services/payment.ts`)

```typescript
export const paymentService = {
  getGatewayAccount: () =>
    api.get<PaymentGatewayAccount>('/payment-gateway/account').then(r => r.data),

  provision: (data: ProvisionData) =>
    api.post<PaymentGatewayAccount>('/payment-gateway/provision', data).then(r => r.data),

  createCharge: (saleId: number, data: GatewayChargeData) =>
    api.post<Payment>('/sales/' + saleId + '/gateway-charge', data).then(r => r.data),

  getChargeStatus: (saleId: number, chargeId: number) =>
    api.get<Payment>('/sales/' + saleId + '/gateway-charge/' + chargeId).then(r => r.data),

  refund: (paymentId: number, data: RefundData) =>
    api.post<RefundResponse>('/payments/' + paymentId + '/refund', data).then(r => r.data),

  list: (params?: PaymentParams) =>
    api.get<PaginatedResponse<Payment>>('/payments', { params }).then(r => r.data),

  summary: (params?: PaymentSummaryParams) =>
    api.get<PaymentSummary>('/payments/summary', { params }).then(r => r.data),

  listSettlements: (params?: SettlementParams) =>
    api.get<PaginatedResponse<PaymentSettlement>>('/payment-gateway/settlements', { params }).then(r => r.data),

  reconcile: (data: ReconcileData) =>
    api.post<ReconciliationReport>('/payment-gateway/reconcile', data).then(r => r.data),

  listCashDrawerSessions: (params?: CashDrawerParams) =>
    api.get<PaginatedResponse<CashDrawerSession>>('/cash-drawer/sessions', { params }).then(r => r.data),

  openCashDrawer: (data: OpenCashDrawerData) =>
    api.post<CashDrawerSession>('/cash-drawer/open', data).then(r => r.data),

  closeCashDrawer: (id: number, data: CloseCashDrawerData) =>
    api.post<CashDrawerSession>('/cash-drawer/' + id + '/close', data).then(r => r.data),
}
```

---

*End of Phase 5 API Specification*
