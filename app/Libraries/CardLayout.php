<?php

namespace App\Libraries;

/**
 * Professional CR80 PVC student card templates (ISO/IEC 7810 ID-1).
 * School Settings editor + PDF generation both use the same % field boxes.
 */
class CardLayout
{
	/** Absolute CSS box from a layout field (% of card). */
	public static function boxStyle(array $f, int $z = 2): string
	{
		$x = (float)($f['x'] ?? 0);
		$y = (float)($f['y'] ?? 0);
		$w = (float)($f['w'] ?? 10);
		$h = (float)($f['h'] ?? 5);
		return "position:absolute;left:{$x}%;top:{$y}%;width:{$w}%;height:{$h}%;z-index:{$z};overflow:hidden;box-sizing:border-box;";
	}

	/**
	 * Auto-fit font size (mm) so text stays on one line inside a % box.
	 */
	public static function fitFontMm(
		string $text,
		float $boxWPct,
		float $boxHPct,
		float $cardWmm,
		float $cardHmm,
		float $maxMm = 3.4,
		float $minMm = 1.35,
		float $charFactor = 0.52
	): float {
		$text = trim($text);
		$len = max(1, mb_strlen($text, 'UTF-8'));
		$boxWmm = max(1.0, $cardWmm * ($boxWPct / 100) * 0.96);
		$boxHmm = max(1.0, $cardHmm * ($boxHPct / 100) * 0.78);
		$fromWidth = $boxWmm / ($len * $charFactor);
		$fromHeight = $boxHmm;
		return max($minMm, min($maxMm, min($fromWidth, $fromHeight)));
	}

	public static function isVisible(array $fields, string $key): bool
	{
		return !empty($fields[$key]['visible']);
	}

	/**
	 * Presentable Header text 1 / 2 from Basic school info.
	 * Header 1 = Tel · Email
	 * Header 2 = Website · Address
	 *
	 * @param object|array $school
	 * @return array{header1:string,header2:string}
	 */
	public static function composeHeaderLines($school): array
	{
		$g = static function (string $key) use ($school): string {
			if (is_array($school)) {
				return trim((string)($school[$key] ?? ''));
			}
			return trim((string)($school->{$key} ?? ''));
		};

		$phone = $g('phone');
		$email = $g('email');
		$website = $g('website');
		$pobox = $g('pobox');
		$address = $g('address');

		$clean = static function (string $s): string {
			$s = preg_replace('/\s*[_\|]+\s*/u', ' · ', $s) ?? $s;
			$s = preg_replace('/\s{2,}/u', ' ', $s) ?? $s;
			return trim($s, " ·\t\n\r");
		};

		$line1 = [];
		if ($phone !== '') {
			$line1[] = 'Tel ' . $clean($phone);
		}
		if ($email !== '') {
			$line1[] = 'Email ' . $clean($email);
		}

		$line2 = [];
		if ($website !== '') {
			$web = preg_replace('#^https?://#i', '', $website) ?? $website;
			$line2[] = $clean($web);
		}
		if ($pobox !== '') {
			$line2[] = 'P.O. Box ' . $clean($pobox);
		}
		if ($address !== '') {
			$line2[] = $clean($address);
		}

		return [
			'header1' => implode(' · ', $line1),
			'header2' => implode(' · ', $line2),
		];
	}

	/**
	 * Individual header lines from School Settings → Basic school info (visitor/staff cards).
	 *
	 * @param object|array $school
	 * @return string[]
	 */
	public static function schoolBasicInfoLines($school): array
	{
		$g = static function (string $key) use ($school): string {
			if (is_array($school)) {
				return trim((string) ($school[$key] ?? ''));
			}
			return trim((string) ($school->{$key} ?? ''));
		};

		$clean = static function (string $s): string {
			$s = preg_replace('/\s*[_\|]+\s*/u', ' ', $s) ?? $s;
			$s = preg_replace('/\s{2,}/u', ' ', $s) ?? $s;
			return trim($s, " \t\n\r");
		};

		$lines = [];
		$phone = $g('phone');
		$email = $g('email');
		$website = $g('website');
		$pobox = $g('pobox');
		$address = $g('address');

		if ($phone !== '') {
			$lines[] = 'Tel: ' . $clean($phone);
		}
		if ($email !== '') {
			$lines[] = 'Email: ' . $clean($email);
		}
		if ($website !== '') {
			$web = preg_replace('#^https?://#i', '', $website) ?? $website;
			$lines[] = 'Web: ' . $clean($web);
		}
		if ($pobox !== '') {
			$lines[] = 'P.O. Box: ' . $clean($pobox);
		}
		if ($address !== '') {
			$lines[] = $clean($address);
		}

		return $lines;
	}

