-- 11/01/2022

-- Create the table sms gatewaye settings
CREATE TABLE `intouch_accounts` ( `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT , `school_id` INT NOT NULL , `username` VARCHAR(255) NOT NULL , `password` VARCHAR(255) NOT NULL , PRIMARY KEY (`id`), INDEX (`school_id`)) ENGINE = InnoDB;

-- Add relationships to the table for integrity
ALTER TABLE `intouch_accounts` ADD CONSTRAINT `school_id_foreign_key` FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT; 

-- Allow timestamps tracking
ALTER TABLE `intouch_accounts`  ADD `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP  AFTER `password`,  ADD `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP  AFTER `created_at`;
