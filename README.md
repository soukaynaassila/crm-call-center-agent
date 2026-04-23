# 📞 Call Center CRM — Setup Guide

A professional **Call Center Client Management System** built with PHP, MySQL, HTML & CSS.

---

## 🗂️ Project Structure

```
call center agent/
├── config.php          ← Database connection + helper functions
├── login.php           ← Authentication page
├── logout.php          ← Session termination
├── dashboard.php       ← Main dashboard with KPI stats
├── clients.php         ← Client management (add/edit/delete/search)
├── calls.php           ← Call management (add/edit/delete/filter/status)
├── database.sql        ← SQL schema + seed data
├── css/
│   └── style.css       ← Global stylesheet (dark premium theme)
└── includes/
    └── sidebar.php     ← Shared navigation sidebar
```

---

## ⚙️ Requirements

| Tool    | Version       |
|---------|---------------|
| PHP     | 7.4 or higher |
| MySQL   | 5.7 or higher |
| XAMPP   | Any recent    |
| Browser | Any modern    |

---

## 🚀 Installation on XAMPP — Step by Step

### Step 1 — Copy the project

1. Start **XAMPP** and ensure **Apache** and **MySQL** are running (green).
2. Copy the entire `call center agent` folder into:

   ```
   C:\xampp\htdocs\call-center-crm\
   ```

   > Rename the folder to `call-center-crm` (no spaces) for a clean URL.

---

### Step 2 — Create the database

1. Open your browser and go to:  
   **http://localhost/phpmyadmin**

2. Click **New** in the left sidebar.

3. Create a database named **`callcenter_crm`** with collation `utf8mb4_unicode_ci`.

4. Select the new database, click the **SQL** tab, paste the contents of `database.sql`, and click **Go**.

   ✅ This will create the tables and insert demo data.

---

### Step 3 — Configure the database connection

Open `config.php` and verify the constants match your XAMPP setup:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'callcenter_crm');
define('DB_USER', 'root');   // ← XAMPP default
define('DB_PASS', '');       // ← XAMPP default (empty)
```

> If you have a custom MySQL password set in XAMPP, update `DB_PASS` accordingly.

---

### Step 4 — Open the app

Go to:

```
http://localhost/call-center-crm/login.php
```

---

## 🔐 Demo Login Credentials

| Agent          | Email                       | Password   |
|----------------|-----------------------------|------------|
| Sophie Martin  | sophie@callcenter.com       | `password` |
| Lucas Bernard  | lucas@callcenter.com        | `password` |

> Passwords are stored as **bcrypt hashes** — never in plain text.

---

## 📋 Features Overview

### Dashboard
- Welcome message with agent name & time
- 5 KPI stat cards (total clients, calls, traités, en attente, à rappeler)
- Donut chart (canvas — no external library)
- Recent clients and recent calls tables

### Client Management (`clients.php`)
- ➕ Add client (name, phone, email)
- ✏️ Edit existing client
- 🗑️ Delete client with modal confirmation (cascades to calls)
- 🔍 Live search by name or phone (debounced 500 ms)
- Shows call count per client with a direct link to filtered calls

### Call Management (`calls.php`)
- ➕ Add call (select client, problem, date, status)
- ✏️ Edit call
- 🗑️ Delete call with modal confirmation
- 🔽 Filter by status (pill buttons)
- ⚡ Quick status update via inline dropdown (no page reload for UX)
- Character counter on problem textarea

### Security
- PDO with prepared statements (SQL injection prevention)
- `htmlspecialchars()` on all output (XSS prevention)
- `password_hash()` / `password_verify()` for credentials
- Session-based authentication (`require_auth()` on every page)
- Session regeneration on login

---

## 🐛 Troubleshooting

| Issue | Solution |
|-------|----------|
| Blank page / DB error | Check `config.php` credentials; verify MySQL is running |
| "Table not found" | Re-run `database.sql` in phpMyAdmin |
| Password doesn't work | The demo password is exactly `password` (lowercase) |
| Accents broken in table | Ensure DB collation is `utf8mb4_unicode_ci` |
| `À rappeler` filter broken | Make sure MySQL column uses `utf8mb4` charset |

---

## 📌 Notes

- The project uses **no external JS framework** — just vanilla JavaScript.
- The donut chart on the dashboard is drawn with the **HTML5 Canvas API**.
- All date comparisons use the **server timezone** (set in XAMPP's `php.ini`).

---

*Built with ❤️ — Call Center CRM v1.0*
