<?php

namespace Config;

/**
 * Fees entry status constants.
 * Entries are saved as approved so accountant/cashier can print the receipt immediately.
 */
class FeesApproval
{
	const STATUS_PENDING = 0;
	const STATUS_APPROVED = 1;
	const STATUS_CANCELLED = -1;

	const ACCOUNTANT_POST = 9;
	const CASHIER_POST = 8;
	const DIRECTOR_OF_FINANCE_POST = 24;

	const PAYMENT_MODE_BANK_SLIP = 1;

	/**
	 * @param int $postId
	 * @return bool
	 */
	public static function canApprove($postId)
	{
		return true;
	}

	/**
	 * Every fee entry is saved as approved (no pending-approval workflow).
	 *
	 * @param int $postId
	 * @return int
	 */
	public static function defaultStatusForPost($postId)
	{
		return self::STATUS_APPROVED;
	}

	/**
	 * @param int $postId
	 * @return bool
	 */
	public static function savesPending($postId)
	{
		return false;
	}
}
