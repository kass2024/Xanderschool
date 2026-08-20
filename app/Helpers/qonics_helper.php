<?php
if (!function_exists('is_allowed')) {
	function is_allowed(...$allowed)
	{
		$id = $_SESSION["soma_post"];
		if ($allowed != null && is_array($allowed) && in_array($id, $allowed)) {
			return true;
		}
		if (count($allowed) == 0) {
			return true;
		}
		return false;
	}
}
if (!function_exists('is_blocked')) {
	function is_blocked(...$blocked)
	{
		$id = $_SESSION["soma_post"];
		if (in_array($id, $blocked)) {
			return true;
		} else {
			return false;//not in blocked list
		}
	}
}

/**
 * Whether the current staff post may see a school-dashboard menu key.
 * Uses Level clearance (DB override or legacy defaults). Full-access posts always true.
 *
 * @param string $menuKey
 * @return bool
 */
if (!function_exists('menu_clearance_allowed')) {
	function menu_clearance_allowed($menuKey)
	{
		static $cacheKeys = null;
		static $cachePost = null;
		static $cacheSchool = null;

		$menuKey = (string) $menuKey;
		if ($menuKey === 'dashboard' || $menuKey === 'profile') {
			return true;
		}

		$postId = isset($_SESSION['soma_post']) ? (int) $_SESSION['soma_post'] : 0;
		$schoolId = isset($_SESSION['soma_school_id']) ? (int) $_SESSION['soma_school_id'] : 0;
		if ($cacheKeys === null || $cachePost !== $postId || $cacheSchool !== $schoolId) {
			$cachePost = $postId;
			$cacheSchool = $schoolId;
			try {
				$mdl = new \App\Models\PostMenuClearanceModel();
				$cacheKeys = $mdl->allowedKeysForPost($postId, $schoolId);
			} catch (\Throwable $e) {
				$cacheKeys = \Config\MenuClearance::applyChildSchoolFinancePolicy(
					\Config\MenuClearance::defaultKeysForPost($postId),
					$postId,
					$schoolId
				);
			}
			if (!is_array($cacheKeys)) {
				$cacheKeys = [];
			}
		}

		return in_array($menuKey, $cacheKeys, true);
	}
}

if (!function_exists('budget_menu_any')) {
	/** True if the current post may see any of the budget sidebar menu keys. */
	function budget_menu_any(array $menuKeys)
	{
		foreach ($menuKeys as $key) {
			if (menu_clearance_allowed((string) $key)) {
				return true;
			}
		}
		return false;
	}
}

if (!function_exists('school_hierarchy_home_id')) {
	/** Staff login school (unchanged when viewing a child school). */
	function school_hierarchy_home_id()
	{
		$home = isset($_SESSION['soma_home_school_id']) ? (int) $_SESSION['soma_home_school_id'] : 0;
		if ($home > 0) {
			return $home;
		}
		return isset($_SESSION['soma_school_id']) ? (int) $_SESSION['soma_school_id'] : 0;
	}
}

if (!function_exists('school_hierarchy_can_switch')) {
	function school_hierarchy_can_switch()
	{
		$homeId = school_hierarchy_home_id();
		$postId = isset($_SESSION['soma_post']) ? (int) $_SESSION['soma_post'] : 0;
		if ($homeId < 1 || $postId < 1) {
			return false;
		}
		try {
			return (new \App\Services\SchoolHierarchyService())->canAccessChildSchools($homeId, $postId);
		} catch (\Throwable $e) {
			return false;
		}
	}
}

if (!function_exists('school_hierarchy_accessible_schools')) {
	/** @return array<int, array<string, mixed>> */
	function school_hierarchy_accessible_schools()
	{
		if (!school_hierarchy_can_switch()) {
			return [];
		}
		try {
			$h = new \App\Services\SchoolHierarchyService();
			return $h->accessibleSchools(school_hierarchy_home_id(), (int) ($_SESSION['soma_post'] ?? 0));
		} catch (\Throwable $e) {
			return [];
		}
	}
}

if (!function_exists('budget_can_manage_line_structure')) {
	function budget_can_manage_line_structure()
	{
		$schoolId = isset($_SESSION['soma_school_id']) ? (int) $_SESSION['soma_school_id'] : 0;
		if ($schoolId < 1) {
			return true;
		}
		try {
			return (new \App\Services\SchoolHierarchyService())->canManageBudgetLineStructure($schoolId);
		} catch (\Throwable $e) {
			return true;
		}
	}
}

if (!function_exists('budget_permission_allowed')) {
	function budget_permission_allowed($permKey)
	{
		static $svc = null;
		$staffId = isset($_SESSION['soma_id']) ? (int) $_SESSION['soma_id'] : 0;
		$postId = isset($_SESSION['soma_post']) ? (int) $_SESSION['soma_post'] : 0;
		if ($staffId <= 0) {
			return false;
		}
		if ($svc === null) {
			$svc = new \App\Services\Budget\BudgetPermissionService();
		}
		return $svc->can($staffId, $postId, $permKey);
	}
}

/**
 * Show a parent menu if the parent key or any child key is allowed.
 *
 * @param string $parentKey
 * @return bool
 */