	public const CR80_W_MM = 85.6;
	public const CR80_H_MM = 54.0;

	public const WISDOM_CHROME = 'assets/images/cards/wisdom_landscape_chrome.png';
	public const WISDOM_TEAL = '#00828E';
	public const WISDOM_NAVY = '#04496B';

	/** @var array<string,array{label:string,desc:string,orientation:string,accent:string,painted?:bool}> */
	public const TEMPLATES = [
		'ribbon' => [
			'label' => 'Crimson Ribbon',
			'desc' => 'Portrait CR80 — header texts + motto footer',
			'orientation' => 'portrait',
			'accent' => '#E53935',
		],
		'ocean' => [
			'label' => 'Ocean Navy',
			'desc' => 'Landscape CR80 — header texts + motto footer',
			'orientation' => 'landscape',
			'accent' => '#0EA5E9',
		],
		'wisdom' => [
			'label' => 'Wisdom Ribbon',
			'desc' => 'Landscape CR80 — teal/navy geometric student ID',
			'orientation' => 'landscape',
			'accent' => '#00828E',
			'painted' => true,
		],
	];

	/** Legacy / retired template keys → current keys */
	private const LEGACY_MAP = [
		'smart' => 'ocean',
		'modern' => 'ribbon',
		'compact' => 'ocean',
		'center' => 'ribbon',
		'pulse' => 'ribbon',
		'wave' => 'ribbon',
		'geo' => 'ocean',
	];

	/**
	 * Full layout keys (chrome + DB). Used by editor canvas + PDF.
	 * header1 / header2 / moto are always reserved (always visible).
	 */
	public const FIELDS = [
		'logo' => 'Logo',
		'school_name' => 'School name',
		'header1' => 'Header text 1',
		'header2' => 'Header text 2',
		'badge' => 'Card title',
		'photo' => 'Student photo',
		'names' => 'Names',
		'regno' => 'Reg No',
		'class' => 'Class',
		'dob' => 'D.O.B',
		'father' => 'Father / Guardian',
		'phone' => 'Emergency call',
		'mode' => 'Study mode',
		'moto' => 'School motto',
	];

	/** Staff layout keys (chrome + DB). */
	public const STAFF_FIELDS = [
		'logo' => 'Logo',
		'school_name' => 'School name',
		'header1' => 'Header text 1',
		'header2' => 'Header text 2',
		'badge' => 'Card title',
		'photo' => 'Staff photo',
		'names' => 'Names',
		'post' => 'Post',
		'phone' => 'Phone',
		'email' => 'Email',
		'staff_id' => 'Staff ID',
		'moto' => 'School motto',
	];

	/** Student DB fields only — shown as untickable toggles. */
	public const STUDENT_DB_FIELDS = [
		'photo' => 'Photo',
		'names' => 'Names',
		'regno' => 'Reg No',
		'class' => 'Class',
		'dob' => 'D.O.B',
		'father' => 'Father / Guardian',
		'phone' => 'Emergency call',
		'mode' => 'Study mode',
	];

	/** Staff DB fields only — shown as untickable toggles. */
	public const STAFF_DB_FIELDS = [
		'photo' => 'Photo',
		'names' => 'Names',
		'post' => 'Post',
		'phone' => 'Phone',
		'email' => 'Email',
		'staff_id' => 'Staff ID',
	];

	/** Parent visitor layout keys (landscape CR80 only). */
	public const VISITOR_FIELDS = [
		'logo' => 'Logo',
		'school_name' => 'School name',
		'header1' => 'Header text 1',
		'header2' => 'Header text 2',
		'badge' => 'Card title',
		'photo' => 'Student photo',
		'names' => 'Visitor name',
		'relationship' => 'Relationship',
		'student_name' => 'Student to visit',
		'student_class' => 'Student class',
		'card_uid' => 'Card ID / RFID',
		'moto' => 'School motto',
	];

