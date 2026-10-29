# Testing

## A. Do this once on your own XAMPP (I could not test these - my workspace has no MySQL)
- [ ] Importing `database/library_db.sql` in phpMyAdmin shows no errors and creates 5 tables.
- [ ] http://localhost/library/ shows the public catalog with 15 books and "80%" availability.
- [ ] Log in as admin, librarian and student (demo accounts in README).
- [ ] Staff Overview shows 15 titles, 7 on loan, 2 overdue, 8 members on a fresh import.
- [ ] Borrow an item (New Loan), then return it (Circulation): availability goes down, then up.
- [ ] http://localhost/library/includes/config.php and /database/library_db.sql are denied (or show nothing).
- [ ] The pages look right on your phone / a narrow window.

## B. Already tested by me (SQLite stand-in database, PHP 8.3, Chromium browser)
**157 API checks - all passed**
- Visitor: can read the public catalog (active items only); blocked (401) from every staff/student endpoint; cannot create anything.
- Student: blocked (403) from all staff endpoints and from every write (create/delete/borrow/return/members/categories/users); sees only their own loans.
- Librarian: blocked from user accounts and from deleting members; Admin can.
- CSRF: requests without/with a wrong token and non-JSON requests are rejected.
- Books: ISBN checksum/duplicate, required fields, year, copies, category, type; copies cannot drop below borrowed; delete rules (active loans / history → inactive).
- Borrow/return: no copy, duplicate loan, 3-loan limit, inactive member, unknown ids, double return; availability always equals total copies minus active loans.
- Members, categories, user accounts, password hashing, deactivated account loses access at once, login throttle.
- Reports + CSV + date/category filters match the database; dashboard figures equal the database.
- SQL-injection text and `%` in search are harmless.

**38 browser checks - all passed** (real clicks in Chromium)
- Public and student pages contain **no** admin buttons or links (also checked in the HTML/JS source files).
- Student/visitor opening a staff page is sent to login or shown "Access denied".
- Staff add/delete a book, see validation messages, confirm dialogs, borrow, return, switch report tabs; librarian has no User Accounts menu and no Delete on members; admin has both.
- Phone width: no horizontal scrolling on catalog, dashboard or tables; menu opens.

Not tested: real MySQL/MariaDB, Internet Explorer/old browsers, many users at the same time.