if (!function_exists('menu_clearance_group_visible')) {
	function menu_clearance_group_visible($parentKey)
	{
		$parentKey = (string) $parentKey;
		if ($parentKey === 'finance') {
			return menu_clearance_group_visible('fees') || menu_clearance_group_visible('budget_cashflow');
		}
		if (menu_clearance_allowed($parentKey)) {
			return true;
		}
		foreach (\Config\MenuClearance::childKeys($parentKey) as $child) {
			if (menu_clearance_allowed($child)) {
				return true;
			}
			// Finance sub-groups (fees, budget_cashflow) are also parent keys.
			if (in_array($child, ['fees', 'budget_cashflow'], true) && menu_clearance_group_visible($child)) {
				return true;
			}
		}
		return false;
	}
}
if (!function_exists('_is_allowed')) {
	function _is_allowed($allowed)
	{
		$id = $_SESSION["soma_post"];
		if ($allowed != null && is_array($allowed) && in_array($id, $allowed)) {
			return true;
		}
		if (count($allowed) == 0) {
			return true;
		}
		return false;
	}
}

if (!function_exists('material_check_full_access')) {
	function material_check_full_access()
	{
		$postId = (int) ($_SESSION['soma_post'] ?? 0);
		if (in_array($postId, \Config\StudentMaterialPermissions::FULL_ACCESS_POST_IDS, true)) {
			return true;
		}
		$title = strtolower(trim((string) ($_SESSION['soma_post_title'] ?? '')));
		foreach (\Config\StudentMaterialPermissions::FULL_ACCESS_TITLE_KEYWORDS as $kw) {
			if ($title !== '' && strpos($title, $kw) !== false) {
				return true;
			}
		}
		return false;
	}
}

if (!function_exists('material_check_mentor_class_ids')) {
	function material_check_mentor_class_ids($schoolId = null)
	{
		static $cache = [];
		$schoolId = (int) ($schoolId ?? ($_SESSION['soma_school_id'] ?? 0));
		if (isset($cache[$schoolId])) {
			return $cache[$schoolId];
		}
		$staffId = (int) ($_SESSION['soma_id'] ?? 0);
		if ($staffId <= 0 || $schoolId <= 0) {
			$cache[$schoolId] = [];
			return [];
		}
		$db = \Config\Database::connect();
		$rows = $db->table('classes')->select('id')
			->where('school_id', $schoolId)
			->where('mentor', $staffId)
			->get()->getResultArray();
		$cache[$schoolId] = array_values(array_map('intval', array_column($rows, 'id')));
		return $cache[$schoolId];
	}
}

if (!function_exists('material_check_menu_visible')) {
	function material_check_menu_visible()
	{
		if (material_check_full_access()) {
			return true;
		}
		return count(material_check_mentor_class_ids()) > 0;
	}
}

if (!function_exists('material_check_can_access_class')) {
	function material_check_can_access_class($classId, $schoolId = null)
	{
		$classId = (int) $classId;
		if ($classId <= 0) {
			return false;
		}
		if (material_check_full_access()) {
			return true;
		}
		return in_array($classId, material_check_mentor_class_ids($schoolId), true);
	}
}

if (!function_exists('cmp')) {
	function cmp($a, $b)
	{
		if ($a['total'] == $b['total']) {
			return 0;
		}
		return ($a['total'] < $b['total']) ? 1 : -1;
	}
}
if (!function_exists('get_months')) {
	function get_months()
	{
		$m = array("January", "February", "March", "April", "May", "June",
			"July", "August", "September", "October", "November", "December");
		return $m;
	}
}
/*if (!function_exists('get_total_days')) {
	function get_total_days($date1,$date2,$weekend=false)
	{
		$date2= ($date2=="now" || $date2=="0000-00-00")?date("Y-m-d"):$date2;
		$start= new DateTime($date1);
		$end= new DateTime($date2);
		$end->modify('+1 day');
		$days = $end->diff($start)->days;
		$period = new DatePeriod($start, new DateInterval('P1D'), $end);

// best stored as array, so you can add more than one
		$holidays = array();//to be done later
		foreach($period as $dt) {
			$curr = $dt->format('D');

			// substract if Saturday or Sunday
			if ($curr == 'Sat' || $curr == 'Sun') {
				$days--;
			}

			// (optional) for the updated question
			elseif (in_array($dt->format('Y-m-d'), $holidays)) {
				$days--;
			}
		}
		return $days;
	}
}
*/

