-- Rename built-in post #15 Principal → Executive Principal (Vice principal #16 unchanged).
UPDATE `posts`
SET `title` = 'Executive Principal', `status` = 1
WHERE `id` = 15 AND `title` = 'Principal';

UPDATE `posts`
SET `title` = 'Executive Principal'
WHERE `title` = 'Principal';
