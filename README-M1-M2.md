# Admission System - Milestones 1-2 (overlay)

UI foundation (layouts, components, `app.css`) + authentication. This ZIP is an **overlay**: it contains only the files written for this project and is laid on top of a fresh Laravel project. **Nothing in it has been run yet** (the authoring environment had no PHP/Composer) - the first `php artisan test` is yours.

## Install (cPanel/SSH or local)

```bash
composer create-project laravel/laravel admission-system     # Laravel 13, needs PHP 8.3+
cd admission-system
unzip -o ../admission-system-m1-m2-overlay.zip               # overwrites the files listed below
npm install bootstrap@5.3.3 bootstrap-icons@1.11.3 @fontsource/plus-jakarta-sans @fontsource/noto-sans-bengali
npm run build
cp .env.example .env && php artisan key:generate             # then fill DB_* in .env
php artisan migrate
php artisan test
php artisan admission:make-staff you@example.com --username=yourname --name="Your Name"
```
Local demo accounts (random passwords, printed once; local/testing only): `php artisan db:seed`.

Files that replace skeleton defaults: `.env.example`, `vite.config.js`, `routes/web.php`, `resources/css/app.css`, `resources/js/app.js`, `app/Models/User.php`, `app/Providers/AppServiceProvider.php`, `database/factories/UserFactory.php`, `database/seeders/DatabaseSeeder.php`, `tests/TestCase.php`. The default `welcome.blade.php` is no longer routed.

## What is in this milestone

| Area | Status |
|---|---|
| `style.css` -> `resources/css/app.css` (unchanged, plus a few additions at the bottom) | written |
| Layouts: auth, admin, student, document (A4 print) | written |
| Components: brand, field, flash/toast, sidebar (from `config/menu.php`), topbar | written |
| Login (tabs, +88 prefix, eye toggle, guide video, spinner), Register, Forgot/Reset password | written |
| Login by mobile / email / username; same error for every failure; 5 tries per identity+IP and 20 per IP, 5-minute window | written |
| Session regenerated on login, invalidated on logout, all DB sessions of a user deleted after a password reset | written |
| Deactivated accounts are signed out on their next request | written |
| `login_histories` (login, failed, lockout, logout, password_reset; identity masked) | written |
| Registration can only create `student` accounts; `account_type` / `status` are not mass-assignable | written |
| Security headers on every page; CSP + HSTS in production | written |
| Admin / student shells with 403/404/419/429/500 pages | written |
| Tests: login, access control, registration, password reset, staff command (~30 cases) | written, **not run** |

Not in this milestone: roles/permissions (M3 - sidebar entries are listed but greyed out until their pages exist), profile page, mobile/email OTP verification, MFA for administrators, "log out other sessions" screen, institute/branding from the database.

## Decisions recorded (your answers)

- Fees: every fee line that has an amount in the backend is added to the payable amount; blank/zero lines are not. College circulars fill admission/uniform fees (collected with the application); school circulars leave them blank (collected after selection - that later payment is not built yet). The planned `collect_at` column is dropped.
- Roll number is allocated when a payment succeeds. Admit card and application form are downloadable after payment success; no approval needed.
- SMS: a provider-agnostic interface with a log driver will be added so a provider can be plugged in later.
- Payments: adapters for several gateways, but only **one active method at a time**; the student sees only that one. First live set: Manual and ShurjoPay.
- Hosting: cPanel, PHP 8.3+, Composer/SSH available. Please confirm a cron job can run `php artisan schedule:run` every minute (needed for queued notifications and exports).

## Known limitations

- `style-src 'unsafe-inline'` is kept in the production CSP until all inline styles in later pages are removed.
- E-mail verification is not enforced yet; password reset needs working `MAIL_*` settings.
- Registration reveals whether a username/mobile/e-mail is already taken (needed for usability); it is rate limited.
- Independent security testing is still required before production use.