if (!function_exists('get_total_days')) {
	function get_total_days($date1, $date2, $shifts)
	{
		$date2 = ($date2 == "now" || $date2 == "0000-00-00") ? date("Y-m-d") : $date2;
		$start = new DateTime($date1);
		$today = strtotime(date("Y-m-d"));
		$date22 = strtotime($date2);
		$date2 = $date22 > $today ? date("Y-m-d") : $date2;
		$end = new DateTime($date2);
		$end->modify('+1 day');
//		$days = $end->diff($start)->days;
		$days = 0;
		$period = new DatePeriod($start, new DateInterval('P1D'), $end);

// best stored as array, so you can add more than one
		$holidays = array();//to be done later
		foreach ($period as $dt) {
			$curr = $dt->format('D');

			// substract if Saturday or Sunday
//			if ($curr == 'Sat' || $curr == 'Sun') {
//				$days--;
//			}
//			echo $dt->format('Y-m-d')."<br>";continue;
			foreach ($shifts as $shift) {
				$opp = explode(" ", $shift);
				if (strtolower($curr) == strtolower(days_mini($opp[0]))) {
					//working days
					//check if it is leave
					if (!in_array($dt->format('Y-m-d'), $holidays)) {
						$days++;
					}
				}
			}
		}
//		die();
		$days = $days == 0 ? 1 : $days;
		return $days;
	}
}
if (!function_exists('get_days_difference')) {
	function get_days_difference($date1, $date2)
	{
		$date2 = ($date2 == "now" || $date2 == "0000-00-00") ? date("Y-m-d") : $date2;
		$start = new DateTime($date1);
		$end = new DateTime($date2);
		$end->modify('+1 day');
		$days = $end->diff($start)->days;
		$period = new DatePeriod($start, new DateInterval('P1D'), $end);

		foreach ($period as $dt) {
			$curr = $dt->format('D');
			// substract if Saturday or Sunday
			if ($curr == 'Sat' || $curr == 'Sun') {
				$days--;
			}
		}
		$days = $days == 0 ? 1 : $days;
		return $days;
	}
}
if (!function_exists('is_in_array')) {
	function is_in_array($needle, $arr): array
	{
		foreach ($arr as $arr1) {
			if (in_array($needle, $arr1)) {
				return $arr1;
			}
		}
		return [];
	}
}
if (!function_exists('generateAbsentDays')) {
	function generateAbsentDays($date1, $date2, $shifts, $holidays, &$increment, $startOn = 0): string
	{
		$html = '';
		$diff = ($date2 - $date1) / 86400;
		for ($bb = $startOn; ($startOn == 0 ? $bb < $diff : $bb <= $diff); $bb++) {
			foreach ($shifts as $shift) {
				$opp = explode(" ", $shift);
				if (strtolower(date("D", $date1 + $bb * 86400)) == strtolower(days_mini($opp[0]))) {
					$value = "Absent";
					$dt = is_in_array(date("Y-m-d", $date1 + $bb * 86400), $holidays);
					$style = "background-color: #ce1313;color:white;font-weight:bold;";
					if (count($dt) > 0) {
						$value = "Holiday #" . $dt['title'];
						$style = "background-color: grey;color:white;font-weight:bold;";
					}
					$html .= "<tr style='$style'>";
					$html .= "<td style='text-align: right'>$increment</td>";
					$html .= "<td class='td_date'>" . date('F d, Y', ($date1 + $bb * 86400)) . "</td>";
					$html .= "<td class='td_date' colspan='5'>$value</td>";
					$html .= "</tr>";
					$increment++;
					break;
				}
			}
		}
		return $html;
	}
}
if (!function_exists('days')) {
	function days($weekday)
	{
		$day = "Monday";
		switch ($weekday) {
			case 0:
				$day = "monday";
				break;
			case 1:
				$day = "tuesday";
				break;
			case 2:
				$day = "wednesday";
				break;
			case 3:
				$day = "thursday";
				break;
			case 4:
				$day = "friday";
				break;
			case 5:
				$day = "saturday";
				break;
			case 6:
				$day = "sunday";
				break;
		}
		return $day;
	}
}
if (!function_exists('days_mini')) {
	function days_mini($weekday)
	{
		$day = "Mon";
		switch ($weekday) {
			case 0:
				$day = "Mon";
				break;
			case 1:
				$day = "Tue";
				break;
			case 2:
				$day = "Wed";
				break;
			case 3:
				$day = "Thu";
				break;
			case 4:
				$day = "Fri";
				break;
			case 5:
				$day = "Sat";
				break;
			case 6:
				$day = "Sun";
				break;
		}
		return $day;
	}
}
if (!function_exists('hours')) {
	function hours($hour, $type = 0)//0:12,1: 24 hours
	{
		$data = explode(".", $hour);
		$hh = sprintf("%02d", $data[0]);
		$mm = $data[1] == "0" ? "00" : "30";
		if ($type == 1) {
			$hour = $hh . ":" . $mm;
			return $hour;
		}
		if ($hour == '0.0') {
			$hour = "12:00 am (midnight next day)";
		} else if ($hour == '12.0') {
			$hour = "12:00 pm (noon)";
		} else if ($hh == '0') {
			$hour = "12:" . $mm . " am";
		} else if ($hh == '12') {
			$hour = "12:" . $mm . " pm";
		} else if ($hh > 12) {
			$hour = ($hh - 12) . ":" . $mm . " pm";
		} else {
			$hour = $hh . ":" . $mm . " am";
		}
		return $hour;
	}
}
if (!function_exists('termToStr')) {
	function termToStr($term)
	{
		$text = "";
		switch ($term) {
			case 1:
				$text = "term1";
				break;
			case 2:
				$text = "term2";
				break;
			case 3:
				$text = "term3";
				break;
		}
		return $text;
	}
}
if (!function_exists('paymentModeToString')) {
	function paymentModeToString($mode)
	{
		switch ($mode) {
			case '1':
				return lang("app.bankSlip");
			case '2':
				return lang("app.cash");
			case '3':
				return lang("app.cheque");
			case '4':
				return lang("app.momo");
			case '5':
				return lang("app.airtelMoney");
			default:
				return $mode;
		}
	}
}
if (!function_exists('grade_color')) {
	function grade_color($grades, $marks, $school_id = null, &$keyword = null)
	{
		if (!is_null($school_id) && in_array($school_id, [54])) {
			$keyword = $grades['color_title'];
			return "#ffffff";
		}
		$marks = (int)$marks;
		foreach ($grades as $grade) {
			if ($grade['min_point'] <= $marks && $grade['max_point'] >= $marks) {
				return $grade['color'];
			}
		}
		return "#ffffff";
	}
}
if (!function_exists("get_first_letters")) {
	function get_first_letters($string)
	{
		$string = preg_replace("/\s+/", " ", trim($string));
		// var_dump($string);
		return implode('',
			array_map(
				function ($part) {
					// var_dump($part);
					return strtoupper($part['0']);
				}, explode(' ', $string))
		);
	}
}
if (!function_exists('grade_letter')) {
	function grade_letter(&$grades, $marks)
	{
		$marks = !is_null($marks) ? (int)$marks : null;
		// var_dump($grades);
		// die($marks);
		foreach ($grades as $grade) {
			if ($grade['min_point'] <= $marks && $grade['max_point'] >= $marks) {
				return get_first_letters($grade['color_title']); //$grade['color'];
			}
		}
		return "";
	}
}
if (!function_exists('grade_name')) {
	function grade_name(&$grades, $marks)
	{
		$marks = !is_null($marks) ? (int)$marks : null;
		// var_dump($grades);
		// die($marks);
		foreach ($grades as $grade) {
			if ($grade['min_point'] <= $marks && $grade['max_point'] >= $marks) {
				return $grade['color_title']; //$grade['color'];
			}
		}
		return "";
	}
}
if (!function_exists('get_years')) {
	function get_years($school_start_year)
	{
		return $range = range($school_start_year, date('Y'));
	}
}

