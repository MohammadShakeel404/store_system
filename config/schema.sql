-- ============================================================
-- DMR Construction PVT. LTD. — Store Management System
-- Database Schema — Production Ready
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+05:30';
SET foreign_key_checks = 0;
SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

CREATE DATABASE IF NOT EXISTS dmr_store CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dmr_store;

-- ============================================================
-- ROLES
-- ============================================================
CREATE TABLE IF NOT EXISTS roles (
    id          TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(50) NOT NULL UNIQUE,
    label       VARCHAR(80) NOT NULL,
    description TEXT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO roles (id, name, label, description) VALUES
(1, 'admin',      'Store Admin',    'Full access — add/edit/delete products, approve/reject all requests, manage stock, users'),
(2, 'keeper',     'Store Keeper',   'Manage inventory, approve/issue materials, view reports'),
(3, 'employee',   'Employee',       'Create indents and issue requests, track own request status'),
(4, 'management', 'Management',     'View dashboards and reports only — read-only access');

-- ============================================================
-- DEPARTMENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS departments (
    id         SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL UNIQUE,
    code       VARCHAR(20) NOT NULL UNIQUE,
    is_active  TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO departments (name, code) VALUES
('Civil Engineering',  'CIVIL'),
('Electrical',         'ELEC'),
('Plumbing',           'PLUMB'),
('Project Management', 'PM'),
('Administration',     'ADMIN'),
('Safety & Security',  'SAFETY'),
('Mechanical',         'MECH'),
('Human Resources',    'HR'),
('Finance',            'FIN'),
('IT',                 'IT');

-- ============================================================
-- USERS
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id     VARCHAR(30) NOT NULL UNIQUE,
    full_name       VARCHAR(150) NOT NULL,
    username        VARCHAR(100) NOT NULL UNIQUE,
    email           VARCHAR(150) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    role_id         TINYINT UNSIGNED NOT NULL DEFAULT 3,
    department_id   SMALLINT UNSIGNED,
    phone           VARCHAR(20),
    profile_photo   VARCHAR(255),
    is_active       TINYINT(1) DEFAULT 1,
    last_login      DATETIME,
    login_attempts  TINYINT DEFAULT 0,
    locked_until    DATETIME,
    created_by      INT UNSIGNED,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id),
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_username (username),
    INDEX idx_role (role_id),
    INDEX idx_dept (department_id)
) ENGINE=InnoDB;