## Update: public home page
`/` now shows a simple public home page (brand, institute name, one **Login** button) - `resources/views/home.blade.php`, to be redesigned later. Signed-in users are redirected to their own panel. The skeleton's `tests/Feature/ExampleTest.php` (expects 200 at `/`) now passes; the old redirect test was replaced.

---

# Milestone 3 - roles, permissions, policies, audit log, User Roles / User List pages

## Apply (on top of the M1-M2 project)

```bash
unzip -o ../admission-system-m1-m2-overlay.zip     # same ZIP, now contains M3 files too
npm run build                                      # new page scripts (roles.js, users.js)
php artisan migrate                                # 4 new migrations: roles, role_permissions, users.role_id, audit_logs
php artisan admission:assign-role you@example.com super-admin   # REQUIRED once for staff created in M2 (they have no role yet)
php artisan test
```
Without a role a staff account can log in but every admin page returns 403. New staff: `php artisan admission:make-staff you@example.com --role=super-admin` (default role is super-admin).

## Design decisions

- **Own small RBAC instead of `spatie/laravel-permission`.** I could not verify here that the package supports Laravel 13, and the need is small (one role per staff user, permission keys). No new Composer dependency. Permissions are keys like `circular.add`, registered in `config/permissions.php`; adding a permission = one line in that file.
- **One role per staff account** ("User Type" in the UI). Students have no role and can never reach `/admin`.
- **Super Admin is implicit and locked**: always every permission, cannot be edited, deleted or deactivated from the UI; at least one active Super Admin must exist; nobody can change their own role or deactivate themselves.
- **Super Admin does not bypass record-level policies** (locked role, own account, last Super Admin).
- **No privilege escalation**: you can only grant a permission you hold yourself, only assign roles that are not more powerful than yours, and only edit/deactivate/reset accounts whose permissions you fully hold.
- **Role / status / password changes end that user's sessions and remember-me tokens** (database session driver).
- Built-in roles (Super Admin, Admin, Admission Officer, Accountant, Viewer) are created by a migration; their default permissions are in `config/permissions.php` -> `defaults` and can be changed from the User Access tab afterwards. Admin does not get user/role management, audit log or payment-method settings.
- Sidebar shows only entries the user may open; every route is also protected by `can:` middleware and policies.
- **Audit log** (`audit_logs`): role created/updated/deleted, permission changes (added/removed), user created/updated/activated/deactivated, console role assignment. Passwords and tokens are scrubbed. The model refuses update/delete; there is no UI to change entries. Limitation: someone with direct database access can still alter rows - restrict DB access and keep backups.

## Pages (converted from user-roles.html and user-list.html, same markup and classes)

| Page | Route | Permission |
|---|---|---|
| User Roles (User Type + User Access tabs) | /admin/settings/roles | roles.view / add / edit / del |
| User List (search, add, edit, activate/deactivate, generate password) | /admin/settings/users | users.view / add / edit |
| Audit Log (new, read-only) | /admin/audit-logs | audit.view |

Tests added: permission gates and default roles, route protection, sidebar filtering, role CRUD and escalation guards, user CRUD, session revocation, self/last-admin protection, audit immutability, console commands (~45 more cases). **Not run yet.**

Known limits: no forced password change on first login, no MFA yet, applicant/circular/payment pages still pending (greyed in the sidebar), `Payment Method` entry waits for its page.

---

# Milestone 4 - institute profile, classes & sessions, circulars and class setup

## Apply

```bash
unzip -o ../admission-system-m1-m4-overlay.zip
npm run build                      # new page scripts
php artisan migrate                # 4 new migrations (institutions, master lists, circulars, class setups)
php artisan test
```
`.env`: add `APP_TIMEZONE=Asia/Dhaka` (circular open/close times are stored and shown in this timezone) and set `INSTITUTE_NAME` **before** migrating if you want the first institute name pre-filled (it can also be edited later). The logo feature needs PHP's **GD** extension (standard on cPanel; select it in "Select PHP Version" if missing).

## What is new

