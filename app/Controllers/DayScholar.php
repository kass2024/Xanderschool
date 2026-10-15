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
		if ($method !== '' && strcasecmp($method, 'today') !== 0) {
			header('Location: ' . base_url('dashboard'));
			exit;
		}
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
		$customerCareOnly = $overview->isCustomerCarePost($postId) && !$overview->isLeaderPost($postId);
		$chiefAccountant = \Config\MenuClearance::isChiefAccountantPost($postId);
		if ((!$overview->isLeaderPost($postId) && !$customerCareOnly && !$chiefAccountant) || !$overview->isWisdomMaster($homeId)) {
			return redirect()->to(base_url('dashboard'));
		}
		$allowed = [];
		foreach ($overview->schools($homeId) as $school) {
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
}
