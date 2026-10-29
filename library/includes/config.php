<?php
/**
 * Project settings. Edit this file if your XAMPP setup is different.
 * (This folder is blocked from the browser by includes/.htaccess.)
 */

// --- Database (XAMPP default: user "root" with an empty password) ---
const DB_HOST    = '127.0.0.1';
const DB_NAME    = 'library_db';
const DB_USER    = 'root';
const DB_PASS    = '';
const DB_CHARSET = 'utf8mb4';

// --- Web address of the project folder (htdocs/library -> /library) ---
const BASE_URL = '/library';

// --- Library rules ---
const LOAN_DAYS        = 7;   // how many days a borrowed item may be kept
const MAX_ACTIVE_LOANS = 3;   // most items one member may borrow at the same time
const PER_PAGE         = 10;  // rows per page in tables

const APP_NAME         = 'Library Resource Management and Analytics System';
const SESSION_IDLE_SEC = 1800; // log out after 30 minutes of no activity

date_default_timezone_set('Asia/Manila');
