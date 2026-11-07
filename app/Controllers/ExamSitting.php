<?php

namespace App\Controllers;

use App\Libraries\ExamSittingWorkbook;
use App\Models\AttendanceAreaModel;
use App\Models\ExamSittingModel;

class ExamSitting extends Home
{
	private function canSetup(): bool
	{
		return function_exists('menu_clearance_allowed') && menu_clearance_allowed('settings');
	}

	private function canMix(): bool
	{
		if (!function_exists('menu_clearance_allowed')) {
			return false;
		}
		if (function_exists('menu_clearance_group_visible') && menu_clearance_group_visible('students')) {
			return true;
		}
		return menu_clearance_allowed('students')
			|| menu_clearance_allowed('exam_sitting')
			|| menu_clearance_allowed('settings');
	}

	private function denied()
	{
		$this->_preset(1, 3);
		if (!$this->canSetup()) {
			return $this->response->setStatusCode(403)->setJSON(['error' => 'Settings access is required.']);
		}
		return null;
	}

	private function deniedMix()
	{
		$this->_preset(1, 3);
		if (!$this->canMix()) {
			return $this->response->setStatusCode(403)->setJSON(['error' => 'Open this from Students, Exam sitting Plan.']);
		}
		return null;
	}

	public function page()
	{
		$this->_preset(1, 3);
		if (!$this->canMix()) {
			return redirect()->to(base_url('dashboard'));
		}
		$schoolId = (int) $this->session->get('soma_school_id');
		$yearId = (int) ($this->data['academic_year'] ?? 0);
		$model = new ExamSittingModel();
		$data = $this->data;
		$data['title'] = 'Exam sitting Plan';
		$data['subtitle'] = 'Mix classes and assign seats';
		$data['page'] = 'exam_sitting';
		$data['classes'] = $model->regularClasses($schoolId);
		$data['exam_sittings'] = $model->plansForSchool($schoolId, $yearId);
		$data['open_plan'] = (int) $this->request->getGet('plan');
		$data['content'] = view('pages/partials/exam_sitting_assign', $data);
		return view('main', $data);
	}

	public function savePlan()
	{
		if ($block = $this->denied()) {
			return $block;
		}
		$schoolId = (int) $this->session->get('soma_school_id');
		$yearId = (int) ($this->data['academic_year'] ?? 0);
		$title = trim(preg_replace('/\s+/', ' ', (string) $this->request->getPost('title')) ?? '');
		if ($title === '' || (function_exists('mb_strlen') ? mb_strlen($title) : strlen($title)) > 160) {
			return $this->response->setJSON(['error' => 'Name the exam, up to 160 characters.']);
		}
		$model = new ExamSittingModel();
		$planId = (int) $this->request->getPost('plan_id');
		if ($planId > 0) {
			if (!$model->renamePlan($schoolId, $planId, $title)) {
				return $this->response->setJSON(['error' => 'Sitting plan not found.']);
			}
			return $this->response->setJSON(['success' => true, 'plan' => $model->planDetail($schoolId, $planId)]);
		}
		$band = ExamSittingModel::normalizeBand((string) $this->request->getPost('level_band'));
		$id = $model->createPlan($schoolId, $yearId, $title, $band);
		$plan = $model->planDetail($schoolId, $id);
		return $this->response->setJSON(['success' => true, 'plan' => $plan]);
	}

	public function saveRoom()
	{
		if ($block = $this->denied()) {
			return $block;
		}
		$schoolId = (int) $this->session->get('soma_school_id');
		$planId = (int) $this->request->getPost('plan_id');
		$source = (string) $this->request->getPost('source');
		$tables = (int) $this->request->getPost('tables');
		$columns = (int) $this->request->getPost('columns');
		$perTable = (int) $this->request->getPost('per_table');
		if (!preg_match('/^(class|area):(\d+)$/', $source, $match)) {
			return $this->response->setJSON(['error' => 'Choose a class or an attendance location.']);
		}
		$type = $match[1];
		$sourceId = (int) $match[2];
		$name = $this->roomName($schoolId, $type, $sourceId);
		if ($name === '') {
			return $this->response->setJSON(['error' => 'That room is not in this school.']);
		}
		$result = (new ExamSittingModel())->addRoom($schoolId, $planId, $type, $sourceId, $tables, $columns, $perTable, $name);
		if (empty($result['ok'])) {
			return $this->response->setJSON(['error' => $result['error'] ?? 'Could not add the room.']);
		}
		return $this->response->setJSON([
			'success' => true,
			'plan' => (new ExamSittingModel())->planDetail($schoolId, $planId),
		]);
	}