	public const VISITOR_DB_FIELDS = [
		'photo' => 'Student photo',
		'student_name' => 'Student to visit',
		'student_class' => 'Student class',
		'card_uid' => 'Card ID / RFID',
	];

	/** Landscape templates allowed for visitor cards. */
	public const VISITOR_TEMPLATES = ['ocean'];

	/** Always-on chrome (not listed as optional DB toggles). */
	public const RESERVED_FIELDS = ['logo', 'school_name', 'header1', 'header2', 'badge', 'moto'];

	public static function normalizeTemplate(?string $template): string
	{
		$template = strtolower(trim((string) $template));
		if (isset(self::LEGACY_MAP[$template])) {
			$template = self::LEGACY_MAP[$template];
		}
		return isset(self::TEMPLATES[$template]) ? $template : 'ocean';
	}

	public static function preferredOrientation(string $template): string
	{
		$template = self::normalizeTemplate($template);
		$ori = self::TEMPLATES[$template]['orientation'] ?? 'landscape';
		return $ori === 'portrait' ? 'portrait' : 'landscape';
	}

	public static function normalizeOrientation(?string $orientation): string
	{
		return strtolower(trim((string) $orientation)) === 'portrait' ? 'portrait' : 'landscape';
	}

	public static function defaultAccent(string $template): string
	{
		$template = self::normalizeTemplate($template);
		return self::TEMPLATES[$template]['accent'] ?? '#0EA5E9';
	}

	/** Painted templates ship their own CSS design — backgrounds are never applied. */
	public static function isPainted(string $template): bool
	{
		$template = self::normalizeTemplate($template);
		return !empty(self::TEMPLATES[$template]['painted']);
	}

	/** Fixed-chrome templates (geometry + palette locked; fields still dynamic). */
	public static function isFixedChrome(string $template): bool
	{
		return self::normalizeTemplate($template) === 'wisdom';
	}

	/** Academic year as printed on the Wisdom ID (2025/2026). */
	public static function formatAcademicYear(?string $year): string
	{
		$year = trim((string) $year);
		if ($year === '') {
			return '';
		}
		if (stripos($year, 'A.Y') === 0) {
			$year = trim(substr($year, 3));
		}
		$year = preg_replace('/\s+/', '', $year) ?? $year;
		$year = str_replace(['–', '—', '-'], '/', $year);
		return $year;
	}

	/**
	 * Wisdom printed school name from the student's path.
	 * Nursery / Primary → Wisdom School Musanze; otherwise Wisdom High School.
	 */
	public static function wisdomCardSchoolName(array $student): string
	{
		$hay = self::wisdomStudentPathHaystack($student);
		if (preg_match('/\b(nurs(?:e|ery|ary)|maternelle|baby|primary|primaire)\b/', $hay)) {
			return 'WISDOM SCHOOL MUSANZE';
		}
		if (preg_match('/\b(p[1-6]|pp[1-3]?|reception|kindergarten|\bkg\b)\b/', $hay)) {
			return 'WISDOM SCHOOL MUSANZE';
		}
		return 'WISDOM HIGH SCHOOL';
	}

	/**
	 * True for Wisdom primary (P1–P6) students — use primary pass PNG.
	 * Nursery / secondary are excluded.
	 */
	public static function isWisdomPrimaryStudent(array $student): bool
	{
		$hay = self::wisdomStudentPathHaystack($student);
		if (preg_match('/\b(nurs(?:e|ery|ary)|maternelle|baby\s*class|middle\s*class|top\s*class|\bn[1-3]\b)\b/', $hay)) {
			return false;
		}
		return (bool) preg_match('/\b(primary|primaire|p[1-6])\b/', $hay);
	}

	/** @param array<string,mixed> $student */
	private static function wisdomStudentPathHaystack(array $student): string
	{
		$hay = strtolower(trim(implode(' ', [
			(string) ($student['faculty_title'] ?? ''),
			(string) ($student['level_faculty_title'] ?? ''),
			(string) ($student['dept_title'] ?? ''),
			(string) ($student['level_title'] ?? ''),
			(string) ($student['class'] ?? ''),
		])));
		return preg_replace('/\s+/', ' ', $hay) ?? $hay;
	}

