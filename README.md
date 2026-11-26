# የየም ዞን ግብርና መምሪያ — ክትትልና ሪፖርት ሲስተም (Agri)

Yem Zone Agriculture Department monitoring & reporting system — **Phase 1 (MVP)** + **Phase 2: weekly plan/actual matrix**.
Laravel 12 · MySQL/MariaDB · Blade (no front-end build step) · Amharic UI.

Phased plan (Amharic): see the plan document shared with the project (`yem-agri-monitoring-plan.md`).

## Phase 1 features

- Hierarchy **ዞን → ወረዳ → ቀበሌ** (`locations`), seeded with Yem Zone and the woredas from the weekly matrix
- Roles: `kebele` (ቀበሌ ባለሙያ), `woreda` (ወረዳ ኃላፊ), `zone` (ዞን ኃላፊ) — each sees only their own scope
- Daily extension tracking form (farmers served, new/repeat, female, youth 18–35, service type, technique, duration)
- Approval workflow: ረቂቅ → ተልኳል → **ወረዳ አረጋግጥ** → **ዞን አጽድቅ** (return with reason at any review step; full action history)
- Woreda / zone / kebele dashboards with the 5–10 farmers/day alert bands (daily, weekly, monthly), and a list of experts who did not report on the last working day
- Excel export (reports list + expert performance), respecting role scope and filters
- User and location management (zone: all; woreda: kebele experts in its woreda)
- Ethiopian date shown next to every date

## Phase 2 — weekly plan / actual performance matrix (ሳምንታዊ ዕቅድ/ክንውን ማትሪክስ)

Implements `agriculture-weekly-plan-report.docx`: strategic goal → activity (መለኪያ: ሄ/ር, ኩ/ል, ቁጥር, ቶን, ሊትር) → ዞናዊ ድምር (ዕቅድ, ክንውን) → woreda performance.

- **Structure** — `strategic_goals`, `plan_activities` (seeded from the Word matrix: 7 goals, 48 activities, 2018 zone plan; zone can add/edit/disable under **ዕቅድ → ግቦችና ተግባራት**)
- **Plan** — `plan_targets`: zone sets the yearly plan per activity (**ዕቅድ**) and splits it top-down to woredas (manually or equally); the "ያልተከፋፈለ" column shows what is left to allocate
- **Weekly actuals** — each woreda officer enters the week's actuals (**ሳምንታዊ ክንውን**), saves a draft, then submits. Flow: ረቂቅ → ተልኳል → **ዞን አጽድቅ** (or **ተመልሷል** with a mandatory reason, woreda fixes and resubmits). Every step is stored in `weekly_plan_actions`. Blank = not reported, `0` = a real zero.
- **Matrix** (`/matrix`) — cumulative totals from the start of the plan year up to a chosen week, or a single week; submitted + approved reports count (tick **የጸደቁ ብቻ** for approved only). **Zone actual = sum of the woreda actuals** (automatic aggregation). Optional woreda plan and % columns. Zone sees all woredas + zone totals, woreda officers only their own woreda, kebele experts have no access.
- **Excel** — `/exports/matrix` reproduces the Word layout (merged goal cells, ዞናዊ ድምር / የመዋቅሮች አፈፃፀም headers) with the same filters.
- **Dashboards** — zone: weekly reports waiting for approval and woredas that did not submit last week; woreda: returned reports.
- **Plan year** — named after the Ethiopian year it ends in (2018 = ሐምሌ 1, 2017 – ሰኔ 30, 2018). Change the start month with `PLAN_FISCAL_YEAR_START_MONTH` (`config/plan.php`; `1` = plain Ethiopian calendar year). Weeks run Monday–Sunday.
- Demo seed: the Word matrix's cumulative actuals are loaded as one approved week per woreda, and the zone plan is split equally to the woredas so the % columns have values. Production seeding loads only the structure and the zone plan.

Deferred (Phase 2 remainder / later): reports #2–#8 (area by crop, farmer counts, fertilizer warehouse, staff, training, zone inputs, seed supply), digital signature, SMS/e-mail reminders, PWA, farmer feedback (QR), PDF, comparisons, maps, native mobile app.

## Setup

```bash
composer install
cp .env.example .env          # set DB_* for your MySQL
php artisan key:generate
php artisan migrate --seed    # local env also seeds demo users + 2 weeks of sample reports
php artisan serve
```

Create the database first, e.g. `CREATE DATABASE agri CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`

### Demo accounts (local / `SEED_DEMO=true` only, password `password`)

| Role | Email |
|---|---|
| ዞን | `zone@yem.test` |
| ወረዳ | `woreda.fofa@yem.test`, `woreda.toba@yem.test`, `woreda.skar@yem.test`, `woreda.dsz@yem.test` |
| ቀበሌ | `kebele.fofa.1@yem.test` … `kebele.<woreda>.<1-3>@yem.test` |

### Production