| Page | Route | Permission |
|---|---|---|
| Institute Profile (logo, name, short name, address, phone, e-mail, web, upload limits and formats) | /admin/settings/institute | institute.view / edit |
| Classes & Sessions - tabs: Class, Version, Sessions/Year, Shift, Group, **Category**, **Document** | /admin/settings/master/{type} | classes.view / add / edit / del |
| Manage Circular (list, New Circular School/College, edit, archive/restore, delete) | /admin/circulars | circular.view / add / edit / del |
| Circular Classes: class list, **Class Setup** (2-step modal, several classes at once, also used for editing), **Configure Downloads** | /admin/circulars/{id} | circular.view / edit |

## Configurable without code changes

Fees per class (form, admission, uniform, bank charge), available seats, **category quotas** (optional seats per category), **required documents** per class, version / shift / group / category choices, gender rule, age limit, roll type and prefix, lottery / viva / written / result times, circular open and close time, circular status, unique-birth-ID rule, which documents students may download, admit-card instruction text. Classes, versions, shifts, groups, sessions, categories and document types are database lists you maintain on the Classes & Sessions page - nothing is hardcoded in Blade or JavaScript any more (including "groups are required for Nine and above": it is the **Uses groups** flag on the class).

## Decisions and assumptions

- **Fees are four columns on the class setup**, not a separate table. Payable with the application = every fee line whose amount is above zero (`CircularOffering::payableMinor()`, integer paisa, never floats). A school circular leaves Admission/Uniform fee empty; a college circular fills them. The later school payment after selection is not built yet.
- **Circular status** is stored as `draft | published | archived`; `scheduled / open / closed` are derived from the dates (`effectiveStatus()`, `isOpen()`, `AdmissionCircular::open()` scope). The UI "Is Active" switch = published / draft. The server will use `isOpen()` to reject applications outside the window (Milestone 5). An archived circular cannot be changed; restoring returns it as a draft. A circular with class setups cannot be deleted, only archived.
- **Session lives on the class setup** (as in the UI), unique per circular + class + session. Re-saving the same class and session updates it.
- **Master records in use** by a class setup can be deactivated but not deleted; inactive records cannot be chosen in a new setup. New records are appended at the end of the list (no re-ordering screen yet).
- **Logo**: PNG, JPG or WebP up to 1 MB. **SVG is not accepted** (it can carry scripts). The image is decoded and re-encoded as a PNG (max 512 px) and stored on the private disk; it is served by `/branding/logo`, so no `storage:link` is needed on cPanel.
- **Single institute**: one `institutions` row is created by the migration; `Institution::current()` returns it. Circulars and sessions carry `institution_id` so isolation can be added later.
- The UI's "Select institute" dropdown is replaced by the institute name; "Mode: Flexible" (college circular) and the "Hosted URL" semantics were unclear: Hosted URL is kept as the portal domain, Flexible mode is not implemented.
- The supplied Bangla admit-card instruction text is HCPSC-specific, so it is **not** shipped as a default: paste it into Configure Downloads for your circular.
- "Applied", "Amount" and the class "Applied amount" columns show `-` until applications exist (Milestone 5). Report downloads, Interview Schedule and Previous Academic Info Setup stay greyed out.

Tests added: institute profile and logo handling, master data CRUD and in-use guard, circular rules and statuses, class setup (multi-class, groups, quotas, age limit, fee lines, inactive records), download settings, permissions (~45 cases). **Not run yet.**

---

# Milestone 5 - student application, documents, submission and staff review

## Apply

```bash
unzip -o ../admission-system-m1-m5-overlay.zip
npm run build
php artisan migrate                # 3 new migrations: document step, number sequences, applications and related tables
php artisan test
```
GD is needed for photograph uploads (the tests that upload images skip themselves if GD is missing).

## What is new

