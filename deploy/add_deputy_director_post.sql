-- Academic Deputy Director (same menu/budget rights as Director / Head master).
-- Not finance post #21 (Deputy Director of Finance).
INSERT INTO `posts` (`id`, `title`, `status`) VALUES
(30, 'Deputy Director', 1)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `status` = 1;
