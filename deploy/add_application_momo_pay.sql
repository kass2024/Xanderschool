ALTER TABLE `application_settings`
	ADD COLUMN `momo_pay_code` VARCHAR(32) NOT NULL DEFAULT '' AFTER `operator`;
ALTER TABLE `application_settings`
	ADD COLUMN `momo_pay_name` VARCHAR(120) NOT NULL DEFAULT '' AFTER `momo_pay_code`;
UPDATE `application_settings`
	SET `momo_pay_code` = '059010', `momo_pay_name` = 'WISDOM SCHOOL'
	WHERE `school_id` = 27 AND (`momo_pay_code` IS NULL OR `momo_pay_code` = '');