	public function deleteRoom()
	{
		if ($block = $this->denied()) {
			return $block;
		}
		$schoolId = (int) $this->session->get('soma_school_id');
		$planId = (int) $this->request->getPost('plan_id');
		$roomId = (int) $this->request->getPost('room_id');
		if (!(new ExamSittingModel())->deleteRoom($schoolId, $planId, $roomId)) {
			return $this->response->setJSON(['error' => 'Room not found.']);
		}
		return $this->response->setJSON([
			'success' => true,
			'plan' => (new ExamSittingModel())->planDetail($schoolId, $planId),
		]);
	}

	public function deletePlan()
	{
		if ($block = $this->denied()) {
			return $block;
		}
		$schoolId = (int) $this->session->get('soma_school_id');
		$planId = (int) $this->request->getPost('plan_id');
		(new ExamSittingModel())->deletePlan($schoolId, $planId);
		return $this->response->setJSON(['success' => true]);
	}

	public function assign()
	{
		if ($block = $this->deniedMix()) {
			return $block;
		}
		$schoolId = (int) $this->session->get('soma_school_id');
		$yearId = (int) ($this->data['academic_year'] ?? 0);
		$planId = (int) $this->request->getPost('plan_id');
		if ((string) $this->request->getPost('clear') === '1') {
			$result = (new ExamSittingModel())->clearPairing($schoolId, $planId);
			if (empty($result['ok'])) {
				return $this->response->setJSON(['error' => $result['error'] ?? 'Could not clear the pairing.']);
			}
			return $this->response->setJSON([
				'success' => true,
				'plan' => $result['plan'],
				'cleared' => true,
			]);
		}
		$classIds = $this->request->getPost('class_id');
		if (!is_array($classIds)) {
			$classIds = [];
		}
		$all = (string) $this->request->getPost('all_classes') === '1';
		$rawPairs = $this->request->getPost('pairs');
		if (is_string($rawPairs)) {
			$decoded = json_decode($rawPairs, true);
			$pairs = is_array($decoded) ? $decoded : [];
		} elseif (is_array($rawPairs)) {
			$pairs = $rawPairs;
		} else {
			$pairs = [];
		}
		$result = (new ExamSittingModel())->assign($schoolId, $planId, $yearId, $all ? [] : $classIds, 'pairs', $pairs);
		if (empty($result['ok'])) {
			return $this->response->setJSON(['error' => $result['error'] ?? 'Could not assign seats.']);
		}
		return $this->response->setJSON([
			'success' => true,
			'plan' => $result['plan'],
			'unseated' => $result['unseated'],
			'student_count' => $result['student_count'],
		]);
	}

	public function show($planId = 0)
	{
		if ($block = $this->deniedMix()) {
			return $block;
		}
		$schoolId = (int) $this->session->get('soma_school_id');
		$plan = (new ExamSittingModel())->planDetail($schoolId, (int) $planId);
		if (!$plan) {
			return $this->response->setJSON(['error' => 'Sitting plan not found.']);
		}
		return $this->response->setJSON(['success' => true, 'plan' => $plan]);
	}

	public function excelRoom($planId = 0, $roomId = 0)
	{
		$plan = $this->planForDownload((int) $planId);
		if ($plan === null) {
			return;
		}
		$room = null;
		foreach ($plan['rooms'] as $candidate) {
			if ((int) ($candidate['id'] ?? 0) === (int) $roomId) {
				$room = $candidate;
				break;
			}
		}
		if ($room === null || empty($room['seats'])) {
			echo 'Assign seats before downloading this room.';
			return;
		}
		$binary = ExamSittingWorkbook::roomBinary($plan, $room, $this->schoolBrand());
		$this->sendDownload(
			ExamSittingWorkbook::downloadName((string) ($plan['title'] ?? 'Exam'), (string) ($room['room_name'] ?? 'Room')),
			$binary,
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
		);
	}

