<?php

namespace App\Controllers;

use App\Libraries\TimetableClassLabel;
use Config\Database;

/**
 * Teachers upload their own assessment files.
 * Only that teacher, the Head Teacher, and the Director can open a file.
 */
class AssessmentUpload extends Home
{
	const MAX_BYTES = 15728640;

	public function page()
	{
		$this->_preset();
		$this->guardUse();
		$this->ensureSchema();

		$schoolId = (int) $this->session->get('soma_school_id');
		$staffId = (int) $this->session->get('soma_id');
		$yearId = (int) ($this->data['academic_year'] ?? 0);
		$viewAll = function_exists('can_view_all_teacher_assessments') && can_view_all_teacher_assessments();
		$filterStaff = $viewAll ? (int) $this->request->getGet('teacher') : 0;

		$data = $this->data;
		$data['title'] = 'Assessment upload';
		$data['page'] = 'assessment_upload';
		$data['view_all_assessments'] = $viewAll;
		$data['filter_teacher'] = $filterStaff;
		$data['assignments'] = $this->assignmentChoices($schoolId, $staffId, $yearId);
		$data['uploads'] = $this->listUploads($schoolId, $staffId, $viewAll, $filterStaff);
		$data['teachers'] = $viewAll ? $this->teacherChoices($schoolId) : [];
		$data['can_reject_assessments'] = $this->canReviewAssessments();
		$data['content'] = view('pages/assessment/upload', $data);
		return view('main', $data);
	}

	public function save()
	{
		$this->_preset();
		$this->guardUse();
		if (strtolower($this->request->getMethod()) !== 'post') {
			return redirect()->to(base_url('assessmentupload'));
		}
		$this->ensureSchema();

		$schoolId = (int) $this->session->get('soma_school_id');
		$staffId = (int) $this->session->get('soma_id');
		$yearId = (int) ($this->data['academic_year'] ?? 0);
		$termId = (int) ($this->data['active_term'] ?? 0);
		$type = trim(preg_replace('/\s+/', ' ', (string) $this->request->getPost('assessment_type')));
		$type = trim(strip_tags($type));
		if (strlen($type) < 2 || strlen($type) > 80) {
			return $this->fail('Type the assessment type, using 2 to 80 characters.');
		}

		$yearId = $yearId > 0 ? $yearId : (int) ($this->session->get('soma_academics_year') ?? 0);
		$choices = $this->assignmentChoices($schoolId, $staffId, $yearId);
		$picked = $this->pickedAssignment((string) $this->request->getPost('assignment'), $choices);
		if ($choices !== [] && $picked === null) {
			return $this->fail('Select the class and course this assessment belongs to.');
		}
		$classId = $picked['class_id'] ?? 0;
		$courseId = $picked['course_id'] ?? 0;

		$file = $this->request->getFile('assessment_file');
		if (!$file || !$file->isValid() || $file->hasMoved()) {
			return $this->fail('Choose a PDF or Word document to upload.');
		}
		if ($file->getSize() < 1 || $file->getSize() > self::MAX_BYTES) {
			return $this->fail('The file must be a PDF or Word document under 15 MB.');
		}
		$ext = strtolower((string) $file->getExtension());
		if (!in_array($ext, ['pdf', 'doc', 'docx'], true)) {
			$ext = strtolower(pathinfo((string) $file->getClientName(), PATHINFO_EXTENSION));
		}
		if (!in_array($ext, ['pdf', 'doc', 'docx'], true)) {
			return $this->fail('Upload a PDF (.pdf) or Word (.doc, .docx) file.');
		}

		$dir = $this->storageDir($schoolId);
		$stored = bin2hex(random_bytes(16)) . '.' . $ext;
		try {
			$file->move($dir, $stored, true);
		} catch (\Throwable $e) {
			return $this->fail('The file could not be saved. Try again.');
		}
		$path = $dir . DIRECTORY_SEPARATOR . $stored;
		if (!$this->documentMatchesExtension($path, $ext)) {
			@unlink($path);
			return $this->fail('That file is not a valid PDF or Word document.');
		}

		$original = $this->cleanOriginalName((string) $file->getClientName(), $ext);
		Database::connect()->table('teacher_assessment_uploads')->insert([
			'school_id' => $schoolId,
			'staff_id' => $staffId,
			'academic_year' => $yearId,
			'term_id' => $termId,
			'class_id' => $classId,
			'course_id' => $courseId,
			'assessment_type' => $type,
			'original_name' => $original,
			'stored_name' => $stored,
			'byte_size' => (int) filesize($path),
			'created_at' => date('Y-m-d H:i:s'),
		]);
		$this->session->setFlashdata('success', 'Assessment uploaded.');
		if ($this->wantsJson()) {
			return $this->response->setJSON(['ok' => true, 'message' => 'Assessment uploaded.']);
		}
		return redirect()->to(base_url('assessmentupload'));
	}

