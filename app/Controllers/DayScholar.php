<?php

namespace App\Controllers;

/** Day scholars on one of today's four gate taps: morning, lunch, back, evening. */
class DayScholar extends Home
{
	public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
	{
		parent::initController($request, $response, $logger);
		$router = service('router');
		$method = method_exists($router, 'methodName') ? (string) $router->methodName() : '';
		$allowed = ['today', 'stafftoday', 'populationreport'];
		if ($method !== '' && !in_array(strtolower($method), $allowed, true)) {
			header('Location: ' . base_url('dashboard'));
			exit;
		}
	}

	private function canOpenDirectorDashboard(\App\Services\WisdomGroupOverview $overview, int $homeId, int $postId): bool
	{
		if ($overview->wisdomDashboardMasterId($homeId) < 1) {
			return false;
		}
		if ($overview->isAliceNamahoro()) {
			return true;
		}
		$customerCareOnly = $overview->isCustomerCarePost($postId) && !$overview->isLeaderPost($postId);
		$chiefAccountant = \Config\MenuClearance::isChiefAccountantPost($postId);
		return $overview->isLeaderPost($postId) || $customerCareOnly || $chiefAccountant;
	}

	public function today($schoolId = 0, $kind = 'morning')
	{
		if ($this->session->get($this->log_status) == null) {
			return redirect()->to(base_url('login'));
		}
		$this->_preset();
		helper('qonics');
		$overview = new \App\Services\WisdomGroupOverview();
		$homeId = (int) school_hierarchy_home_id();
		$postId = (int) $this->session->get('soma_post');
		if (!$this->canOpenDirectorDashboard($overview, $homeId, $postId)) {
			return redirect()->to(base_url('dashboard'));
		}
		$dashId = $overview->wisdomDashboardMasterId($homeId);
		$customerCareOnly = $overview->isCustomerCarePost($postId) && !$overview->isLeaderPost($postId) && !$overview->isAliceNamahoro();
		$allowed = [];
		foreach ($overview->schools($dashId) as $school) {
			$id = (int) ($school['id'] ?? 0);
			if ($id > 0) {
				$allowed[$id] = (string) ($school['name'] ?? '');
			}
		}
		$schoolId = (int) $schoolId;
		if ($customerCareOnly) {
			$schoolId = $homeId;
		}
		if ($schoolId < 1 || !isset($allowed[$schoolId])) {
			return redirect()->to(base_url('dashboard'));
		}
		$kind = $overview->dayScholarTapKind((string) $kind);
		$labels = [
			'morning' => 'Arrived in the morning',
			'lunch' => 'Went out for lunch',
			'back' => 'Returned from lunch',
			'evening' => 'Left school in the evening',
		];
		$yearId = (int) ($this->data['academic_year'] ?? 0);
		$data = $this->data;
		$data['title'] = $labels[$kind];
		$data['page'] = 'dashboard';
		$data['kind'] = $kind;
		$data['tap_label'] = $labels[$kind];
		$data['list'] = $overview->dayScholarTapList($schoolId, $yearId, $kind);
		$data['counts'] = $overview->dayScholarTapSummary($schoolId, $yearId);
		$print = \Config\Database::connect()->table('schools')
			->select('name,logo,slogan,header_text_1,header_text_2,phone,email,address,pobox')
			->where('id', $schoolId)
			->get(1)
			->getRowArray();
		$data['print_school'] = is_array($print) ? $print : [];
		$data['school_label'] = $allowed[$schoolId] ?? '';
		$data['school_id'] = $schoolId;
		$data['content'] = view('pages/wisdom_day_scholar', $data);
		return view('main', $data);
	}

