-- Finance workflow posts (Budget & Cash Flow module)
INSERT INTO `posts` (`id`, `title`, `status`) VALUES
(19, 'Budget Manager', 1),
(20, 'Procurement Manager', 1),
(21, 'Deputy Director of Finance', 1)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `status` = 1;
