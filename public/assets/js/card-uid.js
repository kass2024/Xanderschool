/**
 * Shared RFID UID normalization — assign-card, attendance-card, parent visiting.
 *
 * NFC reader wedge sends UID in reader byte order (e.g. 6C0477CD).
 * Storage / assign-card form reverses byte pairs for DB (e.g. CD77046C).
 * Always use CardUid.toStorage() before save; lookups try both orders server-side.
 */
(function (global) {
	'use strict';

	function isNfcHexLength(uid) {
		var len = uid.length;
		return len === 8 || len === 14 || len === 20;
	}

	function cleanRaw(uid) {
		uid = String(uid == null ? '' : uid).replace(/\s+/g, '').trim();
		if (!uid) return '';

		var hexOnly = uid.replace(/[^A-Fa-f0-9]/g, '').toUpperCase();
		// Android NFC / USB hex: keep 8/14/20-char UIDs as hex even if they are all digits (94280002).
		if (isNfcHexLength(hexOnly)) {
			return hexOnly;
		}

		if (/^\d+$/.test(uid) && uid.length >= 5 && uid.length <= 13) {
			try {
				uid = BigInt(uid).toString(16).toUpperCase().padStart(8, '0');
			} catch (e) { /* keep as-is */ }
			uid = uid.replace(/[^A-Fa-f0-9]/g, '').toUpperCase();
			return uid.length >= 4 ? uid : '';
		}

		return hexOnly.length >= 4 ? hexOnly : '';
	}

	function reverseBytes(uid) {
		uid = cleanRaw(uid);
		if (!uid || uid.length % 2 !== 0) return uid;
		var bytes = uid.match(/.{1,2}/g);
		bytes.reverse();
		return bytes.join('');
	}

	/** Storage form (assign-card): clean then reverse byte order. */
	function toStorage(uid) {
		return reverseBytes(cleanRaw(uid));
	}

	/** Scan form (attendance-card): clean only — server tries both orders. */
	function forScan(uid) {
		return cleanRaw(uid);
	}

	global.CardUid = {
		cleanRaw: cleanRaw,
		reverseBytes: reverseBytes,
		toStorage: toStorage,
		forScan: forScan
	};
})(typeof window !== 'undefined' ? window : this);
