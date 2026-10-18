# HeatAlert

HeatAlert is a web application that warns residents about dangerous heat. It follows the weather forecast of each monitored neighborhood, raises heat alerts (automatically or by an administrator), shows them on an interactive map, and notifies the people who live in the affected area. It also brings together practical information for heat waves: cool spots, safety advice and power outages.

## Features

| Area | What it does |
|---|---|
| **Heat alerts and neighborhoods** | Alerts by neighborhood with severity (low, medium, high), dates and optional picture. A red banner appears above the navbar when a high alert is active. |
| **Weather** | Forecasts from Open-Meteo (no API key). A forecast of 35 °C raises a medium alert and 40 °C a high alert, created automatically every 3 hours. |
| **Interactive map** | OpenStreetMap (Leaflet), colored risk zones, temperatures, population and residences per zone. Users can report a heat spot by clicking the map. |
| **AI advisor** | Safety brief per zone, a question box, and AI-drafted alert text. Works with Google Gemini or Claude, and falls back to rule-based text when no key is set. |
| **Users and roles** | Resident, residence manager and administrator. Managers create residences (approved by an admin) and add residents to them. |
| **Account security** | Registration, email verification, password reset, two-factor authentication (QR code and recovery codes), profile page with photo, account deletion. |
| **Community modules** | Cool spots, safety advice, power outages and outage reports. |

## Tech stack

- **Backend:** Laravel 12, PHP 8.2+ (CI and Docker run PHP 8.3), MySQL 8
- **Authentication:** Laravel Fortify (verification, reset, 2FA)
- **Frontend:** Blade views, Tailwind CSS 4, Vite 7, Leaflet
- **Background work:** database queue (notifications), Laravel scheduler (weather sync)
- **AI:** Google Gemini (REST) or Anthropic Claude (official PHP SDK), chosen by configuration
- **Tests:** Pest
- **DevOps:** Docker, Docker Compose, GitHub Actions, GitHub Container Registry

## Quick start (local)

Requirements: PHP 8.2+, Composer, Node 20.19+ (22 recommended), MySQL running on port 3306.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Create an empty MySQL database named `heatalert` and check the `DB_*` values in `.env`, then:

```bash
php artisan migrate:fresh --seed
npm run build            # or: npm run dev (keeps rebuilding while you edit)
php artisan serve        # http://127.0.0.1:8000
```

In two more terminals, start the background processes:

```bash
php artisan queue:work       # sends the notification emails
php artisan schedule:work    # runs the weather sync every 3 hours and the alert notifications
```

### Demo accounts

All demo accounts use the password `password`.

| Role | Email |
|---|---|
| Administrator | `admin@heatalert.test` |
| Residence manager (La Marsa) | `sami.manager@heatalert.test` |
| Residence manager (Ariana) | `leila.manager@heatalert.test` |
| Resident | `amine@heatalert.test`, `sarra@heatalert.test`, `karim@heatalert.test` |

### Configuration (`.env`)

| Variable | Purpose |
|---|---|
| `DB_*` | MySQL connection |
| `MAIL_*` | SMTP settings. Use `MAIL_MAILER=log` to write emails to `storage/logs/laravel.log`, or a Mailtrap or Gmail SMTP account for real delivery. |
| `GEMINI_API_KEY`, `GEMINI_MODEL` | Enables the AI advisor with Google Gemini (default model `gemini-flash-latest`) |
| `ANTHROPIC_API_KEY` | Enables the AI advisor with Claude when no Gemini key is set |
| `AI_PROVIDER` | Optional: force `gemini` or `anthropic` |
| `HTTP_CA_BUNDLE` | Optional path to a CA bundle when PHP has no certificates configured (common on Windows) |
| `MAPBOX_TOKEN` | Optional: adds Mapbox map layers |

Never commit `.env` files or API keys. Both `.env` and `.env.docker` are ignored by Git.

### Useful commands

```bash
php artisan test                 # run the Pest suite
vendor/bin/pint                  # fix code style
php artisan weather:sync         # refresh the weather and automatic alerts now
php artisan alerts:notify        # notify residents about active alerts not announced yet
```

The test suite uses SQLite in memory by default. If your PHP has no `pdo_sqlite`, run it against a throwaway MySQL database: `DB_CONNECTION=mysql DB_DATABASE=heatalert_testing php artisan test`.

## Architecture

