# A2Z Central — Midterm version

Web platform for **A to Z Engineering Solution Limited** that replaces six paper-based office processes with one secure system.
Solo project · Institute of Information Technology (IIT), University of Dhaka.

**Midterm scope:** database design, authentication with roles, and the six functions.

| # | Module | File |
|---|--------|------|
| 1 | Accounts & HR: invoices, payments, attendance | `accounts.php` |
| 2 | Client Records: contacts and follow-ups | `clients.php` |
| 3 | Design Files: upload with automatic versions (v1, v2 …) | `files.php`, `download.php` |
| 4 | Office Monitoring: visitor log and activity trail | `monitoring.php` |
| 5 | Project Tracking: projects, progress, drag-and-drop task board | `projects.php` |
| 6 | Approvals: request, decision, notification | `approvals.php` |

Also: dashboard (`dashboard.php`), notifications, Users & Roles (admin only), login/logout.
**Planned for the final phase:** automation engine (scheduled status updates and reminders) and a chatbot assistant.

## Requirements
XAMPP with **PHP 7.4+** and **MySQL / MariaDB**, and a modern browser.

## Install
1. Copy the folder `a2z-central` to `C:\xampp\htdocs\`.
2. In XAMPP Control Panel start **Apache** and **MySQL**.
3. Open `http://localhost/a2z-central/setup.php` and press **Install now** (creates the database `a2z_central`, 11 tables and demo data).
4. Open `http://localhost/a2z-central/` and sign in. Delete `setup.php` when you are done.

| Role | Email | Password |
|------|-------|----------|
| Administrator | `admin@a2z.com` | `Admin@123` |
| Staff | `nusrat@a2z.com` | `Staff@123` |

Settings (database password, company name, upload size) are in `config/config.php`.
To reinstall: drop the `a2z_central` database in phpMyAdmin and delete `config/installed.lock`.

## Project structure
```
a2z-central/
├── login.php  logout.php  index.php  setup.php
├── dashboard.php  notifications.php  users.php
├── accounts.php  clients.php  files.php  download.php
├── monitoring.php  projects.php  approvals.php
├── includes/   db.php  auth.php  helpers.php  queries.php  layout_top.php  layout_bottom.php
├── assets/     css/app.css   js/app.js
├── database/   schema.sql  (11 tables, foreign keys)
├── config/     config.php
└── uploads/    stored design files (blocked from direct web access)
```
Technology: HTML5, CSS3, JavaScript, Chart.js (dashboard), PHP, MySQL. Editor: Visual Studio Code.

## Database
`users` · `clients` → `projects` → `tasks` · `invoices` (→ clients, projects) · `attendance` (→ users) · `design_files` (→ projects, users) ·
`visit_logs` · `approvals` (→ projects, users) · `notifications` · `activity_log`.

## Roles
| | Admin | Staff |
|---|:---:|:---:|
| Invoices and financial data | yes | no |
| Clients, tasks, files, visitors | yes (+ delete) | yes (no delete) |
| Approvals | request + approve / reject | request only |
| Users & Roles | yes | no |

## Security
Prepared statements (SQL injection) · output escaping with `e()` (XSS) · CSRF token on every form · bcrypt passwords ·
session regeneration and login lock after 5 failures · server-side role checks · safe uploads (whitelist, size limit, random names, folder blocked, download only after login).

## Troubleshooting
* **"Database not ready":** start MySQL in XAMPP, then open `setup.php`.
* **Blank page:** set `display_errors=On` in `php.ini`, restart Apache, read the message.
* **Different MySQL password or port:** edit `config/config.php`.
* **Charts without internet:** save Chart.js as `assets/js/chart.umd.min.js`, otherwise simple bars are shown.

## Note on AI assistance
Developed with the help of an AI assistant (Claude, Anthropic) for code generation, debugging and documentation, as declared on the project form.
The code is plain procedural PHP so every part can be read, explained and modified. See `docs/MIDTERM_GUIDE_BN.md`.