	/** Mix a hex color with white (ratio 0..1, higher = lighter). */
	public static function tint(string $hex, float $ratio): string
	{
		$hex = ltrim(trim($hex), '#');
		if (strlen($hex) === 3) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if (!preg_match('/^[0-9A-Fa-f]{6}$/', $hex)) {
			$hex = '1E6FD9';
		}
		$ratio = max(0.0, min(1.0, $ratio));
		$out = '#';
		foreach ([0, 2, 4] as $i) {
			$c = hexdec(substr($hex, $i, 2));
			$c = (int) round($c + (255 - $c) * $ratio);
			$out .= str_pad(dechex($c), 2, '0', STR_PAD_LEFT);
		}
		return $out;
	}

	/** Human D.O.B from DB date (empty for missing / zero dates). */
	public static function formatDob(?string $dob): string
	{
		$dob = trim((string) $dob);
		if ($dob === '' || strpos($dob, '0000') === 0) {
			return '';
		}
		$ts = strtotime($dob);
		return $ts ? date('d-m-Y', $ts) : $dob;
	}

	/** Force reserved header texts + motto footer on every resolve. */
	private static function enforceReserved(array $fields, array $defaults, string $template = 'ocean'): array
	{
		$fixed = self::isFixedChrome($template);
		$reserved = $fixed
			? ['logo', 'school_name', 'badge', 'header1', 'photo', 'names', 'class', 'regno']
			: self::RESERVED_FIELDS;
		foreach ($reserved as $key) {
			if (!isset($fields[$key]) && isset($defaults[$key])) {
				$fields[$key] = $defaults[$key];
			}
			if (!isset($fields[$key])) {
				continue;
			}
			$fields[$key]['visible'] = true;
			if ($fixed) {
				if (isset($defaults[$key])) {
					$fields[$key]['x'] = $defaults[$key]['x'];
					$fields[$key]['y'] = $defaults[$key]['y'];
					$fields[$key]['w'] = $defaults[$key]['w'];
					$fields[$key]['h'] = $defaults[$key]['h'];
				}
				continue;
			}
			// Keep moto pinned to footer band from template defaults
			if ($key === 'moto' && isset($defaults['moto'])) {
				$fields[$key]['x'] = $defaults['moto']['x'];
				$fields[$key]['y'] = $defaults['moto']['y'];
				$fields[$key]['w'] = $defaults['moto']['w'];
				$fields[$key]['h'] = $defaults['moto']['h'];
			}
			// Card title bar always full-bleed (touch left + right edges)
			if ($key === 'badge') {
				$fields[$key]['x'] = 0;
				$fields[$key]['w'] = 100;
				if (isset($defaults['badge']['y'])) {
					$fields[$key]['y'] = $defaults['badge']['y'];
				}
				if (isset($defaults['badge']['h'])) {
					$fields[$key]['h'] = $defaults['badge']['h'];
				}
			}
			// Keep header1/header2 in reserved header slots from defaults
			if (($key === 'header1' || $key === 'header2') && isset($defaults[$key])) {
				$fields[$key]['x'] = $defaults[$key]['x'];
				$fields[$key]['y'] = $defaults[$key]['y'];
				$fields[$key]['w'] = $defaults[$key]['w'];
				$fields[$key]['h'] = $defaults[$key]['h'];
			}
		}
		return $fields;
	}

	/**
	 * @return array{template:string,fields:array<string,array{x:float,y:float,w:float,h:float,visible:bool}>,orientation:string}
	 */
	public static function defaults(string $template, string $orientation = 'landscape'): array
	{
		$template = self::normalizeTemplate($template);
		$orientation = self::normalizeOrientation($orientation);
		if ($template === 'wisdom') {
			$orientation = 'landscape';
		}
		$map = [
			'ribbon' => self::layoutRibbon(),
			'ocean' => self::layoutOcean(),
			'wisdom' => self::layoutWisdom(),
		];
		$fields = $map[$template] ?? self::layoutOcean();
		// Drop legacy keys no longer in FIELDS
		$fields = array_intersect_key($fields, self::FIELDS);
		return [
			'template' => $template,
			'fields' => $fields,
			'orientation' => $orientation,
		];
	}

