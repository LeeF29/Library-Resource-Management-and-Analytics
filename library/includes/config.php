
<?php
/**
 * Project configuration.
 * Supports local XAMPP and production environment variables.
 */

// --- Database configuration ---
define(
    'DB_HOST',
    getenv('DB_HOST') !== false
        ? getenv('DB_HOST')
        : 'mysql-3b856bfb-leeanthonybautista23-1bf8.h.aivencloud.com'
);

define(
    'DB_NAME',
    getenv('DB_NAME') !== false
        ? getenv('DB_NAME')
        : 'defaultdb'
);

define(
    'DB_USER',
    getenv('DB_USER') !== false
        ? getenv('DB_USER')
        : 'avnadmin'
);

define(
    'DB_PASS',
    getenv('DB_PASSWORD') !== false
        ? getenv('DB_PASSWORD')
        : ''
);

define(
    'DB_PORT',
    (int) (getenv('DB_PORT') ?: 11005)
);

const DB_CHARSET = 'utf8mb4';

// --- Web address of the project folder ---
// Local XAMPP: http://localhost/library
define(
    'BASE_URL',
    getenv('BASE_URL') !== false
        ? rtrim((string) getenv('BASE_URL'), '/')
        : '/library'
);

// --- Library rules ---
const LOAN_DAYS        = 7;
const MAX_ACTIVE_LOANS = 3;
const PER_PAGE         = 10;

const APP_NAME = 'Library Resource Management and Analytics System';

const SESSION_IDLE_SEC = 1800; // 30 minutes

date_default_timezone_set('Asia/Manila');
