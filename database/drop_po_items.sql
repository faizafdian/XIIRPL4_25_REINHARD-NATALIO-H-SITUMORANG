-- Script untuk menghapus table po_items
-- Jalankan script ini SETELAH update file PHP selesai

USE PO_DEV;

-- Hapus table po_items
DROP TABLE IF EXISTS po_items;

-- Verifikasi table sudah dihapus
SELECT 'Table po_items sudah dihapus' as status;

