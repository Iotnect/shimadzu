# Shimadzu Production Dashboard

A Laravel application for reviewing DET production activity from an imported work log. The dashboard brings daily production metrics, station timelines, and individual job records into one workspace.

## Features

- Username and password sign-in, remember-me sessions, and login rate limiting.
- Date and station filters with comparisons to the previous recorded date.
- Logged cycles, recorded quantities, running time, idle time, efficiency, availability, and performance.
- Station activity timelines and searchable, paginated job records with detail dialogs.
- CSV export of jobs matching the current filters and search.
- Source coverage and import metadata.

The dashboard reads a historical JSON snapshot. It does not connect to a live production feed or import Excel or Power BI files automatically.

## Requirements

- PHP 8.3 or later, with the extensions required by Composer and PDO SQLite for the default database.
- Composer 2.
- Node.js 20.19+ within the 20.x release line, or 22.12+; npm.

The project uses Laravel 13, Blade, Vite 8, and Tailwind CSS 4. Dependency versions are pinned in `composer.lock` and `package-lock.json`.

## Local setup

Clone the repository and install dependencies:

```sh
git clone https://github.com/Iotnect/shimadzu.git
cd shimadzu
composer install
npm ci
```

Create the environment file and SQLite database, then initialize the application:

```sh
php -r "file_exists('.env') || copy('.env.example', '.env');"
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan key:generate --no-interaction
php artisan migrate --no-interaction
```

The example environment uses SQLite with database-backed sessions and cache. To use another database, configure the `DB_*` values in `.env` before running migrations.

### Production data

Before opening the dashboard, supply `storage/app/private/det-worklog.json`. Private storage contents, the local database, and `.env` are excluded from Git, so a fresh clone does not include production data or user accounts.

The snapshot must contain `source`, `importedAt`, and a nonempty `jobs` array. Each job should include the fields shown below. This synthetic example can be saved at that path for a local preview:

```json
{
  "source": "example-worklog.xlsx",
  "importedAt": "2026-10-23T09:00:00+08:00",
  "jobs": [
    {
      "id": 1,
      "station": "St1",
      "product": "Example product",
      "worker": "Example operator",
      "reference": "DEMO-001",
      "start": "2026-10-22T09:00:00",
      "end": "2026-10-22T09:30:00",
      "quantity": 2
    }
  ]
}
```

Use station identifiers `St1` through `St5` and timestamps in the source's local time. Use a numeric quantity, or `null` when it was not recorded; zero quantities remain zero. Replace the snapshot with a new prepared JSON file to update the data. No import command is currently included.

### Login account

Create an account with a password of at least eight characters:

```sh
php artisan app:create-admin admin
```

Enter the password at the secure prompt. Usernames accept letters, numbers, dots, underscores, and hyphens, up to 80 characters, and are stored in lowercase. Running the command again for the same username updates that account and its password.

### Start the application

Build the frontend and start the local server:

```sh
npm run build
php artisan serve
```

Open the address printed by Artisan and sign in with the account you created. During frontend development, run `npm run dev` in a separate terminal to enable Vite updates.

## Metric definitions

| Metric | Calculation |
| --- | --- |
| Logged cycles | Number of job records for the selected date and station(s). |
| Recorded quantity | Sum of source quantities; missing quantities are counted separately. |
| Running time | Job duration in minutes, merging overlaps within each station. |
| Idle time | Gaps between consecutive jobs at the same station, on the same date, shorter than 12 hours. |
| Available time | 435 minutes per selected station. |
| Efficiency | Running time / available time, as a percentage. |
| Availability | Running time / (running time + idle time), as a percentage. |
| Performance | Expected cycle time / running time, as a percentage. |

Expected cycle time is the number of logged cycles multiplied by the station standard: St1 = 25.515, St2 = 37.24, St3 = 23.695, and St4 = 14.8925 minutes. St5 has no configured standard; performance is unavailable when selected jobs include a station without a standard. Standards are defined in `app/Services/ProductionDashboard.php` and attributed in the UI to `DET cycle timeline.pbix`.

## Checks

```sh
php artisan test --compact
npm run build
```

Tests cover authentication, dashboard filtering, metric calculations, overlapping jobs, missing values, and invalid filter inputs. Dashboard tests supply their own snapshot fixtures.