Do not seed demo data. Create the first zone administrator by setting `ADMIN_EMAIL`, `ADMIN_PASSWORD` (and optionally `ADMIN_NAME`) before running `php artisan db:seed`, then add woredas/kebeles/users from the UI.
The woreda names `ሳ/ከ/አስ/ር` and `ደ/ሳ/ዙ/ወረዳ` are abbreviations copied from the matrix. They are shown as best-guess expansions (`ሳጃ ከተማ አስተዳደር (ሳ/ከ/አስ/ር)`, `ደ/ሳጃ ዙሪያ ወረዳ (ደ/ሳ/ዙ/ወረዳ)`) with a red **ግምት — ያረጋግጡ** badge, and the kebeles are numbered placeholders (`ቀበሌ 01` … `ቀበሌ 12`) with an amber **ናሙና — ሊስተካከል የሚችል** badge. Rename them under **አካባቢዎች** ("ስም ቀይር"); saving removes the badge and re-running `db:seed` never overwrites a renamed row. Codes (`YEM-FOFA-K1` …) never change.

## Configuration

`config/extension.php` — alert thresholds (`EXT_LOW_BELOW=5`, `EXT_TARGET_FROM=8`, `EXT_TARGET_TO=10`), working days (`EXT_WORK_DAYS=1,2,3,4,5`), service types and techniques.

| Daily farmers | Band |
|---|---|
| < 5 | ዝቅተኛ — ማስጠንቀቂያ |
| 5 – 7 | ተቀባይነት ያለው |
| 8 – 10 | ግብ ላይ ደርሷል |
| > 10 | ከመጠን በላይ — ወረዳ ያረጋግጣል |

Weekly / monthly view uses total farmers ÷ days with a submitted report. Draft and returned reports are not counted.

## Data polish, audit trail, permissions (config)

| Setting | Default | What it does |
|---|---|---|
| `PLAN_KEBELE_WEEKLY_ENTRY` | `false` | Kebele experts enter weekly actuals at `/kebele-weekly`; the woreda weekly form is pre-filled with the sum of its kebeles (and shows how many kebeles reported). The matrix only counts woreda reports, so kebele rows are never double counted. |
| `AUDIT_ENABLED` | `true` | Audit trail (`/audit`, zone only): create/update/delete of reports, weekly reports, plan targets/activities/goals, locations, users, feedback, dispatches, role permissions, plus login / failed login / logout. Passwords and signature data are never stored; seeder/console writes are not logged. |
| `SMS_DRIVER` | `log` | SMS port (`App\Contracts\SmsGateway`). `log` writes the message to the log (stub, no paid gateway), `null` discards. Add a driver in `AppServiceProvider` once a gateway contract exists. |
| `EXT_SMS_REMINDERS` | `false` | Also send missing-report reminders through the SMS port to users that have a phone number. |

**Role permissions (ፈቃዶች).** `config/permissions.php` holds the default capabilities per role; the zone can switch them on/off per role at `/admin/permissions` (stored in `role_permissions`, audited). Only *what a role may do* is configurable (matrix/map access, weekly entry, weekly approval, plan editing, user/location management, audit view, kebele weekly entry). Record-level rules (a kebele edits only its own drafts, a woreda sees only its kebeles, final approval is the zone's) are structural and not configurable; `permissions.manage` is locked to the zone so it cannot lock itself out.

**Matrix data quality.** Zone users see an admin-notes panel on `/matrix` with documented unit/plan doubts (`config/plan.php` → `known_issues`) and automatic outlier flags (actual > 3× plan or < 1% of plan). The `ባህላዊ ቀፎ` plan `578001806` in the source Word file is an obvious digit-run typo and is seeded as `57,800` (the seeder repairs the old value only if it is untouched). Units written as ditto marks in the source were resolved by meaning — confirm the doubtful ones (`ኮረሪማ ልማት`, `በመደበኛ ኮምፖስት ዝግጅት`) on the plan screens. See the comment block in `database/data/weekly-matrix-2018.php`.

**Map boundaries.** `public/geo/yem-woredas.geojson` uses the real outer outline of the former *Yem Special Woreda* from geoBoundaries (gbOpen ETH ADM3, Open Africa / Code for Ethiopia 2016, ODbL). No public dataset publishes the four current woreda boundaries, so the inside split is a clearly labelled **schematic** quadrant split; replace each feature's geometry with the official boundary (CSA / OCHA / zone GIS office) keeping `properties.code`.

### SSO / 2FA (scaffold notes only — not implemented)

Authentication is local email + password (Laravel session guard, throttled login, audited). SSO and 2FA are intentionally **not** half-built. Suggested path when the zone/region chooses an identity provider: (1) SSO — add `laravel/socialite` (or an OIDC/SAML package such as `socialiteproviders/*`) behind a new `auth.driver` setting, map the IdP subject/e-mail to `users.email`, keep `is_active` and `role/location_id` assignment local, and keep the password login as break-glass for the zone admin; (2) 2FA — add TOTP (e.g. `pragmarx/google2fa-laravel`) with a `two_factor_secret` column on `users`, a challenge step after `Auth::attempt` in `AuthController::login`, recovery codes, and enforce it first for zone/woreda roles through `config/permissions.php`. Native mobile apps are out of scope; the PWA covers mobile use.

## Tests

```bash
php artisan test
```
