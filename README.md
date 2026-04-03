# DMR Construction PVT. LTD. - Store Management System

**Version:** 1.0.0  
**Designed and Developed by:** Mohammad Shakeel  

A complete, production-ready multi-user store management system built in PHP with MySQL, specifically engineered to streamline inventory tracking, material requests, and stock ledger management.

---

## 🎯 The Problem it Solves
In construction, manufacturing, and large-scale operational environments, physical store management can quickly become chaotic. Common issues include:
- **Lack of Accountability:** Items are issued to employees without proper tracking, leading to misplacement or theft.
- **Stock Discrepancies:** Paper-based ledgers become inaccurate, leading to unexpected "out-of-stock" situations when materials are urgently needed.
- **Approval Bottlenecks:** Manual requests for new materials (Indents) or issuing existing stock (Issue Slips) take time and lack a systematic approval chain.
- **No Traceability:** It's difficult to answer "Who took what, when, and who approved it?"

**The DMR Store Management System solves this by:**
Providing a centralized, digital tracking platform with Role-Based Access Control (RBAC). Every stock movement is tied to a specific user and requires authorization. The immutable audit log guarantees 100% accountability, meaning no transaction can be deleted or altered without a trace. It digitizes the entire workflow from material request (Indent) to material usage (Issue Slip), providing real-time visibility into stock levels, automatic low-stock alerts, and comprehensive reporting.

---

## ⚙️ How It Works (The Workflow)
The system operates on a clear, role-based workflow to ensure every action is authenticated and tracked:

1. **Setup & Roles:** An Admin defines Users (Store Keeper, Employee, Management) and categorizes Products.
2. **Stock Entry:** Store Keepers receive materials and log them via "Stock In", updating the main ledger.
3. **Material Requests (Issues):** When an employee needs material, they raise an **Issue Request** through the portal specifying the item, quantity, and purpose.
4. **Approval Chain:** Store Keepers or Admins review the Issue Request. If approved, the stock is automatically deducted from the inventory, and a printable **Issue Slip** is generated as physical proof. 
5. **Purchase Requests (Indents):** If an item is out of stock or completely new material is needed, employees/keepers raise an **Indent**. Management or Admins review and approve the indent to authorize procurement.
6. **Reporting & Auditing:** Management can generate real-time reports on material consumption per employee, category-wise stock, and download complete immutable audit trails.

---

## 🚀 Core Features & Functions

- **📊 Intelligent Dashboard:** Gives a bird's-eye view of total products, low stock alerts, pending indents, and recent stock movements.
- **📦 Inventory Management:** A categorized master list of all products with their current stock levels, units of measurement, and minimum stock threshold alerts.
- **📉 Stock Ledger:** An immutable record of every single stock-in, stock-out, and adjustment. It automatically calculates running balances.
- **📋 Issue Tracking & Slips:** Complete lifecycle management of material issuance (Pending -> Approved -> Issued -> Rejected). Generates official Issue Slips for signatures.
- **🛒 Indent System:** A dedicated portal for requesting new purchases with justification and tracking its approval status.
- **🔐 User Management & RBAC:** Fine-grained permissions. Employees can only request, Keepers can manage stock, Admins configure everything, and Management can view reports.
- **🕵️ Immutable Audit Log:** Every login, insert, update, and action is logged with an IP address, timestamp, and user ID. It cannot be tampered with.
- **📑 Comprehensive Reports:** Export data via CSV or Print-to-PDF including Daily Issue Reports, Low Stock Alerts, and Employee Issue History.

---

## 📁 File Structure

