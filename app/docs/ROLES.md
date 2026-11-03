# Roles matrix

All checks are server-side (domain actions throw `DomainException`; Filament resources gate via `GatesByRole`;
portal/media via middleware + `MediaFilePolicy`). Customers authenticate by phone OTP and can never pass `canAccessPanel()`.

| Capability | Owner | Advisor | Mechanic | Customer |
|---|:-:|:-:|:-:|:-:|
| Admin panel `/admin` | ✓ | ✓ | dashboard only (no finance widgets) | ✗ |
| Request inbox, triage, convert to appointment | ✓ | ✓ | ✗ | ✗ |
| Confirm / reschedule / cancel / no-show appointments | ✓ | ✓ | ✗ | change *request* only |
| Calendar (drag/drop) | ✓ | ✓ | ✗ | ✗ |
| Check-in (intake, km) | ✓ | ✓ | own assigned appointments | ✗ |
| Walk-in intake | ✓ | ✓ | ✗ | ✗ |
| Status: received→diagnosing, approved→in progress, in progress→QC/waiting parts | ✓ | ✓ | own assigned jobs | ✗ |
| Inspection items, photos | ✓ | ✓ | own assigned jobs | ✗ |
| Publish findings/photos to customer | ✓ | ✓ | ✗ | ✗ |
| Draft / publish estimates (prices) | ✓ | ✓ | ✗ (prices hidden) | ✗ |
| Approve/decline estimate | record phone/in-person attestation | record phone/in-person attestation | ✗ | portal, own orders |
| Owner override (work before approval) | ✓ (reason, audited) | ✗ | ✗ | ✗ |
| Repair log | ✓ | ✓ | own assigned, approved scope only | ✗ |
| QC completion | ✓ | ✓ | only with `qc` capability | ✗ |
| Deliver vehicle | ✓ (incl. credit with reason) | ✓ (paid only) | ✗ | ✗ |
| Invoices issue, manual payments | ✓ | ✓ | ✗ | view own balance |
| Payment reversal, invoice void | ✓ | ✗ | ✗ | ✗ |
| Odometer correction downward | ✓ | ✓ | ✗ | ✗ |
| Maintenance plans | ✓ | ✓ | ✗ (create via staff UI) | view own |
| Message outbox, reconcile, retry | ✓ | ✓ | ✗ | ✗ |
| Consent | record on behalf (with source) | record on behalf | ✗ | own preferences |
| Link portal account to customer | ✓ | ✓ | ✗ | ✗ |
| Reports | ✓ | ✓ | ✗ | ✗ |
| CSV export (audited, formula-safe) | ✓ | ✗ | ✗ | ✗ |
| Services/prices, bays, hours | ✓ | read | ✗ | public view |
| Closures / mechanic leave | ✓ | ✓ | ✗ | ✗ |
| Staff management, business settings, audit log | ✓ | ✗ | ✗ | ✗ |
| Private media | all | all | assigned jobs | own, customer-visible only |

Inactive staff: rejected on the next request; sessions deleted when deactivated in Staff.
