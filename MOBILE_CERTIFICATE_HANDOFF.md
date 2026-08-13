# Mobile Handoff — Final Result + Certificate PDF with Identity Re-confirmation

Hand this to the Flutter developer (or paste it into a coding agent working in
`Alansar-MobileApp`). Everything below is verified against the live backend
code, not assumed.

---

## 1. Context

After a project's evaluation cycle is completed, its results approved and
published to students, and the certificates issued, each student must be able
to open the mobile app, view their final result, and view/download their
certificate as a PDF.

The certificate is an official document in the student's name. A session token
alone is **not** sufficient to hand it over — the device may be unlocked in
someone else's hands. So at download time the student **re-confirms their
identity** by entering their identifier and password again.

The backend already enforces the whole chain and the app cannot bypass or
reorder it:

1. Result approved and published → `evaluation_results.status = 'published'`.
2. Certificate issued → `CertificateService::issue()` rejects any result that
   is not `published`.
3. `GET /mobile/student/me/final-results` returns **published results only**.
4. `POST /mobile/student/me/certificates/{id}/download` streams the PDF only
   after identity re-confirmation, and only when the certificate is `issued`,
   belongs to this authenticated student, and its stored SHA-256 still matches
   the file on disk.

---

## 2. Login is UNCHANGED — do not touch it

**`POST /api/mobile/auth/login` behaves exactly as before.** Students sign in
with their **username**; staff with their **email**.

The **الرقم الذاتي (selfnumber) is not a login identifier and must never become
one.** A student who is not yet enrolled in any circle has no selfnumber
(`NULL`), so basing login on it would lock them out entirely.

The selfnumber is accepted in exactly **one** place: the identity
re-confirmation at certificate download (§3.3).

No changes to `login_screen.dart`, `ApiAuthRepository`, `AuthInterceptor`,
`SessionController`, or the `emailOrUsername` label. Leave them alone.

---

## 3. API contract

Base URL already ends in `/api` (`ApiConfig.baseUrl`), matching the existing
`ApiEndpoints` constants.

### 3.1 Final results list — `GET /mobile/student/me/final-results`

- Channel: mobile access token (`auth.channel:mobile-student`).
- Permission: `عرض نتيجتي النهائية`
- Returns `{ "data": [ … ] }`, newest `published_at` first, **published only**.
- No re-confirmation needed — viewing the result is not gated.

Each element (Eloquent model JSON):

| Field | Type | Notes |
|---|---|---|
| `id` | int | use as `resultId` |
| `base_score`, `base_maximum`, `bonus_score`, `final_score` | string decimal(9,2) | **arrives as a string** — parse, don't cast |
| `final_percentage` | string decimal(7,2) | same |
| `is_excellent` | bool | |
| `rank` | int? | nullable |
| `status` | string | always `published` here |
| `published_at` | ISO datetime | |
| `run.cycle` | object | `id, name, season, start_date, end_date, published_at` |
| `candidate` | object | `id, evaluation_cycle_id, student_id` |
| `certificates` | array | pre-filtered to `status = 'issued'` |

`certificates[]` elements expose `id`, `serial_number`, `certificate_type`,
`status`, `version`, `issued_at`. (`data_snapshot` and
`verification_token_hash` are hidden by the model and never appear.)

**`certificates` is the only source of `certificateId` — never construct or
guess it.** An empty array means no certificate issued yet: hide the download
button and show a "not issued yet" state.

### 3.2 Result detail — `GET /mobile/student/me/final-results/{resultId}`

Same permission. Adds `criteria[]` (`criterion_key`, `criterion_name`,
`is_applicable`, `score`, `maximum_score`, `readiness_status`, `warnings`) and
`candidate.student` (`id, first_name, last_name, selfnumber`).

The `candidate.student.selfnumber` here is useful UI context — you may display
it on the confirmation sheet as a hint of what the student can type. Returns
`404` if the result is not this student's or is not published.

### 3.3 Certificate PDF — `POST /mobile/student/me/certificates/{certificateId}/download`

- Permission: `تحميل شهادتي النهائية`
- Rate limit: **5 requests per minute per account** (`throttle:certificate-confirm`).
- Request body (both required):

```json
{ "identifier": "ahmad.khaled", "password": "…" }
```

`identifier` accepts the student's **username** *or* their **current
selfnumber**. Whitespace is trimmed; case is normalized on both sides. The
server compares against the authenticated student's own columns — it does not
search the students table — so another student's identifier will never open
this account's certificate.

- Success: a **binary PDF stream**, not a JSON envelope.
  `Content-Type: application/pdf`, attachment filename `{serial_number}.pdf`.

**There is deliberately no `GET` variant.** Confirmation is part of the
download request itself, so there is no window between confirming and
receiving, and no intermediate confirmation token to leak or replay. Do not
ask for one to be added.

Errors:

