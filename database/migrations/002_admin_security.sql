-- Migration 002 : sécurité des comptes administrateur et index du journal d'audit.
-- Ne jamais modifier ce fichier après application : créer une nouvelle migration.

ALTER TABLE admin_users
    ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER password_hash,
    ADD COLUMN password_changed_at DATETIME NULL AFTER must_change_password;

ALTER TABLE audit_log
    ADD KEY idx_audit_action (action, created_at);
