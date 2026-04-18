-- Offline payments MVP: rider declares method + optional proof; driver confirms receipt.
-- Run after migration_sos_promos_loyalty.sql (payment_status on rides). Idempotent column adds.

DROP PROCEDURE IF EXISTS vpride_offline_pay_add_column;

DELIMITER $$

CREATE PROCEDURE vpride_offline_pay_add_column(
  IN p_table VARCHAR(64),
  IN p_column VARCHAR(64),
  IN p_definition TEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table
      AND COLUMN_NAME = p_column
  ) THEN
    SET @q = CONCAT(
      'ALTER TABLE `',
      REPLACE(p_table, '`', ''),
      '` ADD COLUMN `',
      REPLACE(p_column, '`', ''),
      '` ',
      p_definition
    );
    PREPARE stmt FROM @q;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END$$

DELIMITER ;

-- Widen payment_status for rider → driver confirmation flow.
ALTER TABLE rides
  MODIFY COLUMN payment_status ENUM('pending', 'submitted', 'paid') NOT NULL DEFAULT 'pending';

CALL vpride_offline_pay_add_column(
  'rides',
  'payment_method',
  'ENUM(\'cash\', \'pos\', \'bank_transfer\') NULL AFTER payment_status'
);

CALL vpride_offline_pay_add_column(
  'rides',
  'payment_proof_url',
  'VARCHAR(768) NULL AFTER payment_method'
);

CALL vpride_offline_pay_add_column(
  'rides',
  'payment_reference_note',
  'VARCHAR(500) NULL AFTER payment_proof_url'
);

CALL vpride_offline_pay_add_column(
  'rides',
  'payment_submitted_at',
  'DATETIME NULL AFTER payment_reference_note'
);

DROP PROCEDURE IF EXISTS vpride_offline_pay_add_column;