if (!function_exists('chiffreRomain')) {
	function chiffreRomain($number)
	{
		switch ($number) {
			case '1':
				return "I";
			case '2':
				return "II";
			case '3':
				return "III";
			case '4':
				return "IV";
			case '5':
				return "V";
			case '6':
				return "VI";
			case '7':
				return "VII";
			case '8':
				return "VIII";
			case '9':
				return "IX";
			case '10':
				return "X";
			default:
				return $number;
		}
	}
}
if (!function_exists('marksTotal')) {
	function marksTotal($data)
	{
		$tot = 0;
		try {
			foreach ($data as $dt) {
				$tot += $dt;
			}
		} catch (\Exception $e) {
			// here the error comes.
			$tot = null;
		}
		return $tot;
	}
}
if (!function_exists('sortTermsTotal')) {
	function sortTermsTotal($data)
	{
		$total = array_column($data, 'total');
		array_multisort($total, SORT_DESC, $data);
		return array_column($data, 'student', 'key');
	}
}
if (!function_exists('extractDisciplineMarks')) {
	function extractDisciplineMarks($data, $term)
	{
		$disciplineMarks = explode(",", $data);
		foreach ($disciplineMarks as $dt) {
			$dd = explode(":", $dt);
			if (count($dd) == 2) {
				if ($term == $dd[1]) {
					return $dd[0];
				}
			}
		}
		return 0;
	}
}
if (!function_exists('getDeliberationVerdict')) {
	function getDeliberationVerdict($data, $marks, $discipline, $courses = '')
	{
		foreach ($data as $dt) {
			$dc = explode(",", $dt['conditions']);
			$cond = true;
			foreach ($dc as $dc1) {
				$dc1 = explode(":", $dc1);
				if ($dc1[2] == 0) {
					//marks
					if (!renderComparisonSign($dc1[0], $marks, $dc1[1])) {
						$cond = false;
					}
				}
				if ($dc1[2] == 1 && $cond) {
					//discipline
					if (!renderComparisonSign($dc1[0], $discipline, $dc1[1])) {
						$cond = false;
					}
				}
			}
//			$df = explode(",",$dt['courses']);
//			foreach ($df as $df1){
//				$df1 = explode(":",$df1);
//				if ($dc1[0] == 0){
//					//marks
//					if (!renderComparisonSign($dc1[0],$marks, $dc1[1])){
//						$cond = false;
//					}
//				}if ($dc1[2] == 1 && $cond){
//					//discipline
//					if (!renderComparisonSign($dc1[0],$discipline, $dc1[1])){
//						$cond = false;
//					}
//				}
//			}
			if ($cond) {
				return ['id' => $dt['id'], 'verdict' => $dt['verdict']];
			}
		}
		return null;
	}
}
if (!function_exists('renderComparisonSign')) {
	function renderComparisonSign($sign, $data1, $data2)
	{
		switch ($sign) {
			case '>':
				return $data1 > $data2;
			case '>=':
				return $data1 >= $data2;
			case '<=':
				return $data1 <= $data2;
			case '<':
				return $data1 < $data2;
			case '=':
				return $data1 == $data2;
			case '!=':
				return $data1 != $data2;
		}
	}
}
if (!function_exists('verdictText')) {
	function verdictText($type)
	{
		switch ($type) {
			case '1':
				return lang("app.promoted");
			case '2':
				return lang("app.repeat");
			case '3':
				return lang("app.secondSitting");
			case '4':
				return lang("app.dismissed");
			case '5':
				return lang("app.reoriented");
			default:
				return $type;
		}
	}
}
if (!function_exists('decisionTypeStr')) {
	function decisionTypeStr($type)
	{
		switch ($type) {
			case '1':
				return lang("app.automaticDecision");
			case '0':
				return lang("app.manualDecision");
			default:
				return $type;
		}
	}
}

