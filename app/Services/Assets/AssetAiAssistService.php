<?php

namespace App\Services\Assets;

use App\Models\AssetCategoryModel;
use App\Models\AssetModel;
use App\Models\AssetOpsSchema;

/**
 * Lightweight heuristic helpers (no external AI API). PHP 7.4.
 */
class AssetAiAssistService
{
	/**
	 * Suggest category from name/description keywords.
	 *
	 * @param int $schoolId
	 * @param string $name
	 * @param string|null $description
	 * @return array|null
	 */
	public function suggestCategory($schoolId, $name, $description = null)
	{
		AssetOpsSchema::ensureAll();
		(new AssetModel())->ensureSchema();

		$text = strtolower(trim($name . ' ' . (string) $description));
		if ($text === '') {
			return null;
		}

		$categories = (new AssetCategoryModel())->listForSchool((int) $schoolId);
		if (empty($categories)) {
			return null;
		}

		$keywords = [
			'laptop' => ['LAPTOP', 'ICT'],
			'desktop' => ['DESKTOP', 'ICT'],
			'computer' => ['DESKTOP', 'ICT', 'ICT'],
			'printer' => ['PRINTER', 'ICT'],
			'projector' => ['PROJ', 'ICT'],
			'router' => ['ROUTER', 'ICT'],
			'desk' => ['DESK', 'FURN'],
			'chair' => ['CHAIR', 'FURN'],
			'cabinet' => ['CAB', 'FURN'],
			'furniture' => ['FURN', 'FURN'],
			'vehicle' => ['VEH', 'VEH'],
			'car' => ['VEH', 'VEH'],
			'bus' => ['VEH', 'VEH'],
			'lab' => ['LAB', 'LAB'],
			'microscope' => ['LAB', 'LAB'],
			'book' => ['BOOK', 'BOOK'],
			'library' => ['BOOK', 'BOOK'],
			'kitchen' => ['KITCH', 'KITCH'],
			'sport' => ['SPORT', 'SPORT'],
			'ball' => ['SPORT', 'SPORT'],
		];

		$best = null;
		$bestScore = 0;

		foreach ($categories as $cat) {
			$score = 0;
			$code = strtoupper($cat['category_code']);
			$catName = strtolower($cat['name']);

			if (strpos($text, strtolower($code)) !== false) {
				$score += 5;
			}
			foreach (preg_split('/\s+/', $catName) as $word) {
				if (strlen($word) >= 3 && strpos($text, $word) !== false) {
					$score += 3;
				}
			}

			foreach ($keywords as $kw => $codes) {
				if (strpos($text, $kw) !== false && in_array($code, $codes, true)) {
					$score += 4;
				}
			}

			if ($score > $bestScore) {
				$bestScore = $score;
				$best = $cat;
			}
		}

		if (!$best || $bestScore < 3) {
			return null;
		}

		return [
			'suggestion_type' => 'ai_suggestion',
			'category_id' => (int) $best['id'],
			'category_code' => $best['category_code'],
			'category_name' => $best['name'],
			'confidence' => min(100, $bestScore * 10),
			'message' => 'AI suggestion: "' . $best['name'] . '" based on keyword match',
		];
	}

	/**
	 * Find assets with similar name or matching serial.
	 *
	 * @param int $schoolId
	 * @param string $name
	 * @param string|null $serial
	 * @return array
	 */
	public function detectLikelyDuplicates($schoolId, $name, $serial = null)
	{
		AssetOpsSchema::ensureAll();
		$db = \Config\Database::connect();
		$schoolId = (int) $schoolId;
		$name = trim($name);
		$matches = [];

		if ($serial !== null && trim($serial) !== '') {
			$serial = trim($serial);
			$serialRows = $db->table('assets')
				->where('school_id', $schoolId)
				->where('archived_at', null)
				->where('serial_number', $serial)
				->get()->getResultArray();
			foreach ($serialRows as $row) {
				$row['match_reason'] = 'exact_serial';
				$matches[(int) $row['id']] = $row;
			}
		}

		if ($name !== '') {
			$nameRows = $db->table('assets')
				->where('school_id', $schoolId)
				->where('archived_at', null)
				->like('name', $name)
				->limit(20)
				->get()->getResultArray();

			foreach ($nameRows as $row) {
				$id = (int) $row['id'];
				$similarity = 0;
				similar_text(strtolower($name), strtolower($row['name']), $similarity);
				if ($similarity >= 70 || stripos($row['name'], $name) !== false) {
					$row['match_reason'] = 'similar_name';
					$row['similarity_percent'] = round($similarity, 1);
					$matches[$id] = $row;
				}
			}
		}

		return array_values($matches);
	}

	/**
	 * Text summary of audit discrepancies.
	 *
	 * @param int $auditId
	 * @return string
	 */
	public function summarizeAuditDiscrepancies($auditId)
	{
		AssetOpsSchema::ensureAll();
		$db = \Config\Database::connect();
		$auditId = (int) $auditId;

		$audit = $db->table('asset_audits')->where('id', $auditId)->get(1)->getRowArray();
		if (!$audit) {
			return 'Audit not found.';
		}

		$items = $db->table('asset_audit_items')
			->where('audit_id', $auditId)
			->get()->getResultArray();

		$counts = [];
		foreach ($items as $item) {
			$r = $item['result'] ?? 'pending';
			if (!isset($counts[$r])) {
				$counts[$r] = 0;
			}
			$counts[$r]++;
		}

		$issues = [];
		foreach ($items as $item) {
			if (($item['result'] ?? '') === 'found_ok') {
				continue;
			}
			$issues[] = $item;
		}

		$lines = [];
		$lines[] = 'Audit ' . ($audit['audit_no'] ?? $auditId) . ' — ' . ($audit['title'] ?? 'Untitled');
		$lines[] = 'Status: ' . ($audit['status'] ?? 'unknown');
		$lines[] = 'Total items: ' . count($items);
		$lines[] = 'Found OK: ' . (int) ($counts['found_ok'] ?? 0);

		$problemTypes = ['wrong_location', 'wrong_custodian', 'damaged', 'not_found', 'unexpected', 'duplicate_tag', 'unregistered', 'pending'];
		foreach ($problemTypes as $pt) {
			if (!empty($counts[$pt])) {
				$lines[] = ucfirst(str_replace('_', ' ', $pt)) . ': ' . $counts[$pt];
			}
		}

		if (empty($issues)) {
			$lines[] = 'No discrepancies — all scanned items match expectations.';
		} else {
			$lines[] = '';
			$lines[] = 'Discrepancy details (up to 15):';
			$shown = 0;
			foreach ($issues as $item) {
				if ($shown >= 15) {
					$lines[] = '… and ' . (count($issues) - 15) . ' more.';
					break;
				}
				$code = $item['scanned_code'] ?? ('asset#' . ($item['asset_id'] ?? '?'));
				$lines[] = '- [' . ($item['result'] ?? '?') . '] ' . $code
					. (!empty($item['notes']) ? ' — ' . $item['notes'] : '');
				$shown++;
			}
		}

		return implode("\n", $lines);
	}
}