| Status | Meaning | Suggested UX |
|---|---|---|
| `422` | `identifier` or `password` missing | inline field validation |
| `403` | identity re-confirmation failed | "تعذر تأكيد هويتك؛ تحقق من المعرّف وكلمة المرور ثم أعد المحاولة." — keep the sheet open, clear only the password |
| `403` | wrong auth channel / missing permission | "غير مصرح لك بتحميل الشهادة." |
| `404` | not this student's certificate, or not `issued` | "الشهادة غير متاحة." |
| `409` | file missing or SHA-256 mismatch (`CERTIFICATE_FILE_CORRUPT`) | show the server message; do **not** auto-retry |
| `429` | rate limit exceeded | "حاولت مرات كثيرة؛ انتظر دقيقة ثم أعد المحاولة." — disable the button until the `Retry-After` window passes |

The `403` message does not say which field was wrong, and the server checks the
password even when the identifier is wrong so response timing reveals nothing.
**Do not "improve" this by telling the user which field failed.**

---

## 4. Existing app conventions to follow

Mirror the layering already used by `lib/features/warnings/`:

```
lib/features/<feature>/
  domain/      <model>.dart, <model>_repository.dart   (abstract)
  data/        api_<model>_repository.dart             (Dio impl)
  providers/   <model>_providers.dart (+ .g.dart, riverpod_generator)
  presentation/<screen>.dart
```

Riverpod (`flutter_riverpod` + `riverpod_annotation`), `go_router`, Dio with
`ApiEnvelope`/`guardApi`/`ApiException`, `AppPermissions` gating,
`AppColors`/`AppSpacing`/`AppTextStyles`, and `AppLocalizations` for **all**
user-facing copy (ar + en). Run
`dart run build_runner build --delete-conflicting-outputs` after adding
annotated providers.

---

## 5. Work to do

### 5.1 Permissions

Add to `lib/core/permissions/app_permissions.dart` (verbatim Arabic — these
strings are the API contract and must match byte-for-byte):

```dart
static const String studentViewFinalResults = 'عرض نتيجتي النهائية';
static const String studentDownloadCertificate = 'تحميل شهادتي النهائية';
```

Append both to `AppPermissions.values`. Gate the nav entry and screen on
`studentViewFinalResults`, and the download button on
`studentDownloadCertificate` — a student may hold the first and not the second.

### 5.2 Endpoints

Add to `lib/core/network/api_endpoints.dart`:

```dart
static const String studentFinalResults = '$_mobileStudentMe/final-results';

static String studentFinalResult(int resultId) =>
    '$studentFinalResults/$resultId';

static String studentCertificateDownload(int certificateId) =>
    '$_mobileStudentMe/certificates/$certificateId/download';
```

### 5.3 Feature `lib/features/final_result/`

**`domain/final_result.dart`** — `FinalResult` and `IssuedCertificate` with
`fromJson`. Parse decimals with
`double.tryParse(json['final_score'].toString()) ?? 0` — they arrive as
strings. Treat `rank` as nullable and `certificates` as possibly empty.

**`domain/final_result_repository.dart`** — abstract:

```dart
Future<List<FinalResult>> myFinalResults();
Future<FinalResult> finalResult(int resultId);
Future<Uint8List> certificatePdf({
  required int certificateId,
  required String identifier,
  required String password,
});
```

**`data/api_final_result_repository.dart`** — Dio impl inside `guardApi`.
List/detail use `ApiEnvelope` as usual. The PDF call must **bypass the
envelope** and post the confirmation:

```dart
final response = await _dio.post<List<int>>(
  ApiEndpoints.studentCertificateDownload(certificateId),
  data: {'identifier': identifier.trim(), 'password': password},
  options: Options(
    responseType: ResponseType.bytes,
    headers: {'Accept': 'application/pdf'},
  ),
);
return Uint8List.fromList(response.data!);
```

`AuthInterceptor._retry()` replays the original `RequestOptions`, so
`responseType: bytes` **and the request body** survive a 401 → refresh → retry
cycle. Do not build a separate Dio instance for this.

**`providers/final_result_providers.dart`** — repository provider + an async
list provider. Keep the PDF fetch **imperative** (invoked on confirm), never a
cached auto-provider: certificate bytes and the typed password must not sit in
provider memory.

**`presentation/`** —

- `my_final_results_screen.dart`: list of published results (cycle name,
  season, final score, percentage, rank, excellence badge), loading/empty/error
  states, pull-to-refresh, tap → detail.
- `final_result_detail_screen.dart`: header + `criteria[]` breakdown. Show the
  certificate button only when `certificates` is non-empty **and** the student
  holds `studentDownloadCertificate`.