if (!function_exists('catTypeStr')) {
	function catTypeStr($type)
	{
		switch ($type) {
			case 'Q1':
				return lang("app.quiz1");
			case 'Q2':
				return lang("app.quiz2");
			case 'Q3':
				return lang("app.quiz3");
			case 'Q4':
				return lang("app.quiz4");
			case 'Q5':
				return lang("app.quiz5");
			case 'T1':
				return lang("app.test1");
			case 'T2':
				return lang("app.test2");
			case 'T3':
				return lang("app.test3");
			case 'T4':
				return lang("app.test4");
			case 'T5':
				return lang("app.test5");
			case 'H1':
				return lang("app.homework1");
			case 'H2':
				return lang("app.homework2");
			case 'H3':
				return lang("app.homework3");
			case 'H4':
				return lang("app.homework4");
			case 'H5':
				return lang("app.homework5");
			default:
				return $type;
		}
	}
}

if (!function_exists('symbolsText')) {
	function symbolsText($type)
	{
		switch ($type) {
			case '>':
				return lang("app.greaterThan");
			case '<':
				return lang("app.lessThan");
			case '>=':
				return lang("app.greaterEqual");
			case '<=':
				return lang("app.lessEqual");
			case '==':
				return lang("app.equal");
			default:
				return $type;
		}
	}
}
if (!function_exists('wdaTermMarks')) {
	function wdaTermMarks($student, $core, $datas, $term, $year)
	{
		$termHtml = "";
		$mCat1 = isset($datas['cat'][$term]) ? $datas['cat'][$term] * 100 / $core['marks'] : 0;
		$mEx1 = isset($datas['exam'][$term]) ? $datas['exam'][$term] * 100 / $core['marks'] : 0;
		$rowTotal1 = $mEx1 + $mCat1;
		$termHtml .= '<tr>';
		$termHtml .= "<td>{$core['code']}</td>";
		$termHtml .= "<td>{$core['title']}</td>";
		$termHtml .= "<td>{$core['credit']} credits/" . ($core['credit'] * 10) . " Hrs</td>";
		$cm1 = strlen($mCat1) == 0 ? '-' : number_format($mCat1, 1);
		$em1 = strlen($mEx1) == 0 ? '-' : number_format($mEx1, 1);
		$tm1 = (strlen($mEx1) == 0 && strlen($mCat1) == 0) ? '-' : number_format($rowTotal1 / 2, 1);
		$reAssessment1 = \App\Controllers\Home::reAssessment($core['id'], $student['id'], $term, $year);
		$observationMarks = $reAssessment1 == null ? $tm1 : $reAssessment1['marks'];
		//						$row_total=($datas['marks'] + $datas['exam_marks']);
		$termHtml .= "<td>" . $cm1 . "</td>
								      <td>" . $em1 . "</td>
									  <td>" . $tm1 . "</td>
									  <td>" . ($reAssessment1 == null ? '' : number_format($reAssessment1['marks'], 1)) . "</td>
									  <td>" . ($observationMarks >= 70 ? 'C' : 'NYC') . "</td>
									 ";

		return $termHtml;
	}
}
if (!function_exists('parentType')) {
	function parentType($types)
	{
		$type = '__________';
		switch ($types) {
			case 1:
				$type = 'FATHER';
				break;
			case 2:
				$type = 'MOTHER';
				break;
			case 3:
				$type = 'GUARDIAN';
				break;
		}
		return $type;
	}
}
if (!function_exists('transactions_words')) {
	function transactions_words($type)
	{
		$trans = "Transfer";
		switch ($type) {
			case 0:
				$trans = "Transfer";
				break;
			case 1:
				$trans = "Payment";
				break;
			case 2:
				$trans = "Withdraw";
				break;
			case 3:
				$trans = "Refund";
				break;
		}
		return $trans;
	}
}

/**
 * Safe profile filename (fits historically short DB columns; avoid uniqid(..., true) dots).
 */
if (!function_exists('make_profile_photo_name')) {
	function make_profile_photo_name(string $ext): string
	{
		$ext = strtolower(preg_replace('/[^a-z0-9]/i', '', $ext) ?: 'jpg');
		return 'img_' . bin2hex(random_bytes(8)) . '.' . $ext;
	}
}

/**
 * Resolve a stored photo name to an existing file under assets/images/profile/.
 * Handles DB truncation (varchar(20)) of longer uniqid filenames.
 */
