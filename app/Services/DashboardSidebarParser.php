<?php

namespace App\Services;

/**
 * Builds Level clearance menu tree by parsing app/Views/main.php sidebar.
 * When dashboard sidebar menus change, clearance updates automatically on next load.
 */
class DashboardSidebarParser
{
	/** @var array<string, string> */
	private static $labelCache = [];

	/** @var array<int, array<string, mixed>>|null */
	private static $treeCache = null;

	/**
	 * Sidebar group order and display labels (matches main.php structure).
	 *
	 * @return array<int, array{key:string,label:string,lang?:string,standalone?:bool,always?:bool,nested?:string[]}>
	 */
	private static function groupDefinitions()
	{
		return [
			['key' => 'dashboard', 'label' => 'Dashboard', 'always' => true],
			['key' => 'students', 'label' => 'Students', 'lang' => 'app.students'],
			['key' => 'classes', 'label' => 'Classes', 'lang' => 'app.classes', 'standalone' => true],
			['key' => 'course', 'label' => 'Course', 'lang' => 'app.course'],
			['key' => 'behavior', 'label' => 'Discipline', 'lang' => 'app.discipline'],
			['key' => 'permissions', 'label' => 'Permissions', 'lang' => 'app.permissions'],
			['key' => 'parent_visiting', 'label' => 'Parent visiting'],
			['key' => 'daily_visitors', 'label' => 'Daily visiting', 'lang' => 'app.dailyVisitors'],
			['key' => 'marks', 'label' => 'Marks', 'lang' => 'app.marks'],
			['key' => 'pedagogical', 'label' => 'Pedagogical Documents'],
			['key' => 'messaging', 'label' => 'Messaging', 'lang' => 'app.messaging'],
			['key' => 'student_reports', 'label' => 'Student Attendance', 'lang' => 'app.studentAttendance'],
			['key' => 'staff_reports', 'label' => 'Staff Attendance', 'lang' => 'app.staffAttendance'],
			['key' => 'staffs', 'label' => 'Staffs', 'lang' => 'app.staffs'],
			['key' => 'finance', 'label' => 'Finance', 'lang' => 'app.finance', 'nested' => ['fees', 'budget_cashflow']],
			['key' => 'asset_management', 'label' => 'Asset Management'],
			['key' => 'transport', 'label' => 'Transport Management', 'lang' => 'app.transportManagement'],
			['key' => 'pocket_money', 'label' => 'Pocket Money', 'lang' => 'app.PocketMoney', 'standalone' => true],
			['key' => 'leave_application', 'label' => 'Leave Application', 'lang' => 'app.leaveApplication', 'standalone' => true],
			['key' => 'leave_management', 'label' => 'Leave Management', 'lang' => 'app.leaveManagement', 'standalone' => true],
			['key' => 'settings', 'label' => 'Settings', 'lang' => 'app.settings', 'standalone' => true],
			['key' => 'profile', 'label' => 'Profile', 'always' => true],
		];
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function tree()
	{
		if (self::$treeCache !== null) {
			return self::$treeCache;
		}

		$sidebar = self::extractSidebarContent();
		if ($sidebar === '') {
			self::$treeCache = \Config\MenuClearance::staticTree();
			return self::$treeCache;
		}

		$tree = [];
		foreach (self::groupDefinitions() as $def) {
			$key = $def['key'];
			if (!empty($def['always'])) {
				$tree[] = [
					'key' => $key,
					'label' => $def['label'],
					'always' => true,
					'children' => [],
				];
				continue;
			}

			if (!empty($def['standalone'])) {
				if (!self::isKeyVisibleInSidebar($sidebar, $key)) {
					continue;
				}
				$tree[] = [
					'key' => $key,
					'label' => self::resolveLabel($def),
					'children' => [],
				];
				continue;
			}

			if ($key === 'finance') {
				$block = self::extractGroupBlock($sidebar, 'finance');
				if ($block === '') {
					continue;
				}
				$children = self::buildFinanceChildren($block);
				if ($children) {
					$tree[] = [
						'key' => $key,
						'label' => self::resolveLabel($def),
						'children' => $children,
					];
				}
				continue;
			}

			if ($key === 'marks') {
				$block = self::extractGroupBlock($sidebar, $key);
				if ($block === '') {
					continue;
				}
				$children = self::buildMarksChildren($block);
				$tree[] = [
					'key' => $key,
					'label' => self::resolveLabel($def),
					'children' => $children,
				];
				continue;
			}

			if ($key === 'asset_management') {
				$block = self::extractCombinedBlock($sidebar, ['asset_management', 'library']);
				if ($block === '') {
					continue;
				}
				$children = self::buildMenuItemNodes($block);
				if ($children) {
					$tree[] = [
						'key' => $key,
						'label' => self::resolveLabel($def),
						'children' => $children,
					];
				}
				continue;
			}

			$visible = ($key === 'student_reports' || $key === 'staff_reports')
				? self::isKeyVisibleInSidebar($sidebar, $key)
				: (self::extractGroupBlock($sidebar, $key) !== '' || self::isKeyVisibleInSidebar($sidebar, $key));

			if (!$visible) {
				continue;
			}

			$block = self::extractGroupBlock($sidebar, $key);
			$children = [];
			if ($block !== '') {
				$children = self::buildMenuItemNodes($block);
				foreach (self::extractBudgetSidebarLinks($block) as $budgetItem) {
					$children[] = $budgetItem;
				}
			}

			$tree[] = [
				'key' => $key,
				'label' => self::resolveLabel($def),
				'children' => $children,
			];
		}

		// Settings lives in header profile dropdown — still parsed from full main.php
		if (!self::hasGroupKey($tree, 'settings') && self::isKeyVisibleInSidebar(self::readMainContent(), 'settings')) {
			$tree[] = ['key' => 'settings', 'label' => 'Settings', 'children' => []];
		}

		self::$treeCache = $tree;
		return self::$treeCache;
	}

	/**
	 * @return string[]
	 */
	public static function allKeys()
	{
		$keys = [];
		foreach (self::tree() as $group) {
			if (!empty($group['key'])) {
				$keys[] = $group['key'];
			}
			$keys = array_merge($keys, self::collectKeysFromNodes($group['children'] ?? []));
		}
		return array_values(array_unique($keys));
	}

	/**
	 * Leaf permission keys under a subgroup (e.g. fees, budget_cashflow).
	 *
	 * @param string $financeChildKey
	 * @return string[]
	 */
	public static function financeSubgroupKeys($financeChildKey)
	{
		foreach (self::tree() as $group) {
			if (($group['key'] ?? '') !== 'finance') {
				continue;
			}
			foreach ($group['children'] ?? [] as $sub) {
				if (($sub['key'] ?? '') === $financeChildKey) {
					return self::collectKeysFromNodes($sub['children'] ?? []);
				}
			}
		}
		return [];
	}

	/**
	 * @param array<int, array<string, mixed>> $nodes
	 * @return string[]
	 */
	private static function collectKeysFromNodes(array $nodes)
	{
		$keys = [];
		foreach ($nodes as $node) {
			if (!empty($node['keys']) && is_array($node['keys'])) {
				foreach ($node['keys'] as $k) {
					if (is_string($k) && $k !== '') {
						$keys[] = $k;
					}
				}
			} elseif (!empty($node['key'])) {
				$k = (string) $node['key'];
				if (strpos($k, '::') === false
					&& strpos($k, 'budget_link_') !== 0
					&& strpos($k, 'marks_group_') !== 0) {
					$keys[] = $k;
				}
			}
			if (!empty($node['children']) && is_array($node['children'])) {
				$keys = array_merge($keys, self::collectKeysFromNodes($node['children']));
			}
		}
		return array_values(array_unique($keys));
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function buildFinanceChildren($financeBlock)
	{
		$children = [];

		$feesBlock = self::extractGroupBlock($financeBlock, 'fees');
		if ($feesBlock !== '') {
			$feeItems = self::buildMenuItemNodes($feesBlock);
			if ($feeItems) {
				$children[] = [
					'key' => 'fees',
					'label' => self::subgroupLabel($feesBlock, 'Fees Management', 'app.feesManagement'),
					'children' => $feeItems,
				];
			}
		}

		$budgetBlock = self::extractGroupBlock($financeBlock, 'budget_cashflow');
		if ($budgetBlock !== '') {
			$budgetItems = self::extractBudgetSidebarLinks($budgetBlock);
			if ($budgetItems) {
				$children[] = [
					'key' => 'budget_cashflow',
					'label' => self::subgroupLabel($budgetBlock, 'Budget & Cash Flow'),
					'children' => $budgetItems,
				];
			}
		}

		return $children;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function buildMarksChildren($block)
	{
		$children = [];
		$used = [];

		$offset = 0;
		while (preg_match('/<\?php if \((menu_clearance_allowed\([^)]+\)(?:\s*\|\|\s*menu_clearance_allowed\([^)]+\))+)\)\s*\{\s*\?>/s', $block, $m, PREG_OFFSET_CAPTURE, $offset)) {
			$pos = $m[0][1];
			$subBlock = self::extractPhpIfBlock($block, $pos + 10);
			$subChildren = self::buildMenuItemNodes($subBlock);
			foreach ($subChildren as $child) {
				$perm = !empty($child['keys'][0]) ? $child['keys'][0] : ($child['key'] ?? '');
				if ($perm !== '') {
					$used[$perm] = true;
				}
			}
			if ($subChildren) {
				$label = 'Submenu';
				if (preg_match('/<a[^>]*>.*?lang\("app\.([^"]+)"\)/s', $subBlock, $lm)) {
					if (function_exists('lang')) {
						$t = lang('app.' . $lm[1]);
						if (is_string($t) && trim($t) !== '' && strpos($t, 'app.') === false) {
							$label = trim($t);
						}
					}
					if ($label === 'Submenu') {
						$label = self::humanizeLangKey($lm[1]);
					}
				}
				$children[] = [
					'key' => 'marks_group_' . md5(implode(',', array_column($subChildren, 'key'))),
					'label' => $label,
					'children' => $subChildren,
				];
			}
			$offset = $pos + 1;
		}

		foreach (self::buildMenuItemNodes($block) as $node) {
			$perm = !empty($node['keys'][0]) ? $node['keys'][0] : ($node['key'] ?? '');
			if ($perm === 'marks' || ($perm !== '' && !empty($used[$perm]))) {
				continue;
			}
			$children[] = $node;
		}

		return $children;
	}

	/**
	 * Sidebar budget links (grouped via budget_menu_any), not raw permission keys.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function extractBudgetSidebarLinks($block)
	{
		$items = [];

		if (preg_match("/(?<!false && )menu_clearance_allowed\('budget_dashboard'\)/", $block)) {
			$items[] = [
				'key' => 'budget_dashboard',
				'label' => self::labelForKey($block, 'budget_dashboard', 'Dashboard'),
			];
		}

		$offset = 0;
		while (preg_match('/budget_menu_any\(\[([^\]]+)\]\)/', $block, $m, PREG_OFFSET_CAPTURE, $offset)) {
			$pos = $m[0][1];
			$inner = $m[1][0];
			$keys = [];
			if (preg_match_all("/'([^']+)'/", $inner, $km)) {
				$keys = $km[1];
			}
			$label = 'Budget menu';
			$after = substr($block, $pos, 500);
			if (preg_match('/<a[^>]*>(.*?)<\/a>/s', $after, $am)) {
				$label = self::sanitizeLabel($am[1]);
			}
			if ($keys) {
				$items[] = [
					'key' => 'budget_link_' . md5(implode(',', $keys)),
					'label' => $label !== '' ? $label : self::humanizeKey($keys[0]),
					'keys' => array_values(array_unique($keys)),
				];
			}
			$offset = $pos + 1;
		}

		return $items;
	}

	private static function subgroupLabel($block, $fallback, $langKey = null)
	{
		if ($langKey !== null && function_exists('lang')) {
			$t = lang($langKey);
			if (is_string($t) && trim($t) !== '' && strpos($t, 'app.') === false) {
				return trim($t);
			}
		}
		if (preg_match('/<a[^>]*>(.*?)<\/a>/s', $block, $m)) {
			$label = self::sanitizeLabel($m[1]);
			if ($label !== '') {
				return $label;
			}
		}
		return $fallback;
	}

	private static function hasGroupKey(array $tree, $key)
	{
		foreach ($tree as $g) {
			if (($g['key'] ?? '') === $key) {
				return true;
			}
		}
		return false;
	}

	private static function readMainContent()
	{
		$path = APPPATH . 'Views/main.php';
		return is_file($path) ? (string) file_get_contents($path) : '';
	}

	private static function extractSidebarContent()
	{
		$content = self::readMainContent();
		if (!preg_match('/<ul class="vertical-nav-menu metismenu">(.*?)<\/ul>\s*<\/div>\s*<div class="ps__rail-x"/s', $content, $m)) {
			return '';
		}
		return $m[1];
	}

	private static function resolveLabel(array $def)
	{
		if (!empty($def['lang']) && function_exists('lang')) {
			$translated = lang($def['lang']);
			if (is_string($translated) && $translated !== '' && strpos($translated, 'app.') === false) {
				return $translated;
			}
		}
		return $def['label'] ?? $def['key'];
	}

	private static function isKeyVisibleInSidebar($content, $key)
	{
		if (preg_match("/menu_clearance_group_visible\('".preg_quote($key, '/')."'\)/", $content)) {
			return true;
		}
		if (preg_match("/(?<!false && )menu_clearance_allowed\('".preg_quote($key, '/')."'\)/", $content)) {
			return true;
		}
		return false;
	}

	private static function extractCombinedBlock($content, array $keys)
	{
		foreach ($keys as $key) {
			$block = self::extractGroupBlock($content, $key);
			if ($block !== '') {
				return $block;
			}
		}
		return '';
	}

	private static function extractGroupBlock($content, $key)
	{
		$needles = ["menu_clearance_group_visible('".$key."')"];
		if ($key === 'asset_management') {
			array_unshift(
				$needles,
				"menu_clearance_group_visible('asset_management') || menu_clearance_group_visible('library')"
			);
		}

		$best = '';
		$bestCount = -1;
		foreach ($needles as $needle) {
			$offset = 0;
			while (($pos = strpos($content, $needle, $offset)) !== false) {
				$block = self::extractPhpIfBlock($content, $pos);
				$count = count(self::extractAllowedKeys($block));
				if ($count > $bestCount) {
					$best = $block;
					$bestCount = $count;
				}
				$offset = $pos + 1;
			}
		}

		return $best;
	}

	private static function extractPhpIfBlock($content, $startPos)
	{
		$before = substr($content, 0, max(0, (int) $startPos));
		$open = strrpos($before, '<?php if');
		if ($open === false) {
			return '';
		}

		$depth = 0;
		$len = strlen($content);
		$i = $open;
		while ($i < $len) {
			if (substr($content, $i, 5) !== '<?php') {
				$i++;
				continue;
			}

			$tag = substr($content, $i, 40);
			if (preg_match('/^<\?php\s+if\b/', $tag)) {
				$depth++;
				$i += 5;
				continue;
			}

			if (preg_match('/^<\?php\s+\}/', $tag)) {
				$rest = ltrim(substr($content, $i + 5, 30));
				if (strpos($rest, 'else') !== 0) {
					$depth--;
					if ($depth === 0) {
						$end = strpos($content, '?>', $i);
						if ($end === false) {
							return substr($content, $open);
						}
						return substr($content, $open, $end + 2 - $open);
					}
				}
				$i += 5;
				continue;
			}

			$i++;
		}

		return '';
	}

	/**
	 * Parse every visible sidebar link inside clearance blocks (matches dashboard labels).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function buildMenuItemNodes($block)
	{
		$nodes = [];
		$seenUiKeys = [];
		$offset = 0;

		while (preg_match('/(?<!false && )menu_clearance_allowed\(\'([^\']+)\'\)|material_check_menu_visible\(\)[^)]*menu_clearance_allowed\(\'([^\']+)\'\)/', $block, $m, PREG_OFFSET_CAPTURE, $offset)) {
			$permKey = $m[1][0] !== '' ? $m[1][0] : $m[2][0];
			$pos = $m[0][1];
			$itemBlock = self::extractPhpIfBlock($block, $pos + 10);
			if ($itemBlock === '') {
				$offset = $pos + 1;
				continue;
			}

			$liMatches = [];
			if (preg_match_all('/<li>\s*<a\b([^>]*)>(.*?)<\/a>\s*<\/li>/s', $itemBlock, $liMatches, PREG_SET_ORDER)) {
				$multi = count($liMatches) > 1;
				foreach ($liMatches as $li) {
					$label = self::labelFromAnchorInner($li[2]);
					if ($label === '') {
						continue;
					}
					$href = '';
					if (preg_match('/href="([^"]*)"/', $li[1], $hm)) {
						$href = $hm[1];
					}
					$node = self::makeMenuItemNode($permKey, $label, $href, $multi, $seenUiKeys);
					if ($node !== null) {
						$nodes[] = $node;
					}
				}
			} else {
				$node = self::makeMenuItemNode($permKey, self::labelForKey($block, $permKey), '', false, $seenUiKeys);
				if ($node !== null) {
					$nodes[] = $node;
				}
			}

			$offset = $pos + 1;
		}

		return self::dedupeMenuItemNodes($nodes);
	}

	/**
	 * @param array<int, array<string, mixed>> $nodes
	 * @return array<int, array<string, mixed>>
	 */
	private static function dedupeMenuItemNodes(array $nodes)
	{
		$out = [];
		$seen = [];
		foreach ($nodes as $node) {
			$perm = !empty($node['keys'][0]) ? $node['keys'][0] : ($node['key'] ?? '');
			$sig = $perm . '|' . ($node['label'] ?? '');
			if (isset($seen[$sig])) {
				continue;
			}
			$seen[$sig] = true;
			$out[] = $node;
		}
		return $out;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function makeMenuItemNode($permKey, $label, $href, $forceVirtual, array &$seenUiKeys)
	{
		$label = self::sanitizeLabel($label);
		if ($label === '') {
			return null;
		}

		if ($forceVirtual) {
			$uiKey = self::uniqueUiKey($permKey, $label, $href, $seenUiKeys);
			$seenUiKeys[] = $uiKey;
			return [
				'key' => $uiKey,
				'label' => $label,
				'keys' => [$permKey],
			];
		}

		if (!in_array($permKey, $seenUiKeys, true)) {
			$seenUiKeys[] = $permKey;
			return ['key' => $permKey, 'label' => $label];
		}

		$uiKey = self::uniqueUiKey($permKey, $label, $href, $seenUiKeys);
		$seenUiKeys[] = $uiKey;
		return [
			'key' => $uiKey,
			'label' => $label,
			'keys' => [$permKey],
		];
	}

	private static function uniqueUiKey($permKey, $label, $href, array $seen)
	{
		$slug = preg_replace('/[^a-z0-9]+/i', '_', strtolower($label));
		$slug = trim((string) $slug, '_');
		if ($slug === '' && $href !== '') {
			$slug = preg_replace('/[^a-z0-9]+/i', '_', strtolower(basename(parse_url($href, PHP_URL_PATH) ?: $href)));
			$slug = trim((string) $slug, '_');
		}
		if ($slug === '') {
			$slug = 'item';
		}

		$candidate = $permKey . '::' . $slug;
		$i = 2;
		while (in_array($candidate, $seen, true)) {
			$candidate = $permKey . '::' . $slug . '_' . $i;
			$i++;
		}
		return $candidate;
	}

	private static function labelFromAnchorInner($inner)
	{
		if (preg_match_all('/lang\("app\.([^"]+)"\)/', $inner, $allLang) && !empty($allLang[1])) {
			$langSlug = $allLang[1][count($allLang[1]) - 1];
			if (function_exists('lang')) {
				$t = lang('app.' . $langSlug);
				if (is_string($t) && trim($t) !== '' && strpos($t, 'app.') === false) {
					return trim($t);
				}
			}
			return self::humanizeLangKey($langSlug);
		}

		return self::sanitizeLabel($inner);
	}

	/**
	 * @return string[]
	 */
	private static function extractAllowedKeys($block)
	{
		$keys = [];
		if (preg_match_all("/(?<!false && )menu_clearance_allowed\('([^']+)'\)/", $block, $m)) {
			foreach ($m[1] as $k) {
				$keys[] = $k;
			}
		}
		if (preg_match_all("/material_check_menu_visible\(\)[^)]*menu_clearance_allowed\('([^']+)'\)/", $block, $m2)) {
			foreach ($m2[1] as $k) {
				$keys[] = $k;
			}
		}
		return array_values(array_unique($keys));
	}

	/**
	 * @return string[]
	 */
	private static function extractBudgetKeys($block)
	{
		$keys = [];
		if (preg_match_all("/budget_menu_any\(\[([^\]]+)\]\)/", $block, $m)) {
			foreach ($m[1] as $inner) {
				if (preg_match_all("/'([^']+)'/", $inner, $km)) {
					foreach ($km[1] as $k) {
						$keys[] = $k;
					}
				}
			}
		}
		if (preg_match_all("/(?<!false && )menu_clearance_allowed\('(budget_[^']+)'\)/", $block, $m2)) {
			foreach ($m2[1] as $k) {
				$keys[] = $k;
			}
		}
		return array_values(array_unique($keys));
	}

	private static function legacyLabelMap()
	{
		static $map = null;
		if ($map !== null) {
			return $map;
		}

		$map = [];
		foreach (\Config\MenuClearance::staticTree() as $group) {
			$map[$group['key']] = $group['label'];
			foreach ($group['children'] ?? [] as $child) {
				$map[$child['key']] = $child['label'];
			}
		}
		return $map;
	}

	private static function humanizeKey($key)
	{
		$part = strpos($key, '/') !== false ? substr($key, strrpos($key, '/') + 1) : $key;
		$part = str_replace(['-', '_'], ' ', $part);
		return ucwords(trim($part));
	}

	private static function humanizeLangKey($key)
	{
		$spaced = preg_replace('/([a-z])([A-Z])/', '$1 $2', (string) $key);
		return ucwords(str_replace('_', ' ', $spaced));
	}

	private static function sanitizeLabel($label)
	{
		$label = trim(html_entity_decode(strip_tags((string) $label)));
		$label = preg_replace('/^[">=\s\?]+/', '', $label);
		return trim($label);
	}

	private static function extractMenuItemBlock($block, $key)
	{
		$quoted = preg_quote($key, '/');
		if (!preg_match("/menu_clearance_allowed\\('".$quoted."'\\)/", $block, $m, PREG_OFFSET_CAPTURE)) {
			return '';
		}
		$sub = self::extractPhpIfBlock($block, $m[0][1]);
		return $sub !== '' ? $sub : substr($block, $m[0][1], 400);
	}

	private static function labelForKey($block, $key, $fallback = null)
	{
		$cacheKey = md5($key . substr($block, 0, 80));
		if (isset(self::$labelCache[$cacheKey])) {
			return self::$labelCache[$cacheKey];
		}

		$label = '';
		$itemBlock = self::extractMenuItemBlock($block, $key);

		$langSlug = null;
		if ($itemBlock !== '' && preg_match_all('/lang\("app\.([^"]+)"\)/', $itemBlock, $allLang) && !empty($allLang[1])) {
			$langParts = $allLang[1];
			$langSlug = $langParts[count($langParts) - 1];
			if (function_exists('lang')) {
				$t = lang('app.' . $langSlug);
				if (is_string($t) && trim($t) !== '' && strpos($t, 'app.') === false) {
					$label = trim($t);
				}
			}
		}

		if ($label === '') {
			$legacy = self::legacyLabelMap();
			if (!empty($legacy[$key])) {
				$label = $legacy[$key];
			} elseif ($langSlug !== null) {
				$label = self::humanizeLangKey($langSlug);
			}
		}

		if ($label === '' && $itemBlock !== '' && preg_match('/<\/i>\s*([^<]+?)\s*<\/a>/s', $itemBlock, $m)) {
			$label = self::sanitizeLabel($m[1]);
		}

		if ($label === '' && $itemBlock !== '' && preg_match('/<a[^>]*>(.*?)<\/a>/s', $itemBlock, $m)) {
			$label = self::sanitizeLabel($m[1]);
		}

		if ($label === '') {
			$legacy = self::legacyLabelMap();
			if (!empty($legacy[$key])) {
				$label = $legacy[$key];
			}
		}

		if ($label === '' && strpos($key, 'budget_') === 0) {
			$label = ucwords(str_replace('_', ' ', substr($key, 7)));
		}

		if ($label === '') {
			$label = $fallback ?: self::humanizeKey($key);
		}

		self::$labelCache[$cacheKey] = $label;
		return $label;
	}
}
