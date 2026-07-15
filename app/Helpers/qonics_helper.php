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
