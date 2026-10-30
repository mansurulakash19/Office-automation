# A2Z Central — গাইড (বাংলায়)

এই কোডে আছে: **Database + Login/Role + ৪টা module** (Accounts & HR, Client Records, Design Files, Project Tracking)। সব কোড নিজে পড়ে বুঝে নাও; sir যেকোনো লাইন খুলে ব্যাখ্যা চাইতে পারে।

## ১. ফাইল ম্যাপ

| ফাইল | কাজ |
|---|---|
| `config/config.php` | database password, কোম্পানির নাম, `HOME_PAGE` |
| `database/schema.sql` | 9টা table (foreign key সহ) |
| `docs/DATABASE_DESIGN.md` | ER diagram, table, সম্পর্ক, data dictionary |
| `setup.php` | এক ক্লিকে database + table + demo data |
| `includes/db.php` | MySQL যোগ + ৫টা helper (`db_all`, `db_one`, `db_value`, `db_exec`, `db_insert`) |
| `includes/auth.php` | login session, admin/staff role, CSRF |
| `includes/helpers.php` | `e()`, টাকার format, তারিখ, badge, icon, notification |
| `includes/layout_top.php` / `layout_bottom.php` | পেজের কাঠামো: sidebar, top bar, bell |
| `accounts.php` `clients.php` `files.php` `projects.php` | ৪টা module |
| `users.php` | user ও role (শুধু admin) |
| `assets/css/app.css`, `assets/js/app.js` | ডিজাইন; modal, filter, drag-drop |

## ২. একটা পেজ কীভাবে কাজ করে
1. **উপরে — logic:** `require_login()` → form submit হলে `verify_csrf()` → যাচাই → database-এ save → `flash()` → `redirect()`
2. **মাঝে — ডেটা আনা:** `db_all("SELECT ...")`
3. **নিচে — HTML:** `foreach` দিয়ে সারি সারি দেখানো

> **Post → Redirect → Get:** save-এর পর `redirect()` করি, যাতে refresh করলে ডুপ্লিকেট না হয়।

## ৩. নিরাপত্তার ৬টা ধারণা
1. **Prepared statement** (SQL injection ঠেকায়): `db_all('SELECT * FROM users WHERE email = ?', [$email])`
2. **`e()`** (XSS ঠেকায়): `<script>` সাধারণ লেখা হয়ে যায়
3. **CSRF token:** প্রতি form-এ গোপন token; জাল form আটকায়
4. **Password hash:** `password_hash()` / `password_verify()` (bcrypt)
5. **Session:** login-এর পর `session_regenerate_id(true)`; ৫ বার ভুলে ৬০ সেকেন্ড লক
6. **Role-based access:** server-এও `require_admin()` দিয়ে পরীক্ষা (শুধু menu লুকানো যথেষ্ট নয়)

## ৪. Database (বিস্তারিত `docs/DATABASE_DESIGN.md`-এ)
```
clients ──< projects ──< tasks
   │            ├──< design_files
   └──< invoices >── projects (optional)
users ──< attendance, tasks, design_files, notifications, activity_log
```
`A ──< B` = একটা A-র অনেক B (**foreign key**)। Client-এর project/invoice থাকলে client মোছা যায় না (`RESTRICT`); project মুছলে task ও file-রেকর্ড মুছে যায় (`CASCADE`)। **Normalized:** একই তথ্য একবারই রাখা; invoice-এ client-এর নাম নয়, `client_id`।

## ৫. বিশেষ অংশ
* **File version (`files.php`):** একই project-এ একই নামের ফাইল আবার upload করলে `MAX(version)+1` → v2, v3; ফাইল র‍্যান্ডম নামে থাকে, শুধু login করে `download.php` দিয়ে নামানো যায়।
* **Kanban drag-drop (`app.js`):** কার্ড ড্র্যাগ করলে JS `fetch()` দিয়ে `projects.php`-এ `move_task` পাঠায়; server JSON `{ok:true}` ফেরত দেয়।
* **Attendance:** Check in সময় `OFFICE_START`-এর পরে হলে "Late"।
* **টাকার format:** `money()` → ৳4,82,000

## ৬. ৬ মিনিটের Demo ক্রম
1. `setup.php` (আগেই করা) → Admin দিয়ে login → **Accounts & HR → Invoices**: নতুন invoice → edit → "Mark paid" → search/filter। (due পার হলে লাল "days late" চিহ্ন)
2. **Attendance:** Staff দিয়ে "Check in now"; Admin-এ "Team today" ও মাসের সারাংশ
3. **Client Records:** নতুন client → "Follow-up" লগ। project আছে এমন client মোছার চেষ্টা → **foreign key** আটকায়
4. **Design Files:** একই নামের ফাইল দুবার upload → v1, v2, "Latest" → Download
5. **Project Tracking:** project card ও progress; **Task board**-এ কার্ড drag; task assign করলে ওই user-এর bell-এ notification
6. **Role-based access:** Staff দিয়ে login → Invoices ও Users নেই; সরাসরি `localhost/a2z-central/users.php` লিখলে "administrators only"
7. (বোনাস) **phpMyAdmin → Designer**-এ table ও সম্পর্ক; `docs/er_diagram.png` দেখাও

## ৭. সম্ভাব্য প্রশ্ন
1. **কয়টা table, সম্পর্ক কী?** 9টা table, 11টা foreign key; ER diagram দেখাও।
2. **SQL injection কীভাবে ঠেকাও?** Prepared statement (`includes/db.php`)
3. **Password কীভাবে রাখো?** bcrypt hash
4. **Admin/Staff আলাদা কীভাবে?** `users.role` + server-এ `require_admin()`
5. **File version কীভাবে?** একই project + একই নামে `MAX(version)+1`
6. **Client delete হয় না কেন?** Foreign key `ON DELETE RESTRICT`
7. **CSRF কী?** জাল form ঠেকাতে প্রতি form-এ গোপন token
8. **Kanban save কীভাবে?** JS `fetch()` → `projects.php` (`move_task`) → JSON
9. **Normalization কী?** একই তথ্য একবার রাখা, ID দিয়ে যুক্ত করা (3NF)

## ৮. জমা/দেখানোর আগে নিজের করণীয়
- [ ] উপরের প্রতিটা ফাইল **একবার পড়ো**; বোঝোনি এমন অংশ জিজ্ঞেস করে শিখে নাও
- [ ] ছোট পরিবর্তন করে দেখো (নতুন কলাম, badge-এর রং, নতুন filter)
- [ ] **GitHub-এ ধাপে ধাপে commit করো** (database → login → একটা একটা module)। শেষ মুহূর্তের একটা বড় commit সন্দেহজনক
- [ ] `setup.php` দিয়ে নতুন করে install করে পুরো demo একবার চালিয়ে দেখো