	/**
	 * Staff layout defaults — uses DB fields (post required / always visible).
	 *
	 * @return array{template:string,fields:array,orientation:string}
	 */
	public static function staffDefaults(string $template, string $orientation = 'landscape'): array
	{
		$base = self::defaults($template, $orientation);
		$s = $base['fields'];
		$fields = [
			'logo' => $s['logo'] ?? self::f(5, 3, 14, 10),
			'school_name' => $s['school_name'] ?? self::f(22, 2.5, 74, 5),
			'header1' => $s['header1'] ?? self::f(22, 7.5, 74, 3.5),
			'header2' => $s['header2'] ?? self::f(22, 11, 74, 3.5),
			'badge' => $s['badge'] ?? self::f(0, 15.5, 100, 5.5),
			'photo' => $s['photo'] ?? self::f(32, 22, 36, 30),
			'names' => $s['names'] ?? self::f(8, 52, 84, 5),
			'post' => $s['class'] ?? self::f(8, 58, 84, 4.5),
			'phone' => $s['phone'] ?? self::f(8, 68, 84, 4),
			'email' => $s['father'] ?? self::f(8, 63, 84, 4),
			'staff_id' => $s['regno'] ?? self::f(8, 73, 84, 4),
			'moto' => $s['moto'] ?? self::f(0, 92, 100, 8),
		];
		$fields = array_intersect_key($fields, self::STAFF_FIELDS);
		$fields['post']['visible'] = true;
		$fields['names']['visible'] = true;
		$fields['photo']['visible'] = true;
		$fields = self::enforceReserved($fields, $fields, $base['template']);
		return [
			'template' => $base['template'],
			'orientation' => $base['orientation'],
			'fields' => $fields,
		];
	}

	/**
	 * Visitor card defaults — landscape only; minimal fields for parent visiting.
	 *
	 * @return array{template:string,fields:array,orientation:string}
	 */
	public static function visitorDefaults(string $template, string $orientation = 'landscape'): array
	{
		$template = self::normalizeTemplate($template);
		if (!in_array($template, self::VISITOR_TEMPLATES, true)) {
			$template = 'ocean';
		}
		$orientation = 'landscape';
		$fields = [
			'logo' => self::f(2.5, 3, 12, 18),
			'school_name' => self::f(16, 3, 50, 7),
			'header1' => self::f(16, 10, 50, 5),
			'header2' => self::f(16, 15, 50, 5),
			'badge' => self::f(0, 19, 100, 4.2),
			'photo' => self::f(3, 24.5, 20, 50),
			'names' => self::f(26, 24, 68, 7),
			'relationship' => self::f(26, 32, 68, 6),
			'student_name' => self::f(26, 24, 68, 10),
			'student_class' => self::f(26, 35, 68, 9),
			'card_uid' => self::f(26, 45.5, 68, 10),
			'moto' => self::f(0, 88, 100, 12),
		];
		$fields = array_intersect_key($fields, self::VISITOR_FIELDS);
		foreach (['photo', 'student_name', 'student_class', 'card_uid'] as $req) {
			if (isset($fields[$req])) {
				$fields[$req]['visible'] = true;
			}
		}
		foreach (['names', 'relationship'] as $hidden) {
			if (isset($fields[$hidden])) {
				$fields[$hidden]['visible'] = false;
			}
		}
		$fields = self::enforceReserved($fields, $fields, $template);
		return [
			'template' => $template,
			'orientation' => 'landscape',
			'fields' => $fields,
		];
	}

