CREATE TABLE IF NOT EXISTS receipts (
  receipt_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  institution_id INT UNSIGNED NOT NULL,
  transaction_id INT UNSIGNED NOT NULL,
  receipt_number VARCHAR(50) NOT NULL,
  issued_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_receipt_institution FOREIGN KEY (institution_id) REFERENCES institutions(institution_id),
  CONSTRAINT fk_receipt_txn FOREIGN KEY (transaction_id) REFERENCES transactions(transaction_id),
  UNIQUE KEY uq_receipt_number (receipt_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
