# Upgrading Lukaisu Server

This guide covers how to upgrade Lukaisu Server to a newer version safely.

## How Upgrades Work

Lukaisu Server uses an **automatic database migration system**. When you start
Lukaisu Server after updating the files, it automatically:

1. Detects your current database version
2. Runs any pending migrations to update the database schema
3. Updates the `dbversion` setting to match the current version

Most upgrades are seamless — replace the files (or pull the new image) and open
Lukaisu Server.

::: warning Back up first
Database migrations can be irreversible. Always create a full backup before
upgrading.
:::

## Standard Upgrade Process

### Step 1: Back up your data

**Database backup (recommended):**

1. Open Lukaisu Server in your browser
2. Go to **Settings** (gear icon)
3. Click **Backup/Restore/Empty Database**
4. Click **Backup ENTIRE Database** to download a backup file

**File backup:** copy your `.env` (database configuration) and your `media/`
folder (audio files, if present) to a safe location.

### Step 2: Get the new version

- **Stable releases**: [GitHub Releases](https://github.com/lukaisu/lukaisu-server/releases)
- **Latest development**: [Download the main branch](https://github.com/lukaisu/lukaisu-server/archive/refs/heads/main.zip)

### Step 3: Replace the files

1. Extract the new version
2. Copy your `.env` file and `media/` folder from the backup into the new directory
3. Replace the old directory with the new one

If you run from source, rebuild the frontend assets:

```bash
npm install
npm run build:all
```

### Step 4: Clear your browser cache

Hard refresh (`Ctrl+Shift+R` / `Cmd+Shift+R`) or open a private window, so the new
JavaScript and CSS load fresh.

### Step 5: Open Lukaisu Server

Open it in your browser as usual. Pending migrations run automatically on the
first request; on a typical database this completes in under a minute. Do not
interrupt the server while the first post-upgrade request is processing.

## Docker Upgrades

```bash
# 1. Back up your data
docker compose exec db mysqldump -u root -p lukaisu > backup.sql

# 2. Stop the current containers
docker compose down

# 3. Pull the new image (or rebuild from source)
docker compose pull
# docker compose build --no-cache

# 4. Start the updated containers
docker compose up -d

# 5. Migrations run automatically on the first request
```

Your data persists in Docker volumes across upgrades.

## Requirements

Lukaisu Server requires **PHP 8.2 or higher**. If your server runs an older PHP
version, upgrade PHP first.

## Checking Your Version

To see your current version:

1. Look at the footer of any Lukaisu Server page, or
2. Check the `dbversion` value in your database's `settings` table.

## Downgrading

Downgrading is **not officially supported** — newer database migrations cannot be
reversed automatically. If you must downgrade:

1. Restore your database backup from before the upgrade
2. Use the older Lukaisu Server files

This is why backups before upgrading are essential.

## Troubleshooting

### "Database needs to be reinstalled" error

The migration likely couldn't complete. Try:

1. Restore your database backup
2. Check that your `.env` credentials are correct
3. Ensure your MySQL/MariaDB user has `ALTER TABLE` permissions

### Migration takes too long or times out

Large databases may take several minutes. Increase your PHP `max_execution_time`,
or run migrations from the command line:

```bash
php index.php
```

### Features not working after upgrade

1. Clear your browser cache completely
2. Check the browser console (F12) for JavaScript errors
3. Rebuild assets if running from source (`npm install && npm run build:all`)

## Getting Help

- [GitHub Issues](https://github.com/lukaisu/lukaisu-server/issues) — report bugs or search existing issues