	/**
	 * @return array{template:string,fields:array,orientation:string}
	 */
	public static function resolveVisitor(?string $json, ?string $fallbackTemplate = 'ocean', string $orientation = 'landscape'): array
	{
		$saved = [];
		if ($json) {
			$decoded = json_decode($json, true);
			if (is_array($decoded)) {
				$saved = $decoded;
			}
		}
		$template = self::normalizeTemplate($saved['template'] ?? $fallbackTemplate);
		if (!in_array($template, self::VISITOR_TEMPLATES, true)) {
			$template = 'ocean';
		}
		$orientation = 'landscape';
		$defaults = self::visitorDefaults($template, $orientation);
		$fields = $defaults['fields'];
		$rawFields = is_array($saved['fields'] ?? null) ? $saved['fields'] : [];
		if (!isset($rawFields['student_name']) && isset($rawFields['regno'])) {
			$rawFields['student_name'] = $rawFields['regno'];
		}
		if (!isset($rawFields['card_uid']) && isset($rawFields['phone'])) {
			$rawFields['card_uid'] = $rawFields['phone'];
		}
		if (!isset($rawFields['relationship']) && isset($rawFields['father'])) {
			$rawFields['relationship'] = $rawFields['father'];
		}
		if (!isset($rawFields['student_class']) && isset($rawFields['class'])) {
			$rawFields['student_class'] = $rawFields['class'];
		}
		foreach ($rawFields as $key => $cfg) {
			if (!isset($fields[$key]) || !is_array($cfg)) {
				continue;
			}
			$fields[$key] = [
				'x' => self::clamp((float) ($cfg['x'] ?? $fields[$key]['x']), 0, 95),
				'y' => self::clamp((float) ($cfg['y'] ?? $fields[$key]['y']), 0, 95),
				'w' => self::clamp((float) ($cfg['w'] ?? $fields[$key]['w']), 5, 100),
				'h' => self::clamp((float) ($cfg['h'] ?? $fields[$key]['h']), 4, 100),
				'visible' => array_key_exists('visible', $cfg) ? (bool) $cfg['visible'] : (bool) $fields[$key]['visible'],
			];
		}
		$fields = self::enforceReserved($fields, $defaults['fields'], $template);
		foreach (['names', 'relationship'] as $hidden) {
			if (isset($fields[$hidden])) {
				$fields[$hidden]['visible'] = false;
			}
		}
		return ['template' => $template, 'fields' => $fields, 'orientation' => 'landscape'];
	}

	/** @return array<string,array{label:string,desc:string,orientation:string,accent:string,painted?:bool}> */
	public static function visitorTemplateChoices(): array
	{
		return array_intersect_key(self::TEMPLATES, array_flip(self::VISITOR_TEMPLATES));
	}

	/**
	 * @return array{template:string,fields:array,orientation?:string}
	 */
	public static function resolve(?string $json, ?string $fallbackTemplate = 'ocean', string $orientation = 'landscape'): array
	{
		$saved = [];
		if ($json) {
			$decoded = json_decode($json, true);
			if (is_array($decoded)) {
				$saved = $decoded;
			}
		}
		$template = self::normalizeTemplate($saved['template'] ?? $fallbackTemplate);
		if ($template === 'wisdom') {
			$orientation = 'landscape';
		} elseif (!empty($saved['orientation'])) {
			$orientation = self::normalizeOrientation($saved['orientation']);
		} elseif ($orientation) {
			$orientation = self::normalizeOrientation($orientation);
		} else {
			$orientation = self::preferredOrientation($template);
		}
		$defaults = self::defaults($template, $orientation);
		$fields = $defaults['fields'];
		if (!empty($saved['fields']) && is_array($saved['fields'])) {
			foreach ($saved['fields'] as $key => $cfg) {
				if (!isset($fields[$key]) || !is_array($cfg)) {
					continue;
				}
				$fields[$key] = [
					'x' => self::clamp((float) ($cfg['x'] ?? $fields[$key]['x']), 0, 95),
					'y' => self::clamp((float) ($cfg['y'] ?? $fields[$key]['y']), 0, 95),
					'w' => self::clamp((float) ($cfg['w'] ?? $fields[$key]['w']), 5, 100),
					'h' => self::clamp((float) ($cfg['h'] ?? $fields[$key]['h']), 4, 100),
					'visible' => array_key_exists('visible', $cfg) ? (bool) $cfg['visible'] : (bool) $fields[$key]['visible'],
				];
			}
		}
		$fields = self::enforceReserved($fields, $defaults['fields'], $template);
		return ['template' => $template, 'fields' => $fields, 'orientation' => $orientation];
	}