	public function reject($id = 0)
	{
		$this->_preset();
		$this->guardUse();
		if (strtolower($this->request->getMethod()) !== 'post') {
			return redirect()->to(base_url('assessmentupload'));
		}
		if (!$this->canReviewAssessments()) {
			$this->session->setFlashdata('error', 'Only the Head Teacher or the Director can reject an assessment.');
			return redirect()->to(base_url('assessmentupload'));
		}
		$this->ensureSchema();
		$reason = trim(preg_replace('/\s+/', ' ', strip_tags((string) $this->request->getPost('reason'))));
		if (strlen($reason) < 3 || strlen($reason) > 255) {
			$this->session->setFlashdata('error', 'Write the reason for rejecting this assessment.');
			return redirect()->to(base_url('assessmentupload'));
		}
		$schoolId = (int) $this->session->get('soma_school_id');
		$row = Database::connect()->table('teacher_assessment_uploads')
			->where('id', (int) $id)
			->where('school_id', $schoolId)
			->get(1)->getRowArray();
		if (!$row) {
			$this->session->setFlashdata('error', 'That assessment is not in this school.');
			return redirect()->to(base_url('assessmentupload'));
		}
		Database::connect()->table('teacher_assessment_uploads')->where('id', (int) $row['id'])->update([
			'status' => 'rejected',
			'reject_reason' => $reason,
			'rejected_by' => (int) $this->session->get('soma_id'),
			'rejected_at' => date('Y-m-d H:i:s'),
		]);
		$this->session->setFlashdata('success', 'Assessment rejected.');
		return redirect()->to(base_url('assessmentupload'));
	}

