# Handoff — Final Result Section, Locked Behind Identity Confirmation

Status: **implemented and merged on both sides** (Laravel API + Flutter app).
This document is the contract reference and the list of invariants that must
survive future edits — not a build brief. Read §6 before changing anything in
this feature.

---

## 1. What the feature does

After a project's evaluation cycle is completed, its results approved and
published, and the certificates issued, a student can open the mobile app, view
their results across evaluation cycles, and view/download each certificate as a
PDF.

Tapping the **نتيجتي النهائية** card does **not** show results. It shows a lock.
The student must re-enter their **username or current selfnumber (الرقم الذاتي)**
plus their **password**. Only then does the whole section open — list, detail
and certificate together.

The lock re-arms when the student leaves the section or the app goes to the
background.

### Why the gate is at the section, not at the download

The result itself is personal data. Guarding the certificate while leaving the
scores, rank and criteria breakdown readable would protect the document and
leak its contents. The threat is a phone left unlocked in someone else's hands,
and that threat reaches the whole section.

### Why login was not changed

`POST /api/mobile/auth/login` is untouched: students sign in with their
**username**, staff with their **email**.

**The selfnumber is not a login identifier and must never become one.** A
student not yet enrolled in any circle has `selfnumber = NULL`, so login built
on it would lock out exactly the students who most need to get in. The
selfnumber is accepted only at the identity gate, where the account is already
known from the session token.

---

## 2. Server-enforced ordering

The app cannot bypass or reorder any of this:

1. Result approved and published → `evaluation_results.status = 'published'`.
2. Certificate issued → `CertificateService::issue()` rejects any result that
   is not `published`.
3. `GET final-results` returns **published results only**.
4. `GET certificates/{id}` streams the PDF only when the certificate is
   `issued`, belongs to this student, and its stored SHA-256 still matches the
   file on disk.
5. Steps 3 and 4 both sit behind the identity gate.

---

## 3. API contract

Base URL already ends in `/api` (`ApiConfig.baseUrl`).

### 3.1 Unlock — `POST /mobile/student/me/identity/confirm`

```json
{ "identifier": "ahmad.khaled", "password": "…" }
```

`identifier` accepts the student's **username** *or* their **current
selfnumber**. Whitespace is trimmed; case is normalised on both sides
(usernames stored lowercase, mosque codes uppercase). The server compares
against the authenticated student's own columns — it never searches the
students table — so another student's identifier can never open this account.

- Success → `200`, `data.expires_in` in seconds (currently `300`).
- Failure → `403`, `error_code: IDENTITY_CONFIRMATION_FAILED`. **Any existing
  grant is revoked**, so a wrong attempt locks rather than leaves a stale grant.
- Rate limit → **5/minute per account** (`throttle:identity-confirm`), keyed by
  account rather than IP so a shared mosque network cannot lock out everyone.
- The password is checked even when the identifier is wrong, so response timing
  reveals nothing about which field failed.

The grant is stored server-side against the **current access token**, not the
account. Confirming on a phone does not unlock the section on a tablet holding
a second session for the same student.

### 3.2 Re-lock — `POST /mobile/student/me/identity/lock`

Revokes the grant immediately. Called on leaving the section and on
backgrounding. Best-effort on the client: a failed lock is swallowed, because
the client is already locked and the grant lapses on its own.

### 3.3 Results list — `GET /mobile/student/me/final-results`

Permission `عرض نتيجتي النهائية`. Returns `{ "data": [...] }`, newest
`published_at` first, published only.

| Field | Type | Notes |
|---|---|---|
| `id` | int | use as `resultId` |
| `base_score`, `base_maximum`, `bonus_score`, `final_score` | string decimal | **arrives as a string** — parse, don't cast |
| `final_percentage` | string decimal | may exceed 100 when bonus points apply |
| `is_excellent` | bool | |
| `rank` | int? | nullable |
| `published_at` | ISO datetime | |
| `run.cycle` | object | `id, name, season, start_date, end_date, published_at` |
| `certificates` | array | pre-filtered to `status = 'issued'` |

`certificates[]` exposes `id`, `serial_number`, `certificate_type`, `status`,
`version`, `issued_at`. `data_snapshot` and `verification_token_hash` are hidden
by the model and never appear.

