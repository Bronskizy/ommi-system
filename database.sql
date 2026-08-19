CREATE DATABASE IF NOT EXISTS ommi_company_ltd CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ommi_company_ltd;
CREATE TABLE users (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(60) NOT NULL UNIQUE, full_name VARCHAR(120) NOT NULL, email VARCHAR(120) NULL UNIQUE, phone VARCHAR(30) NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, role ENUM('super_admin','admin','member') NOT NULL DEFAULT 'member', status ENUM('active','suspended','inactive') NOT NULL DEFAULT 'active', must_change_password TINYINT(1) NOT NULL DEFAULT 0, failed_attempts INT NOT NULL DEFAULT 0, locked_until DATETIME NULL, last_login_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP);
CREATE TABLE members (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NULL, member_no VARCHAR(30) NOT NULL UNIQUE, full_name VARCHAR(120) NOT NULL, phone VARCHAR(30) NOT NULL, email VARCHAR(120) NULL, join_date DATE NOT NULL, status ENUM('active','inactive') NOT NULL DEFAULT 'active', deleted_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL);
CREATE TABLE settings (id INT AUTO_INCREMENT PRIMARY KEY, setting_key VARCHAR(80) NOT NULL UNIQUE, setting_value TEXT NOT NULL);
CREATE TABLE project_categories (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(80) NOT NULL UNIQUE, enabled TINYINT(1) NOT NULL DEFAULT 1);
CREATE TABLE projects (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL, category_id INT NOT NULL, budget DECIMAL(14,2) NOT NULL DEFAULT 0, start_date DATE NULL, end_date DATE NULL, status ENUM('Planned','Active','Completed','Suspended') NOT NULL DEFAULT 'Planned', description TEXT NULL, created_by INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, FOREIGN KEY (category_id) REFERENCES project_categories(id), FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL);
CREATE TABLE contributions (id INT AUTO_INCREMENT PRIMARY KEY, member_id INT NOT NULL, project_id INT NULL, contribution_type ENUM('entry','monthly','project','other') NOT NULL, amount DECIMAL(14,2) NOT NULL, payment_date DATE NOT NULL, payment_method VARCHAR(40) NOT NULL DEFAULT 'cash', receipt_no VARCHAR(40) NOT NULL UNIQUE, notes TEXT NULL, recorded_by INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (member_id) REFERENCES members(id), FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL, FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL);
CREATE TABLE expenses (id INT AUTO_INCREMENT PRIMARY KEY, project_id INT NOT NULL, category VARCHAR(80) NOT NULL, amount DECIMAL(14,2) NOT NULL, expense_date DATE NOT NULL, vendor VARCHAR(120) NULL, description TEXT NULL, recorded_by INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL);
CREATE TABLE notifications (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NULL, title VARCHAR(150) NOT NULL, body TEXT NOT NULL, level ENUM('info','warning','danger','success') NOT NULL DEFAULT 'info', is_read TINYINT(1) NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE);
CREATE TABLE audit_logs (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NULL, action VARCHAR(80) NOT NULL, entity_type VARCHAR(80) NOT NULL, entity_id INT NULL, details TEXT NULL, ip_address VARCHAR(45) NULL, user_agent VARCHAR(255) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL);
CREATE TABLE password_resets (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, token VARCHAR(128) NOT NULL UNIQUE, expiry_time DATETIME NOT NULL, used TINYINT(1) NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE);
-- Database-level protection for duplicate contribution records. The PHP form
-- validates these too, while these triggers also protect direct SQL imports and
-- simultaneous requests.
DROP TRIGGER IF EXISTS contributions_before_insert_no_duplicates;
DROP TRIGGER IF EXISTS contributions_before_update_no_duplicates;
DELIMITER $$
CREATE TRIGGER contributions_before_insert_no_duplicates
BEFORE INSERT ON contributions
FOR EACH ROW
BEGIN
    IF NEW.contribution_type = 'entry' AND EXISTS (
        SELECT 1 FROM contributions
        WHERE member_id = NEW.member_id AND contribution_type = 'entry'
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This member already has an entry contribution.';
    END IF;

    IF NEW.contribution_type = 'monthly' AND EXISTS (
        SELECT 1 FROM contributions
        WHERE member_id = NEW.member_id
          AND contribution_type = 'monthly'
          AND YEAR(payment_date) = YEAR(NEW.payment_date)
          AND MONTH(payment_date) = MONTH(NEW.payment_date)
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This member already has a monthly contribution for this month.';
    END IF;
END$$

CREATE TRIGGER contributions_before_update_no_duplicates
BEFORE UPDATE ON contributions
FOR EACH ROW
BEGIN
    IF NEW.contribution_type = 'entry' AND EXISTS (
        SELECT 1 FROM contributions
        WHERE member_id = NEW.member_id AND contribution_type = 'entry' AND id <> OLD.id
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This member already has an entry contribution.';
    END IF;

    IF NEW.contribution_type = 'monthly' AND EXISTS (
        SELECT 1 FROM contributions
        WHERE member_id = NEW.member_id
          AND contribution_type = 'monthly'
          AND YEAR(payment_date) = YEAR(NEW.payment_date)
          AND MONTH(payment_date) = MONTH(NEW.payment_date)
          AND id <> OLD.id
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This member already has a monthly contribution for this month.';
    END IF;
END$$
DELIMITER ;
INSERT INTO settings (setting_key, setting_value) VALUES ('monthly_contribution_amount','30000'),('entry_contribution_amount','117500'),('contribution_start_date','2026-02-24'),('currency','TZS'),('receipt_prefix','RCPT-{YYYY}-'),('reminder_days','7,15,30'),('warning_thresholds','70,90,100'),('password_min_length','8'),('session_timeout_minutes','60'),('login_attempt_limit','5'),('audit_logs_enabled','1'),('mail_driver','smtp'),('mail_from_email','your-gmail-address@gmail.com'),('mail_from_name','OMMI Company Ltd'),('smtp_host','smtp.gmail.com'),('smtp_port','587'),('smtp_username','your-gmail-address@gmail.com'),('smtp_password','') ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);
INSERT INTO project_categories (name) VALUES ('Agriculture'),('Livestock'),('Business'),('Investment') ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO users (username, full_name, email, phone, password_hash, role) VALUES ('superadmin','Super Admin','admin@ommi.local','255700000000','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','super_admin') ON DUPLICATE KEY UPDATE username=username;


