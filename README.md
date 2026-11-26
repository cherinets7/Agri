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
The woreda names `ሳ/ከ/አስ/ር` and `ደ/ሳ/ዙ/ወረዳ` are abbreviations copied from the matrix and the kebeles are placeholders — rename them under **አካባቢዎች**.

## Configuration

`config/extension.php` — alert thresholds (`EXT_LOW_BELOW=5`, `EXT_TARGET_FROM=8`, `EXT_TARGET_TO=10`), working days (`EXT_WORK_DAYS=1,2,3,4,5`), service types and techniques.

| Daily farmers | Band |
|---|---|
| < 5 | ዝቅተኛ — ማስጠንቀቂያ |
| 5 – 7 | ተቀባይነት ያለው |
| 8 – 10 | ግብ ላይ ደርሷል |
| > 10 | ከመጠን በላይ — ወረዳ ያረጋግጣል |

Weekly / monthly view uses total farmers ÷ days with a submitted report. Draft and returned reports are not counted.

## Tests

```bash
php artisan test
```
