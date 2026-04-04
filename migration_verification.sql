-- Миграция: система значка "Проверено"
-- Запустить один раз: mysql -u USER -p DBNAME < migration_verification.sql

CREATE TABLE IF NOT EXISTS verification_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    service_id INT NOT NULL,
    document_path VARCHAR(500) NOT NULL,
    document_original_name VARCHAR(255),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    reviewed_by INT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (service_id) REFERENCES services(id)
);

ALTER TABLE services
    ADD COLUMN IF NOT EXISTS verified_until DATE NULL,
    ADD COLUMN IF NOT EXISTS verification_token VARCHAR(64) NULL,
    ADD COLUMN IF NOT EXISTS verification_token_expires TIMESTAMP NULL;
