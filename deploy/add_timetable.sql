-- Timetable management (periods, schedules, entries)
CREATE TABLE IF NOT EXISTS `timetable_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL,
  `days_json` varchar(120) NOT NULL DEFAULT '["Mon","Tue","Wed","Thu","Fri"]',
  `include_sunday` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `school_id` (`school_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `timetable_slots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL,
  `level_id` int(11) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `label` varchar(40) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_break` tinyint(1) NOT NULL DEFAULT 0,
  `break_label` varchar(60) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `school_order` (`school_id`,`level_id`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `timetable_schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL,
  `academic_year` int(11) NOT NULL,
  `term` int(11) NOT NULL DEFAULT 1,
  `title` varchar(120) NOT NULL DEFAULT 'Main timetable',
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `generated_by` int(11) DEFAULT NULL,
  `generated_at` datetime DEFAULT NULL,
  `assignments_hash` varchar(64) DEFAULT NULL,
  `needs_regen` tinyint(1) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `school_year_term` (`school_id`,`academic_year`,`term`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `timetable_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `schedule_id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `class_id` int(11) DEFAULT NULL,
  `staff_id` int(11) NOT NULL,
  `course_id` int(11) DEFAULT NULL,
  `course_record_id` int(11) DEFAULT NULL,
  `day_of_week` tinyint(4) NOT NULL COMMENT '0=Mon..4=Fri,6=Sun',
  `slot_id` int(11) NOT NULL,
  `room_label` varchar(80) DEFAULT NULL,
  `entry_type` varchar(20) NOT NULL DEFAULT 'lesson',
  `custom_label` varchar(120) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `schedule_day_slot` (`schedule_id`,`day_of_week`,`slot_id`),
  KEY `staff_day_slot` (`schedule_id`,`staff_id`,`day_of_week`,`slot_id`),
  KEY `class_day_slot` (`schedule_id`,`class_id`,`day_of_week`,`slot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `timetable_special_times` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL,
  `level_id` int(11) NOT NULL DEFAULT 0,
  `day_of_week` tinyint(4) NOT NULL COMMENT '0=Mon..4=Fri,6=Sun',
  `slot_id` int(11) NOT NULL,
  `label` varchar(120) NOT NULL,
  `color` varchar(20) NOT NULL DEFAULT 'yellow',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `school_level_day_slot` (`school_id`,`level_id`,`day_of_week`,`slot_id`),
  KEY `school_id` (`school_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
