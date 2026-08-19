-- Run this once in phpMyAdmin (select the ommi_company_ltd database first)
-- for an already-installed system.
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
