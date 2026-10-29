# Library Resource Management and Analytics System

**Frontend:** plain HTML + CSS + JavaScript pages. **Backend:** PHP that only sends/receives JSON (`api/`). **Database:** MySQL/MariaDB. Built for XAMPP. No framework and no external libraries (the charts are plain CSS), so it works without internet.

## Install in XAMPP (Windows)
1. Start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Copy this folder to `C:\xampp\htdocs\` and keep the name **`library`** (result: `C:\xampp\htdocs\library\index.html`).
   If you use another folder name, change `BASE_URL` in `includes/config.php` (for example `/mylibrary`).
3. Open http://localhost/phpmyadmin → **Import** → choose `database/library_db.sql` → **Go**. (Re-importing erases the data.)
4. Open **http://localhost/library/** (public catalog) or **http://localhost/library/login.html**.
5. If your MySQL root user has a password, set it in `includes/config.php` (`DB_PASS`).

Needs PHP 7.4 or newer (any current XAMPP).

## Demo accounts (change/deactivate before real use)
| Role | Username | Password |
|---|---|---|
| Library Administrator | admin | Admin@12345 |
| Librarian | librarian | Librarian@123 |
| Student (linked to member 2024-0001) | student | Student@123 |

The books, members and transactions in the SQL file are sample data; their dates are relative to the import day.

## Two separate sides
**Public / student side** (read-only, no admin functions at all)
- `index.html` – public online catalog: anyone can search and see availability. No login needed.
- `student/my_loans.html` – a logged-in student sees only **their own** loans and due dates.
- `login.html`

**Staff workspace** (Librarian + Administrator only; students are refused by the server)
- `staff/dashboard.html` (Overview), `books.html` + `book_form.html` (Catalog), `transactions.html` (Circulation), `borrow.html` (New Loan), `members.html` / `member_form.html` / `member_view.html`, `categories.html`, `reports.html`
- `admin/users.html`, `admin/user_form.html` – user accounts (Administrator only)

| Feature | Admin | Librarian | Student | Visitor |
|---|---|---|---|---|
| Search catalog, see availability | ✓ | ✓ | ✓ | ✓ |
| See own borrowing history | – | – | ✓ | – |
| Add / edit / delete resources, categories | ✓ | ✓ | – | – |
| Add / edit / deactivate members | ✓ | ✓ | – | – |
| Delete members (no history only) | ✓ | – | – | – |
| Borrow & return, circulation list | ✓ | ✓ | – | – |
| Dashboard, reports, CSV | ✓ | ✓ | – | – |
| Manage user accounts | ✓ | – | – | – |

## How it works
Every `.html` page is only a shell. Its JavaScript (`assets/js/app.js` + `assets/js/pages/*.js`) calls the PHP files in `api/` with `fetch`, then draws the data. **All security is enforced in PHP**, not in the HTML: opening a staff page without permission shows no data because the API answers 401/403.

```
index.html, login.html       public pages
student/ staff/ admin/       HTML pages by audience
assets/css/style.css         one stylesheet
assets/js/app.js             shared code (API calls, layout, tables, dialogs)
assets/js/pages/*.js         one small script per page
api/*.php                    JSON endpoints (auth, resources, members, transactions, categories, dashboard, reports, users)
includes/                    config, db, auth, helpers, services (borrow/return rules), api bootstrap
database/library_db.sql      schema + demo data
```

## Rules (change in `includes/config.php`)
7-day loan, max 3 active loans per member, 10 rows per page. A resource with borrowing history is never really deleted; it becomes **Inactive**. A member with unreturned items cannot be deactivated; a member with history cannot be deleted. "Overdue" = not returned and due date before today (shown on screen; no email/SMS).

## Security measures
Passwords hashed (`password_hash`); prepared statements everywhere; every value is escaped before it is shown; CSRF token (sent in an `X-CSRF-Token` header) plus JSON-only content type on every change; role checks on the server for every endpoint; session cookie HttpOnly + SameSite, session id renewed on login, idle logout after 30 minutes; basic login throttle (5 failures → wait 1 minute); `includes/` and `database/` blocked by `.htaccess`; the public catalog exposes only active items.

## Out of scope (per the capstone)
Payments, supplier purchasing, lost-book tracking, multiple branches, barcode/QR scanning.