- `certificate_confirm_sheet.dart` (**new, the core of this change**): a modal
  bottom sheet with an `identifier` field (label: "اسم المستخدم أو الرقم
  الذاتي" / "Username or self number", `TextDirection.ltr`) and an obscured
  `password` field, plus a submit button with a progress state. On submit it
  calls `certificatePdf(...)`; on `403` it keeps the sheet open, clears **only**
  the password, and shows the server message; on `429` it disables submission
  for the retry window. On success it dismisses and pushes the preview screen
  with the bytes.
- `certificate_preview_screen.dart`: **copy the shape of
  `lib/features/student_report/presentation/student_report_preview_screen.dart`.**
  `printing: 5.15.0` is already a dependency — `PdfPreview` provides viewing,
  printing, sharing and saving in one widget, so **no new package is needed**
  and no manual file I/O or storage-permission handling is required:

```dart
PdfPreview(
  key: const ValueKey('certificate-pdf-preview'),
  build: (_) async => bytes,          // bytes returned by the confirm sheet
  pdfFileName: '$serialNumber.pdf',
  initialPageFormat: PdfPageFormat.a4,
  allowPrinting: true,
  allowSharing: true,
  canChangeOrientation: false,
  canChangePageFormat: false,
  canDebug: false,
  dynamicLayout: false,
)
```

### 5.4 Routing & navigation

Add to `lib/core/router/student_route_paths.dart`:

```dart
static const String finalResults = '/final-results';
static const String finalResultDetailPattern = '/final-result/:resultId';
static String finalResultDetail(int resultId) => '/final-result/$resultId';
```

Register in `app_router.dart`, add the destination in `nav_destinations.dart`,
and gate it in `route_access.dart` on `studentViewFinalResults`, following the
existing student entries. The preview screen takes the bytes as a constructor
argument — do **not** put PDF bytes or the password in a route path, query
parameter, or `go_router` `extra` that survives navigation history.

---

## 6. Security requirements (do not simplify these away)

1. **Never store, cache, log, or auto-fill the confirmation password.** Hold it
   in a `TextEditingController`, dispose it, and let it die with the sheet.
   Set `autofillHints: const []` and `enableSuggestions: false` on that field
   so the OS does not offer to save it as a new credential.
2. **Never reuse the login password from session state** to pre-fill or
   auto-submit the confirmation. The whole point is a fresh human action; a
   silent re-confirm defeats it. Do not add a "remember me" or "don't ask
   again" option.
3. **Never send a student id, `student_id`, or `selfnumber` as a query/path
   parameter to any `/mobile/student/me/*` endpoint.** Ownership is derived
   server-side from the token; `identifier` in the confirm body is a
   verification input, not a selector.
4. **Never persist certificate PDF bytes to app storage yourself.** Hand them
   to `PdfPreview` and let the user save or share through the OS sheet. Drop
   the bytes when the preview screen is popped, and on logout.
5. Tokens stay in `flutter_secure_storage` (already the case) — never in
   `shared_preferences`, never in logs or crash reports.
6. Do not reveal which field failed on `403`, and do not auto-retry `409`.
7. Respect `429` — no client-side retry loop against a password-checking
   endpoint.

---

## 7. Acceptance criteria

- [ ] Login is untouched: students still sign in with username, staff with email; the selfnumber is still rejected at login.
- [ ] The final-results screen lists only published results, newest first.
- [ ] A result whose `certificates` array is empty shows no download button.
- [ ] Tapping the certificate button opens the confirmation sheet, not the PDF.
- [ ] Confirming with the correct **username** + password returns the PDF.
- [ ] Confirming with the correct **selfnumber** + password returns the PDF, including with different casing and stray spaces.
- [ ] A student with no selfnumber can still confirm with their username.
- [ ] Wrong password → `403`, sheet stays open, only the password field clears, message does not say which field was wrong.
- [ ] Another student's identifier + this student's password → `403`.
- [ ] A 6th attempt within a minute → `429` handled gracefully, button disabled for the retry window.
- [ ] `409` shows the server message with no automatic retry.
- [ ] PDF viewing, printing and sharing all work on Android and iOS.
- [ ] Missing `عرض نتيجتي النهائية` hides the nav entry; missing `تحميل شهادتي النهائية` hides only the download button.
- [ ] An expired access token during the download refreshes and retries transparently, preserving the POST body, still returning a valid PDF.
- [ ] The password never appears in logs, `shared_preferences`, or the OS credential-save prompt.
- [ ] All new copy exists in both `app_ar.arb` and `app_en.arb`; RTL layout verified in Arabic.
- [ ] `flutter analyze` clean; `dart run build_runner build --delete-conflicting-outputs` committed.

---

## 8. Local testing

Backend:

```bash
php artisan migrate && php artisan permission:cache-reset && php artisan test
```

A Bruno collection lives in `api/Student Self Service API/` — select the
`Local` environment (`http://localhost:8000`) and run the login request first;
it stores `studentToken` and `refreshToken` automatically.

Ensure the student's role (a `role_family = student` role) carries both
`عرض نتيجتي النهائية` and `تحميل شهادتي النهائية`, and that the target cycle
has a published result with an issued certificate — otherwise the list is
correctly empty and there is nothing to download. To exercise the selfnumber
path the student must be enrolled in a circle; without one they have no
selfnumber and confirm by username only.
