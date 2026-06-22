# Het Nieuwe² Werken (HNNW)

Het Nieuwe² Werken (HNNW) is een crowd-sourcing platform voor de overheid dat vraag en aanbod van werk samenbrengt. Het stelt organisaties en individuen in staat hulpvragen en opdrachten te publiceren, waarop deelnemers kunnen reageren.

Het platform is ontworpen om samenwerking binnen en tussen overheidsorganisaties te stimuleren, met een focus op transparantie, veiligheid en schaalbaarheid.

---

## Table of Contents

* [Overview](#overview)
* [Installation](#installation)
* [Technical Information](#technical-information)
* [Troubleshooting](#troubleshooting)
* [License](#license)
* [Project Team](#project-team)
* [Copyright](#copyright)

---

## Overview

HNNW is the next-generation platform for collaborative work within the Dutch government ecosystem.

The platform enables:

* Publishing projects and work packages
* Matching supply and demand of expertise
* Cross-organisational collaboration
* Community and group formation
* Transparent participation and workflow management

This repository contains the **core Symfony application** powering the HNNW platform.

Production website: https://hnnw.nl \
Repository: https://codeberg.org/HNNW/HNNW-core

---

## Installation

### Requirements

Before starting, ensure the following software is already installed:

* PHP **8.5.4+**
* Composer
* Symfony CLI
* Git

> Note: installation steps for PHP, Composer and Symfony CLI are intentionally not included in this README.

### 1. Clone the repository

```bash
git clone https://codeberg.org/HNNW/HNNW-core.git
cd HNNW-core
```

### 2. Install dependencies

```bash
composer install
```

During installation, one of the Symfony UX packages may ask whether Docker-related files should be installed.

Since Docker is **not used for this project**, simply select:

```text
x
```

This corresponds to:

```text
No, don't ask again for this project
```

### 3. Create environment configuration

Duplicate the example environment file:

```bash
cp .env.local.example .env.local
```

If `cp` is not available, manually duplicate:

```text
.env.local.example
```

Rename it to:

```text
.env.local
```

### 4. Configure APP_SECRET

Generate a secure application secret and place it inside `.env.local`:

```env
APP_SECRET=your_generated_secret_here
```

You may generate one using:

```bash
php -r "echo bin2hex(random_bytes(32));"
```

### 5. Start the application

```bash
symfony server:start
```

The application should now be available at:

```text
http://127.0.0.1:8000
```

---

## Technical Information

| Category             | Information                                                            |
|----------------------| ---------------------------------------------------------------------- |
| Framework            | Symfony                                                                |
| Backend Language     | PHP                                                                    |
| Frontend Languages   | HTML, CSS, JavaScript                                                  |
| Templating           | Twig                                                                   |
| Database             | PostgreSQL *(planned for future implementation)*                       |
| UX Packages          | Symfony UX *(excluding deprecated, Vue.js, React and Svelte packages)* |
| APIs                 | None currently                                                         |
| Codebase Language    | en-AU                                                                  |
| Application Language | nl-NL                                                                  |

---

## Troubleshooting

### Composer install fails

Try clearing cache:

```bash
composer clear-cache
composer install
```

### APP_SECRET missing

Make sure `.env.local` exists and contains:

```env
APP_SECRET=your_generated_secret_here
```

### Symfony cache problems

Clear cache:

```bash
php bin/console cache:clear
```

### Port already in use

If port `8000` is already occupied:

```bash
symfony server:start --port=8001
```

---

## License

This project is licensed under the **European Union Public License (EUPL)** unless stated otherwise.

Please refer to the official license text:

https://eupl.eu/

Repository organisation: https://codeberg.org/HNNW

---

## Project Team

### Project Leadership

* Project Leader
* Multiple Stakeholders

### Product Ownership & Development Leadership

* **Riley de Man** — Product Owner, Lead Developer & Senior Developer

### Development Team

A team of approximately **10 to 12 developers** from **Techniek College Rotterdam (MBO)** contributes to this project.

This team primarily consists of:

* Junior Developers
* Several Medior Developers

---

## Copyright

© Het Nieuwe² Werken (HNNW)

All rights reserved where not superseded by the EUPL.

Project developed for and in collaboration with the Dutch government ecosystem.