**`certificates` is the only source of `certificateId`.** Empty array = not
issued yet → hide the download button.

### 3.4 Result detail — `GET /mobile/student/me/final-results/{resultId}`

Adds `criteria[]` (`criterion_key`, `criterion_name`, `is_applicable`, `score`,
`maximum_score`, `readiness_status`, `warnings`) and `candidate.student`
(`id, first_name, last_name, selfnumber`). `404` if not this student's or not
published.

### 3.5 Certificate PDF — `GET /mobile/student/me/certificates/{certificateId}`

Permission `تحميل شهادتي النهائية`. Binary PDF stream,
`Content-Type: application/pdf`, filename `{serial_number}.pdf`.

**Carries no credentials.** The section gate already proved identity and the
same middleware guards this route.

### 3.6 Errors

| Status | `error_code` | Meaning | Handling |
|---|---|---|---|
| `403` | `IDENTITY_CONFIRMATION_REQUIRED` | no valid grant | send the student back to the gate — **not** a dead-end error |
| `403` | `IDENTITY_CONFIRMATION_FAILED` | wrong identifier or password | keep the form, clear only the password |
| `403` | — | missing permission / wrong channel | "غير مصرح لك بتحميل الشهادة." |
| `404` | — | not this student's, or not issued | "الشهادة غير متاحة." |
| `409` | `CERTIFICATE_FILE_CORRUPT` | file missing or SHA-256 mismatch | show the server message, **never auto-retry** |
| `429` | — | rate limit | disable submit for the cooldown |

`IDENTITY_CONFIRMATION_REQUIRED` is deliberately distinct from a permission
`403`: the student can act on the first and cannot act on the second.

---

## 4. Where the code lives

### Laravel (`QIMS`)

| File | Role |
|---|---|
| `app/Services/StudentIdentityConfirmationService.php` | identity matching + grant issue/check/revoke |
| `app/Http/Middleware/EnsureStudentIdentityConfirmed.php` | the gate (`student.identity.confirmed`) |
| `app/Http/Controllers/StudentIdentityController.php` | `confirm` / `lock` |
| `app/Http/Controllers/CertificateController.php` | `studentDownload`, no credentials of its own |
| `routes/api.php` | the three guarded routes in one middleware group |
| `app/Providers/AppServiceProvider.php` | `identity-confirm` rate limiter |
| `bootstrap/app.php` | middleware alias |

### Flutter (`Alansar-MobileApp`) — `lib/features/final_result/`

| File | Role |
|---|---|
| `domain/final_result.dart` | `FinalResult`, `FinalResultCriterion`, `IssuedCertificate` |
| `domain/final_result_repository.dart` | abstract contract |
| `data/api_final_result_repository.dart` | Dio impl + bytes-error decoding |
| `providers/final_result_providers.dart` | repository, list, detail, **`FinalResultLock`** |
| `presentation/final_result_gate_screen.dart` | section entry + identity form + lifecycle lock |
| `presentation/my_final_results_screen.dart` | `FinalResultsList`, shown once unlocked |
| `presentation/final_result_detail_screen.dart` | detail + download action |
| `presentation/certificate_preview_screen.dart` | `PdfPreview` + `CertificateDocument` |

Touched elsewhere: `api_endpoints.dart`, `app_permissions.dart`,
`student_route_paths.dart`, `app_router.dart`, `route_access.dart`,
`student_design_system.dart` (one enum value), `student_dashboard_screen.dart`
(one card), both `.arb` files.

`printing: 5.15.0` was already a dependency — **no new packages were added.**

---

## 5. Client lock behaviour

`FinalResultLock` is a Riverpod notifier holding one bool, starting locked.

- `unlock()` after a successful confirm.
- `lock()` invalidates `myFinalResultsProvider` (dropping loaded results from
  memory) and calls `identity/lock`.
- `FinalResultGateScreen` locks in `dispose()` (leaving the section) and in
  `didChangeAppLifecycleState` on anything other than `resumed`.
- The detail and preview screens `ref.listen` on the lock and pop themselves
  when it re-arms, so scores and PDF bytes cannot linger behind a locked gate.

---

## 6. Invariants — do not break these

