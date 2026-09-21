CREATE TABLE IF NOT EXISTS disputes (
  dispute_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  institution_id INT UNSIGNED NOT NULL,
  transaction_id INT UNSIGNED NOT NULL,
  raised_by INT UNSIGNED NOT NULL,
  status ENUM('open','under_review','resolved','rejected') NOT NULL DEFAULT 'open',
  issue_summary VARCHAR(255) NOT NULL,
  description TEXT NULL,
  resolution_notes TEXT NULL,
  resolved_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL,
  CONSTRAINT fk_dispute_institution FOREIGN KEY (institution_id) REFERENCES institutions(institution_id),
  CONSTRAINT fk_dispute_txn FOREIGN KEY (transaction_id) REFERENCES transactions(transaction_id),
  CONSTRAINT fk_dispute_raised_by FOREIGN KEY (raised_by) REFERENCES users(user_id),
  CONSTRAINT fk_dispute_resolved_by FOREIGN KEY (resolved_by) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