if (!function_exists('resolve_profile_photo')) {
	function resolve_profile_photo(?string $stored): ?string
	{
		$stored = trim((string)$stored);
		if ($stored === '' || strlen($stored) < 3) {
			return null;
		}
		$base = basename(str_replace(["\0", '\\'], '', $stored));
		if ($base === '' || $base === '.' || $base === '..') {
			return null;
		}
		$dir = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'profile' . DIRECTORY_SEPARATOR;
		if (is_file($dir . $base)) {
			return $base;
		}
		// Truncated name often has no extension; match prefix on disk.
		$matches = glob($dir . $base . '*') ?: [];
		$matches = array_values(array_filter($matches, 'is_file'));
		if (count($matches) === 1) {
			return basename($matches[0]);
		}
		if (count($matches) > 1) {
			usort($matches, static function ($a, $b) {
				return filemtime($b) <=> filemtime($a);
			});
			return basename($matches[0]);
		}
		return null;
	}
}

if (!function_exists('profile_photo_url')) {
	function profile_photo_url(?string $stored, ?string $fallback = null): string
	{
		$resolved = resolve_profile_photo($stored);
		if ($resolved !== null) {
			$path = FCPATH . 'assets/images/profile/' . $resolved;
			return base_url('assets/images/profile/' . $resolved) . '?v=' . (@filemtime($path) ?: time());
		}
		if ($fallback !== null) {
			return $fallback;
		}
		if (is_file(FCPATH . 'assets/images/fallback-avatar.png')) {
			return base_url('assets/images/fallback-avatar.png');
		}
		if (is_file(FCPATH . 'assets/images/fallback-avatar.svg')) {
			return base_url('assets/images/fallback-avatar.svg');
		}
		if (is_file(FCPATH . 'assets/images/no_image.jpg')) {
			return base_url('assets/images/no_image.jpg');
		}
		return base_url('assets/images/logo.jpeg');
	}
}

/** Thumbnail for discipline / permission entry student rows. */
if (!function_exists('student_entry_photo_html')) {
	function student_entry_photo_html(?string $stored): string
	{
		$fallback = profile_photo_url(null);
		$resolved = resolve_profile_photo($stored);
		$url = $resolved !== null ? profile_photo_url($resolved) : $fallback;
		return '<img class="student-entry-photo" src="' . esc($url, 'attr') . '" alt="" '
			. 'onerror="this.onerror=null;this.src=\'' . esc($fallback, 'attr') . '\';" />';
	}
}

/**
 * Absolute filesystem path under public/ for an assets-relative path.
 */
if (!function_exists('asset_fs_path')) {
	function asset_fs_path(string $relative): string
	{
		$relative = ltrim(str_replace(['\\', "\0"], ['/', ''], $relative), '/');
		return rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
	}
}

/**
 * Resolve first existing candidate path under public assets.
 */
if (!function_exists('asset_resolve_path')) {
	function asset_resolve_path(?string $relativeOrAbs, ?string $fallbackRelative = null): ?string
	{
		$candidates = [];
		if ($relativeOrAbs) {
			$p = $relativeOrAbs;
			if (strpos($p, '://') === false && !preg_match('#^([a-zA-Z]:[\\\\/]|/|\\\\)#', $p)) {
				$p = asset_fs_path($p);
			}
			$candidates[] = $p;
		}
		if ($fallbackRelative) {
			$candidates[] = asset_fs_path($fallbackRelative);
		}
		$candidates[] = asset_fs_path('assets/images/fallback-avatar.png');
		$candidates[] = asset_fs_path('assets/images/no_image.jpg');
		$candidates[] = asset_fs_path('assets/images/white_blank.png');

		foreach ($candidates as $path) {
			if (!$path || !is_file($path)) {
				continue;
			}
			$real = realpath($path);
			if ($real !== false) {
				return $real;
			}
		}
		return null;
	}
}

/**
 * file:// URL for wkhtmltopdf (enable-local-file-access).
 */
if (!function_exists('asset_file_src')) {
	function asset_file_src(?string $relativeOrAbs, ?string $fallbackRelative = null): string
	{
		$real = asset_resolve_path($relativeOrAbs, $fallbackRelative);
		if ($real === null) {
			return '';
		}
		$posix = str_replace('\\', '/', $real);
		if (preg_match('#^[a-zA-Z]:/#', $posix)) {
			return 'file:///' . $posix;
		}
		return 'file://' . $posix;
	}
}

/**
 * Data-URI src (small images). Prefer asset_card_img_src() for PDFs.
 */
if (!function_exists('asset_embed_src')) {
	function asset_embed_src(?string $relativeOrAbs, ?string $fallbackRelative = null): string
	{
		$real = asset_resolve_path($relativeOrAbs, $fallbackRelative);
		if ($real === null) {
			return '';
		}
		$data = @file_get_contents($real);
		if ($data === false || $data === '') {
			return '';
		}
		$ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
		if ($ext === 'jpg' || $ext === 'jpeg') {
			$mime = 'image/jpeg';
		} elseif ($ext === 'png') {
			$mime = 'image/png';
		} elseif ($ext === 'gif') {
			$mime = 'image/gif';
		} elseif ($ext === 'webp') {
			$mime = 'image/webp';
		} elseif ($ext === 'svg') {
			$mime = 'image/svg+xml';
		} else {
			$mime = 'application/octet-stream';
		}
		return 'data:' . $mime . ';base64,' . base64_encode($data);
	}
}

