<?php

namespace Config;

/**
 * Who can use Required Material Check — full school vs class mentor only.
 */
class StudentMaterialPermissions
{
	/** Head master, DoS, Headmistress, Accountant, Head Teacher, Deputy Head Teacher (post ids). */
	const FULL_ACCESS_POST_IDS = [1, 3, 9, 18, 25, 26];

	/** Post title keywords (case-insensitive substring match). */
	const FULL_ACCESS_TITLE_KEYWORDS = [
		'head master',
		'headmaster',
		'headmistress',
		'head teacher',
		'headteacher',
		'deputy head teacher',
		'deputy headteacher',
		'dean of discipline',
		'head of discipline',
		'matron',
		'patron',
		'accountant',
	];
}