	public function excelZip($planId = 0)
	{
		$plan = $this->planForDownload((int) $planId);
		if ($plan === null) {
			return;
		}
		$ready = false;
		foreach ($plan['rooms'] as $room) {
			if (!empty($room['seats'])) {
				$ready = true;
				break;
			}
		}
		if (!$ready) {
			echo 'Assign seats before downloading the room files.';
			return;
		}
		$binary = ExamSittingWorkbook::bookBinary($plan, $this->schoolBrand());
		$name = preg_replace('/[\\\\\\/:*?"<>|]+/', '', trim((string) ($plan['title'] ?? 'Exam'))) ?: 'Exam';
		$name = preg_replace('/\s+/', '_', $name) ?: 'Exam';
		$this->sendDownload($name . '.xlsx', $binary, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	}

	/** @return array<string,mixed>|null */
	private function planForDownload(int $planId): ?array
	{
		$this->_preset(1, 3);
		if (!$this->canMix()) {
			redirect()->to(base_url('dashboard'))->send();
			exit;
		}
		$plan = (new ExamSittingModel())->planDetail((int) $this->session->get('soma_school_id'), $planId);
		if (!$plan) {
			echo 'Sitting plan not found.';
			return null;
		}
		return $plan;
	}

	/** @return array<string,string> */
	private function schoolBrand(): array
	{
		$term = trim((string) ($this->data['term'] ?? ''));
		if ($term !== '' && stripos($term, 'term') === false) {
			$term = 'Term ' . $term;
		}
		return [
			'name' => (string) ($this->data['school_name'] ?? ''),
			'slogan' => (string) ($this->data['school_moto'] ?? ''),
			'address' => (string) ($this->data['school_address'] ?? ''),
			'pobox' => (string) ($this->data['school_pobox'] ?? ''),
			'phone' => (string) ($this->data['school_phone'] ?? ''),
			'email' => (string) ($this->data['school_email'] ?? ''),
			'website' => (string) ($this->data['school_website'] ?? ''),
			'logo' => basename((string) ($this->data['school_logo'] ?? '')),
			'year' => (string) ($this->data['academic_year_title'] ?? ''),
			'term' => $term,
		];
	}

	private function sendDownload(string $filename, string $binary, string $type): void
	{
		$filename = str_replace(['"', "\r", "\n"], '', $filename);
		header('Content-Type: ' . $type);
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Content-Length: ' . strlen($binary));
		header('Cache-Control: max-age=0');
		echo $binary;
		exit;
	}

	public function printPlan($planId = 0)
	{
		$this->_preset(1, 3);
		if (!$this->canMix()) {
			return redirect()->to(base_url('dashboard'));
		}
		$schoolId = (int) $this->session->get('soma_school_id');
		$plan = (new ExamSittingModel())->planDetail($schoolId, (int) $planId);
		if (!$plan) {
			echo 'Sitting plan not found.';
			return;
		}
		$roomId = (int) $this->request->getGet('room');
		if ($roomId > 0) {
			$plan['rooms'] = array_values(array_filter($plan['rooms'], static function ($room) use ($roomId) {
				return (int) ($room['id'] ?? 0) === $roomId;
			}));
		}
		return view('pages/exam_sitting_print', [
			'plan' => $plan,
			'school_name' => (string) ($this->data['school_name'] ?? ''),
			'year_title' => (string) ($this->data['academic_year_title'] ?? ''),
		]);
	}

	private function roomName(int $schoolId, string $type, int $sourceId): string
	{
		$db = \Config\Database::connect();
		if ($type === 'class') {
			$row = $db->table('classes c')
				->select("l.title AS level_name, d.code AS dept_code, c.title")
				->join('levels l', 'l.id = c.level', 'left')
				->join('departments d', 'd.id = c.department', 'left')
				->where('c.school_id', $schoolId)
				->where('c.id', $sourceId)
				->get()->getRowArray();
			if (!$row) {
				return '';
			}
			return trim(preg_replace('/\s+/', ' ', trim((string) ($row['level_name'] ?? '') . ' ' . (string) ($row['dept_code'] ?? '') . ' ' . (string) ($row['title'] ?? ''))) ?? '');
		}
		$area = (new AttendanceAreaModel())->where('school_id', $schoolId)->where('id', $sourceId)->where('active', 1)->first();
		return $area ? trim((string) ($area['name'] ?? '')) : '';
	}
}
