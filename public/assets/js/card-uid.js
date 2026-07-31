/**
 * Shared RFID UID normalization — assign-card, attendance-card, parent visiting.
 */
(function (global) {
	'use strict';

	function cleanRaw(uid) {
		uid = String(uid == null ? '' : uid).trim();
		if (!uid) return '';

		if (/^\d+$/.test(uid)) {
			try {
				uid = BigInt(uid).toString(16).toUpperCase().padStart(8, '0');
			} catch (e) { /* keep as-is */ }
		}

		uid = uid.replace(/[^A-Fa-f0-9]/g, '').toUpperCase();
		return uid.length >= 4 ? uid : '';
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