/**
 * Resized image for card PDFs (PNG keeps alpha so light logos stay visible on dark cards).
 * Written next to the wkhtml temp HTML so relative paths always load.
 */
if (!function_exists('asset_card_img_src')) {
	function asset_card_img_src(?string $relativeOrAbs, ?string $fallbackRelative = null, int $maxW = 240, int $maxH = 240): string
	{
		$real = asset_resolve_path($relativeOrAbs, $fallbackRelative);
		if ($real === null) {
			return '';
		}

		// Sibling folder of wkhtml HTML output (FCPATH/assets/templates/)
		$cacheDir = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . '_card_img';
		if (!is_dir($cacheDir)) {
			@mkdir($cacheDir, 0775, true);
		}

		$info = @getimagesize($real);
		$keepAlpha = is_array($info) && (int) $info[2] === IMAGETYPE_PNG;
		$ext = $keepAlpha ? 'png' : 'jpg';
		$key = md5($real . '|' . @filemtime($real) . "|{$maxW}x{$maxH}|a1") . '.' . $ext;
		$cached = $cacheDir . DIRECTORY_SEPARATOR . $key;

		if (!is_file($cached) && function_exists('imagecreatetruecolor')) {
			$src = null;
			if (is_array($info)) {
				switch ($info[2]) {
					case IMAGETYPE_JPEG:
						$src = @imagecreatefromjpeg($real);
						break;
					case IMAGETYPE_PNG:
						$src = @imagecreatefrompng($real);
						break;
					case IMAGETYPE_GIF:
						$src = @imagecreatefromgif($real);
						break;
					case IMAGETYPE_WEBP:
						if (function_exists('imagecreatefromwebp')) {
							$src = @imagecreatefromwebp($real);
						}
						break;
				}
			}
			if ($src) {
				$sw = imagesx($src);
				$sh = imagesy($src);
				$scale = min($maxW / max(1, $sw), $maxH / max(1, $sh), 1.0);
				$dw = max(1, (int) round($sw * $scale));
				$dh = max(1, (int) round($sh * $scale));
				$dst = imagecreatetruecolor($dw, $dh);
				if ($keepAlpha) {
					imagealphablending($dst, false);
					imagesavealpha($dst, true);
					$transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
					imagefill($dst, 0, 0, $transparent);
					imagealphablending($dst, true);
				} else {
					$white = imagecolorallocate($dst, 255, 255, 255);
					imagefill($dst, 0, 0, $white);
				}
				imagecopyresampled($dst, $src, 0, 0, 0, 0, $dw, $dh, $sw, $sh);
				if ($keepAlpha) {
					@imagepng($dst, $cached, 6);
				} else {
					@imagejpeg($dst, $cached, 85);
				}
				imagedestroy($dst);
				imagedestroy($src);
			} else {
				// Non-GD fallback: copy original
				@copy($real, $cached);
			}
		}

		if (is_file($cached)) {
			// Relative to assets/templates/*.html used by Wkhtmltopdf
			return '_card_img/' . $key;
		}

		return asset_file_src($real, $fallbackRelative);
	}
}

if (!function_exists('profile_photo_embed')) {
	function profile_photo_embed(?string $stored, int $maxW = 220, int $maxH = 260): string
	{
		$resolved = resolve_profile_photo($stored);
		if ($resolved !== null) {
			return asset_card_img_src('assets/images/profile/' . $resolved, 'assets/images/fallback-avatar.png', $maxW, $maxH);
		}
		return asset_card_img_src(null, 'assets/images/fallback-avatar.png', $maxW, $maxH);
	}
}

/**
 * Student card PDF photo — returns empty string when no uploaded photo (no fallback silhouette).
 */
if (!function_exists('profile_photo_card_src')) {
	function profile_photo_card_src(?string $stored, int $maxW = 360, int $maxH = 440): string
	{
		$resolved = resolve_profile_photo($stored);
		if ($resolved === null) {
			return '';
		}
		return asset_card_img_src('assets/images/profile/' . $resolved, null, $maxW, $maxH);
	}
}

/**
 * Center-crop profile photo to exact WxH (keeps face proportional — no stretch).
 * Used by CR80 PDF so wkhtmltopdf cannot squash the image into the photo box.
 */
