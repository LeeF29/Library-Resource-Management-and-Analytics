-- ============================================================
-- Library Resource Management and Analytics System
--
--
-- DEMO ACCOUNTS (change these passwords before real use):
--   admin      / Admin@12345     (Library Administrator)
--   librarian  / Librarian@123   (Librarian)
--   student    / Student@123     (Student, linked to member 2024-0001)
--
-- Demo books, members and transactions are sample data only.
-- Dates of the sample transactions are relative to the day you import.
-- ============================================================
CREATE DATABASE IF NOT EXISTS library_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE library_db;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS transactions;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS resources;
DROP TABLE IF EXISTS members;
DROP TABLE IF EXISTS categories;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(80) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE members (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  member_no VARCHAR(20) NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(100) NULL,
  contact_number VARCHAR(20) NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_members_member_no (member_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE resources (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(200) NOT NULL,
  author VARCHAR(150) NOT NULL,
  isbn VARCHAR(13) NULL,
  category_id INT UNSIGNED NOT NULL,
  resource_type ENUM('book','journal','other') NOT NULL DEFAULT 'book',
  publisher VARCHAR(150) NULL,
  year_published SMALLINT UNSIGNED NULL,
  total_copies INT UNSIGNED NOT NULL DEFAULT 1,
  available_copies INT UNSIGNED NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_resources_isbn (isbn),
  KEY idx_resources_title (title),
  KEY idx_resources_category (category_id),
  CONSTRAINT fk_resources_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE RESTRICT,
  CONSTRAINT chk_resources_copies CHECK (available_copies <= total_copies)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(50) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  role ENUM('admin','librarian','student') NOT NULL,
  member_id INT UNSIGNED NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_member (member_id),
  CONSTRAINT fk_users_member FOREIGN KEY (member_id) REFERENCES members (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE transactions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  member_id INT UNSIGNED NOT NULL,
  resource_id INT UNSIGNED NOT NULL,
  borrow_date DATE NOT NULL,
  due_date DATE NOT NULL,
  return_date DATE NULL,
  processed_by INT UNSIGNED NULL,
  returned_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tx_member (member_id),
  KEY idx_tx_resource (resource_id),
  KEY idx_tx_return (return_date),
  KEY idx_tx_borrow (borrow_date),
  CONSTRAINT fk_tx_member FOREIGN KEY (member_id) REFERENCES members (id) ON DELETE RESTRICT,
  CONSTRAINT fk_tx_resource FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE RESTRICT,
  CONSTRAINT fk_tx_processed FOREIGN KEY (processed_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_tx_returned FOREIGN KEY (returned_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Categories (editable in the system)
INSERT INTO categories (id, name) VALUES
(1, 'Fiction'),
(2, 'Non-Fiction'),
(3, 'Reference'),
(4, 'Science'),
(5, 'Technology'),
(6, 'Filipino Literature');

-- Demo members
INSERT INTO members (id, member_no, full_name, email, contact_number, status) VALUES
(1, '2024-0001', 'Maria Santos', 'maria.santos@example.com', '09170000001', 'active'),
(2, '2024-0002', 'Juan Dela Cruz', 'juan.delacruz@example.com', '09170000002', 'active'),
(3, '2024-0003', 'Angelica Reyes', 'angelica.reyes@example.com', '09170000003', 'active'),
(4, '2024-0004', 'Mark Villanueva', 'mark.villanueva@example.com', '09170000004', 'active'),
(5, '2024-0005', 'Sofia Garcia', 'sofia.garcia@example.com', '09170000005', 'active'),
(6, '2024-0006', 'Paolo Mendoza', NULL, '09170000006', 'active'),
(7, '2024-0007', 'Isabel Torres', 'isabel.torres@example.com', NULL, 'active'),
(8, '2024-0008', 'Rafael Bautista', NULL, NULL, 'inactive');

-- Demo accounts
INSERT INTO users (id, username, password_hash, full_name, role, member_id, is_active) VALUES
(1, 'admin', '$2y$10$yVnWoWzEoHoa6jm6QAFheedlVqPJuJWOmRqinDJScFlYqKuVNg7IK', 'Library Administrator', 'admin', NULL, 1),
(2, 'librarian', '$2y$10$jdOqPZKNGSv90rwouCT8JuIj9UW.cOvrGdd3jxVwu517yUOXPfk0i', 'Demo Librarian', 'librarian', NULL, 1),
(3, 'student', '$2y$10$ZRLbsWfiYMdxwsL/2su5MO0XaUD63g6VBqsWAJ.iCSqeQe/CNGJGq', 'Maria Santos', 'student', 1, 1);

-- Demo library resources (ISBNs are sample values)
INSERT INTO resources (id, title, author, isbn, category_id, resource_type, publisher, year_published, total_copies, available_copies, is_active) VALUES
(1, 'Noli Me Tangere', 'Jose Rizal', '9789712723261', 6, 'book', 'Anvil Publishing', 2008, 4, 3, 1),
(2, 'El Filibusterismo', 'Jose Rizal', '9789712723278', 6, 'book', 'Anvil Publishing', 2008, 3, 3, 1),
(3, 'Clean Code', 'Robert C. Martin', '9780132350884', 5, 'book', 'Prentice Hall', 2008, 2, 0, 1),
(4, 'Introduction to Algorithms', 'Thomas H. Cormen', '9780262033848', 5, 'book', 'MIT Press', 2009, 2, 2, 1),
(5, 'Database System Concepts', 'Abraham Silberschatz', '9780078022159', 5, 'book', 'McGraw-Hill', 2019, 3, 2, 1),
(6, 'A Brief History of Time', 'Stephen Hawking', '9780553380163', 4, 'book', 'Bantam', 1998, 2, 1, 1),
(7, 'Cosmos', 'Carl Sagan', '9780345539434', 4, 'book', 'Ballantine Books', 2013, 2, 2, 1),
(8, 'The Great Gatsby', 'F. Scott Fitzgerald', '9780743273565', 1, 'book', 'Scribner', 2004, 3, 2, 1),
(9, 'To Kill a Mockingbird', 'Harper Lee', '9780061120084', 1, 'book', 'Harper Perennial', 2006, 3, 3, 1),
(10, 'Merriam-Webster''s Collegiate Dictionary', 'Merriam-Webster', '9789712723285', 3, 'book', 'Merriam-Webster', 2003, 1, 1, 1),
(11, 'Philippine Journal of Science', 'Department of Science and Technology', NULL, 4, 'journal', 'DOST-STII', 2023, 2, 2, 1),
(12, 'Journal of Information Technology Education', 'Informing Science Institute', NULL, 5, 'journal', 'Informing Science Press', 2022, 1, 1, 1),
(13, 'Campus Research Compendium', 'Library Staff', NULL, 3, 'other', 'School Library', 2024, 1, 1, 1),
(14, 'Sapiens: A Brief History of Humankind', 'Yuval Noah Harari', '9780062316110', 2, 'book', 'Harper', 2015, 3, 2, 1),
(15, 'Atomic Habits', 'James Clear', '9780735211292', 2, 'book', 'Avery', 2018, 3, 3, 1);

-- Demo transactions: 20 returned + 7 still borrowed (2 of them overdue)
INSERT INTO transactions (member_id, resource_id, borrow_date, due_date, return_date, processed_by, returned_by) VALUES
(1, 1, DATE_SUB(CURDATE(), INTERVAL 150 DAY), DATE_SUB(CURDATE(), INTERVAL 143 DAY), DATE_SUB(CURDATE(), INTERVAL 144 DAY), 2, 2),
(2, 15, DATE_SUB(CURDATE(), INTERVAL 140 DAY), DATE_SUB(CURDATE(), INTERVAL 133 DAY), DATE_SUB(CURDATE(), INTERVAL 133 DAY), 2, 2),
(3, 1, DATE_SUB(CURDATE(), INTERVAL 130 DAY), DATE_SUB(CURDATE(), INTERVAL 123 DAY), DATE_SUB(CURDATE(), INTERVAL 125 DAY), 2, 2),
(4, 3, DATE_SUB(CURDATE(), INTERVAL 120 DAY), DATE_SUB(CURDATE(), INTERVAL 113 DAY), DATE_SUB(CURDATE(), INTERVAL 111 DAY), 2, 2),
(5, 15, DATE_SUB(CURDATE(), INTERVAL 110 DAY), DATE_SUB(CURDATE(), INTERVAL 103 DAY), DATE_SUB(CURDATE(), INTERVAL 106 DAY), 2, 2),
(6, 5, DATE_SUB(CURDATE(), INTERVAL 100 DAY), DATE_SUB(CURDATE(), INTERVAL 93 DAY), DATE_SUB(CURDATE(), INTERVAL 93 DAY), 2, 2),
(1, 14, DATE_SUB(CURDATE(), INTERVAL 90 DAY), DATE_SUB(CURDATE(), INTERVAL 83 DAY), DATE_SUB(CURDATE(), INTERVAL 84 DAY), 2, 2),
(7, 15, DATE_SUB(CURDATE(), INTERVAL 80 DAY), DATE_SUB(CURDATE(), INTERVAL 73 DAY), DATE_SUB(CURDATE(), INTERVAL 77 DAY), 2, 2),
(3, 3, DATE_SUB(CURDATE(), INTERVAL 70 DAY), DATE_SUB(CURDATE(), INTERVAL 63 DAY), DATE_SUB(CURDATE(), INTERVAL 63 DAY), 2, 2),
(2, 1, DATE_SUB(CURDATE(), INTERVAL 60 DAY), DATE_SUB(CURDATE(), INTERVAL 53 DAY), DATE_SUB(CURDATE(), INTERVAL 52 DAY), 2, 2),
(4, 5, DATE_SUB(CURDATE(), INTERVAL 50 DAY), DATE_SUB(CURDATE(), INTERVAL 43 DAY), DATE_SUB(CURDATE(), INTERVAL 44 DAY), 2, 2),
(5, 6, DATE_SUB(CURDATE(), INTERVAL 45 DAY), DATE_SUB(CURDATE(), INTERVAL 38 DAY), DATE_SUB(CURDATE(), INTERVAL 40 DAY), 2, 2),
(6, 8, DATE_SUB(CURDATE(), INTERVAL 40 DAY), DATE_SUB(CURDATE(), INTERVAL 33 DAY), DATE_SUB(CURDATE(), INTERVAL 33 DAY), 2, 2),
(7, 2, DATE_SUB(CURDATE(), INTERVAL 35 DAY), DATE_SUB(CURDATE(), INTERVAL 28 DAY), DATE_SUB(CURDATE(), INTERVAL 28 DAY), 2, 2),
(1, 4, DATE_SUB(CURDATE(), INTERVAL 30 DAY), DATE_SUB(CURDATE(), INTERVAL 23 DAY), DATE_SUB(CURDATE(), INTERVAL 24 DAY), 2, 2),
(2, 9, DATE_SUB(CURDATE(), INTERVAL 28 DAY), DATE_SUB(CURDATE(), INTERVAL 21 DAY), DATE_SUB(CURDATE(), INTERVAL 23 DAY), 2, 2),
(3, 7, DATE_SUB(CURDATE(), INTERVAL 25 DAY), DATE_SUB(CURDATE(), INTERVAL 18 DAY), DATE_SUB(CURDATE(), INTERVAL 18 DAY), 2, 2),
(4, 15, DATE_SUB(CURDATE(), INTERVAL 20 DAY), DATE_SUB(CURDATE(), INTERVAL 13 DAY), DATE_SUB(CURDATE(), INTERVAL 16 DAY), 2, 2),
(5, 1, DATE_SUB(CURDATE(), INTERVAL 15 DAY), DATE_SUB(CURDATE(), INTERVAL 8 DAY), DATE_SUB(CURDATE(), INTERVAL 9 DAY), 2, 2),
(6, 3, DATE_SUB(CURDATE(), INTERVAL 10 DAY), DATE_SUB(CURDATE(), INTERVAL 3 DAY), DATE_SUB(CURDATE(), INTERVAL 7 DAY), 2, 2),
(1, 3, DATE_SUB(CURDATE(), INTERVAL 3 DAY), DATE_ADD(CURDATE(), INTERVAL 4 DAY), NULL, 2, NULL),
(2, 1, DATE_SUB(CURDATE(), INTERVAL 5 DAY), DATE_ADD(CURDATE(), INTERVAL 2 DAY), NULL, 2, NULL),
(3, 6, DATE_SUB(CURDATE(), INTERVAL 12 DAY), DATE_SUB(CURDATE(), INTERVAL 5 DAY), NULL, 2, NULL),
(4, 8, DATE_SUB(CURDATE(), INTERVAL 10 DAY), DATE_SUB(CURDATE(), INTERVAL 3 DAY), NULL, 2, NULL),
(1, 14, DATE_SUB(CURDATE(), INTERVAL 1 DAY), DATE_ADD(CURDATE(), INTERVAL 6 DAY), NULL, 2, NULL),
(5, 5, DATE_SUB(CURDATE(), INTERVAL 2 DAY), DATE_ADD(CURDATE(), INTERVAL 5 DAY), NULL, 2, NULL),
(2, 3, DATE_SUB(CURDATE(), INTERVAL 1 DAY), DATE_ADD(CURDATE(), INTERVAL 6 DAY), NULL, 2, NULL);
