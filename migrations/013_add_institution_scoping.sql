ALTER TABLE institutions
  ADD COLUMN status ENUM('active','inactive') NOT NULL DEFAULT 'active' AFTER contact_info;

ALTER TABLE users
  MODIFY role ENUM('student','account_office','admin','super_admin') NOT NULL;

ALTER TABLE users
  ADD COLUMN institution_id INT UNSIGNED NULL AFTER role,
  ADD CONSTRAINT fk_users_institution FOREIGN KEY (institution_id) REFERENCES institutions(institution_id);
