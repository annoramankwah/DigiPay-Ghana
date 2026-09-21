CREATE INDEX idx_txn_student_status ON transactions (student_id, status);
CREATE INDEX idx_audit_user_timestamp ON audit_logs (user_id, timestamp);
CREATE INDEX idx_notifications_user_read ON notifications (user_id, read_status);
CREATE INDEX idx_disputes_status ON disputes (status);