	/** Staff in / absent list from the Director dashboard. */
	public function staffToday($schoolId = 0, $kind = 'in')
	{
		if ($this->session->get($this->log_status) == null) {
			return redirect()->to(base_url('login'));
		}
		$this->_preset();
		helper('qonics');
		$overview = new \App\Services\WisdomGroupOverview();
		$homeId = (int) school_hierarchy_home_id();
		$postId = (int) $this->session->get('soma_post');
		if (!$this->canOpenDirectorDashboard($overview, $homeId, $postId)) {
			return redirect()->to(base_url('dashboard'));
		}
		$dashId = $overview->wisdomDashboardMasterId($homeId);
		$customerCareOnly = $overview->isCustomerCarePost($postId) && !$overview->isLeaderPost($postId) && !$overview->isAliceNamahoro();
		$allowed = [];
		foreach ($overview->schools($dashId) as $school) {
			$id = (int) ($school['id'] ?? 0);
			if ($id > 0) {
				$allowed[$id] = (string) ($school['name'] ?? '');
			}
		}
		$schoolId = (int) $schoolId;
		if ($customerCareOnly) {
			$schoolId = $homeId;
			$allowed = isset($allowed[$homeId]) ? [$homeId => $allowed[$homeId]] : $allowed;
		}
		if ($schoolId > 0 && !isset($allowed[$schoolId])) {
			return redirect()->to(base_url('dashboard'));
		}
		$ids = $schoolId > 0 ? [$schoolId] : array_keys($allowed);
		$kind = $kind === 'absent' ? 'absent' : 'in';
		$data = $this->data;
		$data['title'] = $kind === 'absent' ? 'Staff absent today' : 'Staff in today';
		$data['page'] = 'dashboard';
		$data['kind'] = $kind;
		$data['list'] = $overview->todayStaffList($ids, $kind);
		$letterId = $schoolId > 0 ? $schoolId : $dashId;
		$print = \Config\Database::connect()->table('schools')
			->select('name,logo,slogan,header_text_1,header_text_2,phone,email,address,pobox')
			->where('id', $letterId)
			->get(1)
			->getRowArray();
		$data['print_school'] = is_array($print) ? $print : [];
		$data['school_label'] = $schoolId > 0 ? ($allowed[$schoolId] ?? '') : 'All Wisdom schools';
		$data['content'] = view('pages/wisdom_staff_today', $data);
		return view('main', $data);
	}

	/** Attendance workbook linked from the Director dashboard. */
	public function populationReport()
	{
		if ($this->session->get($this->log_status) == null) {
			return redirect()->to(base_url('login'));
		}
		helper('qonics');
		$homeId = (int) school_hierarchy_home_id();
		$currentId = (int) $this->session->get('soma_school_id');
		if ($homeId > 0 && $currentId > 0 && $homeId !== $currentId) {
			$school = \Config\Database::connect()->table('schools')->select('name')->where('id', $homeId)->get(1)->getRowArray();
			if ($school) {
				$this->session->set([
					'soma_school_id' => $homeId,
					'soma_school' => (string) ($school['name'] ?? ''),
				]);
			}
		}
		$this->_preset();
		$postId = (int) $this->session->get('soma_post');
		$overview = new \App\Services\WisdomGroupOverview();
		$allowed = [
			\App\Models\PostsModel::PRINCIPAL_ID,
			\App\Models\PostsModel::DIRECTOR_ID,
		];
		$dashId = $overview->wisdomDashboardMasterId($homeId);
		$postAllowed = in_array($postId, $allowed, true) || \Config\MenuClearance::isChiefAccountantPost($postId) || $overview->isAliceNamahoro();
		if (!$postAllowed || $dashId < 1) {
			return redirect()->to(base_url('dashboard'));
		}
		$yearId = (int) ($this->data['academic_year'] ?? $this->data['academic_year_id'] ?? 0);
		$date = date('Y-m-d');
		$posted = trim((string) $this->request->getGet('date'));
		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $posted)) {
			$date = $posted;
		}
		@ini_set('memory_limit', '512M');
		@set_time_limit(180);
		\App\Libraries\WisdomPopulationReport::stream($dashId, $yearId, $date);
	}
}
