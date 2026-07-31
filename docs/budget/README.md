# Budget & Cash Flow Module

## Installation
Schema is applied automatically on first visit via `BudgetSchemaModel::ensureSchema()` or run:
```bash
mysql xander_school < deploy/add_budget_cashflow.sql
```

## Roles (posts)
- 9 Accountant — create budgets & cash requests
- 19 Budget Manager — budget review
- 20 Procurement Manager — procurement review
- 21 Deputy Director of Finance — final approval, all branches
- 22 Finance Officer — payments
- 23 Internal Auditor — audit read-only

Assign menu keys under Admin → Level clearance.

## Branch isolation (standalone school behaviour)

- **Each branch = one school** — staff only see their own branch data (budgets, cash requests, periods).
- **Wisdom Schools** — detected when the school name or acronym contains `Wisdom` (case-insensitive).
  - Linked to org `wisdom-schools` with 15 branch catalog (Musanze, Nyabihu, …).
  - **Central dashboard** (Deputy Director + `budget.view_all_branches`): shows all Wisdom branches with **Wisdom** prefix (e.g. *Wisdom Musanze*).
  - Branch accountants see their school name only — no prefix in daily work.
- **Non-Wisdom schools** — completely separate org (`school-{id}`), single branch, **never** mixed with Wisdom data or naming.

## Detection

School is Wisdom if `schools.name` or `schools.acronym` contains `wisdom`. No impact on Xander School, APADE, or other tenants.
Budget: DRAFT → SUBMITTED → PROCUREMENT_REVIEW → BUDGET_MANAGER_REVIEW → DEPUTY_DIRECTOR_REVIEW → APPROVED

Cash request: DRAFT → SUBMITTED → PROCUREMENT_APPROVED → BUDGET_APPROVED → FINANCE_AUTHORIZED → PAID → RECEIPT_CONFIRMED → CLOSED

## URLs
- `/budget/dashboard`
- `/budget/prepare`
- `/budget/cash_requests`