```
app/
  Actions/Fortify/        user creation, profile and password rules (Fortify)
  Console/Commands/       weather:sync, alerts:notify
  Contracts/              AiTextGenerator interface (swap Gemini / Claude)
  Enums/                  Severity (labels, colors, advice, thresholds)
  Http/
    Controllers/          web controllers (+ Admin/ and Api/V1/)
    Requests/             form validation (heat alerts, neighborhoods)
    Resources/            JSON resources for the API and the map
    Responses/            role-based redirects after login, register, logout
  Models/                 User, Neighborhood, HeatAlert, Residence, ...
  Notifications/          HeatAlertIssued (mail + database)
  Policies/               authorization rules for residences and reports
  Services/               WeatherForecastService, HeatAdvisorService, AI generators
  View/Components/        danger banner
database/                 migrations, factories, seeders
resources/views/          layouts, front office, back office, partials
routes/                   web.php, api.php, console.php (scheduler)
tests/Feature/            Pest feature tests
```

### How the pieces fit together

- **Weather to alerts:** `WeatherForecastService` calls Open-Meteo for every neighborhood that has coordinates, stores the temperatures, then creates, updates or closes an automatic alert. New or escalated alerts notify the residents of the neighborhood through a queued notification.
- **Who gets notified:** the owners and members of approved residences located in the neighborhood.
- **Roles:** enforced on the server by the `admin` middleware and by policies, not only by what the interface shows.
- **Residence approval:** a manager creates a residence, which stays pending until an admin approves it. Only then can the manager add residents.
- **Map:** `GET /map/data` returns zones, alerts and (depending on the role) the residences of each zone. Residences are never listed for guests.
- **AI:** controllers call `HeatAdvisorService`, which depends on the `AiTextGenerator` interface. The provider is selected in `AppServiceProvider` from the configuration.
- **Public API:** `GET /api/v1/alerts/active` and `GET /api/v1/neighborhoods`.

## DevOps

### Docker architecture

The `Dockerfile` is multi-stage, so the final images contain only what they need:

| Stage | Base image | Purpose |
|---|---|---|
| `assets` | `node:22-alpine` | Installs npm packages and builds the Vite and Tailwind assets |
| `vendor` | `composer:2` | Installs the PHP dependencies |
| `app` | `php:8.3-fpm-alpine` | Runtime: PHP-FPM with the code, `vendor/` and the built assets |
| `web` | `nginx:1.27-alpine` | Serves static files and forwards PHP requests to the `app` container |

`docker-compose.yml` runs five services:

```
                +-----------+      FastCGI      +-----------+
 browser  --->  |  web      | ----------------> |   app     |
  :8080         |  (nginx)  |                   | (PHP-FPM) |
                +-----------+                   +-----+-----+
                                                      |
              +-----------+   +-----------+     +-----v-----+
              |  queue    |   | scheduler |     |    db     |
              | queue:work|   |schedule:  | --> |  MySQL 8  |
              |           |   |   work    |     +-----------+
              +-----------+   +-----------+
```

| Service | Role |
|---|---|
| `web` | nginx on port 8080, also serves uploaded files from the shared storage volume |
| `app` | PHP-FPM running the application; runs the migrations on start |
| `queue` | `php artisan queue:work`, sends the queued emails |
| `scheduler` | `php artisan schedule:work`, runs the weather sync every 3 hours |
| `db` | MySQL 8.4 with a persistent volume and a health check |

Startup order: `db` becomes healthy, then `app` prepares the application (`docker/entrypoint.sh` fixes file ownership, caches the configuration, routes and views, runs the migrations, then creates a readiness marker). `web`, `queue` and `scheduler` only start once `app` reports healthy, so no worker runs before the database is migrated. Workers run as the unprivileged `www-data` user.

Run the stack:

```bash
cp .env.docker.example .env.docker
# put a key in APP_KEY:  php artisan key:generate --show   (add your Gmail / Gemini values if you have them)
docker compose up --build
docker compose exec -u www-data app php artisan db:seed --force    # demo data, first run only
# open http://localhost:8080
```

### CI/CD pipeline (GitHub Actions)

**CI (Continuous Integration)** means every change is checked automatically, so a broken change is caught before it is merged. **CD (Continuous Delivery)** means a version that passed is packaged automatically into a ready-to-run artifact. Here the artifact is a Docker image. Two workflow files implement this, and you can follow every run in the **Actions** tab of the GitHub repository.

```
 PUSH to main / develop / member branches,  or PULL REQUEST into main / develop
                                |
                                v
   +------------------------------- ci.yml  [CI] -------------------------------+
   |  1. lint (Pint)    2. tests (Pest)                                          |
   |        \               /                                                    |
   |         +-- both pass -+--> 3. docker image build (check only, no push)     |
   +-----------------------------------------------------------------------------+

 PUSH to main  or  TAG v*   (starts at the same time as CI, see the note below)
                                |
                                v
   +----------------------------- deploy.yml  [CD] ------------------------------+
   |  build the production image  -->  push it to GitHub Container Registry      |
   +-----------------------------------------------------------------------------+
```

