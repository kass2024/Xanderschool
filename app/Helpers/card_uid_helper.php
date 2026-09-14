<?php

/**
 * RFID card UID helpers — same rules as assign-card + attendance-card.
 *
 * Android Tag.getId() and USB hex wedges send 8/14/20-char HEX (e.g. 94280002).
 * Some USB HID readers send 9–13 digit DECIMAL instead.
 *
 * All-digit 8-char values MUST stay hex: 94280002 is a real NFC UID, not decimal 94,280,002.
 *
 * Save: normalize_card_uid() reverses byte pairs once (assign-card / DB form).
 * Lookup: card_uid_lookup_variants() tries hex, reversed hex, and decimal-as-hex.
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

if (!function_exists('card_uid_is_nfc_hex_length')) {
	/** ISO 14443 UID hex lengths: 4 / 7 / 10 bytes. */
	function card_uid_is_nfc_hex_length(string $uid): bool
	{
		$len = strlen($uid);
		return $len === 8 || $len === 14 || $len === 20;
	}
}

if (!function_exists('card_uid_decimal_to_hex')) {
	function card_uid_decimal_to_hex(string $digits): string
	{
		$digits = preg_replace('/\D/', '', $digits);
		if ($digits === '') {
			return '';
		}
		$digits = ltrim($digits, '0');
		if ($digits === '') {
			return '00000000';
		}
		try {
			if (function_exists('gmp_init')) {
				$hex = strtoupper(gmp_strval(gmp_init($digits, 10), 16));
			} else {
				$hex = '';
				while ($digits !== '' && $digits !== '0') {
					$quot = '';
					$rem = 0;
					$len = strlen($digits);
					for ($i = 0; $i < $len; $i++) {
						$acc = $rem * 10 + (int) $digits[$i];
						$q = intdiv($acc, 16);
						$rem = $acc % 16;
						if ($quot !== '' || $q > 0) {
							$quot .= (string) $q;
						}
					}
					$hex = dechex($rem) . $hex;
					$digits = $quot === '' ? '0' : $quot;
				}
				$hex = strtoupper($hex);
			}
			return str_pad($hex, 8, '0', STR_PAD_LEFT);
		} catch (\Throwable $e) {
			return '';
		}
	}
}

if (!function_exists('clean_card_uid_raw')) {
	/**
	 * Clean reader input. Does NOT reverse bytes.
	 * NFC hex (8/14/20 chars, including all-digit UIDs) stays hex.
	 * Longer all-digit HID values are converted from decimal.
	 */
	function clean_card_uid_raw(string $raw): string
	{
		$uid = strtoupper(trim(preg_replace('/\s+/', '', $raw)));
		if ($uid === '') {
			return '';
		}

		$hexOnly = strtoupper(preg_replace('/[^A-F0-9]/', '', $uid));
		if (card_uid_is_nfc_hex_length($hexOnly) && ctype_xdigit($hexOnly)) {
			return $hexOnly;
		}

		if (ctype_digit($uid) && strlen($uid) >= 5 && strlen($uid) <= 13) {
			$fromDec = card_uid_decimal_to_hex($uid);
			if ($fromDec !== '') {
				return $fromDec;
			}
		}

		return strlen($hexOnly) >= 4 ? $hexOnly : '';
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
	 * All UID forms to try in DB lookups (hex, reversed, decimal-as-hex).
	 *
	 * @return string[]
	 */
	function card_uid_lookup_variants(string $rawOrStored): array
	{
		$out = [];
		$add = static function (string $uid) use (&$out): void {
			$uid = strtoupper(preg_replace('/[^A-F0-9]/', '', $uid));
			if (strlen($uid) < 4) {
				return;
			}
			$out[$uid] = true;
			$rev = reverse_card_uid_bytes($uid);
			if ($rev !== '') {
				$out[$rev] = true;
			}
		};

		$stripped = strtoupper(preg_replace('/[^A-F0-9]/', '', $rawOrStored));
		$clean = clean_card_uid_raw($rawOrStored);
		if ($stripped !== '' && card_uid_is_nfc_hex_length($stripped)) {
			$add($stripped);
		}
		if ($clean !== '') {
			$add($clean);
		}

		// USB decimal wedge AND all-digit hex UIDs (e.g. Android 94280002).
		$digits = preg_replace('/\D/', '', $rawOrStored);
		if ($digits !== '' && strlen($digits) >= 5 && strlen($digits) <= 13) {
			$fromDec = card_uid_decimal_to_hex($digits);
			if ($fromDec !== '') {
				$add($fromDec);
			}
		}

		return array_keys($out);
	}
}
