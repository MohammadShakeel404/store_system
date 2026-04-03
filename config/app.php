<?php
/**
 * DMR Construction Store Management System
 * Application Configuration
 */

define('APP_NAME',      'DMR Store Management');
define('APP_COMPANY',   'DMR Construction PVT. LTD.');
define('APP_VERSION',   '1.0.0');
define('APP_URL',       getenv('APP_URL') ?: 'http://localhost/dmr-store');
define('APP_ROOT',      dirname(__DIR__));
define('UPLOAD_PATH',   APP_ROOT . '/uploads/docs/');
define('EXPORT_PATH',   APP_ROOT . '/exports/');

// Session settings
define('SESSION_LIFETIME', 3600 * 8); // 8 hours
define('SESSION_NAME',     'DMR_STORE_SESSION');

// Pagination
define('ROWS_PER_PAGE', 25);

// Roles
define('ROLE_ADMIN',      'admin');
define('ROLE_KEEPER',     'keeper');
define('ROLE_EMPLOYEE',   'employee');
define('ROLE_MANAGEMENT', 'management');

define('ROLE_LABELS', [
    ROLE_ADMIN      => 'Store Admin',
    ROLE_KEEPER     => 'Store Keeper',
    ROLE_EMPLOYEE   => 'Employee',
    ROLE_MANAGEMENT => 'Management',
]);

// Status constants
define('STATUS_PENDING',   'pending');
define('STATUS_APPROVED',  'approved');
define('STATUS_REJECTED',  'rejected');
define('STATUS_ISSUED',    'issued');
define('STATUS_CONVERTED', 'converted');
define('STATUS_ACTIVE',    'active');
define('STATUS_INACTIVE',  'inactive');

// Transaction types
define('TXN_STOCK_IN',    'stock_in');
define('TXN_STOCK_OUT',   'stock_out');
define('TXN_ADJUSTMENT',  'adjustment');
define('TXN_RETURN',      'return');
define('TXN_TRANSFER',    'transfer');

// Categories
define('CATEGORIES', [
    'Electrical', 'Civil', 'Plumbing', 'Safety',
    'Office', 'Mechanical', 'Hardware', 'Consumables'
]);

// Units
define('UNITS', [
    'Nos', 'Kg', 'Meter', 'Liter', 'Box',
    'Roll', 'Set', 'Pair', 'Bag', 'Bundle',
    'Pail', 'Sheet', 'Cum', 'Sqm', 'Rmt'
]);

// Departments
define('DEPARTMENTS', [
    'Civil', 'Electrical', 'Plumbing', 'Project',
    'Admin', 'Safety', 'HR', 'Accounts', 'Management', 'Stores'
]);