	public function file($id = 0)
	{
		$this->_preset();
		$this->guardUse();
		$this->ensureSchema();
		$row = $this->authorizedRow((int) $id);
		if ($row === null) {
			$this->session->setFlashdata('error', 'You cannot open that assessment.');
			return redirect()->to(base_url('assessmentupload'));
		}
		$path = $this->safePath((int) $row['school_id'], (string) $row['stored_name']);
		if ($path === '') {
			$this->session->setFlashdata('error', 'That assessment file is no longer available.');
			return redirect()->to(base_url('assessmentupload'));
		}
		$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
		$types = [
			'pdf' => 'application/pdf',
			'doc' => 'application/msword',
			'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		];
		return $this->response->download($path, null)
			->setFileName((string) $row['original_name'])
			->setContentType($types[$ext] ?? 'application/octet-stream');
	}

	public function remove($id = 0)
	{
		$this->_preset();
		$this->guardUse();
		if (strtolower($this->request->getMethod()) !== 'post') {
			return redirect()->to(base_url('assessmentupload'));
		}
		$this->ensureSchema();
		$schoolId = (int) $this->session->get('soma_school_id');
		$staffId = (int) $this->session->get('soma_id');
		$builder = Database::connect()->table('teacher_assessment_uploads')
			->where('id', (int) $id)
			->where('school_id', $schoolId);
		if (!$this->canReviewAssessments()) {
			$builder->where('staff_id', $staffId);
		}
		$row = $builder->get(1)->getRowArray();
		if (!$row) {
			$this->session->setFlashdata('error', 'You can only remove an assessment you uploaded.');
			return redirect()->to(base_url('assessmentupload'));
		}
		$path = $this->safePath($schoolId, (string) $row['stored_name']);
		if ($path !== '') {
			@unlink($path);
		}
		Database::connect()->table('teacher_assessment_uploads')->where('id', (int) $row['id'])->delete();
		$this->session->setFlashdata('success', 'Assessment removed.');
		return redirect()->to(base_url('assessmentupload'));
	}

	protected function canReviewAssessments(): bool
	{
		if (function_exists('is_head_teacher_post') && is_head_teacher_post()) {
			return true;
		}
		$postId = (int) $this->session->get('soma_post');
		return \Config\MenuClearance::isDirectorPost($postId);
	}

	protected function guardUse(): void
	{
		if (function_exists('can_use_assessment_upload') && can_use_assessment_upload()) {
			return;
		}
		$this->session->setFlashdata('error', 'You do not have access to Assessment upload.');
		header('Location: ' . base_url('dashboard'));
		exit;
	}

	protected function wantsJson(): bool
	{
		return $this->request->isAJAX();
	}

	protected function fail(string $message)
	{
		if ($this->wantsJson()) {
			return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'message' => $message]);
		}
		$this->session->setFlashdata('error', $message);
		return redirect()->to(base_url('assessmentupload'));
	}

	protected function ensureSchema(): void
	{
		$db = Database::connect();
		$db->query("CREATE TABLE IF NOT EXISTS `teacher_assessment_uploads` (
			`id` int unsigned NOT NULL AUTO_INCREMENT,
			`school_id` int NOT NULL,
			`staff_id` int NOT NULL,
			`academic_year` int NOT NULL DEFAULT 0,
			`term_id` int NOT NULL DEFAULT 0,
			`class_id` int NOT NULL DEFAULT 0,
			`course_id` int NOT NULL DEFAULT 0,
			`assessment_type` varchar(80) NOT NULL,
			`original_name` varchar(180) NOT NULL,
			`stored_name` varchar(80) NOT NULL,
			`byte_size` int unsigned NOT NULL DEFAULT 0,
			`status` varchar(20) NOT NULL DEFAULT 'pending',
			`reject_reason` varchar(255) DEFAULT NULL,
			`rejected_by` int NOT NULL DEFAULT 0,
			`rejected_at` datetime DEFAULT NULL,
			`created_at` datetime NOT NULL,
			PRIMARY KEY (`id`),
			KEY `idx_school_staff` (`school_id`,`staff_id`),
			KEY `idx_school_created` (`school_id`,`created_at`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		$fields = $db->getFieldNames('teacher_assessment_uploads');
		if (!in_array('status', $fields, true)) {
			$db->query("ALTER TABLE `teacher_assessment_uploads` ADD COLUMN `status` varchar(20) NOT NULL DEFAULT 'pending' AFTER `byte_size`");
		}
		if (!in_array('reject_reason', $fields, true)) {
			$db->query("ALTER TABLE `teacher_assessment_uploads` ADD COLUMN `reject_reason` varchar(255) DEFAULT NULL AFTER `status`");
		}
		if (!in_array('rejected_by', $fields, true)) {
			$db->query("ALTER TABLE `teacher_assessment_uploads` ADD COLUMN `rejected_by` int NOT NULL DEFAULT 0 AFTER `reject_reason`");
		}
		if (!in_array('rejected_at', $fields, true)) {
			$db->query("ALTER TABLE `teacher_assessment_uploads` ADD COLUMN `rejected_at` datetime DEFAULT NULL AFTER `rejected_by`");
		}
	}

	protected function assignmentChoices(int $schoolId, int $staffId, int $yearId): array
	{
		if ($schoolId < 1 || $staffId < 1) {
			return [];
		}
		$builder = Database::connect()->table('course_records cr')
			->select('cr.class as class_id, cr.course as course_id, cl.title as class_title, c.title as course_title, l.title as level_name, d.code as dept_code, d.title as department_name')
			->join('classes cl', 'cl.id = cr.class')
			->join('courses c', 'c.id = cr.course')
			->join('levels l', 'l.id = cl.level', 'left')
			->join('departments d', 'd.id = cl.department', 'left')
			->where('cr.lecturer', $staffId)
			->where('cl.school_id', $schoolId)
			->orderBy('l.title', 'ASC')
			->orderBy('d.code', 'ASC')
			->orderBy('cl.title', 'ASC')
			->orderBy('c.title', 'ASC');
		if ($yearId > 0) {
			$builder->where('cr.year', $yearId);
		}
		$rows = $builder->get()->getResultArray();
		$out = [];
		$seen = [];
		foreach ($rows as $row) {
			$classId = (int) ($row['class_id'] ?? 0);
			$courseId = (int) ($row['course_id'] ?? 0);
			$key = $classId . ':' . $courseId;
			if ($classId < 1 || $courseId < 1 || isset($seen[$key])) {
				continue;
			}
			$seen[$key] = true;
			$out[] = [
				'class_id' => $classId,
				'course_id' => $courseId,
				'class_title' => $this->classLabel($row),
				'course_title' => (string) ($row['course_title'] ?? ''),
			];
		}
		return $out;
	}

	protected function pickedAssignment(string $raw, array $choices): ?array
	{
		$raw = trim($raw);
		if ($raw === '') {
			return $choices === [] ? ['class_id' => 0, 'course_id' => 0] : null;
		}
		foreach ($choices as $choice) {
			if ($raw === ((int) $choice['class_id']) . ':' . ((int) $choice['course_id'])) {
				return $choice;
			}
		}
		return null;
	}

	protected function listUploads(int $schoolId, int $staffId, bool $viewAll, int $filterStaff): array
	{
		$builder = Database::connect()->table('teacher_assessment_uploads u')
			->select('u.id, u.staff_id, u.assessment_type, u.original_name, u.byte_size, u.created_at, u.status, u.reject_reason, s.fname, s.lname, cl.title as class_title, c.title as course_title, l.title as level_name, d.code as dept_code, d.title as department_name')
			->join('staffs s', 's.id = u.staff_id AND s.school_id = u.school_id', 'left')
			->join('classes cl', 'cl.id = u.class_id AND cl.school_id = u.school_id', 'left')
			->join('levels l', 'l.id = cl.level', 'left')
			->join('departments d', 'd.id = cl.department', 'left')
			->join('courses c', 'c.id = u.course_id AND c.school_id = u.school_id', 'left')
			->where('u.school_id', $schoolId)
			->orderBy('u.id', 'DESC');
		if (!$viewAll) {
			$builder->where('u.staff_id', $staffId);
		} elseif ($filterStaff > 0) {
			$builder->where('u.staff_id', $filterStaff);
		}
		$rows = $builder->get(300)->getResultArray();
		foreach ($rows as $i => $row) {
			$rows[$i]['class_title'] = $this->classLabel($row);
		}
		return $rows;
	}

	protected function classLabel(array $row): string
	{
		return TimetableClassLabel::fromRow($row);
	}

	protected function teacherChoices(int $schoolId): array
	{
		$rows = Database::connect()->table('teacher_assessment_uploads u')
			->select('u.staff_id, s.fname, s.lname')
			->join('staffs s', 's.id = u.staff_id AND s.school_id = u.school_id', 'left')
			->where('u.school_id', $schoolId)
			->orderBy('s.fname', 'ASC')
			->orderBy('s.lname', 'ASC')
			->get()->getResultArray();
		$out = [];
		$seen = [];
		foreach ($rows as $row) {
			$id = (int) ($row['staff_id'] ?? 0);
			if ($id < 1 || isset($seen[$id])) {
				continue;
			}
			$seen[$id] = true;
			$out[] = $row;
		}
		return $out;
	}

	protected function authorizedRow(int $id): ?array
	{
		if ($id < 1) {
			return null;
		}
		$schoolId = (int) $this->session->get('soma_school_id');
		$staffId = (int) $this->session->get('soma_id');
		$builder = Database::connect()->table('teacher_assessment_uploads')
			->where('id', $id)
			->where('school_id', $schoolId);
		$viewAll = function_exists('can_view_all_teacher_assessments') && can_view_all_teacher_assessments();
		if (!$viewAll) {
			$builder->where('staff_id', $staffId);
		}
		$row = $builder->get(1)->getRowArray();
		return $row ?: null;
	}

	protected function storageDir(int $schoolId): string
	{
		$dir = rtrim(WRITEPATH, '\\/') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'assessments' . DIRECTORY_SEPARATOR . $schoolId;
		if (!is_dir($dir)) {
			mkdir($dir, 0755, true);
		}
		return $dir;
	}

	protected function safePath(int $schoolId, string $stored): string
	{
		if (!preg_match('/^[a-f0-9]{32}\.(pdf|doc|docx)$/', $stored)) {
			return '';
		}
		$dir = $this->storageDir($schoolId);
		$realDir = realpath($dir);
		$real = realpath($dir . DIRECTORY_SEPARATOR . $stored);
		if ($realDir === false || $real === false) {
			return '';
		}
		$prefix = rtrim($realDir, '\\/') . DIRECTORY_SEPARATOR;
		if (strpos($real, $prefix) !== 0 || !is_file($real)) {
			return '';
		}
		return $real;
	}

	protected function documentMatchesExtension(string $path, string $ext): bool
	{
		$handle = @fopen($path, 'rb');
		if (!$handle) {
			return false;
		}
		$head = fread($handle, 8);
		fclose($handle);
		if (!is_string($head) || strlen($head) < 4) {
			return false;
		}
		if ($ext === 'pdf') {
			return strncmp($head, '%PDF', 4) === 0;
		}
		if ($ext === 'docx') {
			return strncmp($head, "PK\x03\x04", 4) === 0;
		}
		if ($ext === 'doc') {
			return strncmp($head, "\xD0\xCF\x11\xE0", 4) === 0;
		}
		return false;
	}

	protected function cleanOriginalName(string $name, string $ext): string
	{
		$name = basename(str_replace('\\', '/', $name));
		$name = preg_replace('/[^\w.\- ()]+/u', '_', $name);
		$name = trim((string) $name);
		if ($name === '' || $name === '.' || $name === '..') {
			$name = 'assessment.' . $ext;
		}
		if (strlen($name) > 180) {
			$name = substr($name, -180);
		}
		return $name;
	}
}
