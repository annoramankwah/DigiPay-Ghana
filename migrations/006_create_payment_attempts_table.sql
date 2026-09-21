CREATE TABLE IF NOT EXISTS payment_attempts (
  attempt_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  institution_id INT UNSIGNED NOT NULL,
  transaction_id INT UNSIGNED NOT NULL,
  provider ENUM('momo','vodafone_cash','airteltigo','card','bank_simulated') NOT NULL,
  provider_reference VARCHAR(100) NOT NULL,
  status ENUM('pending','success','failed','duplicate') NOT NULL DEFAULT 'pending',
  raw_callback_payload JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_attempt_institution FOREIGN KEY (institution_id) REFERENCES institutions(institution_id),
  CONSTRAINT fk_attempt_txn FOREIGN KEY (transaction_id) REFERENCES transactions(transaction_id),
  UNIQUE KEY uq_attempt_provider_reference (provider_reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
