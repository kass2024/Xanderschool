-- Director post: same rights as Head master (#1) in MenuClearance / BudgetPermissions
INSERT INTO `posts` (`id`, `title`, `status`) VALUES
(29, 'Director', 1)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `status` = 1;