```text
dmr-store/
│
├── index.php                    ← Root redirect
├── dashboard.php                ← Main dashboard
├── inventory.php                ← Inventory management
├── products.php                 ← Product master CRUD
├── indents.php                  ← Indent requests (raise, approve, reject)
├── issues.php                   ← Issue requests (raise, approve, reject, slip)
├── stock.php                    ← Stock ledger, stock-in, adjustments
├── users.php                    ← User Management
├── reports.php                  ← Reports and analytics
├── audit.php                    ← Immutable audit log
│
├── auth/
│   ├── login.php                ← Login page
│   └── logout.php               ← Session destroy
│
├── config/
│   ├── config.php               ← App config, auth helpers, permissions
│   └── db.php                   ← PDO connection
│
├── includes/
│   ├── functions.php            ← Core helper functions
│   ├── header.php               ← Sidebar + topbar layout
│   └── footer.php               ← Closing tags + JS
│
├── assets/
│   ├── css/style.css            ← Complete stylesheet
│   └── js/main.js               ← Frontend JavaScript
│
├── reports_export/
│   ├── export.php               ← Universal CSV/Print exporter
│   └── issue_slip.php           ← Printable issue slip
│
└── install/
    ├── index.php                ← Web-based installer
    └── database.sql             ← Complete schema + seed data
```

---

## ⚡ Quick Setup

### Option A — Web Installer (Recommended)
1. Upload the `dmr-store/` folder to your server's `htdocs` or `www` directory.
2. Visit: `http://yoursite.com/dmr-store/install/`
3. Fill in your database credentials and click **Install**.
4. **⚠️ Delete the `install/` folder** immediately after setup to secure your system.

### Option B — Manual Setup
1. Create a MySQL database: `CREATE DATABASE dmr_store CHARACTER SET utf8mb4;`
2. Import the schema: `mysql -u root -p dmr_store < install/database.sql`
3. Edit `config/db.php` with your database username and password.
4. Visit the portal via your web server.

---

## 🔐 Default Login Credentials
Use the following accounts to explore the role-based system:

| Role          | Username                        | Password    |
|---------------|---------------------------------|-------------|
| Store Admin   | admin@dmrconstruction.in        | Admin@1234  |
| Store Keeper  | keeper@dmrconstruction.in       | Admin@1234  |
| Employee      | deepak@dmrconstruction.in       | Admin@1234  |
| Management    | mgmt@dmrconstruction.in         | Admin@1234  |

> ⚠️ **IMPORTANT: Change all default passwords immediately after the first login in a production environment!**

---

## 👥 Role Permissions Overview

| Feature                  | Admin | Keeper | Employee | Management |
|--------------------------|-------|--------|----------|------------|
| Dashboard                | ✅    | ✅     | ✅       | ✅         |
| View Inventory           | ✅    | ✅     | ✅       | ✅         |
| Add/Edit Products        | ✅    | ❌     | ❌       | ❌         |
| Stock In                 | ✅    | ✅     | ❌       | ❌         |
| Stock Adjustment         | ✅    | ❌     | ❌       | ❌         |
| Raise Issue Request      | ✅    | ✅     | ✅       | ❌         |
| Approve/Reject Issues    | ✅    | ✅     | ❌       | ❌         |
| Raise Indent             | ✅    | ✅     | ✅       | ❌         |
| Approve/Reject Indents   | ✅    | ✅     | ❌       | ❌         |
| Stock Ledger             | ✅    | ✅     | ❌       | ✅         |
| User Management          | ✅    | ❌     | ❌       | ❌         |
| Reports & Export         | ✅    | ✅     | ❌       | ✅         |
| Audit Log                | ✅    | ❌     | ❌       | ✅         |

---

## ⚙️ Server Requirements

- **PHP:** 7.4 or higher (8.x highly recommended)
- **Database:** MySQL 5.7+ or MariaDB 10.3+
- **Required Extensions:** `pdo_mysql`, `session`, `mbstring`
- **Supported Servers:** Apache / Nginx / XAMPP / WAMP

---

## 🛡️ Security Measures
This system was built with security as a priority:
- **BCrypt Password Hashing:** Highly secure, encrypted password storage (cost factor 12).
- **CSRF Tokens:** All forms are protected against Cross-Site Request Forgery.
- **Session Protection:** Session regeneration upon login to prevent session hijacking, with HTTPOnly & SameSite cookie flags.
- **SQL Injection Prevention:** 100% usage of PDO Prepared Statements.
- **XSS Prevention:** Output escaping implementation using pure `htmlentities` functions.
- **Immutable Databases:** Activity and Audit logs are write-only. They cannot be modified or deleted, ensuring absolute traceability.

---

**Thank you for using the DMR Store Management System. Designed and Developed with modern standards for real-world enterprise needs by Mohammad Shakeel.**
