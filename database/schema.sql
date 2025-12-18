-- Database Schema for PO Management System
-- Create Database
CREATE DATABASE IF NOT EXISTS PO_DEV CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE PO_DEV;

-- Table: users (Admin & Partner)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    role ENUM('admin', 'partner') NOT NULL DEFAULT 'partner',
    company_name VARCHAR(200),
    phone VARCHAR(20),
    address TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: purchase_orders
CREATE TABLE IF NOT EXISTS purchase_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_number VARCHAR(50) UNIQUE NOT NULL,
    partner_id INT NOT NULL,
    created_by INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    project_name VARCHAR(200) NULL COMMENT 'Nama Proyek',
    company_name_partner VARCHAR(200) NULL COMMENT 'Nama Perusahaan Partner',
    supplier_name VARCHAR(200) NULL COMMENT 'Nama Supplier',
    total_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    delivery_date DATE NULL COMMENT 'Tanggal Pengiriman/Target Selesai',
    status ENUM('draft', 'sent', 'pending_review', 'approved', 'rejected', 'in_progress', 'completed', 'closed') DEFAULT 'draft',
    rejection_reason TEXT,
    attachment VARCHAR(500) NULL COMMENT 'File Invoice PO (Mandatory)',
    completion_proof VARCHAR(500) NULL COMMENT 'Bukti Selesai (Foto/Dokumen)',
    approved_by_partner INT NULL COMMENT 'Partner yang approve',
    approved_at_partner TIMESTAMP NULL COMMENT 'Waktu approve partner',
    last_updated_by INT NULL COMMENT 'Admin terakhir update',
    last_updated_at TIMESTAMP NULL COMMENT 'Waktu update terakhir',
    sent_at TIMESTAMP NULL,
    approved_at TIMESTAMP NULL,
    rejected_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (partner_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (approved_by_partner) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (last_updated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_po_number (po_number),
    INDEX idx_partner_id (partner_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_project_name (project_name),
    INDEX idx_delivery_date (delivery_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: po_items
CREATE TABLE IF NOT EXISTS po_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_id INT NOT NULL,
    item_name VARCHAR(200) NOT NULL,
    description TEXT,
    quantity DECIMAL(10,2) NOT NULL,
    unit VARCHAR(50) NOT NULL,
    unit_price DECIMAL(15,2) NOT NULL,
    total_price DECIMAL(15,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    INDEX idx_po_id (po_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: po_revisions
CREATE TABLE IF NOT EXISTS po_revisions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_id INT NOT NULL,
    revision_number INT NOT NULL,
    revised_by INT NOT NULL,
    changes_summary TEXT,
    old_data JSON,
    new_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (revised_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_po_id (po_id),
    INDEX idx_revision_number (revision_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: activity_logs
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    po_id INT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_po_id (po_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: notifications
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    po_id INT,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: po_completion
CREATE TABLE IF NOT EXISTS po_completion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_id INT NOT NULL,
    completed_by INT NOT NULL,
    completion_date DATE NOT NULL,
    proof_file VARCHAR(500) NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (completed_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_po_id (po_id),
    INDEX idx_completion_date (completion_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default admin user (password: admin)
-- Password hash generated with: password_hash('admin', PASSWORD_DEFAULT)
INSERT INTO users (username, password, full_name, email, role, status) VALUES
('admin', '$2y$10$.UfuNQHJsFCpYOegCOgjre/p14Zqc5VdLvNyAag.oCJaBv.Zv7F4y', 'Administrator', 'admin@po-system.com', 'admin', 'active');

-- Insert sample partner (password: partner)
-- Password hash generated with: password_hash('partner', PASSWORD_DEFAULT)
INSERT INTO users (username, password, full_name, email, role, company_name, phone, status) VALUES
('partner', '$2y$10$wc0QAoOp2qosOicffMXNae3ERX2Gzbkf84YeVEP4CKvFhkg/CJpQC', 'Partner Supplier', 'partner@example.com', 'partner', 'PT Supplier Jaya', '081234567890', 'active');

