-- ============================================================
--  Document Management System — MySQL Schema (XAMPP Ready)
--  Run this entire file in phpMyAdmin > SQL tab
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS borrow_transactions;
DROP TABLE IF EXISTS documents;
DROP TABLE IF EXISTS document_types;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ── Users ────────────────────────────────────────────────────
CREATE TABLE users (
    id             VARCHAR(36)  NOT NULL PRIMARY KEY,
    fullname       VARCHAR(255) NOT NULL,
    birth_date     DATE,
    address        TEXT,
    contact_number VARCHAR(50),
    email          VARCHAR(255) NOT NULL UNIQUE,
    password_hash  VARCHAR(255) NOT NULL,
    role           ENUM('ADMIN','USER') NOT NULL DEFAULT 'USER',
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Document Types ───────────────────────────────────────────
CREATE TABLE document_types (
    id          VARCHAR(36)  NOT NULL PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    description TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Documents ────────────────────────────────────────────────
CREATE TABLE documents (
    id               VARCHAR(36)  NOT NULL PRIMARY KEY,
    file_path        VARCHAR(500),
    location         VARCHAR(500) NOT NULL,
    status           ENUM('ACTIVE','BORROWED','EXPIRED','ARCHIVED') NOT NULL DEFAULT 'ACTIVE',
    version          INT NOT NULL DEFAULT 1,
    expiration_date  DATE,
    is_deleted       TINYINT(1) NOT NULL DEFAULT 0,
    backup_path      VARCHAR(500),
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    owner_id         VARCHAR(36) NOT NULL,
    document_type_id VARCHAR(36) NOT NULL,
    renewed_from_id  VARCHAR(36),
    FOREIGN KEY (owner_id)         REFERENCES users(id)          ON DELETE CASCADE,
    FOREIGN KEY (document_type_id) REFERENCES document_types(id) ON DELETE RESTRICT,
    FOREIGN KEY (renewed_from_id)  REFERENCES documents(id)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Borrow Transactions ──────────────────────────────────────
CREATE TABLE borrow_transactions (
    id          VARCHAR(36) NOT NULL PRIMARY KEY,
    borrow_date TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    due_date    DATETIME,
    return_date DATETIME,
    status      ENUM('ACTIVE','RETURNED','PENDING') NOT NULL DEFAULT 'ACTIVE',
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    document_id VARCHAR(36) NOT NULL,
    borrower_id VARCHAR(36) NOT NULL,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (borrower_id) REFERENCES users(id)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Audit Logs ───────────────────────────────────────────────
CREATE TABLE audit_logs (
    id          VARCHAR(36)  NOT NULL PRIMARY KEY,
    action      VARCHAR(50)  NOT NULL,
    details     TEXT,
    ip_address  VARCHAR(45)  NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    user_id     VARCHAR(36) NOT NULL,
    document_id VARCHAR(36),
    FOREIGN KEY (user_id)     REFERENCES users(id)     ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Seed: Default Admin ──────────────────────────────────────
-- Password: password
INSERT INTO users (id, fullname, email, password_hash, role) VALUES (
    '00000000-0000-0000-0000-000000000001',
    'System Administrator',
    'admin@mns.local',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'ADMIN'
);

-- ── Seed: Document Types ─────────────────────────────────────
INSERT INTO document_types (id, name, description) VALUES
    ('dt-00000000-0000-0000-0001', 'Contract',          'Legal contracts and agreements'),
    ('dt-00000000-0000-0000-0002', 'Invoice',           'Billing and payment invoices'),
    ('dt-00000000-0000-0000-0003', 'Report',            'Internal and external reports'),
    ('dt-00000000-0000-0000-0004', 'Memorandum',        'Official internal memos'),
    ('dt-00000000-0000-0000-0005', 'Certificate',       'Certificates and credentials'),
    ('dt-00000000-0000-0000-0006', 'Policy',            'Company policies and procedures'),
    ('dt-00000000-0000-0000-0007', 'Legal Document',    'Court filings and legal papers'),
    ('dt-00000000-0000-0000-0008', 'Financial Record',  'Accounting and financial records'),
    ('dt-00000000-0000-0000-0009', 'HR Document',       'Human resources records'),
    ('dt-00000000-0000-0000-0010', 'Other',             'Miscellaneous documents');

-- ── Seed: Sample User ────────────────────────────────────────
-- Password: password
INSERT INTO users (id, fullname, email, password_hash, role, contact_number, address) VALUES (
    '00000000-0000-0000-0000-000000000002',
    'Juan dela Cruz',
    'user@mns.local',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'USER',
    '09171234567',
    'Manila, Philippines'
);