if (!function_exists('profile_photo_card_cover_src')) {
	function profile_photo_card_cover_src(?string $stored, int $outW = 360, int $outH = 480): string
	{
		$resolved = resolve_profile_photo($stored);
		if ($resolved === null) {
			return '';
		}
		$outW = max(80, min(1200, $outW));
		$outH = max(80, min(1600, $outH));
		$relative = 'assets/images/profile/' . $resolved;
		$real = asset_resolve_path($relative, null);
		if ($real === null) {
			return '';
		}

		$cacheDir = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . '_card_img';
		if (!is_dir($cacheDir)) {
			@mkdir($cacheDir, 0775, true);
		}
		$key = md5($real . '|' . @filemtime($real) . "|cover{$outW}x{$outH}|v2") . '.jpg';
		$cached = $cacheDir . DIRECTORY_SEPARATOR . $key;
		if (is_file($cached)) {
			return '_card_img/' . $key;
		}

		if (!function_exists('imagecreatetruecolor')) {
			return asset_card_img_src($relative, null, $outW, $outH);
		}

		$info = @getimagesize($real);
		$src = null;
		if (is_array($info)) {
			switch ((int) $info[2]) {
				case IMAGETYPE_JPEG:
					$src = @imagecreatefromjpeg($real);
					break;
				case IMAGETYPE_PNG:
					$src = @imagecreatefrompng($real);
					break;
				case IMAGETYPE_GIF:
					$src = @imagecreatefromgif($real);
					break;
				case IMAGETYPE_WEBP:
					if (function_exists('imagecreatefromwebp')) {
						$src = @imagecreatefromwebp($real);
					}
					break;
			}
		}
		if (!$src) {
			return asset_card_img_src($relative, null, $outW, $outH);
		}

		$sw = imagesx($src);
		$sh = imagesy($src);
		$targetRatio = $outW / max(1, $outH);
		$srcRatio = $sw / max(1, $sh);
		if ($srcRatio > $targetRatio) {
			// Source wider → crop sides
			$cropH = $sh;
			$cropW = (int) round($sh * $targetRatio);
			$sx = (int) max(0, ($sw - $cropW) / 2);
			$sy = 0;
		} else {
			// Source taller → crop top/bottom (bias slightly toward top for faces)
			$cropW = $sw;
			$cropH = (int) round($sw / $targetRatio);
			$sx = 0;
			$sy = (int) max(0, ($sh - $cropH) * 0.28);
		}
		$cropW = max(1, min($sw - $sx, $cropW));
		$cropH = max(1, min($sh - $sy, $cropH));

		$dst = imagecreatetruecolor($outW, $outH);
		$white = imagecolorallocate($dst, 255, 255, 255);
		imagefill($dst, 0, 0, $white);
		imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $outW, $outH, $cropW, $cropH);
		@imagejpeg($dst, $cached, 90);
		imagedestroy($dst);
		imagedestroy($src);

		if (is_file($cached)) {
			return '_card_img/' . $key;
		}
		return asset_card_img_src($relative, null, $outW, $outH);
	}
}

if (!function_exists('is_wisdom_school')) {
	/** Wisdom org only — Holiday coaching and related extras. */
	function is_wisdom_school($schoolId = null)
	{
		$schoolId = $schoolId !== null ? (int) $schoolId : (int) ($_SESSION['soma_school_id'] ?? 0);
		if ($schoolId < 1) {
			return false;
		}
		try {
			return (new \App\Services\Budget\BranchContextService())->isWisdomSchoolId($schoolId);
		} catch (\Throwable $e) {
			return false;
		}
	}
}

if (!function_exists('holiday_coaching_mark_type')) {
	function holiday_coaching_mark_type()
	{
		return 11;
	}
}

if (!function_exists('normalize_course_program_type')) {
	function normalize_course_program_type($type)
	{
		$t = strtolower(trim((string) $type));
		if ($t === 'holiday' || $t === 'holiday_coaching' || $t === 'coaching') {
			return 'holiday';
		}
		if ($t === 'reb') {
			return 'reb';
		}
		return 'tvet';
	}
}

if (!function_exists('is_holiday_course_program')) {
	function is_holiday_course_program($type)
	{
		return normalize_course_program_type($type) === 'holiday';
	}
}

/** Stored on course_records.term for year-wide (not termly) holiday coaching assignments. */
if (!function_exists('holiday_course_year_term')) {
	function holiday_course_year_term()
	{
		return '0';
	}
}

if (!function_exists('staff_name_initials')) {
	function staff_name_initials($fname, $lname = '')
	{
		$parts = preg_split('/\s+/', trim((string) $fname . ' ' . (string) $lname), -1, PREG_SPLIT_NO_EMPTY);
		$out = '';
		foreach ($parts as $p) {
			$ch = function_exists('mb_substr') ? mb_substr($p, 0, 1) : substr($p, 0, 1);
			$out .= strtoupper((string) $ch);
		}
		return $out;
	}
}

if (!function_exists('marks_assessment_types')) {
	/**
	 * Marks entry types for web + mobile. Holiday coaching is Wisdom-only and has no period.
	 *
	 * @return array<int, array{id:int, academic_type_id:int, title:string, requires_period:bool}>
	 */
	function marks_assessment_types($schoolId, $academicTypeId = 1)
	{
		$academicTypeId = (int) $academicTypeId;
		if ($academicTypeId < 1) {
			$academicTypeId = 1;
		}
		$types = [
			['id' => 1, 'academic_type_id' => $academicTypeId, 'title' => 'CAT', 'requires_period' => true],
			['id' => 2, 'academic_type_id' => $academicTypeId, 'title' => 'Exam', 'requires_period' => false],
			['id' => 3, 'academic_type_id' => $academicTypeId, 'title' => 'Second sitting', 'requires_period' => false],
			['id' => 9, 'academic_type_id' => $academicTypeId, 'title' => 'Re-assess', 'requires_period' => false],
		];
		if (is_wisdom_school($schoolId)) {
			$types[] = [
				'id' => holiday_coaching_mark_type(),
				'academic_type_id' => $academicTypeId,
				'title' => 'Holiday coaching',
				'requires_period' => false,
			];
		}
		return $types;
	}
}
