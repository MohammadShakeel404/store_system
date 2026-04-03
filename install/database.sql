-- ============================================================
-- DMR Construction PVT. LTD. — Store Management System
-- Database Schema + Seed Data
-- MySQL 5.7+ / MariaDB 10.3+
-- ============================================================

CREATE DATABASE IF NOT EXISTS dmr_store CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dmr_store;

-- ============================================================
-- CATEGORIES
-- ============================================================
CREATE TABLE categories (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    description VARCHAR(200),
    color VARCHAR(10) DEFAULT '#1B4F72',
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- USERS
-- ============================================================
CREATE TABLE users (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    employee_id VARCHAR(20) UNIQUE,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    department VARCHAR(50),
    designation VARCHAR(100),
    role ENUM('admin','keeper','employee','management') DEFAULT 'employee',
    status ENUM('active','inactive') DEFAULT 'active',
    last_login TIMESTAMP NULL,
    password_changed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- PRODUCTS (PRODUCT MASTER)
-- ============================================================
CREATE TABLE products (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    product_code VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    category_id INT UNSIGNED,
    unit VARCHAR(20) NOT NULL DEFAULT 'Nos',
    current_stock DECIMAL(12,2) DEFAULT 0,
    reserved_stock DECIMAL(12,2) DEFAULT 0,
    min_stock_level DECIMAL(12,2) DEFAULT 0,
    rack_location VARCHAR(50),
    supplier_name VARCHAR(100),
    supplier_contact VARCHAR(100),
    hsn_code VARCHAR(20),
    unit_price DECIMAL(12,2) DEFAULT 0,
    status ENUM('active','inactive') DEFAULT 'active',
    created_by INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- STOCK TRANSACTIONS (IMMUTABLE LEDGER)
-- ============================================================
CREATE TABLE stock_transactions (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    txn_number VARCHAR(25) UNIQUE NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    type ENUM('stock_in','stock_out','adjustment_in','adjustment_out','return') NOT NULL,
    quantity DECIMAL(12,2) NOT NULL,
    balance_before DECIMAL(12,2) NOT NULL,
    balance_after DECIMAL(12,2) NOT NULL,
    reference_number VARCHAR(50),
    reference_type ENUM('grn','issue','adjustment','return','opening') DEFAULT NULL,
    supplier VARCHAR(100),
    invoice_number VARCHAR(50),
    remarks TEXT,
    created_by INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- INDENT REQUESTS
-- ============================================================
CREATE TABLE indents (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    indent_number VARCHAR(25) UNIQUE NOT NULL,
    employee_id INT UNSIGNED,
    employee_name VARCHAR(100) NOT NULL,
    department VARCHAR(50),
    item_name VARCHAR(200) NOT NULL,
    quantity DECIMAL(12,2) NOT NULL,
    unit VARCHAR(20),
    category_id INT UNSIGNED,
    purpose TEXT,
    required_date DATE,
    priority ENUM('normal','urgent','critical') DEFAULT 'normal',
    status ENUM('pending','approved','rejected','converted') DEFAULT 'pending',
    hod_approved TINYINT DEFAULT 0,
    hod_approved_by INT UNSIGNED,
    hod_approved_at TIMESTAMP NULL,
    approved_by INT UNSIGNED,
    approved_at TIMESTAMP NULL,
    rejection_reason TEXT,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- ISSUE REQUESTS
-- ============================================================
CREATE TABLE issue_requests (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    issue_number VARCHAR(25) UNIQUE NOT NULL,
    employee_id INT UNSIGNED,
    employee_name VARCHAR(100) NOT NULL,
    department VARCHAR(50),
    product_id INT UNSIGNED NOT NULL,
    quantity_requested DECIMAL(12,2) NOT NULL,
    quantity_issued DECIMAL(12,2) DEFAULT NULL,
    purpose TEXT,
    work_order_ref VARCHAR(100),
    required_date DATE,
    status ENUM('pending','issued','partially_issued','rejected') DEFAULT 'pending',
    issued_by INT UNSIGNED,
    issued_at TIMESTAMP NULL,
    rejection_reason TEXT,
    stock_txn_id INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (stock_txn_id) REFERENCES stock_transactions(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- NOTIFICATIONS
-- ============================================================
CREATE TABLE notifications (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNSIGNED,
    title VARCHAR(200) NOT NULL,
    body TEXT,
    type ENUM('info','success','warning','danger') DEFAULT 'info',
    link VARCHAR(200),
    is_read TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- AUDIT LOG (IMMUTABLE)
-- ============================================================
CREATE TABLE audit_log (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNSIGNED,
    user_name VARCHAR(100),
    user_role VARCHAR(50),
    action VARCHAR(100) NOT NULL,
    module VARCHAR(100) NOT NULL,
    record_id INT UNSIGNED,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent VARCHAR(300),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- COUNTER TABLE (FOR AUTO-NUMBERING)
-- ============================================================
CREATE TABLE counters (
    name VARCHAR(50) PRIMARY KEY,
    prefix VARCHAR(10),
    current_value INT UNSIGNED DEFAULT 0,
    fiscal_year VARCHAR(10)
) ENGINE=InnoDB;

INSERT INTO counters VALUES
('indent','IND',0,'2024-25'),
('issue','ISS',0,'2024-25'),
('grn','GRN',0,'2024-25'),
('txn','TXN',0,'2024-25'),
('adjustment','ADJ',0,'2024-25');

-- ============================================================
-- SEED DATA — CATEGORIES
-- ============================================================
INSERT INTO categories (name, description, color) VALUES
('Civil','Cement, sand, steel, formwork','#1B4F72'),
('Electrical','Cables, MCB, switches, lights','#E67E22'),
('Plumbing','Pipes, fittings, valves','#2ECC71'),
('Safety','PPE, helmets, harness, vests','#E74C3C'),
('Office','Stationery, printer consumables','#9B59B6'),
('Mechanical','Tools, equipment, machinery parts','#1ABC9C'),
('Finishing','Paint, tiles, woodwork materials','#F39C12');

-- ============================================================
-- SEED DATA — USERS (passwords are all: Admin@1234)
-- ============================================================
INSERT INTO users (employee_id, name, username, password, email, department, designation, role) VALUES
('EMP-001', 'Rajesh Sharma', 'admin@dmrconstruction.in', '$2y$10$F1QNAjcqOPjASTcue6xJ5.idKOaZYpx8xLth7QVdzRdbmmTSkqGt.', 'rajesh@dmrconstruction.in', 'Administration', 'Store Admin', 'admin'),
('EMP-002', 'Mukesh Patel', 'keeper@dmrconstruction.in', '$2y$10$F1QNAjcqOPjASTcue6xJ5.idKOaZYpx8xLth7QVdzRdbmmTSkqGt.', 'mukesh@dmrconstruction.in', 'Store', 'Store Keeper', 'keeper'),
('EMP-003', 'Deepak Verma', 'deepak@dmrconstruction.in', '$2y$10$F1QNAjcqOPjASTcue6xJ5.idKOaZYpx8xLth7QVdzRdbmmTSkqGt.', 'deepak@dmrconstruction.in', 'Electrical', 'Electrical Engineer', 'employee'),
('EMP-004', 'Priya Singh', 'priya@dmrconstruction.in', '$2y$10$F1QNAjcqOPjASTcue6xJ5.idKOaZYpx8xLth7QVdzRdbmmTSkqGt.', 'priya@dmrconstruction.in', 'Project', 'Project Engineer', 'employee'),
('EMP-005', 'Vikram Mishra', 'mgmt@dmrconstruction.in', '$2y$10$F1QNAjcqOPjASTcue6xJ5.idKOaZYpx8xLth7QVdzRdbmmTSkqGt.', 'vikram@dmrconstruction.in', 'Management', 'Project Director', 'management'),
('EMP-006', 'Suresh Yadav', 'suresh@dmrconstruction.in', '$2y$10$F1QNAjcqOPjASTcue6xJ5.idKOaZYpx8xLth7QVdzRdbmmTSkqGt.', 'suresh@dmrconstruction.in', 'Civil', 'Site Engineer', 'employee'),
('EMP-007', 'Neha Tiwari', 'neha@dmrconstruction.in', '$2y$10$F1QNAjcqOPjASTcue6xJ5.idKOaZYpx8xLth7QVdzRdbmmTSkqGt.', 'neha@dmrconstruction.in', 'Admin', 'Office Assistant', 'employee');

-- ============================================================
-- SEED DATA — PRODUCTS
-- ============================================================
INSERT INTO products (product_code, name, category_id, unit, current_stock, reserved_stock, min_stock_level, rack_location, supplier_name, created_by) VALUES
('P-001', '14mm TMT Steel Rod', 1, 'Nos', 150, 20, 50, 'Yard-A1', 'Raj Steel Traders', 1),
('P-002', 'OPC Cement 50kg', 1, 'Bag', 85, 10, 100, 'Yard-B', 'UltraTech Cement', 1),
('P-003', 'XLPE Cable 4mm (Per Meter)', 2, 'Meter', 300, 50, 100, 'Rack-B2', 'Havells India', 1),
('P-004', 'MCB 32A Double Pole', 2, 'Nos', 8, 2, 15, 'Rack-B1', 'Legrand India', 1),
('P-005', 'PVC Conduit Pipe 25mm', 2, 'Meter', 200, 0, 50, 'Rack-B3', 'Supreme Industries', 1),
('P-006', 'CPVC Pipe 1 Inch (Per Meter)', 3, 'Meter', 5, 0, 20, 'Rack-C1', 'Astral Pipes', 1),
('P-007', 'ISI Safety Helmet', 4, 'Nos', 25, 3, 20, 'Rack-D1', 'Safari Pro', 1),
('P-008', 'Full Body Safety Harness', 4, 'Nos', 12, 2, 10, 'Rack-D2', 'Karam Industries', 1),
('P-009', 'A4 Paper Ream 500 Sheets', 5, 'Ream', 20, 0, 10, 'Office-1', 'JK Paper Ltd', 1),
('P-010', 'Exterior Wall Paint 20L', 7, 'Pail', 18, 5, 10, 'Yard-C', 'Asian Paints', 1),
('P-011', 'Coarse Sand 20mm Gravel', 1, 'Cum', 45, 10, 20, 'Yard-A', 'Local Supplier', 1),
('P-012', 'GI Binding Wire 1mm', 1, 'Kg', 60, 0, 25, 'Rack-A2', 'Local Supplier', 1),
('P-013', 'High Visibility Safety Vest', 4, 'Nos', 30, 5, 20, 'Rack-D3', 'Safari Pro', 1),
('P-014', 'Angle Grinder 4.5 inch', 6, 'Nos', 4, 1, 2, 'Tool-1', 'Bosch India', 1),
('P-015', 'Welding Rod 3.15mm (Box)', 6, 'Box', 22, 3, 10, 'Rack-E1', 'D&H Secheron', 1);

-- ============================================================
-- SEED DATA — STOCK TRANSACTIONS (OPENING)
-- ============================================================
INSERT INTO stock_transactions (txn_number, product_id, type, quantity, balance_before, balance_after, reference_number, reference_type, remarks, created_by) VALUES
('TXN-0001', 1, 'stock_in', 150, 0, 150, 'GRN-0001', 'opening', 'Opening stock entry', 1),
('TXN-0002', 2, 'stock_in', 85, 0, 85, 'GRN-0001', 'opening', 'Opening stock entry', 1),
('TXN-0003', 3, 'stock_in', 300, 0, 300, 'GRN-0001', 'opening', 'Opening stock entry', 1),
('TXN-0004', 4, 'stock_in', 8, 0, 8, 'GRN-0001', 'opening', 'Opening stock entry', 1),
('TXN-0005', 5, 'stock_in', 200, 0, 200, 'GRN-0001', 'opening', 'Opening stock entry', 1),
('TXN-0006', 6, 'stock_in', 5, 0, 5, 'GRN-0001', 'opening', 'Opening stock entry', 1),
('TXN-0007', 7, 'stock_in', 25, 0, 25, 'GRN-0001', 'opening', 'Opening stock entry', 1),
('TXN-0008', 8, 'stock_in', 12, 0, 12, 'GRN-0001', 'opening', 'Opening stock entry', 1),
('TXN-0009', 9, 'stock_in', 20, 0, 20, 'GRN-0001', 'opening', 'Opening stock entry', 1),
('TXN-0010', 10, 'stock_in', 18, 0, 18, 'GRN-0001', 'opening', 'Opening stock entry', 1);

UPDATE counters SET current_value = 10 WHERE name = 'txn';
UPDATE counters SET current_value = 1 WHERE name = 'grn';

-- ============================================================
-- SEED DATA — INDENT REQUESTS
-- ============================================================
INSERT INTO indents (indent_number, employee_id, employee_name, department, item_name, quantity, unit, category_id, purpose, required_date, status, created_at) VALUES
('IND-0001', 3, 'Deepak Verma', 'Electrical', 'LED Flood Light 50W', 10, 'Nos', 2, 'Site lighting for Phase-2 block', '2025-03-20', 'pending', NOW() - INTERVAL 2 DAY),
('IND-0002', 6, 'Suresh Yadav', 'Civil', 'Formwork Plywood 18mm', 50, 'Nos', 1, 'Column shuttering for Block-B', '2025-03-18', 'approved', NOW() - INTERVAL 4 DAY),
('IND-0003', 4, 'Priya Singh', 'Project', 'Survey Total Station Tripod', 1, 'Nos', 6, 'Road alignment survey for plot 12', '2025-03-22', 'pending', NOW() - INTERVAL 1 DAY),
('IND-0004', 7, 'Neha Tiwari', 'Admin', 'HP Toner Cartridge 85A', 3, 'Nos', 5, 'Printer consumables for admin block', '2025-03-16', 'rejected', NOW() - INTERVAL 5 DAY);

UPDATE indents SET rejection_reason = 'Please check if compatible with installed printer first' WHERE id = 4;
UPDATE indents SET approved_by = 1, approved_at = NOW() - INTERVAL 3 DAY WHERE id = 2;
UPDATE counters SET current_value = 4 WHERE name = 'indent';

-- ============================================================
-- SEED DATA — ISSUE REQUESTS
-- ============================================================
INSERT INTO issue_requests (issue_number, employee_id, employee_name, department, product_id, quantity_requested, quantity_issued, purpose, work_order_ref, required_date, status, issued_by, issued_at, created_at) VALUES
('ISS-0001', 3, 'Deepak Verma', 'Electrical', 4, 2, 2, 'DB installation Block-A', 'WO-2501', '2025-03-14', 'issued', 1, NOW() - INTERVAL 2 DAY, NOW() - INTERVAL 3 DAY),
('ISS-0002', 6, 'Suresh Yadav', 'Civil', 2, 10, NULL, 'Foundation concrete mix Block-C', 'WO-2502', '2025-03-15', 'pending', NULL, NULL, NOW() - INTERVAL 1 DAY),
('ISS-0003', 4, 'Priya Singh', 'Project', 7, 5, NULL, 'New workers PPE kit', 'WO-2503', '2025-03-15', 'pending', NULL, NULL, NOW() - INTERVAL 1 DAY),
('ISS-0004', 7, 'Neha Tiwari', 'Admin', 9, 3, 3, 'Monthly stationery requirement', 'ADMIN-01', '2025-03-13', 'issued', 2, NOW() - INTERVAL 3 DAY, NOW() - INTERVAL 4 DAY),
('ISS-0005', 6, 'Suresh Yadav', 'Civil', 6, 30, NULL, 'Toilet block plumbing work', 'WO-2501', '2025-03-14', 'rejected', NULL, NULL, NOW() - INTERVAL 2 DAY),
('ISS-0006', 3, 'Deepak Verma', 'Electrical', 3, 50, 50, 'Main DB to sub-DB wiring', 'WO-2501', '2025-03-12', 'issued', 1, NOW() - INTERVAL 4 DAY, NOW() - INTERVAL 5 DAY),
('ISS-0007', 4, 'Priya Singh', 'Project', 8, 3, NULL, 'Height work safety equipment', 'WO-2504', '2025-03-15', 'pending', NULL, NULL, NOW());

UPDATE issue_requests SET rejection_reason = 'Stock critically low — Indent raised for purchase' WHERE id = 5;
UPDATE counters SET current_value = 7 WHERE name = 'issue';

-- ============================================================
-- SEED DATA — NOTIFICATIONS
-- ============================================================
INSERT INTO notifications (user_id, title, body, type, link, is_read) VALUES
(1, 'New Issue Request', 'Suresh Yadav requested 10 bags OPC Cement (ISS-0002)', 'info', 'issues.php', 0),
(1, 'Low Stock Alert ⚠️', 'MCB 32A DP is below minimum level (8 available, min: 15)', 'warning', 'inventory.php', 0),
(1, 'New Indent Request', 'Deepak Verma raised indent IND-0001 for LED Flood Lights', 'info', 'indents.php', 0),
(1, 'Low Stock Alert ⚠️', 'CPVC Pipe 1 Inch is critically low (5 units, min: 20)', 'danger', 'inventory.php', 0),
(2, 'New Issue Request', 'Priya Singh requested 5 Safety Helmets (ISS-0003)', 'info', 'issues.php', 0),
(1, 'Indent Approved', 'IND-0002 Formwork Plywood approved by Admin', 'success', 'indents.php', 1);

-- ============================================================
-- SEED DATA — AUDIT LOG
-- ============================================================
INSERT INTO audit_log (user_id, user_name, user_role, action, module, details, ip_address) VALUES
(1, 'Rajesh Sharma', 'admin', 'APPROVE', 'Issue Request', 'Approved ISS-0001 — MCB 32A DP ×2 issued to Deepak Verma', '127.0.0.1'),
(1, 'Rajesh Sharma', 'admin', 'STOCK IN', 'Stock Movement', 'GRN-0001 — Opening stock recorded for 10 products', '127.0.0.1'),
(1, 'Rajesh Sharma', 'admin', 'APPROVE', 'Indent', 'Approved IND-0002 — Formwork Plywood 50 Nos by Suresh Yadav', '127.0.0.1'),
(2, 'Mukesh Patel', 'keeper', 'ISSUE', 'Issue Request', 'Issued ISS-0004 — A4 Paper ×3 to Neha Tiwari', '127.0.0.1'),
(1, 'Rajesh Sharma', 'admin', 'REJECT', 'Issue Request', 'Rejected ISS-0005 — Stock insufficient, indent raised', '127.0.0.1'),
(1, 'Rajesh Sharma', 'admin', 'REJECT', 'Indent', 'Rejected IND-0004 — Compatibility check required', '127.0.0.1'),
(1, 'Rajesh Sharma', 'admin', 'LOGIN', 'System', 'Admin user logged in successfully', '127.0.0.1');

-- ============================================================
-- INDEXES FOR PERFORMANCE
-- ============================================================
CREATE INDEX idx_products_category ON products(category_id);
CREATE INDEX idx_products_status ON products(status);
CREATE INDEX idx_stock_txn_product ON stock_transactions(product_id);
CREATE INDEX idx_stock_txn_type ON stock_transactions(type);
CREATE INDEX idx_stock_txn_created ON stock_transactions(created_at);
CREATE INDEX idx_indents_status ON indents(status);
CREATE INDEX idx_indents_employee ON indents(employee_id);
CREATE INDEX idx_issues_status ON issue_requests(status);
CREATE INDEX idx_issues_employee ON issue_requests(employee_id);
CREATE INDEX idx_issues_product ON issue_requests(product_id);
CREATE INDEX idx_audit_user ON audit_log(user_id);
CREATE INDEX idx_audit_created ON audit_log(created_at);
CREATE INDEX idx_notif_user ON notifications(user_id, is_read);