-- Default admin user: password = Admin@DMR2025
INSERT IGNORE INTO users (employee_id, full_name, username, email, password_hash, role_id, department_id) VALUES
('EMP-0001', 'Rajesh Sharma',  'admin',          'admin@dmrconstruction.in',   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uJsN7.pOi', 1, 5),
('EMP-0002', 'Mukesh Patel',   'storekeeper',    'keeper@dmrconstruction.in',  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uJsN7.pOi', 2, 5),
('EMP-0003', 'Deepak Verma',   'deepak.verma',   'deepak@dmrconstruction.in',  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uJsN7.pOi', 3, 2),
('EMP-0004', 'Priya Singh',    'priya.singh',    'priya@dmrconstruction.in',   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uJsN7.pOi', 3, 4),
('EMP-0005', 'Vikram Mishra',  'vikram.mishra',  'vikram@dmrconstruction.in',  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uJsN7.pOi', 4, 5),
('EMP-0006', 'Suresh Yadav',   'suresh.yadav',   'suresh@dmrconstruction.in',  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uJsN7.pOi', 3, 1),
('EMP-0007', 'Neha Tiwari',    'neha.tiwari',    'neha@dmrconstruction.in',    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uJsN7.pOi', 3, 5),
('EMP-0008', 'Anil Sharma',    'anil.sharma',    'anil@dmrconstruction.in',    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uJsN7.pOi', 3, 6);

-- ============================================================
-- CATEGORIES
-- ============================================================
CREATE TABLE IF NOT EXISTS categories (
    id         SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL UNIQUE,
    code       VARCHAR(30) NOT NULL UNIQUE,
    color_hex  VARCHAR(7) DEFAULT '#1B4F72',
    is_active  TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO categories (name, code, color_hex) VALUES
('Electrical',     'ELEC',  '#E67E22'),
('Civil',          'CIVIL', '#1B4F72'),
('Plumbing',       'PLUMB', '#2ECC71'),
('Safety',         'SAFE',  '#E74C3C'),
('Office',         'OFF',   '#9B59B6'),
('Mechanical',     'MECH',  '#1ABC9C'),
('Tools',          'TOOL',  '#F39C12'),
('Consumables',    'CONS',  '#95A5A6');

-- ============================================================
-- UNITS
-- ============================================================
CREATE TABLE IF NOT EXISTS units (
    id         SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(50) NOT NULL UNIQUE,
    symbol     VARCHAR(20) NOT NULL,
    is_active  TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

INSERT IGNORE INTO units (name, symbol) VALUES
('Numbers',    'Nos'),
('Kilogram',   'Kg'),
('Meter',      'Mtr'),
('Liter',      'Ltr'),
('Box',        'Box'),
('Roll',       'Roll'),
('Set',        'Set'),
('Pair',       'Pair'),
('Bag',        'Bag'),
('Ream',       'Ream'),
('Cubic Meter','CuM'),
('Square Meter','SqM'),
('Bundle',     'Bndl'),
('Piece',      'Pcs'),
('Gallon',     'Gal');

-- ============================================================
-- SUPPLIERS
-- ============================================================
CREATE TABLE IF NOT EXISTS suppliers (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,
    code        VARCHAR(30) NOT NULL UNIQUE,
    contact     VARCHAR(100),
    phone       VARCHAR(20),
    email       VARCHAR(150),
    address     TEXT,
    gst_number  VARCHAR(20),
    is_active   TINYINT(1) DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (name)
) ENGINE=InnoDB;

INSERT IGNORE INTO suppliers (name, code, contact, phone, email) VALUES
('RajSteel Industries',   'SUP-001', 'Raj Kumar',    '9876543210', 'raj@rajsteel.in'),
('UltraTech Cement Ltd',  'SUP-002', 'Sales Desk',   '1800222222', 'sales@ultratech.in'),
('Havells India Ltd',     'SUP-003', 'Area Manager', '9998887770', 'trade@havells.com'),
('Legrand India',         'SUP-004', 'Distributor',  '9887766550', ''),
('Supreme Industries',    'SUP-005', 'Local Agent',  '9765432100', ''),
('Astral Poly Technik',   'SUP-006', 'Branch Mgr',   '9654321000', ''),
('Safari Industries',     'SUP-007', 'Sales Rep',    '9543210000', ''),
('Karam Safety',          'SUP-008', 'Distributor',  '9432100000', ''),
('JK Paper Ltd',          'SUP-009', 'Area Sales',   '9321000000', ''),
('Asian Paints Ltd',      'SUP-010', 'Dealer',       '9210000000', '');

-- ============================================================
-- PRODUCTS (Product Master)
-- ============================================================
CREATE TABLE IF NOT EXISTS products (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_code   VARCHAR(30) NOT NULL UNIQUE,
    name           VARCHAR(200) NOT NULL,
    description    TEXT,
    category_id    SMALLINT UNSIGNED NOT NULL,
    unit_id        SMALLINT UNSIGNED NOT NULL,
    min_stock      DECIMAL(10,2) DEFAULT 0,
    max_stock      DECIMAL(10,2) DEFAULT 0,
    reorder_level  DECIMAL(10,2) DEFAULT 0,
    location       VARCHAR(100) COMMENT 'Rack/Shelf location',
    supplier_id    INT UNSIGNED,
    hsn_code       VARCHAR(20),
    rate           DECIMAL(10,2) DEFAULT 0 COMMENT 'Standard rate per unit',
    is_active      TINYINT(1) DEFAULT 1,
    created_by     INT UNSIGNED,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id),
    FOREIGN KEY (unit_id) REFERENCES units(id),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_code (product_code),
    INDEX idx_cat (category_id),
    FULLTEXT idx_search (name, description)
) ENGINE=InnoDB;

-- ============================================================
-- STOCK (Current stock levels — always derived from transactions but cached here)
-- ============================================================
CREATE TABLE IF NOT EXISTS stock (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id      INT UNSIGNED NOT NULL UNIQUE,
    opening_stock   DECIMAL(10,2) DEFAULT 0,
    current_stock   DECIMAL(10,2) DEFAULT 0,
    reserved_stock  DECIMAL(10,2) DEFAULT 0,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- STOCK TRANSACTIONS (Immutable ledger — no deletes allowed)
-- ============================================================
CREATE TABLE IF NOT EXISTS stock_transactions (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    txn_number      VARCHAR(30) NOT NULL UNIQUE,
    product_id      INT UNSIGNED NOT NULL,
    txn_type        ENUM('opening','stock_in','stock_out','adjustment_in','adjustment_out','return_in','return_out','reserved','unreserved') NOT NULL,
    quantity        DECIMAL(10,2) NOT NULL,
    balance_after   DECIMAL(10,2) NOT NULL,
    reference_type  ENUM('manual','grn','issue','indent','adjustment','return','opening') DEFAULT 'manual',
    reference_id    INT UNSIGNED COMMENT 'Links to grn_id, issue_id etc.',
    reference_number VARCHAR(50),
    unit_rate       DECIMAL(10,2) DEFAULT 0,
    total_value     DECIMAL(12,2) DEFAULT 0,
    remarks         TEXT,
    done_by         INT UNSIGNED NOT NULL,
    txn_date        DATE NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (done_by) REFERENCES users(id),
    INDEX idx_product (product_id),
    INDEX idx_txn_date (txn_date),
    INDEX idx_type (txn_type),
    INDEX idx_ref (reference_type, reference_id)
) ENGINE=InnoDB;

-- ============================================================
-- GRN (Goods Receipt Notes — Stock In)
-- ============================================================
CREATE TABLE IF NOT EXISTS grns (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    grn_number      VARCHAR(30) NOT NULL UNIQUE,
    supplier_id     INT UNSIGNED,
    invoice_number  VARCHAR(100),
    invoice_date    DATE,
    received_date   DATE NOT NULL,
    total_value     DECIMAL(12,2) DEFAULT 0,
    remarks         TEXT,
    status          ENUM('draft','confirmed') DEFAULT 'confirmed',
    created_by      INT UNSIGNED NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_grn (grn_number),
    INDEX idx_date (received_date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS grn_items (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    grn_id      INT UNSIGNED NOT NULL,
    product_id  INT UNSIGNED NOT NULL,
    quantity    DECIMAL(10,2) NOT NULL,
    unit_rate   DECIMAL(10,2) DEFAULT 0,
    total_value DECIMAL(12,2) DEFAULT 0,
    remarks     VARCHAR(255),
    FOREIGN KEY (grn_id) REFERENCES grns(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- ============================================================
-- INDENTS (New requirement requests)
-- ============================================================
CREATE TABLE IF NOT EXISTS indents (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    indent_number   VARCHAR(30) NOT NULL UNIQUE,
    requested_by    INT UNSIGNED NOT NULL,
    department_id   SMALLINT UNSIGNED NOT NULL,
    item_name       VARCHAR(200) NOT NULL,
    category_id     SMALLINT UNSIGNED,
    quantity        DECIMAL(10,2) NOT NULL,
    unit_id         SMALLINT UNSIGNED,
    purpose         TEXT NOT NULL,
    required_date   DATE NOT NULL,
    priority        ENUM('low','normal','high','urgent') DEFAULT 'normal',
    status          ENUM('pending','approved','rejected','converted','cancelled') DEFAULT 'pending',
    hod_approved_by INT UNSIGNED,
    hod_approved_at DATETIME,
    hod_remarks     TEXT,
    approved_by     INT UNSIGNED,
    approved_at     DATETIME,
    approval_remarks TEXT,
    rejected_by     INT UNSIGNED,
    rejected_at     DATETIME,
    rejection_reason TEXT,
    converted_to_po TINYINT(1) DEFAULT 0,
    po_reference    VARCHAR(50),
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (requested_by) REFERENCES users(id),
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (category_id) REFERENCES categories(id),
    FOREIGN KEY (unit_id) REFERENCES units(id),
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (rejected_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_number (indent_number),
    INDEX idx_status (status),
    INDEX idx_requested (requested_by),
    INDEX idx_date (created_at)
) ENGINE=InnoDB;

-- ============================================================
-- ISSUE REQUESTS
-- ============================================================
CREATE TABLE IF NOT EXISTS issue_requests (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    issue_number    VARCHAR(30) NOT NULL UNIQUE,
    requested_by    INT UNSIGNED NOT NULL,
    department_id   SMALLINT UNSIGNED NOT NULL,
    product_id      INT UNSIGNED NOT NULL,
    quantity        DECIMAL(10,2) NOT NULL,
    purpose         TEXT,
    work_order      VARCHAR(100),
    required_date   DATE,
    priority        ENUM('low','normal','high','urgent') DEFAULT 'normal',
    status          ENUM('pending','approved','issued','rejected','converted_indent','cancelled') DEFAULT 'pending',
    approved_by     INT UNSIGNED,
    approved_at     DATETIME,
    issued_by       INT UNSIGNED,
    issued_at       DATETIME,
    issue_remarks   TEXT,
    rejected_by     INT UNSIGNED,
    rejected_at     DATETIME,
    rejection_reason TEXT,
    txn_id          BIGINT UNSIGNED COMMENT 'Links to stock_transactions',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (requested_by) REFERENCES users(id),
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (rejected_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_number (issue_number),
    INDEX idx_status (status),
    INDEX idx_product (product_id),
    INDEX idx_requested (requested_by),
    INDEX idx_date (created_at)
) ENGINE=InnoDB;

-- ============================================================
-- NOTIFICATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS notifications (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    title       VARCHAR(200) NOT NULL,
    body        TEXT,
    type        ENUM('info','success','warning','danger') DEFAULT 'info',
    module      VARCHAR(50),
    ref_id      INT UNSIGNED,
    ref_url     VARCHAR(255),
    is_read     TINYINT(1) DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id, is_read),
    INDEX idx_date (created_at)
) ENGINE=InnoDB;

-- ============================================================
-- ACTIVITY / AUDIT LOG (Immutable)
-- ============================================================
CREATE TABLE IF NOT EXISTS audit_log (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED,
    user_name   VARCHAR(150) NOT NULL,
    user_role   VARCHAR(50),
    action      VARCHAR(100) NOT NULL,
    module      VARCHAR(80) NOT NULL,
    details     TEXT,
    ref_table   VARCHAR(80),
    ref_id      INT UNSIGNED,
    ip_address  VARCHAR(45),
    user_agent  VARCHAR(500),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_date (created_at),
    INDEX idx_module (module)
) ENGINE=InnoDB;

-- ============================================================
-- SETTINGS
-- ============================================================
CREATE TABLE IF NOT EXISTS settings (
    id          SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    key_name    VARCHAR(100) NOT NULL UNIQUE,
    value       TEXT,
    label       VARCHAR(200),
    group_name  VARCHAR(80) DEFAULT 'general',
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO settings (key_name, value, label, group_name) VALUES
('company_name',     'DMR Construction PVT. LTD.',   'Company Name',           'company'),
('company_address',  'Raipur, Chhattisgarh',          'Company Address',         'company'),
('company_phone',    '+91-771-XXXXXXX',               'Phone',                   'company'),
('company_email',    'store@dmrconstruction.in',       'Store Email',             'company'),
('company_gst',      '',                               'GST Number',              'company'),
('low_stock_email',  '1',                              'Email on Low Stock',       'notification'),
('auto_grn_prefix',  'GRN',                           'GRN Number Prefix',        'numbering'),
('auto_ind_prefix',  'IND',                           'Indent Number Prefix',     'numbering'),
('auto_iss_prefix',  'ISS',                           'Issue Number Prefix',      'numbering'),
('auto_txn_prefix',  'TXN',                           'Transaction Number Prefix','numbering'),
('indent_hod_approval','0',                           'Require HOD Approval for Indents','workflow'),
('issue_hod_approval','0',                            'Require HOD Approval for Issues','workflow'),
('items_per_page',   '25',                            'Records Per Page',         'display'),
('date_format',      'd M Y',                         'Date Format',              'display'),
('financial_year',   '2025-26',                       'Current Financial Year',   'general'),
('session_timeout',  '120',                           'Session Timeout (minutes)','security');

-- ============================================================
-- SAMPLE PRODUCTS DATA
-- ============================================================
INSERT IGNORE INTO products (product_code, name, category_id, unit_id, min_stock, max_stock, reorder_level, location, supplier_id, rate, created_by) VALUES
('P-CIVIL-001', '14mm Steel TMT Rod',          2, 1,  50,  500, 75,  'Yard-A1', 1, 72.50, 1),
('P-CIVIL-002', 'OPC Cement 50kg Bag',         2, 9, 100,  500, 150, 'Yard-B1', 2, 385.0, 1),
('P-CIVIL-003', 'River Sand (Fine)',            2, 11, 10,   50, 15,  'Yard-A2', NULL, 0, 1),
('P-CIVIL-004', 'Coarse Aggregate 20mm',       2, 11, 10,   50, 15,  'Yard-A3', NULL, 0, 1),
('P-CIVIL-005', 'Binding Wire 18 SWG',         2, 2,  25,  200, 40,  'Rack-A1', NULL, 4.50, 1),
('P-CIVIL-006', 'Bricks (Class A)',            2, 1, 500, 5000,1000, 'Yard-C1', NULL, 8.00, 1),
('P-ELEC-001',  'XLPE Cable 4 Sqmm',           1, 3, 100,  500, 150, 'Rack-B1', 3, 45.0, 1),
('P-ELEC-002',  'XLPE Cable 6 Sqmm',           1, 3,  50,  300, 80,  'Rack-B2', 3, 68.0, 1),
('P-ELEC-003',  'MCB 32A Double Pole',         1, 1,  15,   50, 20,  'Rack-B3', 4, 650.0, 1),
('P-ELEC-004',  'MCB 16A Single Pole',         1, 1,  20,  100, 30,  'Rack-B4', 4, 180.0, 1),
('P-ELEC-005',  'PVC Conduit Pipe 25mm',       1, 3,  50,  300, 80,  'Rack-B5', 5, 38.0, 1),
('P-ELEC-006',  'LED Flood Light 50W',         1, 1,  10,   50, 15,  'Rack-B6', NULL, 1200.0, 1),
('P-ELEC-007',  'Switch Socket 6A',            1, 1,  50,  200, 80,  'Rack-B7', 4, 75.0, 1),
('P-PLMB-001',  'CPVC Pipe 1 inch',            3, 3,  20,  200, 30,  'Rack-C1', 6, 185.0, 1),
('P-PLMB-002',  'GI Pipe 1.5 inch',            3, 3,  10,  100, 20,  'Rack-C2', NULL, 320.0, 1),
('P-PLMB-003',  'Ball Valve 1 inch',           3, 1,  10,   50, 15,  'Rack-C3', NULL, 450.0, 1),
('P-SAFE-001',  'Safety Helmet IS 2925',        4, 1,  20,  100, 30,  'Rack-D1', 7, 280.0, 1),
('P-SAFE-002',  'Safety Harness Full Body',    4, 1,  10,   50, 15,  'Rack-D2', 8, 1800.0, 1),
('P-SAFE-003',  'Safety Shoes Size 8',         4, 1,  10,   50, 15,  'Rack-D3', NULL, 950.0, 1),
('P-SAFE-004',  'Hand Gloves (Pair)',          4, 14, 30,  200, 50,  'Rack-D4', NULL, 85.0, 1),
('P-SAFE-005',  'Reflective Jacket',           4, 1,  20,  100, 30,  'Rack-D5', NULL, 320.0, 1),
('P-OFF-001',   'A4 Paper Ream 75 GSM',        5, 10, 10,   50, 15,  'Off-1',   9,  420.0, 1),
('P-OFF-002',   'Printer Cartridge HP 680',    5, 1,   3,   10,  5,  'Off-2',   NULL, 850.0, 1),
('P-MECH-001',  'Drill Machine 13mm',          6, 1,   2,    5,  3,  'Tool-1',  NULL,4500.0, 1),
('P-MECH-002',  'Angle Grinder 4.5 inch',      6, 1,   3,   10,  5,  'Tool-1',  NULL,3200.0, 1),
('P-CIVIL-007', 'White Cement 25kg',           2, 9,  10,   50, 15,  'Rack-A2', NULL, 480.0, 1),
('P-CIVIL-008', 'Waterproof Paint 20L',        2, 6,   5,   30,  8,  'Rack-A3', 10, 2800.0, 1);

-- Insert opening stock for all products
INSERT IGNORE INTO stock (product_id, opening_stock, current_stock, reserved_stock)
SELECT id,
    CASE product_code
        WHEN 'P-CIVIL-001' THEN 150  WHEN 'P-CIVIL-002' THEN 85
        WHEN 'P-CIVIL-003' THEN 20   WHEN 'P-CIVIL-004' THEN 18
        WHEN 'P-CIVIL-005' THEN 60   WHEN 'P-CIVIL-006' THEN 2500
        WHEN 'P-ELEC-001'  THEN 300  WHEN 'P-ELEC-002'  THEN 180
        WHEN 'P-ELEC-003'  THEN 8    WHEN 'P-ELEC-004'  THEN 35
        WHEN 'P-ELEC-005'  THEN 200  WHEN 'P-ELEC-006'  THEN 12
        WHEN 'P-ELEC-007'  THEN 80   WHEN 'P-PLMB-001'  THEN 5
        WHEN 'P-PLMB-002'  THEN 25   WHEN 'P-PLMB-003'  THEN 18
        WHEN 'P-SAFE-001'  THEN 25   WHEN 'P-SAFE-002'  THEN 12
        WHEN 'P-SAFE-003'  THEN 8    WHEN 'P-SAFE-004'  THEN 45
        WHEN 'P-SAFE-005'  THEN 22   WHEN 'P-OFF-001'   THEN 20
        WHEN 'P-OFF-002'   THEN 4    WHEN 'P-MECH-001'  THEN 3
        WHEN 'P-MECH-002'  THEN 4    WHEN 'P-CIVIL-007' THEN 15
        WHEN 'P-CIVIL-008' THEN 8    ELSE 0
    END AS opening,
    CASE product_code
        WHEN 'P-CIVIL-001' THEN 150  WHEN 'P-CIVIL-002' THEN 85
        WHEN 'P-CIVIL-003' THEN 20   WHEN 'P-CIVIL-004' THEN 18
        WHEN 'P-CIVIL-005' THEN 60   WHEN 'P-CIVIL-006' THEN 2500
        WHEN 'P-ELEC-001'  THEN 300  WHEN 'P-ELEC-002'  THEN 180
        WHEN 'P-ELEC-003'  THEN 8    WHEN 'P-ELEC-004'  THEN 35
        WHEN 'P-ELEC-005'  THEN 200  WHEN 'P-ELEC-006'  THEN 12
        WHEN 'P-ELEC-007'  THEN 80   WHEN 'P-PLMB-001'  THEN 5
        WHEN 'P-PLMB-002'  THEN 25   WHEN 'P-PLMB-003'  THEN 18
        WHEN 'P-SAFE-001'  THEN 25   WHEN 'P-SAFE-002'  THEN 12
        WHEN 'P-SAFE-003'  THEN 8    WHEN 'P-SAFE-004'  THEN 45
        WHEN 'P-SAFE-005'  THEN 22   WHEN 'P-OFF-001'   THEN 20
        WHEN 'P-OFF-002'   THEN 4    WHEN 'P-MECH-001'  THEN 3
        WHEN 'P-MECH-002'  THEN 4    WHEN 'P-CIVIL-007' THEN 15
        WHEN 'P-CIVIL-008' THEN 8    ELSE 0
    END AS current,
    CASE product_code
        WHEN 'P-CIVIL-001' THEN 20   WHEN 'P-CIVIL-002' THEN 10
        WHEN 'P-ELEC-001'  THEN 50   WHEN 'P-ELEC-003'  THEN 2
        WHEN 'P-SAFE-001'  THEN 3    WHEN 'P-SAFE-002'  THEN 2
        ELSE 0
    END AS reserved
FROM products;

-- Sample indents
INSERT IGNORE INTO indents (indent_number, requested_by, department_id, item_name, category_id, quantity, unit_id, purpose, required_date, priority, status, created_at) VALUES
('IND-2025-0001', 3, 2, 'LED Flood Light 50W', 1, 10, 1, 'Site lighting for Phase-2 construction area', '2025-03-15', 'high', 'pending',    NOW() - INTERVAL 3 DAY),
('IND-2025-0002', 6, 1, 'Formwork Plywood 18mm', 2, 50, 1, 'Column shuttering for Block-B', '2025-03-18', 'normal', 'approved',  NOW() - INTERVAL 5 DAY),
('IND-2025-0003', 4, 4, 'Survey Total Station Leica', 6, 1, 1, 'Road alignment survey for Phase-3', '2025-03-20', 'urgent', 'pending',   NOW() - INTERVAL 2 DAY),
('IND-2025-0004', 8, 6, 'High Visibility Jacket', 4, 20, 1, 'New workforce onboarding safety requirement', '2025-03-16', 'normal', 'rejected', NOW() - INTERVAL 7 DAY),
('IND-2025-0005', 6, 1, 'TMT Rod 20mm',           2, 500, 2, 'Foundation reinforcement for Block-C', '2025-03-22', 'high', 'converted', NOW() - INTERVAL 8 DAY);

-- Sample issue requests
INSERT IGNORE INTO issue_requests (issue_number, requested_by, department_id, product_id, quantity, purpose, work_order, priority, status, approved_by, approved_at, issued_by, issued_at, created_at) VALUES
('ISS-2025-0001', 3, 2, 9,  2, 'Distribution panel installation', 'WO-2025-045', 'normal', 'issued',  1, NOW()-INTERVAL 2 DAY, 2, NOW()-INTERVAL 2 DAY, NOW()-INTERVAL 3 DAY),
('ISS-2025-0002', 6, 1, 2,  10,'Plastering work Block-B Level-2', 'WO-2025-041', 'normal', 'pending', NULL, NULL, NULL, NULL, NOW()-INTERVAL 1 DAY),
('ISS-2025-0003', 4, 4, 17, 5, 'New site workers safety induction', 'WO-2025-050', 'high',   'pending', NULL, NULL, NULL, NULL, NOW()-INTERVAL 1 DAY),
('ISS-2025-0004', 7, 5, 23, 3, 'Monthly office stationery',        NULL,          'low',    'issued',  1, NOW()-INTERVAL 4 DAY, 2, NOW()-INTERVAL 4 DAY, NOW()-INTERVAL 5 DAY),
('ISS-2025-0005', 6, 1, 14, 30,'Plumbing work washroom Block-A',   'WO-2025-038', 'high',   'pending', NULL, NULL, NULL, NULL, NOW()-INTERVAL 1 DAY),
('ISS-2025-0006', 8, 6, 18, 3, 'Height work safety requirement',   'WO-2025-052', 'urgent', 'issued',  2, NOW()-INTERVAL 6 DAY, 2, NOW()-INTERVAL 6 DAY, NOW()-INTERVAL 7 DAY),
('ISS-2025-0007', 3, 2, 7,  50,'Phase-2 main feeder cable run',    'WO-2025-044', 'high',   'rejected',1, NOW()-INTERVAL 5 DAY, NULL, NULL, NOW()-INTERVAL 6 DAY);

UPDATE issue_requests SET rejection_reason='Cable purchase order is pending. Contact procurement.' WHERE issue_number='ISS-2025-0007';
UPDATE issue_requests SET approved_by=1, approved_at=NOW()-INTERVAL 5 DAY WHERE indent_number='IND-2025-0002' AND 0=1;
UPDATE indents SET approved_by=1, approved_at=NOW()-INTERVAL 4 DAY WHERE indent_number='IND-2025-0002';
UPDATE indents SET rejected_by=1, rejected_at=NOW()-INTERVAL 6 DAY, rejection_reason='Items already available in stock — check inventory before raising indent.' WHERE indent_number='IND-2025-0004';
UPDATE indents SET po_reference='PO-2025-018', converted_to_po=1 WHERE indent_number='IND-2025-0005';

-- Sample notifications
INSERT IGNORE INTO notifications (user_id, title, body, type, module, is_read) VALUES
(1, 'New Issue Request Pending', 'Kiran Desai requested 10 bags OPC Cement (ISS-2025-0002)', 'warning', 'issue', 0),
(1, 'New Issue Request Pending', 'Priya Singh requested 5 Safety Helmets (ISS-2025-0003)', 'warning', 'issue', 0),
(1, 'Low Stock Alert', 'MCB 32A DP is below minimum level — only 8 units remaining (min: 15)', 'danger', 'inventory', 0),
(1, 'Low Stock Alert', 'CPVC Pipe 1 inch is critically low — only 5 mtrs remaining (min: 20)', 'danger', 'inventory', 0),
(1, 'Indent Submitted', 'Deepak Verma raised indent for LED Flood Light 50W (IND-2025-0001)', 'info', 'indent', 1),
(3, 'Issue Request Approved', 'Your request ISS-2025-0001 for MCB 32A DP has been approved and issued.', 'success', 'issue', 0),
(6, 'Indent Approved', 'Your indent IND-2025-0002 for Formwork Plywood has been approved.', 'success', 'indent', 1),
(8, 'Issue Approved & Issued', 'Your request ISS-2025-0006 for Safety Harness has been issued.', 'success', 'issue', 0);

-- Seed audit log
INSERT IGNORE INTO audit_log (user_id, user_name, user_role, action, module, details, ip_address) VALUES
(1,'Rajesh Sharma','Store Admin','APPROVE','Issue Request','Approved ISS-2025-0001 — MCB 32A DP x2 to Deepak Verma','192.168.1.10'),
(1,'Rajesh Sharma','Store Admin','REJECT','Issue Request','Rejected ISS-2025-0007 — XLPE Cable 50m (PO pending)','192.168.1.10'),
(2,'Mukesh Patel','Store Keeper','ISSUE','Issue Request','Issued ISS-2025-0006 — Safety Harness x3 to Anil Sharma','192.168.1.15'),
(1,'Rajesh Sharma','Store Admin','APPROVE','Indent','Approved IND-2025-0002 — Formwork Plywood 50 Nos','192.168.1.10'),
(1,'Rajesh Sharma','Store Admin','REJECT','Indent','Rejected IND-2025-0004 — Items in stock','192.168.1.10');

SET foreign_key_checks = 1;
