# Historical Patches Archive

These patches are **historical reference only**. All changes have been integrated into the main codebase.

## Patch Files (Obsolete)

| Patch | Original Module | Status |
|-------|-----------------|--------|
| `Invoice.patch` | ivozprovider-balance-topup | **Merged** - Invoice.php modified directly |
| `CompanyAbstract.patch` | ivozprovider-whmcs | **Merged** - Company entity extended |
| `CompanyInterface.patch` | ivozprovider-whmcs | **Merged** - Company entity extended |
| `Company.orm.xml.patch` | ivozprovider-whmcs | **Merged** - ORM mapping updated |

## Consolidation Date

2026-01-16 - All integration module code consolidated into fork

## Where to Find Current Code

All custom code now lives directly in the fork:

| Component | Path |
|-----------|------|
| Invoice entity | `library/Ivoz/Provider/Domain/Model/Invoice/` |
| Company entity | `library/Ivoz/Provider/Domain/Model/Company/` |
| Brand entity | `library/Ivoz/Provider/Domain/Model/Brand/` |
| DDI entity | `library/Ivoz/Provider/Domain/Model/Ddi/` |
| Services | `library/Ivoz/Provider/Domain/Service/` |
| Controllers | `web/rest/client/src/Controller/` |
| React components | `web/portal/client/src/` |

## Do Not Apply These Patches

These patches should NOT be applied. They are kept only for:
- Historical reference
- Understanding what changes were made
- Potential rollback investigation

The main branch contains all changes already integrated.

---

## Original Patch Documentation

### Invoice.patch (from ivozprovider-balance-topup)

IvozProvider's `CheckValidity` service validates invoice date ranges (in/out dates) for period-based invoices (e.g., monthly billing). Balance topup invoices are instant, same-day invoices where:
- `inDate` = today
- `outDate` = today

Without this patch, creating a balance_topup invoice triggers "Forbidden future dates" error because the validation logic incorrectly flags same-day invoices.

**Fix:** Added `mustCheckValidity()` method to Invoice.php that returns `false` for balance_topup invoices.

### Company.* patches (from ivozprovider-whmcs)

Added `whmcsClientId` field to Company entity for linking IvozProvider companies to WHMCS clients.

**Fix:** Extended Company entity with property, getter/setter, ORM mapping, and interface.
