# Het Nieuwe² Werken (HNNW)

Het Nieuwe² Werken (HNNW) is een crowdsourcingplatform voor de overheid dat vraag en aanbod van werk samenbrengt. Het stelt organisaties en individuen in staat hulpvragen en opdrachten te publiceren, waarop deelnemers kunnen reageren.

Het platform is ontworpen om samenwerking binnen en tussen overheidsorganisaties te stimuleren, met een focus op transparantie, veiligheid en schaalbaarheid.

> **Project status:** HNNW is currently under active development. The application is not yet publicly available.

---

## Table of Contents

- [Overview](#overview)
- [Installation](#installation)
- [Running Tests](#running-tests)
- [Technical Information](#technical-information)
- [Troubleshooting](#troubleshooting)
- [License](#license)
- [Project Team](#project-team)
- [Copyright](#copyright)

---

## Overview

HNNW is a platform being developed to support collaborative work within the Dutch government ecosystem.

The platform is designed to enable:

- Publishing projects and work packages
- Matching supply and demand for expertise
- Cross-organisational collaboration
- Community and group formation
- Transparent participation and workflow management

These are the platform's intended capabilities; individual features may still be under development.

This repository contains the **core Symfony application** for the HNNW platform.

**Planned website:** https://hnnw.nl  
**Repository:** https://code.hnnw.nl/HNNW/core

---

## Installation

### Requirements

Before starting, ensure the following software is installed:

- PHP **8.5 or later** (with the extensions required by Composer dependencies)
- Composer
- Symfony CLI
- Git
- PostgreSQL **18**
- pgAdmin 4 *(recommended for the database setup described below)*

> **Note:** Installation instructions for PHP, Composer and Symfony CLI are intentionally not included in this README. The instructions below use pgAdmin 4, but PostgreSQL can also be managed using other database administration tools.

### 1. Install PostgreSQL 18

Download and install PostgreSQL 18. An introductory installation guide is available at:

https://www.w3schools.com/postgresql/postgresql_install.php

During installation:

- Remember the password set for the `postgres` user.
- Install pgAdmin 4 if you intend to follow the graphical database setup below.
- Keep the default PostgreSQL port (`5432`) unless another port is required.

Ensure the PostgreSQL service is running before proceeding.

### 2. Create the database in pgAdmin

Open **pgAdmin 4** and connect to your local PostgreSQL server.

1. Expand the server, for example:

   ```text
   Servers → PostgreSQL 18
   ```

2. Right-click **Databases**.
3. Select **Create → Database...**.
4. Enter a database name, for example:

   ```text
   hnnw_core
   ```

5. Click **Save**.

The database is now ready for use. Remember the database name for the environment configuration.

### 3. Clone the repository

```bash
git clone https://code.hnnw.nl/HNNW/core.git
cd core
```

> **Note:** `core` is the default directory name created by this clone command. If you clone into a different directory, change into that directory instead.

### 4. Install dependencies

```bash
composer install
```

If Composer prompts you to install Docker-related configuration files for a Symfony package, decline the prompt. Docker is not required for the installation described in this README, and the repository's Symfony configuration disables Docker integration.

If the prompt offers the following option, select it:

```text
x — No, don't ask again for this project
```

### 5. Create environment configuration

Duplicate the example environment file:

```bash
cp .env.local.example .env.local
```

If `cp` is unavailable, manually copy `.env.local.example` and name the copy `.env.local`.

> **Important:** `.env.local` contains machine-specific configuration and may contain credentials. Do not commit it to the repository.

### 6. Configure `.env.local`

Open `.env.local` and configure the application settings.

#### Configure `APP_SECRET`

Generate a secure application secret:

```bash
php -r "echo bin2hex(random_bytes(32));"
```

Place the generated value inside `.env.local`:

```dotenv
APP_SECRET=your_generated_secret_here
```

Replace the placeholder with the generated value.

#### Configure the PostgreSQL connection

Set `DATABASE_URL` inside `.env.local`:

```dotenv
DATABASE_URL="postgresql://your_username:your_password@127.0.0.1:5432/hnnw_core?serverVersion=18&charset=utf8"
```

Replace the placeholders with your local database details:

| Value | Description |
| --- | --- |
| `your_username` | PostgreSQL username (often `postgres` for a local installation) |
| `your_password` | Password for the selected PostgreSQL user |
| `hnnw_core` | Database name created in pgAdmin |
| `5432` | PostgreSQL port (change if using a different port) |

If the username or password contains special URL characters, URL-encode them in the connection string.

#### Configure the mailer

The example environment file also contains a `MAILER_DSN` setting:

```dotenv
MAILER_DSN=smtp://localhost:1025
```

This points to a local SMTP service on port `1025`. Configure an appropriate local mail service if you need to test outgoing email, or adjust the setting for your development environment. Do not place production mail credentials in the repository.

### 7. Run database migrations

Database tables are managed through Doctrine Migrations.

Run:

```bash
php bin/console doctrine:migrations:migrate
```

Confirm the migration when prompted. This applies the available migrations to the configured database.

### 8. Start the application

```bash
symfony server:start
```

The Symfony CLI will display the local address of the development server. This is commonly:

```text
http://127.0.0.1:8000
```

You may also be able to access it using `http://localhost:8000`, depending on your local setup.

---

## Running Tests

Tests use a separate Symfony environment and should use a separate database from local development.

### 1. Create environment configuration

Duplicate the test environment example:

```bash
cp .env.test.local.example .env.test.local
```

If `cp` is unavailable, manually copy `.env.test.local.example` and name the copy `.env.test.local`.

### 2. Configure `.env.test.local`

Open `.env.test.local` and configure the application settings.

You can use the same PostgreSQL server and credentials as in `.env.local`, but **use a separate test database name** to avoid modifying development data.

For example:

```dotenv
APP_SECRET=your_generated_test_secret_here
DATABASE_URL="postgresql://your_username:your_password@127.0.0.1:5432/hnnw_core_test?serverVersion=18&charset=utf8"
```

> **Note:** The database name is taken from `DATABASE_URL`. Use an explicit test database name such as `hnnw_core_test` rather than relying on a suffix being added automatically.

### 3. Create the test database

```bash
php bin/console --env=test doctrine:database:create
```

This creates the database configured for the test environment.

### 4. Run database migrations

```bash
php bin/console --env=test doctrine:migrations:migrate
```

Confirm the migration when prompted.

### 5. Load database fixtures

Fixtures provide sample data for development and testing.

```bash
php bin/console --env=test doctrine:fixtures:load
```

Confirm when prompted.

> **Warning:** Loading fixtures normally purges existing data from the target database. Check that the test environment points to the correct database before running this command.

### 6. Run the tests

```bash
php bin/phpunit
```

This runs the project's PHPUnit test suite.

---

## Technical Information

| Category | Information |
| --- | --- |
| Framework | Symfony 8.1 |
| Backend Language | PHP 8.5+ |
| Frontend Languages | HTML, CSS, JavaScript |
| Templating | Twig |
| Database | PostgreSQL 18 |
| ORM | Doctrine ORM |
| Database Migrations | Doctrine Migrations |
| UX Packages | Symfony UX (including Stimulus and Twig Components; no Vue.js or React frontend required) |
| API Tooling | API Platform is installed and configured; API functionality is still under development |
| Codebase Language | en-AU |
| Application Language | nl-NL |

The application uses Composer to manage PHP dependencies. Consult `composer.json` and `composer.lock` for the complete dependency list and installed versions.

---

## Troubleshooting

### Composer install fails

First check that your PHP version and extensions meet the requirements in `composer.json`:

```bash
php -v
composer check-platform-reqs
```

If the problem persists, try clearing the Composer cache and reinstalling dependencies:

```bash
composer clear-cache
composer install
```

### Database connection errors

Verify that:

- PostgreSQL is running.
- The configured database exists.
- The username and password are correct.
- The host and port are correct.
- `DATABASE_URL` in `.env.local` points to the intended database.

You can test database connectivity using:

```bash
php bin/console dbal:run-sql "SELECT 1"
```

### Migration problems

If migrations fail, verify the database connection and inspect the migration status:

```bash
php bin/console doctrine:migrations:status
```

After resolving the underlying issue, retry:

```bash
php bin/console doctrine:migrations:migrate
```

### `APP_SECRET` missing

Ensure `.env.local` exists and contains a generated application secret:

```dotenv
APP_SECRET=your_generated_secret_here
```

### Symfony cache problems

Clear the development cache:

```bash
php bin/console cache:clear
```

### Port already in use

If port `8000` is occupied, start the Symfony server on another port:

```bash
symfony server:start --port=8001
```

### Test database problems

If tests fail because of a database connection or missing tables, check `.env.test.local` and verify that the test database has been created and migrated. Avoid pointing the test environment at your development database.

---

## License

This project is licensed under the **European Union Public Licence (EUPL), version 1.2**, unless stated otherwise.

The full licence text is available in the [`LICENSE`](LICENSE) file.

Further information: https://eupl.eu/

Repository organisation: https://code.hnnw.nl/HNNW

---

## Project Team

### Project Leadership

Project leadership and product direction involve the following roles:

- **Business Owner** — Oversees the project's overall objectives, organisational needs and resources.
- **Project Manager** — Supports project planning, progress, coordination and the agreed Agile approach.
- **Stakeholders** — Provide product requirements, feedback and direction.

### Product Ownership & Development Leadership

- **Product Owner / Lead Developer** — Coordinates software development, translates product requirements into technical work, and oversees architecture, technical standards, code quality and reviews.

### Development Team

The development team consists of approximately **25 developers**, primarily students from **Techniek College Rotterdam (MBO)**, with development roles including:

- **Junior Developers** — Work on assigned development tasks and build technical experience.
- **Medior Developers** — Carry out development tasks with greater independence.
- **Senior Developers** — Handle more complex development work and support other developers.
- **Team Leads** — Support coordination within development teams where appointed.

**Practical trainers** support interns with their placement, professional development and learning objectives.

Responsibilities may overlap, and one person may fulfil more than one role within the project.

---

## Copyright

© Het Nieuwe² Werken (HNNW)

All rights reserved to the extent permitted by, and without limiting the rights granted under, the EUPL.

Project developed for and in collaboration with the Dutch government ecosystem.
