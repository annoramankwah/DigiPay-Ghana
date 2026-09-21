CREATE TABLE IF NOT EXISTS fee_structures (
  fee_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  institution_id INT UNSIGNED NOT NULL,
  program VARCHAR(150) NOT NULL,
  level VARCHAR(20) NOT NULL,
  academic_term VARCHAR(50) NOT NULL,
  category VARCHAR(100) NOT NULL DEFAULT 'General',
  amount DECIMAL(12,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'GHS',
  due_date DATE NOT NULL,
  effective_from DATE NOT NULL,
  effective_to DATE NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_fee_institution FOREIGN KEY (institution_id) REFERENCES institutions(institution_id),
  CONSTRAINT fk_fee_created_by FOREIGN KEY (created_by) REFERENCES users(user_id),
  KEY idx_fee_program_level_term (program, level, academic_term)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
