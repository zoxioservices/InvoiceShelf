# InvoiceShelf — Docker Development

**Prerequisites:** Installed [Docker](https://docs.docker.com/get-docker/) + Docker Compose. Node.js, PHP, and Composer run inside containers.

---

## 1. One-time host setup

### Linux

```bash
bash docker/development/host-setup.sh
```

Adds `127.0.0.1 invoiceshelf.test` to `/etc/hosts` and appends `USRID`/`GRPID` exports to your shell RC. **bash and zsh are configured automatically.**

For any other shell, add the following line to your shell's RC file manually, then reload it:

```sh
export USRID=$(id -u) GRPID=$(id -g)
```

`USRID`/`GRPID` tell the containers to run as your user, which prevents root-owned files appearing in the project directory.

### macOS

```bash
bash docker/development/host-setup.sh
```

Adds `127.0.0.1 invoiceshelf.test` to `/etc/hosts` only. Docker Desktop handles UID mapping via VirtioFS, so `USRID`/`GRPID` are not needed.

### Windows

1. Open `C:\Windows\System32\drivers\etc\hosts` in Notepad **as Administrator** and add:
  ```
   127.0.0.1 invoiceshelf.test
  ```
2. `USRID`/`GRPID` are not needed — Docker Desktop handles permissions automatically.

> **WSL users:** Run `bash docker/development/host-setup.sh` inside your WSL distro instead, following the Linux steps above.

---

## 2. Start

Pick your database and run once — everything else is automatic:

```bash
# MariaDB
docker compose -f docker/development/docker-compose.mysql.node.yml up --build

# SQLite
docker compose -f docker/development/docker-compose.sqlite.node.yml up --build

# PostgreSQL
docker compose -f docker/development/docker-compose.pgsql.node.yml up --build
```

On **first run**, the `setup` container automatically copies the `.env` template, runs `composer install`, generates `APP_KEY`, runs migrations, and seeds the database. Subsequent runs only apply pending migrations — data is preserved. Drop `--build` once images are built.

**Seeding mode** — controlled by the `SEED_MODE` env var (default: `basic`):


| Value             | Behaviour                                                                      |
| ----------------- | ------------------------------------------------------------------------------ |
| `basic` (default) | `DatabaseSeeder` — currencies, countries, one admin user. Wizard skipped.      |
| `demo`            | `DemoSeeder` — full demo company, invoices, customers, etc. Wizard skipped.    |
| `none`            | Skips migrations and seeding. Complete full setup via the installation wizard. |


```bash
SEED_MODE=demo docker compose -f docker/development/docker-compose.mysql.node.yml up --build
```

**Credentials:** `basic` → `admin@invoiceshelf.com` / `invoiceshelf@123` — `demo` → `demo@invoiceshelf.com` / `demo`

---

## 3. URLs


| Service         | URL                                                            |
| --------------- | -------------------------------------------------------------- |
| App             | [http://invoiceshelf.test:8000](http://invoiceshelf.test:8000) |
| Adminer (DB UI) | [http://invoiceshelf.test:8080](http://invoiceshelf.test:8080) |
| Mailpit (mail)  | [http://invoiceshelf.test:8025](http://invoiceshelf.test:8025) |


**Adminer login (MySQL/PostgreSQL):** server `db`, user `invoiceshelf`, password `invoiceshelf`, database `invoiceshelf`.

**Host DB client:** connect to `127.0.0.1:3307` (MySQL) or `127.0.0.1:5433` (PostgreSQL). Non-standard ports avoid conflicts with any local MySQL/PostgreSQL service.

---

## 4. Development

PHP changes take effect immediately on the next request. Vue/JS/CSS changes trigger instant HMR in the browser via the `node` container on `:5173`.

Run Artisan, Pint, or Pest inside the PHP container:

```bash
docker exec -it invoiceshelf-dev-php /bin/sh
```

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
php artisan route:list
```

---

## 5. Reset

Stops all containers, removes DB volumes, and deletes `vendor/`, `.env`, and caches for a guaranteed clean slate.

### Linux / macOS

```bash
bash docker/development/clean.sh
```

Pass `-y` to skip the confirmation prompt.

### Windows

Run the following in PowerShell from the project root. Stop whichever database you were using:

```powershell
# MariaDB
docker compose -f docker/development/docker-compose.mysql.node.yml down -v --remove-orphans

# SQLite
docker compose -f docker/development/docker-compose.sqlite.node.yml down -v --remove-orphans

# PostgreSQL
docker compose -f docker/development/docker-compose.pgsql.node.yml down -v --remove-orphans
```

Then remove local artifacts:

```powershell
Remove-Item .env, storage\.docker-setup-done, public\hot, database\database.sqlite -ErrorAction SilentlyContinue
Remove-Item -Recurse -Force vendor -ErrorAction SilentlyContinue
Remove-Item bootstrap\cache\*.php -ErrorAction SilentlyContinue
if (Test-Path public\storage) { Remove-Item public\storage }
```

> **WSL users:** use the Linux script above inside your WSL distro.

### After a full clean

Rebuild with the `up --build` command for your chosen database (see [Start](#2-start)).

---

### Partial resets

**Wipe DB + remigrate + reseed** (swap compose file for pgsql/sqlite as needed):

Linux / macOS:

```bash
rm storage/.docker-setup-done
docker compose -f docker/development/docker-compose.mysql.node.yml down -v
docker compose -f docker/development/docker-compose.mysql.node.yml up --build
```

Windows (PowerShell):

```powershell
Remove-Item storage\.docker-setup-done -ErrorAction SilentlyContinue
docker compose -f docker/development/docker-compose.mysql.node.yml down -v
docker compose -f docker/development/docker-compose.mysql.node.yml up --build
```

**Reset `.env` only** (next `up` will re-copy from the template):

Linux / macOS:

```bash
rm .env
```

Windows (PowerShell):

```powershell
Remove-Item .env
```

