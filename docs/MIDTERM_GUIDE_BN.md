# A2Z Central — Midterm গাইড (বাংলায়)

Midterm-এ দেখানোর কথা: **Database + Login/Role + ৬টা function**। Automation engine ও Chatbot তোমার Gantt chart অনুযায়ী **final phase** (সপ্তাহ ১৩–১৫)।
এই কোডে শুধু midterm-এর অংশ আছে। সব কোড নিজে পড়ে বুঝে নাও: sir যেকোনো লাইন খুলে ব্যাখ্যা চাইতে পারে।

## ১. ফাইল ম্যাপ — কোন ফাইল কী করে

| ফাইল | কাজ |
|---|---|
| `config/config.php` | database password, কোম্পানির নাম, সেটিংস |
| `database/schema.sql` | ১১টা table (foreign key সহ) |
| `setup.php` | এক ক্লিকে database + table + demo data |
| `includes/db.php` | MySQL যোগ + ৫টা helper (`db_all`, `db_one`, `db_value`, `db_exec`, `db_insert`) |
| `includes/auth.php` | login session, admin/staff role, CSRF |
| `includes/helpers.php` | `e()`, টাকার format (৳4,82,000), তারিখ, badge, icon, notification |
| `includes/layout_top.php`, `layout_bottom.php` | প্রতিটা পেজের কাঠামো: sidebar, top bar, bell |
| `dashboard.php` | সব module-এর সারাংশ, chart, আসন্ন deadline |
| `accounts.php` `clients.php` `files.php` `monitoring.php` `projects.php` `approvals.php` | ৬টা module |
| `users.php` | user ও role (শুধু admin) |
| `assets/css/app.css`, `assets/js/app.js` | ডিজাইন; modal, filter, drag-drop, chart |

## ২. একটা পেজ কীভাবে কাজ করে (সব পেজে একই নিয়ম)

1. **উপরে — logic:** `require_login()` → form submit হলে (`POST`) `verify_csrf()` → ডেটা যাচাই → database-এ save → `flash()` বার্তা → `redirect()`
2. **মাঝে — ডেটা আনা:** `db_all("SELECT ...")`
3. **নিচে — HTML:** `foreach` দিয়ে সারি সারি দেখানো

> **Post → Redirect → Get:** save-এর পর `redirect()` করি, যাতে refresh করলে form আবার submit হয়ে ডুপ্লিকেট না হয়।

## ৩. নিরাপত্তার ৬টা ধারণা (viva-তে সবচেয়ে বেশি আসে)

1. **Prepared statement (SQL injection ঠেকায়):** `db_all('SELECT * FROM users WHERE email = ?', [$email])`: SQL আর ডেটা আলাদা পাঠানো হয়।
2. **`e()` (XSS ঠেকায়):** `htmlspecialchars`: ব্যবহারকারীর লেখা `<script>` সাধারণ লেখা হিসেবে দেখায়।
3. **CSRF token:** প্রতি form-এ গোপন random token; জাল form-এ token মেলে না, server আটকায়।
4. **Password hash:** `password_hash()` / `password_verify()` (bcrypt); আসল password database-এ থাকে না।
5. **Session:** login-এর পর `session_regenerate_id(true)`; ৫ বার ভুলে ৬০ সেকেন্ড লক।
6. **Role-based access:** menu লুকানোই যথেষ্ট নয়; **server-এও** `require_admin()` দিয়ে পরীক্ষা হয়।

## ৪. Database সম্পর্ক

```
clients ──< projects ──< tasks
   │            │
   │            ├──< design_files
   │            └──< approvals
   └──< invoices >── projects (optional)
users ──< attendance, tasks, approvals, visit_logs, notifications
```
`A ──< B` মানে একটা A-র অনেক B (**foreign key**)। যেমন invoice অবশ্যই একটা client-এর; client-এর project বা invoice থাকলে client মোছা যায় না (`ON DELETE RESTRICT`): database নিজেই ডেটা রক্ষা করে।
**Normalized:** একই তথ্য বারবার না লিখে আলাদা table-এ রেখে ID দিয়ে যুক্ত করা (invoice-এ client-এর নাম না লিখে `client_id`)।

## ৫. বিশেষ কিছু অংশ

- **File version control (`files.php`):** একই project-এ একই নামের ফাইল আবার upload করলে `MAX(version)+1` → v2, v3; সর্বশেষটায় "Latest"। ফাইল `uploads/`-এ **র‍্যান্ডম নামে**; সরাসরি খোলা যায় না, শুধু login করে `download.php` দিয়ে।
- **Kanban drag-and-drop (`app.js`):** কার্ড ড্র্যাগ করলে JS `fetch()` দিয়ে `projects.php`-এ `move_task` পাঠায়; server JSON `{ok:true}` ফেরত দেয়।
- **Dashboard:** `SUM`, `COUNT`, `GROUP BY` দিয়ে সব module থেকে সংখ্যা যোগ হয়।
- **Approvals:** request করলে approver-এর bell-এ notification; decision দিলে requester-এর কাছে notification।
- **টাকার format:** `money()` → ৳4,82,000 (লাখ-কোটি গ্রুপিং)।