**Student side** (`/student`)
- Student Panel: pick Class (and session if a class is offered twice), Version, Shift, Group (only for classes that use groups) and Category. The drop-downs only list what the open circular's class setup allows, and the server re-checks every id. Application cards show status, tracking and application numbers.
- 5-step form (Student Information, Guardian Information, Address, Previous Academic Info, Review) - every step is a separate server-validated save, the draft survives logging out, and each box (photo / document) appears only if the class setup requires or lists it.
- Review shows the whole application; **Submit** needs the declaration. After submission the application is locked; the student can only open the read-only view until staff ask for a correction.
- Tracking number (8 random digits, display only) at start, application number (`AP` + circular + 6-digit sequence) at submission.

**Staff side** (`/admin`)
- **Applicant List** (`applicant-list.view`): search by tracking no, application no, name or mobile; filters for circular, class, version, shift, group, category, gender, status, submission dates; sorting (allow-listed) and database pagination. Unfinished drafts are never listed.
- **Application detail**: full review summary, documents (open in a new tab through the authorised route), status history. Identity numbers (birth registration, NID) are masked unless the user has `applicant-edit.view`.
- **Status workflow** (`applicant.status`): submitted -> under review / approved / rejected / waitlisted / correction requested, with the allowed transitions in `Application::TRANSITIONS`, a required remark for reject and correction, history rows and audit entries. Approve and reject cannot skip the allowed path (for example approved -> rejected must go through under review).
- **Document verification** (`document.verify`): verify or reject with a remark; a replaced file goes back to "pending".
- **Dashboard** now shows real counts (submitted, today, under review, approved, waitlisted, rejected, correction requested, drafts), applications by class and recent applications, using grouped queries.

## Security decisions

- Student routes need a student account; every record is checked by `ApplicationPolicy` (owner only, and only while draft or correction requested). Applications are addressed by UUID; numeric ids and tracking numbers give 404.
- Nothing about an application is mass-assignable; owner, status, numbers and fee are set by the services. The fee is always read from the class setup and **snapshotted at submission** (later fee changes do not change an application).
- Submission is transactional: it locks the row, re-validates every step from the stored data plus age, gender, required documents and the duplicate rule, and a second submit changes nothing. Application numbers come from a locked counter (`number_sequences`).
- **Duplicate prevention**: one application per student per class setup and version (database unique index); and when a circular has "Is Student Birth ID Unique" on, an HMAC of the birth registration number is stored with a unique index per circular (the number itself is never used as the key).
- Birth registration number and NID are **encrypted at rest** (Laravel encrypted cast; the key is `APP_KEY`, so back it up and never change it after real data exists).
- **Uploads**: validated by detected content type and size limits from the Institute Profile, stored on the private disk under a random name, photographs and image scans re-encoded as PNG, PDFs checked for the PDF signature, executable/other types refused. Files are served only by `/applications/{uuid}/documents/{id}` after a policy check (`private, no-store`, `nosniff`); staff views are audited (`document.viewed`).
- Corrections reopen only what staff requested through the status change; a resubmission keeps its application number.

## Assumptions and limits

- Page structure: each step is its own page with a stepper (instead of one page with JavaScript panes) so every step is validated and saved on the server.
- Address is still one free-text line per address (division/district/upazila can be added later as optional columns).
- **Seats are not enforced when applying** (they are a capacity for admission, used for reporting later).
- Required documents are those chosen per class setup; each document type has a "Shown in form step" setting (1 student, 2 guardian, 4 other) on Classes & Sessions > Document.
- Remarks entered with a status change are visible to the student (there are no staff-only notes yet).
- **Not in this milestone**: payment (the payable amount is shown, "Pay" follows in Milestone 6), roll numbers, admit card and application PDF, e-mail/SMS notifications, Applicant Info Update (staff editing of a form), bulk actions, Excel/PDF exports, the "applied" columns on the circular pages.

Tests added: application flow, validation, age/gender/duplicate rules, submission and idempotency, correction loop, document upload and access, review list/filters/masking, status workflow, authorization (~65 cases). **Not run yet.**
