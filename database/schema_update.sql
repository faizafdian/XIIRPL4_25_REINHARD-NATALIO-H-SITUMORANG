-- Update Schema untuk PO yang lebih kompleks
-- Jalankan script ini untuk update database yang sudah ada

USE PO_DEV;

-- Update table purchase_orders dengan field baru
ALTER TABLE purchase_orders 
ADD COLUMN IF NOT EXISTS project_name VARCHAR(200) NULL AFTER partner_id,
ADD COLUMN IF NOT EXISTS company_name_partner VARCHAR(200) NULL AFTER project_name,
ADD COLUMN IF NOT EXISTS supplier_name VARCHAR(200) NULL AFTER company_name_partner,
ADD COLUMN IF NOT EXISTS delivery_date DATE NULL AFTER po_date,
ADD COLUMN IF NOT EXISTS approved_by_partner INT NULL AFTER approved_by,
ADD COLUMN IF NOT EXISTS approved_at_partner TIMESTAMP NULL AFTER approved_at,
ADD COLUMN IF NOT EXISTS attachment VARCHAR(500) NULL COMMENT 'File invoice PO (mandatory)',
ADD COLUMN IF NOT EXISTS completion_proof VARCHAR(500) NULL COMMENT 'Bukti selesai (foto/dokumen)',
ADD COLUMN IF NOT EXISTS last_updated_by INT NULL,
ADD COLUMN IF NOT EXISTS last_updated_at TIMESTAMP NULL;

-- Update status enum untuk alur yang lebih kompleks
ALTER TABLE purchase_orders 
MODIFY COLUMN status ENUM('draft', 'sent', 'pending_review', 'approved', 'rejected', 'in_progress', 'completed', 'closed') DEFAULT 'draft';

-- Update index
ALTER TABLE purchase_orders 
ADD INDEX IF NOT EXISTS idx_project_name (project_name),
ADD INDEX IF NOT EXISTS idx_delivery_date (delivery_date),
ADD INDEX IF NOT EXISTS idx_approved_by_partner (approved_by_partner);

-- Update foreign keys
ALTER TABLE purchase_orders 
ADD CONSTRAINT IF NOT EXISTS fk_approved_by_partner FOREIGN KEY (approved_by_partner) REFERENCES users(id) ON DELETE SET NULL,
ADD CONSTRAINT IF NOT EXISTS fk_last_updated_by FOREIGN KEY (last_updated_by) REFERENCES users(id) ON DELETE SET NULL;

-- Update table untuk tracking completion
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