## ৬. ৮ মিনিটের Demo ক্রম

1. `setup.php` (আগেই করা) → Admin দিয়ে login → Dashboard: KPI, chart, deadline
2. **Accounts & HR → Invoices:** নতুন invoice → edit → "Mark paid" → search/filter। **Attendance:** Staff দিয়ে "Check in now"; Admin-এ "Team today"
3. **Client Records:** নতুন client → "Follow-up" লগ। যে client-এর project আছে তাকে delete করার চেষ্টা → **foreign key** আটকায় (database সম্পর্ক দেখানোর সেরা মুহূর্ত)
4. **Design Files:** একই নামের ফাইল দুবার upload → **v1, v2**, "Latest" → Download
5. **Office Monitoring:** visitor sign in → sign out; ডানে "Live activity trail"
6. **Project Tracking:** project card ও progress bar; **Task board**-এ কার্ড drag; নতুন task assign
7. **Approvals:** Staff হয়ে request → Admin হয়ে "Decide" → Staff-এর bell-এ notification
8. **Role-based access:** Staff দিয়ে login → Invoices ও Users নেই; `localhost/a2z-central/users.php` সরাসরি লিখলে "administrators only"
9. (বোনাস) **phpMyAdmin**-এ `a2z_central`-এর table ও foreign key দেখাও

> due date পার হওয়া invoice এখন নিজে "Overdue" হয় না (Pending-ই থাকে); শুধু লাল "days late" চিহ্ন দেখায়। এটাই গল্প: "এখন মানুষকে হাতে খেয়াল রাখতে হয়; final phase-এ automation এটা নিজে করবে।"

## ৭. Sir-কে যা বলবে

> "আমার প্রজেক্ট চার ধাপে। Midterm পর্যন্ত আমি database design, authentication ও role-based access, এবং ছয়টা function সম্পূর্ণ করেছি। Final phase-এ cron job দিয়ে automation engine (overdue status ও reminder) এবং chatbot assistant যোগ হবে, যেমন আমার Gantt chart-এ আছে।"

## ৮. সম্ভাব্য প্রশ্ন

1. **কয়টা table, সম্পর্ক কী?** ১১টা table; `clients → projects → tasks`, `invoices → clients/projects` ইত্যাদি (`schema.sql` খুলে দেখাও)
2. **SQL injection কীভাবে ঠেকাও?** Prepared statement (`includes/db.php`)
3. **Password কীভাবে রাখো?** bcrypt hash
4. **Admin/Staff আলাদা কীভাবে?** `users.role` + server-এ `is_admin()` / `require_admin()`
5. **File version কীভাবে?** একই project + একই নামে `MAX(version)+1`
6. **Client delete হয় না কেন?** Foreign key `ON DELETE RESTRICT`
7. **CSRF কী?** জাল form ঠেকাতে প্রতি form-এ গোপন token (`includes/auth.php`)
8. **Kanban save কীভাবে হয়?** JS `fetch()` → `projects.php` (`move_task`) → JSON
9. **Automation কোথায়?** Gantt অনুযায়ী সপ্তাহ ১৩; midterm-এ ছয়টা function ও database সম্পর্ক সম্পূর্ণ

## ৯. Midterm-এর আগে নিজের করণীয়

- [ ] উপরের প্রতিটা ফাইল **একবার পড়ো** এবং যেটা বোঝোনি সেটা জিজ্ঞেস করে শিখে নাও
- [ ] ছোট পরিবর্তন করে দেখো (নতুন কলাম, badge-এর রং, নতুন filter) যাতে live-এ কোড বদলাতে বললে পারো
- [ ] **GitHub-এ ধাপে ধাপে commit করো:** database, তারপর login, তারপর একটা একটা module। শেষ মুহূর্তে একটা বড় commit সন্দেহজনক। Midterm-এর দিন: `git tag midterm && git push --tags`
- [ ] Demo-র আগে নতুন করে `setup.php` চালিয়ে পুরো ক্রম একবার চালাও

## ১০. Final-এ কী যোগ হবে

`automation/` ফোল্ডার (cron job), Automation Engine পেজ, Chatbot ও dashboard-এর automation অংশ। আলাদা **final package** দেওয়া আছে; তার README-তে আপগ্রেডের ধাপ আছে।
