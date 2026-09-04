# DOCUMENTATION FRAMEWORK

**Document Status:** DRAFT — Pending Approval  
**Created:** 2026-08-11  
**Depends On:** `00-MASTER_PDR.md`

---

## 1. PURPOSE

This document defines the **mandatory documentation requirements** for every phase of the ERP platform. No phase is considered COMPLETE until all documentation is produced and verified.

---

## 2. DOCUMENTATION-FIRST WORKFLOW

Every phase follows this sequence:

```
PDR / Architecture
       ↓
Business Rules
       ↓
Database Design
       ↓
API Design
       ↓
UI/UX Flow
       ↓
Flowchart
       ↓
Implementation
       ↓
Security Tests
       ↓
API Tests
       ↓
Integration Tests
       ↓
Smoke Tests
       ↓
E2E / UI Tests
       ↓
UX Verification
       ↓
Documentation
       ↓
Final Audit
       ↓
Phase COMPLETE
```

**Rule:** Implementation does NOT begin until PDR + Architecture + Database Design + API Design are written and reviewed.

---

## 3. FOLDER STRUCTURE

```
/docs/
  SYSTEM_AUDIT_REPORT.md                  ← Current state audit (done)
  PDR/                                    ← Master PDR documents
    00-MASTER_PDR.md                      ← Vision + decisions (approved)
    01-ERP_ARCHITECTURE.md                ← Technical architecture (approved)
    02-PHASE_ROADMAP.md                   ← Phase plan (approved)
    03-DOCUMENTATION_FRAMEWORK.md         ← This document (approved)
  phases/                                 ← Per-phase documentation
    phase-00/
      PDR.md
      ARCHITECTURE.md
      FLOW.md
      API.md
      SECURITY.md
      TESTING.md
      FINAL-REPORT.md                     ← Produced at phase completion
    phase-01/
      ...
  modules/                                ← Module-specific documentation
    pos/
      pos-pdr.md
      pos-architecture.md
      pos-flow.md
      pos-api.md
      pos-security.md
      pos-testing.md
    inventory/
      inventory-pdr.md
      inventory-architecture.md
      inventory-flow.md
      inventory-api.md
      inventory-security.md
      inventory-testing.md
    payment/
      payment-pdr.md
      payment-architecture.md
      payment-flow.md
      payment-api.md
      payment-security.md
      payment-testing.md
    finance/
      finance-pdr.md
      finance-architecture.md
      finance-flow.md
      finance-api.md
      finance-security.md
      finance-testing.md
    ... (per module)
  phase-reports/                          ← Final phase completion reports
    phase-0-report.md
    phase-1-report.md
    ...
```

---

## 4. PER-MODULE DOCUMENTATION TEMPLATE

Each module/phase must produce these 6 documents:

### 4.1 PDR (`{module}-pdr.md`)

```markdown
# {Module Name} — PDR

## Problem Statement
What business problem does this module solve?

## Scope
What is included? What is NOT included?

## User Stories
- As a {role}, I want to {action} so that {benefit}

## Business Rules
1. Rule 1
2. Rule 2

## Assumptions
- Assumption 1

## Dependencies
- Module X (for Y)

## Risks
- Risk 1 + mitigation
```

### 4.2 Architecture (`{module}-architecture.md`)

```markdown
# {Module Name} — Architecture

## Technical Design
How is this module implemented?

## Database Schema
Table definitions with columns, types, indexes, constraints

## Model Relationships
ERD diagram (text-based or image)

## Service Layer
Service classes and their responsibilities

## Middleware
Module-specific middleware

## Configuration
Feature flags, settings, environment variables

## Integration Points
How this module connects to other modules
```

### 4.3 Flow (`{module}-flow.md`)

```markdown
# {Module Name} — Flow

## Primary Flow
Step-by-step sequence diagram

## Alternative Flows
Edge cases, error paths

## State Machine
Status transitions (if applicable)

## Sequence Diagrams
Text-based or image sequence diagrams for key operations
```

### 4.4 API (`{module}-api.md`)

```markdown
# {Module Name} — API

## Endpoints
| Method | Path | Auth | Permission | Description |

## Request Schemas
JSON body for each endpoint

## Response Schemas
JSON response for each endpoint

## Error Codes
Custom error codes + messages

## Rate Limits
If applicable
```

