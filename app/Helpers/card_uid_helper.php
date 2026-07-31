<?php

/**
 * RFID card UID helpers — same rules as assign-card + attendance-card.
 *
 * NFC reader order:  6C0477CD  (bytes as read from USB wedge)
 * Storage order:     CD77046C  (byte pairs reversed — assign-card / DB form)
 *
 * Save: normalize_card_uid() reverses bytes once.
 * Lookup: card_uid_lookup_variants() tries both orders.
 */

if (!function_exists('reverse_card_uid_bytes')) {
	/**
	 * Reverse byte pairs in a hex UID (e.g. AABBCCDD → DDCCBBAA).
	 */
	function reverse_card_uid_bytes(string $uid): string
	{
		$uid = strtoupper(preg_replace('/[^A-F0-9]/', '', $uid));
		if ($uid === '' || strlen($uid) % 2 !== 0) {
			return '';
		}
		return implode('', array_reverse(str_split($uid, 2)));
	}
}

if (!function_exists('clean_card_uid_raw')) {
	/**
	 * Clean reader input: decimal → hex (padded), strip non-hex. Does NOT reverse bytes.
	 */
	function clean_card_uid_raw(string $raw): string
	{
		$uid = trim(preg_replace('/\s+/', '', $raw));
		if ($uid === '') {
			return '';
		}

		if (ctype_digit($uid)) {
			try {
				if (function_exists('gmp_init')) {
					$uid = strtoupper(gmp_strval(gmp_init($uid, 10), 16));
				} else {
					$uid = strtoupper(base_convert($uid, 10, 16));
				}
				$uid = str_pad($uid, 8, '0', STR_PAD_LEFT);
			} catch (\Throwable $e) {
				return '';
			}
		}

		$uid = strtoupper(preg_replace('/[^A-F0-9]/', '', $uid));
		return strlen($uid) >= 4 ? $uid : '';
	}
}

if (!function_exists('normalize_card_uid')) {
	/**
	 * Canonical storage form — byte-reversed hex (matches assign-card JS).
	 */
	function normalize_card_uid(string $raw): string
	{
		$uid = clean_card_uid_raw($raw);
		if ($uid === '') {
			return '';
		}
		if (strlen($uid) % 2 === 0) {
			$uid = reverse_card_uid_bytes($uid);
		}
		return $uid;
	}
}

if (!function_exists('stored_card_uid')) {
	/**
	 * UID already in DB / picked from dropdown — uppercase hex only, no byte reverse.
	 */
	function stored_card_uid(string $raw): string
	{
		$uid = strtoupper(preg_replace('/[^A-F0-9]/', '', trim($raw)));
		return strlen($uid) >= 4 ? $uid : '';
	}
}

if (!function_exists('resolve_card_uid_for_save')) {
	/**
	 * @param string $raw posted card value
	 * @param bool $fromPicker true when chosen from assigned-card dropdown
	 */
	function resolve_card_uid_for_save(string $raw, bool $fromPicker = false): string
	{
		if ($fromPicker) {
			return stored_card_uid($raw);
		}
		return normalize_card_uid($raw);
	}
}

if (!function_exists('card_uid_lookup_variants')) {
	/**
	 * All UID forms to try in DB lookups (storage + reader order).
	 *
	 * @return string[]
	 */
	function card_uid_lookup_variants(string $rawOrStored): array
	{
		$clean = clean_card_uid_raw($rawOrStored);
		if ($clean === '') {
			$clean = strtoupper(preg_replace('/[^A-F0-9]/', '', $rawOrStored));
		}
		if ($clean === '') {
			return [];
		}

		// Reader order (NFC wedge) + storage order (assign-card byte-reversed), same as attendance scan.
		$reader = $clean;
		$storage = reverse_card_uid_bytes($clean);

		return array_values(array_unique(array_filter([$storage, $reader])));
	}
}
