CREATE TABLE IF NOT EXISTS transactions (
  transaction_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  institution_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  fee_id INT UNSIGNED NOT NULL,
  amount_due DECIMAL(12,2) NOT NULL,
  amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  status ENUM('pending','verified','failed','overdue') NOT NULL DEFAULT 'pending',
  payment_method ENUM('momo','vodafone_cash','airteltigo','card','bank_simulated') NOT NULL,
  timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_txn_institution FOREIGN KEY (institution_id) REFERENCES institutions(institution_id),
  CONSTRAINT fk_txn_student FOREIGN KEY (student_id) REFERENCES students(student_id),
  CONSTRAINT fk_txn_fee FOREIGN KEY (fee_id) REFERENCES fee_structures(fee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
