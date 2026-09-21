CREATE TABLE IF NOT EXISTS audit_logs (
  log_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  institution_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  target_entity VARCHAR(50) NULL,
  target_id VARCHAR(50) NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  before_value JSON NULL,
  after_value JSON NULL,
  status ENUM('success','failure') NOT NULL,
  timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_institution FOREIGN KEY (institution_id) REFERENCES institutions(institution_id),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