	/**
	 * Resolve staff layout JSON; migrates legacy student keys (class→post, etc.).
	 *
	 * @return array{template:string,fields:array,orientation:string}
	 */
	public static function resolveStaff(?string $json, ?string $fallbackTemplate = 'ocean', string $orientation = 'landscape'): array
	{
		$saved = [];
		if ($json) {
			$decoded = json_decode($json, true);
			if (is_array($decoded)) {
				$saved = $decoded;
			}
		}
		$template = self::normalizeTemplate($saved['template'] ?? $fallbackTemplate);
		if ($template === 'wisdom') {
			$orientation = 'landscape';
		} elseif (!empty($saved['orientation'])) {
			$orientation = self::normalizeOrientation($saved['orientation']);
		} else {
			$orientation = self::normalizeOrientation($orientation ?: self::preferredOrientation($template));
		}
		$defaults = self::staffDefaults($template, $orientation);
		$fields = $defaults['fields'];
		$rawFields = is_array($saved['fields'] ?? null) ? $saved['fields'] : [];
		if (!isset($rawFields['post']) && isset($rawFields['class'])) {
			$rawFields['post'] = $rawFields['class'];
		}
		if (!isset($rawFields['staff_id']) && isset($rawFields['regno'])) {
			$rawFields['staff_id'] = $rawFields['regno'];
		}
		if (!isset($rawFields['email']) && isset($rawFields['father'])) {
			$rawFields['email'] = $rawFields['father'];
		}
		foreach ($rawFields as $key => $cfg) {
			if (!isset($fields[$key]) || !is_array($cfg)) {
				continue;
			}
			$fields[$key] = [
				'x' => self::clamp((float) ($cfg['x'] ?? $fields[$key]['x']), 0, 95),
				'y' => self::clamp((float) ($cfg['y'] ?? $fields[$key]['y']), 0, 95),
				'w' => self::clamp((float) ($cfg['w'] ?? $fields[$key]['w']), 5, 100),
				'h' => self::clamp((float) ($cfg['h'] ?? $fields[$key]['h']), 4, 100),
				'visible' => array_key_exists('visible', $cfg) ? (bool) $cfg['visible'] : (bool) $fields[$key]['visible'],
			];
		}
		$fields['post']['visible'] = true;
		$fields = self::enforceReserved($fields, $defaults['fields'], $template);
		return ['template' => $template, 'fields' => $fields, 'orientation' => $orientation];
	}

	private static function clamp(float $v, float $min, float $max): float
	{
		return max($min, min($max, $v));
	}

