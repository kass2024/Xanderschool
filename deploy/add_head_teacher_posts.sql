-- Staff creation posts: Head Teacher and Deputy Head Teacher
INSERT INTO `posts` (`id`, `title`, `status`) VALUES
(25, 'Head Teacher', 1),
(26, 'Deputy Head Teacher', 1)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `status` = 1;
