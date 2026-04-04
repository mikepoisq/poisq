-- Патч: добавляет недостающие колонки в таблицу reviews
-- Запустить если migration_reviews.sql уже выполнялась без этих колонок

ALTER TABLE reviews
    ADD COLUMN IF NOT EXISTS photo VARCHAR(500) NULL AFTER text,
    ADD COLUMN IF NOT EXISTS moderation_comment TEXT NULL AFTER status,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
    ADD COLUMN IF NOT EXISTS edited_until TIMESTAMP NULL AFTER updated_at;

-- Создаём review_owner_replies если не существует
CREATE TABLE IF NOT EXISTS review_owner_replies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    review_id INT NOT NULL,
    owner_user_id INT NOT NULL,
    text TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE
);