1. **Never make the selfnumber a login identifier.** Students without a circle
   have none. It belongs only at the identity gate.
2. **Never weaken the gate to a UI-only check.** The middleware is the real
   lock; the client mirrors it. A UI-only gate is theatre.
3. **Never store, cache, log or pre-fill the confirmation password.** It lives
   in a `TextEditingController` disposed with the form. `AppInputField` sets no
   `autofillHints`, so the OS does not offer to save it — keep it that way.
4. **Never add "remember me" or auto-confirm.** A silent re-confirm defeats the
   feature.
5. **Never send `student_id` or `selfnumber` as a query/path parameter** to any
   `/mobile/student/me/*` endpoint. Ownership comes from the token; `identifier`
   is a verification input, not a selector.
6. **Never persist certificate PDF bytes.** Hand them to `PdfPreview`; the OS
   sheet handles saving and sharing.
7. **Never reveal which field failed** on a confirmation error.
8. **Never auto-retry `409`**, and respect `429`.
9. **Keep the bytes-error decoding** in `_certificateException` (see §7).

---

## 7. The one non-obvious bug already fixed

With `ResponseType.bytes`, Dio delivers **error** bodies as bytes too. So
`ApiException.fromResponse` sees a `List<int>`, misses the `{code, message,
data}` envelope, and falls back to a generic message — silently dropping both
the server's Arabic reason and its `error_code`.

That would have been doubly damaging here: without `error_code` the app cannot
distinguish `IDENTITY_CONFIRMATION_REQUIRED` (send back to the gate) from a
permission `403` (dead end).

`ApiFinalResultRepository._certificateException` decodes the bytes back to JSON
before delegating. Two tests in `test/final_result_certificate_test.dart` guard
it. **Do not "simplify" it away.**

---

## 8. Verification status

Automated: **172 backend tests**, **191 Flutter tests**, `flutter analyze`
clean.

Verified against the live server with real seeded data:

| Check | Result |
|---|---|
| list / certificate before confirming | `403 IDENTITY_CONFIRMATION_REQUIRED` |
| confirm with `"  test01-000002  "` | `200`, `expires_in: 300` |
| list / certificate after confirming | `200` / `200`, PDF 629 KB |
| `identity/lock` then list | `403` |
| wrong password | `403`, generic message |

Verified on a physical Android device (Xiaomi 24115RA8EG, Android 16):

- Pre-redesign build: login, results list, detail, the confirmation failure
  path, and the certificate rendering in `PdfPreview`.
- Post-redesign build:
  - tapping **نتيجتي النهائية** shows the lock, not results; Arabic copy renders
    correctly RTL;
  - a wrong password keeps the identifier, clears only the password, and shows
    the server's message without naming the failing field;
  - a correct confirmation opens the section into the results list;
  - backgrounding the app and reopening it re-locks the section with a fresh,
    empty form.

The re-lock was confirmed to be **real, not cosmetic**: the access token was
used at the moment of backgrounding (the `identity/lock` call) and the
server-side grant for that token read back as absent immediately after. The UI
flag and the server grant drop together.

**Still unverified on-device:** PDF printing/sharing through the OS share sheet,
and iOS entirely (all device testing so far has been Android).

`adb shell input` is blocked on that device (`INJECT_EVENTS`, a HyperOS
restriction), so on-device checks need a human tapping, or Developer options →
**USB debugging (Security settings)** enabled. Screen capture works either way.

---

## 9. Local testing

```bash
php artisan migrate && php artisan permission:cache-reset && php artisan test
```

Bruno collection: `api/Student Self Service API/` — select `Local`, run the
login request first (it stores `studentToken` / `refreshToken`), then
`identity/confirm` before any final-result request.

Seeded accounts carry password `student123`. The student's role needs both
`عرض نتيجتي النهائية` and `تحميل شهادتي النهائية`, and the cycle needs a
published result with an issued certificate — otherwise the list is correctly
empty. To exercise the selfnumber path the student must be enrolled in a
circle; without one they have no selfnumber and confirm by username only.

For a physical phone over USB, prefer `adb reverse` over a LAN IP:

```bash
adb reverse tcp:8000 tcp:8000
```

then run with `--dart-define=API_BASE_URL=http://127.0.0.1:8000/api`.
