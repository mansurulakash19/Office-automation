# A2Z Central

Web platform for **A to Z Engineering Solution Limited** that replaces paper-based office work with one secure system.
Solo project · Institute of Information Technology (IIT), University of Dhaka.

**Current scope:** database design, login with roles (admin / staff), and four modules.

| # | Module | File |
|---|--------|------|
| 1 | Accounts & HR: invoices, payments, attendance | `accounts.php` |
| 2 | Client Records: contacts and follow-ups | `clients.php` |
| 3 | Design Files: upload with automatic versions (v1, v2 …) | `files.php`, `download.php` |
| 4 | Project Tracking: projects, progress, drag-and-drop task board | `projects.php` |

Also: notifications (bell icon), Users & Roles (admin only), login/logout. After sign-in you land on **Accounts & HR** (`HOME_PAGE` in `config/config.php`).

## Requirements
XAMPP with **PHP 7.4+** and **MySQL / MariaDB**, and a modern browser.

## Install (5 minutes)
1. Copy the folder `a2z-central` to `C:\xampp\htdocs\`.
2. In XAMPP Control Panel start **Apache** and **MySQL**.
3. Open `http://localhost/a2z-central/setup.php` and press **Install now** (creates database `a2z_central`, 9 tables and demo data).
4. Open `http://localhost/a2z-central/` and sign in. Delete `setup.php` when you are done.

| Role | Email | Password |
|------|-------|----------|
| Administrator | `admin@a2z.com` | `Admin@123` |
| Staff | `nusrat@a2z.com` | `Staff@123` |

Settings (database password, company name, upload size) are in `config/config.php`. To reinstall: drop the `a2z_central` database in phpMyAdmin and delete `config/installed.lock`.

## Database design
See **`docs/DATABASE_DESIGN.md`**: ER diagram (`docs/er_diagram.png`), tables, relationships and a full data dictionary. SQL: `database/schema.sql`.

## Project structure
```
a2z-central/
├── login.php  logout.php  index.php  setup.php
├── accounts.php  clients.php  files.php  download.php  projects.php
├── users.php  notifications.php
├── includes/   db.php  auth.php  helpers.php  layout_top.php  layout_bottom.php
├── assets/     css/app.css   js/app.js
├── database/   schema.sql
├── config/     config.php
├── docs/       DATABASE_DESIGN.md  er_diagram.png/.svg  MIDTERM_GUIDE_BN.md
└── uploads/    stored design files (blocked from direct web access)
```
Technology: HTML5, CSS3, JavaScript, PHP, MySQL. Editor: Visual Studio Code.

## Roles
| | Admin | Staff |
|---|:---:|:---:|
| Invoices and financial data | yes | no |
| Clients, tasks, files | yes (+ delete) | yes (no delete) |
| Attendance | sees the whole team | own check-in / history |
| Users & Roles | yes | no |

## Security
Prepared statements (SQL injection) · output escaping with `e()` (XSS) · CSRF token on every form · bcrypt passwords · session regeneration and login lock after 5 failures · server-side role checks · safe uploads (type whitelist, size limit, random names, folder blocked, download only after login).

## Troubleshooting
* **"Database not ready":** start MySQL in XAMPP, then open `setup.php`.
* **Blank page:** set `display_errors=On` in `php.ini`, restart Apache, read the message.
* **Different MySQL password or port:** edit `config/config.php`.

## Note on AI assistance
Developed with the help of an AI assistant (Claude, Anthropic) for code generation, debugging and documentation, as declared on the project form. The code is plain procedural PHP so every part can be read, explained and modified.
