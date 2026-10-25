# SDD Project Backend

Laravel 13 backend for the trainer/trainee/club platform. Features a Filament admin panel for operations and an Inertia React frontend for users.

## Stack

- **PHP 8.4** with Laravel 13
- **MySQL 8.4** for persistence
- **Filament 5** for the operations admin panel
- **Inertia v3 + React** for the web frontend
- **Pest 5** for testing
- **Pint** for code formatting
- **Larastan** for static analysis

## Requirements

- PHP 8.4 with `pdo_mysql` extension
- Composer 2
- Node.js 22
- MySQL 8.x

## Local Setup

1. **Clone the repository:**
   ```bash
   git clone https://github.com/7at7ot777/Spec-Drivien-Development-Project.git sdd-project && cd sdd-project
   ```

2. **Create the local database:**
   ```bash
   mysql -uroot -e "CREATE DATABASE sdd_project CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   ```

3. **Install and run migrations:**
   ```bash
   cp .env.example .env            # edit DB_* if your MySQL differs
   composer setup                  # installs dependencies, generates APP_KEY, runs migrations, builds assets
   ```

4. **Start the dev server:**
   ```bash
   composer dev                    # runs at http://localhost:8000
   ```

## Running Tests

Tests run on in-memory SQLite (configured in `phpunit.xml`), so no MySQL setup is needed for tests:

```bash
php artisan test --compact
```

Or run the full lint + type check + test suite:

```bash
composer test
```

## Environment Variables

The `.env.example` file documents every required variable, grouped by feature:

| Group | Variables | Story |
|-------|-----------|-------|
| **App** | `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL` | — |
| **Database** | `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | — |
| **Mail** | `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` | — |
| **Media Storage** | `MEDIA_DISK`, `MEDIA_MAX_VIDEO_MB`, `MEDIA_SIGNED_URL_TTL_MINUTES` | KAN-35 |
| **SMS** | `SMS_DRIVER`, `SMS_API_URL`, `SMS_API_KEY`, `SMS_SENDER_ID` | KAN-55 |
| **Fawry Payments** | `FAWRY_BASE_URL`, `FAWRY_MERCHANT_CODE`, `FAWRY_SECURITY_KEY`, `FAWRY_CALLBACK_URL` | KAN-37, KAN-42 |

Keep `.env` out of git. `.env.example` documents defaults and is always committed.

## Folder Conventions

- **`app/Actions/<Area>/`** — Single-purpose business operations. Each class has one public `handle()` method. Example: `app/Actions/Teams/CreateTeam.php`.
- **`app/Services/`** — Integrations with external systems (payment gateways, SMS providers). Created when the first service lands.
- **`app/Http/Controllers/<Area>/`** — Thin controllers. Validate via Form Request, call an Action, return a Resource or Inertia response.
- **`app/Http/Requests/<Area>/`** — One Form Request per write endpoint. Grouped by area.
- **`app/Http/Resources/`** — Eloquent API Resources for all API responses. Created in KAN-15.
- **`app/Data/`** — Plain DTOs. **`app/Enums/`** — Backed enums with TitleCase keys.
- **`app/Policies/`** — Authorization logic per model.
- **`app/Rules/`** — Custom validation rules.
- **`app/Concerns/`** — Shared traits.
- **`tests/Feature/<Area>/`** and **`tests/Unit/`** — Pest tests, mirroring `app/` area names.

## Development Workflow

Features come from the Jira project **KAN**. We use squad-kit to plan each story:

1. **Create a story intake** from a Jira issue:
   ```bash
   squad new-story <feature> --id KAN-NN
   ```

2. **Generate a plan** with the planner:
   ```bash
   /squad-plan .squad/stories/<feature>/KAN-NN/intake.md
   ```

3. **Implement the plan** — read `.squad/plans/<feature>/NN-story-….md` and execute the tasks.

**Credentials:** Every developer runs `squad config set tracker` in a real terminal (not an SSH session) to save their Jira email and API token to `.squad/secrets.yaml` (git-ignored). Squad-Kit also reads from `JIRA_EMAIL` and `JIRA_API_TOKEN` env vars if set.

## Running Locally

Start the dev server:
```bash
composer dev
```

The app runs at `http://localhost:8000` with hot reload for Vite assets.

## Code Quality

Format code before commits:
```bash
vendor/bin/pint --dirty --format agent
```

Check types and lint:
```bash
composer lint:check
```

## Next Steps

- **KAN-15:** Set up the API foundation (`/api/v1`, Sanctum, JSON response format).
- **KAN-16:** Implement Arabic / English localization.
- **KAN-17:** Register the Filament admin panel.
- See the Jira board for the full roadmap.