	private static function f(float $x, float $y, float $w, float $h, bool $visible = true): array
	{
		return ['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'visible' => $visible];
	}

	/**
	 * Portrait Classic Curve — painted swoosh design (reference: logo left, centered
	 * school name + header lines, big centered photo, label:value rows, motto footer).
	 */
	private static function layoutPulse(): array
	{
		return [
			'logo' => self::f(5, 2.5, 15, 10.5),
			'school_name' => self::f(21, 2.5, 74, 5.5),
			'header1' => self::f(21, 8.6, 74, 3.6),
			'header2' => self::f(21, 12.3, 74, 3.4),
			'badge' => self::f(0, 16.8, 100, 4.8),
			'photo' => self::f(31, 23.2, 38, 30),
			'names' => self::f(9, 55.5, 84, 4.8),
			'regno' => self::f(9, 60.6, 84, 4.2),
			'class' => self::f(9, 65.1, 84, 4.2),
			'dob' => self::f(9, 69.6, 84, 4.2),
			'father' => self::f(9, 74.1, 84, 4.2),
			'phone' => self::f(9, 78.6, 84, 4.2),
			'mode' => self::f(9, 83.1, 84, 4.2, false),
			'moto' => self::f(0, 92, 100, 8),
		];
	}

	/** Portrait Ribbon — photo upper, badge mid, fields below */
	private static function layoutRibbon(): array
	{
		return [
			'logo' => self::f(4, 2, 14, 10),
			'school_name' => self::f(20, 2, 76, 5),
			'header1' => self::f(20, 7.2, 76, 3.8),
			'header2' => self::f(20, 11, 76, 3.5),
			'badge' => self::f(0, 50, 100, 5.5),
			'photo' => self::f(32, 16.5, 36, 30),
			'names' => self::f(8, 57, 84, 4.6),
			'class' => self::f(8, 62, 84, 4),
			'regno' => self::f(8, 66.5, 84, 4),
			'dob' => self::f(8, 71, 84, 4, false),
			'father' => self::f(8, 75.5, 84, 4),
			'phone' => self::f(8, 80, 84, 4),
			'mode' => self::f(8, 84.5, 84, 4, false),
			'moto' => self::f(0, 92, 100, 8),
		];
	}

	/** Portrait Wave — clean centered stack */
	private static function layoutWave(): array
	{
		return [
			'logo' => self::f(4, 2, 14, 9),
			'school_name' => self::f(20, 2, 76, 5),
			'header1' => self::f(20, 7.2, 76, 3.8),
			'header2' => self::f(20, 11, 76, 3.5),
			'badge' => self::f(0, 15.5, 100, 5),
			'photo' => self::f(32, 22, 36, 30),
			'names' => self::f(8, 54, 84, 4.6),
			'regno' => self::f(8, 59, 84, 4),
			'class' => self::f(8, 63.5, 84, 4),
			'dob' => self::f(8, 68, 84, 4, false),
			'father' => self::f(8, 72.5, 84, 4),
			'phone' => self::f(8, 77, 84, 4),
			'mode' => self::f(8, 81.5, 84, 4, false),
			'moto' => self::f(0, 92, 100, 8),
		];
	}

	/** Landscape Ocean — photo left, fields right, motto footer */
	private static function layoutOcean(): array
	{
		return [
			'logo' => self::f(2.5, 3, 12, 18),
			'school_name' => self::f(16, 3, 50, 7),
			'header1' => self::f(16, 10, 50, 5),
			'header2' => self::f(16, 15, 50, 4.5),
			'badge' => self::f(0, 19.5, 100, 4.5),
			'photo' => self::f(3.5, 24, 22, 52),
			'names' => self::f(29, 24, 67, 8),
			'regno' => self::f(29, 33, 67, 6.5),
			'class' => self::f(29, 40.5, 67, 6.5),
			'dob' => self::f(29, 48, 67, 6.5, false),
			'father' => self::f(29, 55.5, 67, 6.5),
			'phone' => self::f(29, 63, 67, 6.5),
			'mode' => self::f(29, 70.5, 67, 6.5, false),
			'moto' => self::f(0, 88, 100, 12),
		];
	}

	/** Landscape Geo — classic left photo */
	private static function layoutGeo(): array
	{
		return [
			'logo' => self::f(2.5, 2.5, 11, 16),
			'school_name' => self::f(15, 2.5, 52, 7),
			'header1' => self::f(15, 9.5, 52, 5),
			'header2' => self::f(15, 14.5, 52, 4.5),
			'badge' => self::f(0, 18.5, 100, 4.5),
			'photo' => self::f(3.5, 22, 22, 54),
			'names' => self::f(29, 24, 67, 8),
			'regno' => self::f(29, 33, 67, 6.5),
			'class' => self::f(29, 40.5, 67, 6.5),
			'dob' => self::f(29, 48, 67, 6.5, false),
			'father' => self::f(29, 55.5, 67, 6.5),
			'phone' => self::f(29, 63, 67, 6.5),
			'mode' => self::f(29, 70.5, 67, 6.5, false),
			'moto' => self::f(0, 88, 100, 12),
		];
	}

	/**
	 * Landscape Wisdom Ribbon — measured from the school FRONT artwork.
	 * header1 is the Academic Year row (not tel/email).
	 */
	private static function layoutWisdom(): array
	{
		return [
			'logo' => self::f(3.6, 2.4, 16.8, 26.6),
			'school_name' => self::f(21.5, 8.2, 66.0, 14.2),
			'header1' => self::f(35.5, 66.2, 62.0, 7.6),
			'header2' => self::f(35.5, 74, 62.0, 6, false),
			'badge' => self::f(36.0, 36.2, 36.0, 10.8),
			'photo' => self::f(6.6, 33.8, 25.2, 40.0),
			'names' => self::f(35.5, 49.5, 62.0, 8.2),
			'class' => self::f(35.5, 57.8, 62.0, 7.8),
			'regno' => self::f(5.5, 84.8, 32.0, 10.2),
			'dob' => self::f(42.8, 74, 54.5, 6, false),
			'father' => self::f(42.8, 74, 54.5, 6, false),
			'phone' => self::f(42.8, 74, 54.5, 6, false),
			'mode' => self::f(42.8, 74, 54.5, 6, false),
			'moto' => self::f(0, 92, 100, 8, false),
		];
	}
}