#### When does it run?

| Event | `ci.yml` (CI) | `deploy.yml` (CD) |
|---|---|---|
| Push to `main`, `develop`, `jasser`, `aziz`, `NizarChaieb` | Yes | Only on `main` |
| Pull request into `main` or `develop` | Yes | No |
| Tag starting with `v` (for example `v1.0.0`) | No | Yes |

#### `ci.yml`: Continuous Integration (CI)

Three jobs. `lint` and `tests` run in parallel on separate fresh Ubuntu machines. `docker` waits for both (`needs: [lint, tests]`), so it only runs if they pass.

**Job 1: Code style (Pint)** is a CI step: it checks the quality of the code.

| Step | What it does |
|---|---|
| Checkout | Downloads the repository code onto the runner. |
| Setup PHP 8.3 | Installs PHP 8.3 and Composer 2. |
| Cache `vendor` | Restores the Composer packages from a previous run. The cache key is the hash of `composer.lock`, so it is rebuilt only when dependencies change. |
| `composer install` | Installs the PHP dependencies exactly as locked. This step fails if `composer.lock` does not match the PHP platform, which is why the platform is pinned to PHP 8.2 in `composer.json`. |
| `vendor/bin/pint --test` | Checks the code against the Laravel style rules without modifying any file. It fails if any file is badly formatted. |

**Job 2: Tests (Pest)** is a CI step: it proves the application still behaves correctly.

| Step | What it does |
|---|---|
| Checkout | Downloads the code. |
| Setup PHP 8.3 | Installs PHP with the extensions the app needs (`mbstring`, `pdo_sqlite`, `intl`, `bcmath`). `pdo_sqlite` is needed because the tests use an in-memory SQLite database. |
| Cache `vendor` and `composer install` | Same as in the lint job. |
| `cp .env.example .env` and `key:generate` | Creates the environment file and the application key the framework needs to boot. |
| Setup Node 22 | Installs Node and caches the npm packages. |
| `npm ci` and `npm run build` | Installs the front-end packages and builds the Vite and Tailwind assets. The pages load these assets, so the tests need them. |
| `php artisan test --compact` | Runs the whole Pest suite. Any failing test fails the job. |

**Job 3: Docker image build** is a CI step: it proves the Dockerfile still works. It does not publish anything.

| Step | What it does |
|---|---|
| Checkout | Downloads the code. |
| Setup Buildx | Enables the Docker build engine with layer caching. |
| Build (`push: false`) | Builds the image from the Dockerfile: assets, PHP dependencies, PHP-FPM runtime. The image is discarded afterwards, and the layer cache is stored in the GitHub Actions cache so later builds are faster. |

#### `deploy.yml`: Continuous Delivery (CD)

One job, `publish`. It needs `packages: write` permission to push to the registry, and uses the automatic `GITHUB_TOKEN`, so no password is stored in the repository.

| Step | What it does |
|---|---|
| Checkout | Downloads the code of the commit or tag being released. |
| Setup Buildx | Enables the Docker build engine with caching. |
| Login to `ghcr.io` | Authenticates to the GitHub Container Registry as the user who pushed. |
| Compute image name and tags | Builds the image name `ghcr.io/<owner>/<repository>` and its tags: the branch name (for example `main`), the git tag (for example `v1.0.0`) and the short commit SHA. |
| Build and push (`push: true`) | Builds the production image and uploads it to the registry with those tags. |

The result is a versioned image that can be started on any machine with Docker: `docker pull ghcr.io/<owner>/<repository>:main`.

#### What is and is not automated

- **Automated, CI:** code style, the full test suite and a Docker build on every push and pull request.
- **Automated, CD:** building and publishing the production image when code reaches `main` or a version tag.
- **Not automated:** starting the new image on a server. That step needs a host and secrets, so it is done by hand with Docker Compose (see "Docker architecture" above).
- **Note:** `deploy.yml` starts at the same time as CI on a push to `main`. It does not wait for CI to finish. In practice code reaches `main` through `develop`, where CI has already passed, but the publish step is not blocked by a CI failure.

**Composer platform pin:** `composer.json` fixes the platform to PHP 8.2. Dependencies are resolved for that version, so `composer install` works the same on every machine and in CI, even when a developer runs a newer PHP.

### Git workflow

- One branch per team member (`jasser`, `aziz`, `NizarChaieb`), merged into `develop` through pull requests. A pull request template is in `.github/pull_request_template.md`.
- Commits follow the conventional style: `feat:`, `fix:`, `ci:`, `docs:`.
- Work reaches `main` only when `develop` is stable and CI is green.
- `.env` files and API keys are never committed.
