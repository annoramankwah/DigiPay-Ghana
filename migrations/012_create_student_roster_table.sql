CREATE TABLE IF NOT EXISTS student_roster (
  roster_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  institution_id INT UNSIGNED NOT NULL,
  student_number VARCHAR(50) NOT NULL,
  full_name VARCHAR(150) NOT NULL,
  date_of_birth DATE NOT NULL,
  program VARCHAR(150) NOT NULL,
  level VARCHAR(20) NOT NULL,
  claimed_by_user_id INT UNSIGNED NULL,
  claimed_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_roster_student_number (student_number),
  CONSTRAINT fk_roster_institution FOREIGN KEY (institution_id) REFERENCES institutions(institution_id),
  CONSTRAINT fk_roster_user FOREIGN KEY (claimed_by_user_id) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