### 4.5 Security (`{module}-security.md`)

```markdown
# {Module Name} — Security

## RBAC
Which roles can access which operations

## Tenant Isolation
How tenant_id is enforced

## Input Validation
Validation rules for each input

## Output Sanitization
How output is sanitized

## Known Risks
Security risks + mitigations
```

### 4.6 Testing (`{module}-testing.md`)

```markdown
# {Module Name} — Testing

## Test Plan
What will be tested

## Unit Tests
Service-level tests

## API Tests
Controller-level tests

## Integration Tests
Cross-module integration tests

## E2E Tests
Playwright tests for user flows

## Test Data
Seeders, factories

## Coverage Target
Minimum coverage percentage
```

---

## 5. PHASE COMPLETION CHECKLIST

Every phase must pass ALL of the following before being marked COMPLETE:

| # | Check | Description | Verification |
|---|-------|-------------|--------------|
| 1 | **Implementation** | All planned features implemented | Code review |
| 2 | **Database** | Migrations created, seeded, tested | `php artisan migrate:fresh --seed` works |
| 3 | **API** | All endpoints working, documented | API spec matches implementation |
| 4 | **Security** | RBAC, tenant isolation, validation verified | Security tests pass |
| 5 | **Smoke Tests** | Basic flow tests pass | Smoke test suite green |
| 6 | **Integration Tests** | Cross-module integration verified | Integration test suite green |
| 7 | **E2E Tests** | Playwright user flow tests pass | E2E test suite green |
| 8 | **UI Tests** | Frontend renders correctly, responsive | Visual verification |
| 9 | **UX Verification** | User flow is intuitive, no dead ends | Manual walkthrough |
| 10 | **Documentation** | All 6 per-module docs written | Docs exist and are accurate |
| 11 | **Regression** | All existing tests still pass | Full test suite green |

**If ANY check fails → Phase is NOT COMPLETE.**

---

## 6. FINAL PHASE REPORT

At the end of each phase, a **Final Phase Report** is produced in `/docs/phase-reports/`:

```markdown
# Phase {N} — {Name} — Final Report

## Summary
What was accomplished

## Deliverables
- [x] Feature 1
- [x] Feature 2

## Test Results
- Backend: {N} tests / {N} assertions — ALL PASS
- Frontend E2E: {N} tests — ALL PASS
- Regression: {N} existing tests — ALL PASS

## Documentation
- [x] PDR
- [x] Architecture
- [x] Flow
- [x] API
- [x] Security
- [x] Testing

## Completion Checklist
| Check | Status |
|-------|--------|
| Implementation | PASS |
| Database | PASS |
| API | PASS |
| Security | PASS |
| Smoke Tests | PASS |
| Integration Tests | PASS |
| E2E Tests | PASS |
| UI Tests | PASS |
| UX Verification | PASS |
| Documentation | PASS |
| Regression | PASS |

## Known Issues
- Issue 1 (non-blocking, tracked for future phase)

## Next Phase
Phase {N+1}: {Name} — {brief description}
```

---

## 7. DOCUMENTATION MAINTENANCE RULES

1. **Docs are living documents** — updated when features change.
2. **Docs are written BEFORE implementation** — not after.
3. **Docs are reviewed at phase boundaries** — user approves before proceeding.
4. **API docs match implementation** — if code changes, docs update.
5. **Database docs match migrations** — if schema changes, docs update.
6. **Test plans match test files** — if tests change, test plans update.

---

## 8. TEMPLATE QUICK REFERENCE

Starting a new phase? Create these files:

```
docs/modules/{module-slug}/{module-slug}-pdr.md
docs/modules/{module-slug}/{module-slug}-architecture.md
docs/modules/{module-slug}/{module-slug}-flow.md
docs/modules/{module-slug}/{module-slug}-api.md
docs/modules/{module-slug}/{module-slug}-security.md
docs/modules/{module-slug}/{module-slug}-testing.md
```

At phase end, create:

```
docs/phase-reports/phase-{N}-report.md
```

---

*End of Documentation Framework*
