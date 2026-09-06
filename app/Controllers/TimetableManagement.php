<?php

namespace App\Controllers;

use App\Libraries\GeminiTimetable;
use App\Libraries\TimetableClassLabel;
use App\Libraries\TimetableTrack;
use App\Libraries\Wkhtmltopdf;
use App\Models\ClassesModel;
use App\Models\StaffModel;
use App\Models\TimetableSchemaModel;
use App\Services\Timetable\TimetableConflictService;
use App\Services\Timetable\TimetableGeneratorService;
use App\Services\Timetable\TimetableStagingService;

class TimetableManagement extends Home
{
	protected function bootTimetable(): array
	{
		$this->_preset();
		$schoolId = (int) $this->session->get('soma_school_id');
		$staffId = (int) $this->session->get('soma_id');
		$schema = new TimetableSchemaModel();
		$schema->ensureSchema();
		$schema->seedDefaultSlots($schoolId);
		return [$schoolId, $staffId, $schema];
	}

	protected function denyMenu(string $key): void
	{
		if (!function_exists('menu_clearance_allowed') || !menu_clearance_allowed($key)) {
			$this->session->setFlashdata('error', 'You do not have access to Timetable Management.');
			header('Location: ' . base_url('dashboard'));
			exit;
		}
	}

	public function dashboard()
	{
		$this->denyMenu('timetable_dashboard');
		list($schoolId, $staffId, $schema) = $this->bootTimetable();
		$db = \Config\Database::connect();
		$data = $this->data;
		$data['title'] = 'Timetable Management';
		$data['subtitle'] = 'Pedagogical Documents';
		$data['page'] = 'timetable_dashboard';

		$year = (int) ($data['academic_year'] ?? 0);
		$term = (int) ($data['term'] ?? 1);

		$data['classes'] = $this->fetchClassRows($db, $schoolId);

		$data['staffs'] = $db->table('staffs s')
			->select('s.id, s.fname, s.lname, s.post, p.title AS post_title')
			->join('posts p', 'p.id = s.post', 'left')
			->where('s.school_id', $schoolId)
			->whereIn('s.status', [1, 2])
			->orderBy('fname')->orderBy('lname')
			->get()->getResultArray();

		$data['schedule'] = $db->table('timetable_schedules')
			->where('school_id', $schoolId)
			->where('academic_year', $year)
			->where('term', $term)
			->orderBy('id', 'DESC')
			->get(1)->getRowArray();

		$data['staff_with_timetable'] = 0;
		if (!empty($data['schedule'])) {
			$data['staff_with_timetable'] = (int) $db->table('timetable_entries')
				->where('schedule_id', (int) $data['schedule']['id'])
				->select('COUNT(DISTINCT staff_id) AS c', false)
				->get(1)->getRow()->c;
		}

		$data['assignment_count'] = $this->countAssignments($schoolId, $year, $term);
		$data['test_assignment_count'] = $this->countTestAssignments($schoolId, $year);
		$data['slots'] = $schema->allSlots($schoolId);
		$data['settings_url'] = base_url('settings#timetable-settings');
		$data['special_times'] = $schema->specialTimes($schoolId);
		$settings = $db->table('timetable_settings')->where('school_id', $schoolId)->get(1)->getRowArray();
		$data['day_labels'] = TimetableSchemaModel::dayLabelsFromSettings($settings);
		$data['class_count'] = count($data['classes']);
		$data['staff_count'] = count($data['staffs']);
		$data['period_count'] = count(array_filter($data['slots'], static function ($s) {
			return empty($s['is_break']);
		}));
		$data['special_count'] = count($data['special_times']);
		$data['entry_count'] = 0;
		if (!empty($data['schedule'])) {
			$data['entry_count'] = (int) $db->table('timetable_entries')
				->where('schedule_id', (int) $data['schedule']['id'])
				->where('day_of_week >=', 0)
				->where('slot_id >', 0)
				->countAllResults();
		}
		$data['preview_class_id'] = !empty($data['classes']) ? (int) $data['classes'][0]['id'] : 0;
		if ($data['preview_class_id'] > 0 && !empty($data['schedule'])) {
			try {
				$data['preview_data'] = $this->buildGridView($schoolId, $schema, 'class', $data['preview_class_id'], true);
			} catch (\Throwable $e) {
				log_message('error', 'Timetable dashboard preview failed: {msg}', ['msg' => $e->getMessage()]);
				$data['preview_data'] = null;
			}
		}

		$data['content'] = view('pages/timetable/dashboard', $data);
		return view('main', $data);
	}

	public function save_slots()
	{
		$this->_preset(1, 3);
		list($schoolId) = $this->bootTimetable();
		$db = \Config\Database::connect();

		$labels = $this->request->getPost('slot_label') ?? [];
		$starts = $this->request->getPost('slot_start') ?? [];
		$ends = $this->request->getPost('slot_end') ?? [];
		$breaks = $this->request->getPost('slot_is_break') ?? [];
		$breakLabels = $this->request->getPost('slot_break_label') ?? [];
		$slotIds = $this->request->getPost('slot_id') ?? [];

		$trackKey = TimetableTrack::normalize($this->request->getPost('track_key') ?: TimetableTrack::ALL);
		$sharedMode = (bool) $this->request->getPost('shared_timetable');
		if ($this->request->getPost('shared_timetable') !== null) {
			$row = $db->table('timetable_settings')->where('school_id', $schoolId)->get(1)->getRowArray();
			$payload = ['shared_timetable' => $sharedMode ? 1 : 0, 'updated_at' => date('Y-m-d H:i:s')];
			if ($row) {
				$db->table('timetable_settings')->where('school_id', $schoolId)->update($payload);
			} else {
				$payload['school_id'] = $schoolId;
				$payload['days_json'] = json_encode(['Mon', 'Tue', 'Wed', 'Thu', 'Fri']);
				$payload['include_sunday'] = 0;
				$payload['include_saturday'] = 0;
				$db->table('timetable_settings')->insert($payload);
			}
			if ($sharedMode) {
				$trackKey = TimetableTrack::ALL;
			}
		}

		$existing = $db->table('timetable_slots')
			->where('school_id', $schoolId)
			->where('track_key', $trackKey)
			->orderBy('sort_order', 'ASC')
			->get()->getResultArray();
		$existingById = [];
		foreach ($existing as $row) {
			$existingById[(int) $row['id']] = $row;
		}

		$keptIds = [];
		$order = 0;
		foreach ($labels as $i => $label) {
			$label = trim((string) $label);
			if ($label === '') {
				$label = (string) ($order + 1);
			}
			$payload = [
				'school_id' => $schoolId,
				'track_key' => $trackKey,
				'level_id' => 0,
				'sort_order' => $order++,
				'label' => $label,
				'start_time' => ($starts[$i] ?? '08:00') . (strlen((string) ($starts[$i] ?? '')) === 5 ? ':00' : ''),
				'end_time' => ($ends[$i] ?? '08:40') . (strlen((string) ($ends[$i] ?? '')) === 5 ? ':00' : ''),
				'is_break' => !empty($breaks[$i]) ? 1 : 0,
				'break_label' => trim((string) ($breakLabels[$i] ?? '')) ?: null,
			];

			$postedId = (int) ($slotIds[$i] ?? 0);
			if ($postedId > 0 && isset($existingById[$postedId])) {
				$db->table('timetable_slots')->where('id', $postedId)->update($payload);
				$keptIds[] = $postedId;
			} else {
				$db->table('timetable_slots')->insert($payload);
				$keptIds[] = (int) $db->insertID();
			}
		}

		if ($keptIds !== []) {
			$db->table('timetable_slots')
				->where('school_id', $schoolId)
				->where('track_key', $trackKey)
				->whereNotIn('id', $keptIds)
				->delete();
		} else {
			$db->table('timetable_slots')->where('school_id', $schoolId)->where('track_key', $trackKey)->delete();
		}

		$labels = TimetableTrack::labels();
		$trackLabel = $labels[$trackKey] ?? $trackKey;

		$includeSaturday = $this->request->getPost('include_saturday') ? 1 : 0;
		$includeSunday = $this->request->getPost('include_sunday') ? 1 : 0;
		$row = $db->table('timetable_settings')->where('school_id', $schoolId)->get(1)->getRowArray();
		$dayList = TimetableSchemaModel::dayLabelsFromSettings([
			'include_saturday' => $includeSaturday,
			'include_sunday' => $includeSunday,
		]);
		$settingsPayload = [
			'days_json' => json_encode($dayList),
			'include_saturday' => $includeSaturday,
			'include_sunday' => $includeSunday,
			'updated_at' => date('Y-m-d H:i:s'),
		];
		if ($this->request->getPost('shared_timetable') !== null) {
			$settingsPayload['shared_timetable'] = $sharedMode ? 1 : 0;
		}
		if ($row) {
			$db->table('timetable_settings')->where('school_id', $schoolId)->update($settingsPayload);
		} else {
			$settingsPayload['school_id'] = $schoolId;
			$settingsPayload['shared_timetable'] = $sharedMode ? 1 : 0;
			$db->table('timetable_settings')->insert($settingsPayload);
		}

		return $this->response->setJSON(['success' => 'Periods saved for ' . $trackLabel . '.']);
	}

	public function save_special_times()
	{
		$this->_preset(1, 3);
		list($schoolId) = $this->bootTimetable();
		$db = \Config\Database::connect();

		$days = $this->request->getPost('special_day') ?? [];
		$slots = $this->request->getPost('special_slot') ?? [];
		$labels = $this->request->getPost('special_label') ?? [];
		$colors = $this->request->getPost('special_color') ?? [];

		$trackKey = TimetableTrack::normalize($this->request->getPost('track_key') ?: TimetableTrack::ALL);

		$db->table('timetable_special_times')->where('school_id', $schoolId)->where('track_key', $trackKey)->delete();
		$order = 0;
		foreach ($labels as $i => $label) {
			$label = trim((string) $label);
			if ($label === '') {
				continue;
			}
			$day = (int) ($days[$i] ?? -1);
			$slotId = (int) ($slots[$i] ?? 0);
			if ($slotId <= 0 || $day < 0) {
				continue;
			}
			$color = trim((string) ($colors[$i] ?? 'yellow'));
			if (!in_array($color, ['yellow', 'blue', 'green', 'orange', 'purple', 'gray'], true)) {
				$color = 'yellow';
			}
			$db->table('timetable_special_times')->insert([
				'school_id' => $schoolId,
				'track_key' => $trackKey,
				'level_id' => 0,
				'day_of_week' => $day,
				'slot_id' => $slotId,
				'label' => $label,
				'color' => $color,
				'sort_order' => $order++,
			]);
		}

		return $this->response->setJSON(['success' => 'Special times saved (Chapel, Sabbath, etc.).']);
	}

	public function preview_grid($classId = 0)
	{
		$this->denyMenu('timetable_dashboard');
		list($schoolId, , $schema) = $this->bootTimetable();
		$db = \Config\Database::connect();
		$mode = $this->request->getGet('mode') === 'teacher' ? 'teacher' : 'class';
		$entityId = (int) $classId;

		if ($entityId <= 0) {
			return $this->response->setJSON(['error' => 'Select a class or teacher to preview.']);
		}
		if ($mode === 'teacher') {
			$staff = $db->table('staffs')->where('id', $entityId)->where('school_id', $schoolId)->get(1)->getRowArray();
			if (!$staff) {
				return $this->response->setJSON(['error' => 'Invalid teacher selected. Please choose a staff member from the Teacher list.']);
			}
		} else {
			$class = $db->table('classes')->where('id', $entityId)->where('school_id', $schoolId)->get(1)->getRowArray();
			if (!$class) {
				return $this->response->setJSON(['error' => 'Invalid class selected.']);
			}
		}

		try {
			@ini_set('memory_limit', '512M');
			@set_time_limit(120);
			$data = $this->buildGridView($schoolId, $schema, $mode, $entityId, true);
			$html = view('pages/timetable/_grid_body', $data);
			return $this->response->setJSON([
				'title' => $data['title'] ?? 'Timetable',
				'html' => $html,
			]);
		} catch (\Throwable $e) {
			log_message('error', 'Timetable preview failed [{mode}:{id}]: {msg}', [
				'mode' => $mode,
				'id' => $entityId,
				'msg' => $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine(),
			]);
			return $this->response->setJSON([
				'error' => 'Could not build timetable preview: ' . $e->getMessage(),
			]);
		}
	}

	public function check_move()
	{
		$this->denyMenu('timetable_dashboard');
		list($schoolId, , $schema) = $this->bootTimetable();
		$entryId = (int) $this->request->getPost('entry_id');
		$day = (int) $this->request->getPost('day');
		$slotId = (int) $this->request->getPost('slot_id');
		$scheduleId = (int) $this->request->getPost('schedule_id');
		if ($entryId <= 0 || $scheduleId <= 0) {
			return $this->response->setJSON(['error' => 'Invalid request.']);
		}
		$checker = new TimetableConflictService();
		$conflicts = $checker->checkMove($scheduleId, $schoolId, $entryId, $day, $slotId, $schema);
		return $this->response->setJSON([
			'ok' => $conflicts === [],
			'conflicts' => $conflicts,
		]);
	}

	public function move_entry()
	{
		$this->denyMenu('timetable_dashboard');
		list($schoolId, , $schema) = $this->bootTimetable();
		$db = \Config\Database::connect();
		$entryId = (int) $this->request->getPost('entry_id');
		$day = (int) $this->request->getPost('day');
		$slotId = (int) $this->request->getPost('slot_id');
		$scheduleId = (int) $this->request->getPost('schedule_id');
		$force = (bool) $this->request->getPost('force');

		if ($entryId <= 0 || $scheduleId <= 0) {
			return $this->response->setJSON(['error' => 'Invalid lesson.']);
		}

		$entry = $db->table('timetable_entries')->where('id', $entryId)
			->where('schedule_id', $scheduleId)->where('school_id', $schoolId)->get(1)->getRowArray();
		if (!$entry) {
			return $this->response->setJSON(['error' => 'Lesson not found.']);
		}

		if ($day < 0 || $slotId <= 0) {
			$db->table('timetable_entries')->where('id', $entryId)->update([
				'day_of_week' => -1,
				'slot_id' => 0,
			]);
			return $this->response->setJSON(['success' => 'Lesson moved to holding area.']);
		}

		$checker = new TimetableConflictService();
		$conflicts = $checker->checkMove($scheduleId, $schoolId, $entryId, $day, $slotId, $schema);
		if ($conflicts !== [] && !$force) {
			return $this->response->setJSON(['error' => 'Conflict detected.', 'conflicts' => $conflicts]);
		}

		$db->table('timetable_entries')->where('id', $entryId)->update([
			'day_of_week' => $day,
			'slot_id' => $slotId,
		]);

		return $this->response->setJSON(['success' => 'Timetable updated.']);
	}

	public function generate()
	{
		$this->denyMenu('timetable_dashboard');
		list($schoolId, $staffId, $schema) = $this->bootTimetable();
		$year = (int) ($this->request->getPost('academic_year') ?: $this->data['academic_year']);
		$term = (int) ($this->request->getPost('term') ?: $this->data['term']);
		$useGemini = (bool) $this->request->getPost('use_gemini');

		$result = $this->runGeneration($schoolId, $staffId, $schema, $year, $term);
		if ($result === null) {
			return $this->response->setJSON(['error' => 'No course assignments found. Assign courses to classes first.']);
		}

		$aiTip = null;
		$gemini = new GeminiTimetable();
		if ($useGemini && $gemini->isConfigured()) {
			if ($result['warnings'] !== []) {
				$aiTip = $gemini->suggestFixes($result['warnings'], [
					'school_id' => $schoolId,
					'year' => $year,
					'term' => $term,
					'entries' => count($result['entries']),
				]);
			} else {
				// Quality review even when generation had no hard warnings.
				$db = \Config\Database::connect();
				$sample = $db->table('timetable_entries te')
					->select('te.day_of_week, te.class_id, c.title AS course_title, c.credit, COUNT(*) AS periods')
					->join('courses c', 'c.id = te.course_id', 'left')
					->where('te.schedule_id', (int) $result['schedule_id'])
					->where('te.day_of_week >=', 0)
					->where('te.slot_id >', 0)
					->groupBy('te.class_id, te.day_of_week, te.course_id, c.title, c.credit')
					->having('periods >', 1)
					->orderBy('c.credit', 'ASC')
					->limit(25)
					->get()->getResultArray();
				$aiTip = $gemini->reviewQuality($sample, [
					'school_id' => $schoolId,
					'year' => $year,
					'term' => $term,
					'entries' => count($result['entries']),
					'rule' => '2 periods/week must be on different days',
				]);
			}
		}

		return $this->response->setJSON([
			'success' => 'Timetable generated with ' . count($result['entries']) . ' lesson slots'
				. (!empty($result['staging_created']) ? ' and ' . (int) $result['staging_created'] . ' unscheduled in parking lot.' : '.'),
			'warnings' => $result['warnings'],
			'ai_tip' => $aiTip,
			'schedule_id' => $result['schedule_id'],
			'staging_created' => (int) ($result['staging_created'] ?? 0),
		]);
	}

	/**
	 * @return array{entries:list<array<string,mixed>>,warnings:list<string>,schedule_id:int}|null
	 */
	private function runGeneration(int $schoolId, int $staffId, TimetableSchemaModel $schema, int $year, int $term): ?array
	{
		$db = \Config\Database::connect();
		$assignments = $this->loadAssignments($schoolId, $year, $term);
		if ($assignments === []) {
			return null;
		}

		$schema->ensureTrackSlots($schoolId, TimetableTrack::ALL);
		if (!$schema->isSharedSchedule($schoolId)) {
			foreach (TimetableTrack::tracksForSchool($schoolId) as $track) {
				$schema->ensureTrackSlots($schoolId, $track);
			}
		}

		$settings = $db->table('timetable_settings')->where('school_id', $schoolId)->get(1)->getRowArray();
		$days = TimetableSchemaModel::weekDaysFromSettings($settings);

		$generator = new TimetableGeneratorService();
		$allEntries = [];
		$allWarnings = [];
		$byTrack = [];
		foreach ($assignments as $assignment) {
			$classId = (int) ($assignment['class_id'] ?? 0);
			$track = $schema->trackForClass($schoolId, $classId);
			$byTrack[$track][] = $assignment;
		}

		$reset = true;
		foreach ($byTrack as $trackKey => $trackAssignments) {
			$blocked = [];
			foreach ($schema->specialTimesMap($schoolId, $trackKey) as $key => $row) {
				$blocked[$key] = true;
			}
			$result = $generator->generate(
				$trackAssignments,
				$schema->teachingSlots($schoolId, $trackKey),
				$days,
				$blocked,
				$reset
			);
			$reset = false;
			$allEntries = array_merge($allEntries, $result['entries']);
			$allWarnings = array_merge($allWarnings, $result['warnings']);
		}

		$existing = $db->table('timetable_schedules')
			->where('school_id', $schoolId)->where('academic_year', $year)->where('term', $term)
			->get(1)->getRowArray();

		$scheduleId = 0;
		if ($existing) {
			$scheduleId = (int) $existing['id'];
			$db->table('timetable_entries')->where('schedule_id', $scheduleId)->delete();
			$db->table('timetable_schedules')->where('id', $scheduleId)->update([
				'status' => 'published',
				'generated_by' => $staffId,
				'generated_at' => date('Y-m-d H:i:s'),
			]);
		} else {
			$db->table('timetable_schedules')->insert([
				'school_id' => $schoolId,
				'academic_year' => $year,
				'term' => $term,
				'title' => 'Main timetable',
				'status' => 'published',
				'generated_by' => $staffId,
				'generated_at' => date('Y-m-d H:i:s'),
			]);
			$scheduleId = (int) $db->insertID();
		}

		foreach ($allEntries as $entry) {
			$db->table('timetable_entries')->insert([
				'schedule_id' => $scheduleId,
				'school_id' => $schoolId,
				'class_id' => $entry['class_id'],
				'staff_id' => $entry['staff_id'],
				'course_id' => $entry['course_id'],
				'course_record_id' => $entry['course_record_id'] ?: null,
				'day_of_week' => $entry['day_of_week'],
				'slot_id' => $entry['slot_id'],
				'entry_type' => $entry['entry_type'],
			]);
		}

		$stagingSvc = new TimetableStagingService();
		$stagingCreated = $stagingSvc->reconcile($scheduleId, $schoolId, $assignments);
		$stagingSvc->autoPlaceStaging($scheduleId, $schoolId, $schema);

		return [
			'entries' => $allEntries,
			'warnings' => $allWarnings,
			'schedule_id' => $scheduleId,
			'staging_created' => $stagingCreated,
		];
	}

	private function ensureTimetableGenerated(int $schoolId, TimetableSchemaModel $schema): void
	{
		$db = \Config\Database::connect();
		$year = (int) ($this->data['academic_year'] ?? 0);
		$term = (int) ($this->data['term'] ?? 1);
		if ($year <= 0) {
			return;
		}
		if ($this->countAssignments($schoolId, $year, $term) <= 0) {
			return;
		}

		$schedule = $db->table('timetable_schedules')
			->where('school_id', $schoolId)->where('academic_year', $year)->where('term', $term)
			->orderBy('id', 'DESC')->get(1)->getRowArray();

		$scheduledCount = 0;
		if ($schedule) {
			$scheduledCount = (int) $db->table('timetable_entries')
				->where('schedule_id', (int) $schedule['id'])
				->where('day_of_week >=', 0)
				->where('slot_id >', 0)
				->countAllResults();
		}

		if ($scheduledCount > 0) {
			return;
		}

		if ($schedule) {
			$db->table('timetable_entries')->where('schedule_id', (int) $schedule['id'])->delete();
		}

		$staffId = (int) $this->session->get('soma_id');
		$this->runGeneration($schoolId, $staffId, $schema, $year, $term);
	}

	public function class_timetable($classId = 0)
	{
		$this->denyMenu('timetable_dashboard');
		list($schoolId, , $schema) = $this->bootTimetable();
		$classId = (int) $classId;
		$data = $this->buildGridView($schoolId, $schema, 'class', $classId, true);
		$data['page'] = 'timetable_class';
		$data['content'] = view('pages/timetable/class_view', $data);
		return view('main', $data);
	}

	public function teacher_timetable($staffId = 0)
	{
		$this->denyMenu('timetable_dashboard');
		list($schoolId, , $schema) = $this->bootTimetable();
		$staffId = (int) $staffId;
		$data = $this->buildGridView($schoolId, $schema, 'teacher', $staffId, true);
		$data['page'] = 'timetable_teacher';
		$data['content'] = view('pages/timetable/teacher_view', $data);
		return view('main', $data);
	}

	public function print_class($classId = 0)
	{
		$this->denyMenu('timetable_dashboard');
		list($schoolId, , $schema) = $this->bootTimetable();
		$data = $this->buildGridView($schoolId, $schema, 'class', (int) $classId);
		$slug = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $data['title'] ?? 'class');
		return $this->outputTimetablePdf([$data], 'Class_' . $slug, null, false);
	}

	public function print_teacher($staffId = 0)
	{
		$this->denyMenu('timetable_dashboard');
		list($schoolId, , $schema) = $this->bootTimetable();
		$data = $this->buildGridView($schoolId, $schema, 'teacher', (int) $staffId);
		$slug = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $data['title'] ?? 'teacher');
		return $this->outputTimetablePdf([$data], 'Teacher_' . $slug, null, false);
	}

	public function pdf_all_classes()
	{
		$this->denyMenu('timetable_dashboard');
		list($schoolId, , $schema) = $this->bootTimetable();
		$db = \Config\Database::connect();
		$sheets = [];
		foreach ($this->fetchClassRows($db, $schoolId) as $class) {
			$grid = $this->buildGridView($schoolId, $schema, 'class', (int) $class['id']);
			if (!empty($grid['schedule'])) {
				$sheets[] = $grid;
			}
		}
		return $this->outputTimetablePdf($sheets, 'All_Class_Timetables', 'All class timetables');
	}

	public function pdf_all_teachers()
	{
		$this->denyMenu('timetable_dashboard');
		list($schoolId, , $schema) = $this->bootTimetable();
		$db = \Config\Database::connect();
		$sheets = [];
		$staffs = $db->table('staffs s')
			->select('s.id')
			->where('s.school_id', $schoolId)
			->whereIn('s.status', [1, 2])
			->orderBy('s.fname')->orderBy('s.lname')
			->get()->getResultArray();
		foreach ($staffs as $staff) {
			$grid = $this->buildGridView($schoolId, $schema, 'teacher', (int) $staff['id']);
			if (!empty($grid['schedule'])) {
				$sheets[] = $grid;
			}
		}
		return $this->outputTimetablePdf($sheets, 'All_Teacher_Timetables', 'All teacher / staff timetables');
	}

	private function countAssignments(int $schoolId, int $year, int $term): int
	{
		return count($this->loadAssignments($schoolId, $year, $term));
	}

	private function countTestAssignments(int $schoolId, int $year): int
	{
		$db = \Config\Database::connect();
		if (!$db->fieldExists('create_source', 'courses')) {
			return 0;
		}
		return (int) $db->table('course_records cr')
			->join('courses c', 'c.id = cr.course')
			->join('classes cl', 'cl.id = cr.class')
			->where('cl.school_id', $schoolId)
			->where('cr.year', $year)
			->where('c.create_source', 'timetable_test')
			->countAllResults();
	}

	/** @return list<array<string,mixed>> */
	private function loadAssignments(int $schoolId, int $year, int $term): array
	{
		$db = \Config\Database::connect();
		$rows = $db->table('course_records cr')
			->select('cr.id AS course_record_id, cr.course AS course_id, cr.lecturer, cr.class AS class_id,
				cl.level AS class_level_id,
				c.title AS course_title, c.code AS course_code, c.credit, c.marks, c.program_type,
				cc.title AS category_title,
				cl.title AS class_title, l.title AS level_name,
				CONCAT(s.fname, " ", s.lname) AS teacher_name')
			->join('courses c', 'c.id = cr.course')
			->join('course_category cc', 'cc.id = c.category', 'left')
			->join('classes cl', 'cl.id = cr.class')
			->join('levels l', 'l.id = cl.level', 'left')
			->join('staffs s', 's.id = cr.lecturer', 'left')
			->where('cl.school_id', $schoolId)
			->where('cr.year', $year)
			->where("find_in_set($term, cr.term) > 0", null, false)
			->orderBy('cl.title')->orderBy('c.title')
			->get()->getResultArray();

		return $this->dedupeTimetableAssignments($rows);
	}

	/**
	 * One lesson per subject per class — REB schools often have Primary + Examinable duplicates.
	 *
	 * @param list<array<string,mixed>> $rows
	 * @return list<array<string,mixed>>
	 */
	private function dedupeTimetableAssignments(array $rows): array
	{
		$categoryPriority = [
			'primary subjects' => 1,
			'examinable subjects' => 2,
			'non-examinable subjects' => 3,
		];
		$byKey = [];

		foreach ($rows as $row) {
			$classId = (int) ($row['class_id'] ?? 0);
			$title = strtolower(trim(preg_replace('/\s+/', ' ', (string) ($row['course_title'] ?? ''))));
			if ($classId <= 0 || $title === '') {
				continue;
			}
			$key = $classId . '|' . $title;
			$cat = strtolower(trim((string) ($row['category_title'] ?? '')));
			$prio = $categoryPriority[$cat] ?? 50;
			$lecturer = (int) ($row['lecturer'] ?? 0);
			$score = $prio * 1000 - ($lecturer > 0 ? 0 : 500);

			if (!isset($byKey[$key]) || $score < ($byKey[$key]['_score'] ?? PHP_INT_MAX)) {
				$row['_score'] = $score;
				$byKey[$key] = $row;
			}
		}

		$out = [];
		foreach ($byKey as $row) {
			unset($row['_score']);
			$out[] = $row;
		}

		usort($out, static function ($a, $b) {
			$c = strcmp((string) ($a['class_title'] ?? ''), (string) ($b['class_title'] ?? ''));
			return $c !== 0 ? $c : strcmp((string) ($a['course_title'] ?? ''), (string) ($b['course_title'] ?? ''));
		});

		return $out;
	}

	/** @return array<string,mixed> */
	private function buildGridView(int $schoolId, TimetableSchemaModel $schema, string $mode, int $entityId, bool $editable = false): array
	{
		$db = \Config\Database::connect();
		$data = $this->data;
		$year = (int) ($data['academic_year'] ?? 0);
		$term = (int) ($data['term'] ?? 1);

		$schema->repairOrphanEntrySlots($schoolId);
		$this->ensureTimetableGenerated($schoolId, $schema);

		$schedule = $db->table('timetable_schedules')
			->where('school_id', $schoolId)->where('academic_year', $year)->where('term', $term)
			->orderBy('id', 'DESC')->get(1)->getRowArray();

		$settings = $db->table('timetable_settings')->where('school_id', $schoolId)->get(1)->getRowArray();
		$dayLabels = TimetableSchemaModel::dayLabelsFromSettings($settings);
		$dayMap = TimetableSchemaModel::dayMapFromSettings($settings);
		$labelByDay = array_flip($dayMap);
		$trackKey = TimetableTrack::ALL;
		$title = '';
		$subtitle = '';
		$entries = [];

		if ($schedule && $entityId > 0) {
			$builder = $db->table('timetable_entries te')
				->select('te.*, c.title AS course_title, c.code AS course_code,
					cl.title AS class_title, cl.level AS class_level_id,
					l.title AS level_name, d.code AS dept_code, d.title AS dept_name,
					CONCAT(s.fname, " ", s.lname) AS teacher_name, ts.start_time, ts.end_time, ts.label AS slot_label')
				->join('timetable_slots ts', 'ts.id = te.slot_id', 'left')
				->join('courses c', 'c.id = te.course_id', 'left')
				->join('classes cl', 'cl.id = te.class_id', 'left')
				->join('levels l', 'l.id = cl.level', 'left')
				->join('departments d', 'd.id = cl.department', 'left')
				->join('staffs s', 's.id = te.staff_id', 'left')
				->where('te.schedule_id', (int) $schedule['id'])
				->where('te.entry_type', 'lesson')
				->where('te.day_of_week >=', 0)
				->where('te.slot_id >', 0);

			if ($mode === 'class') {
				$builder->where('te.class_id', $entityId);
				$class = $db->table('classes c')
					->select('c.title, c.level, l.title AS level_name, d.code AS dept_code, d.title AS dept_name')
					->join('levels l', 'l.id = c.level', 'left')
					->join('departments d', 'd.id = c.department', 'left')
					->where('c.id', $entityId)->get(1)->getRowArray();
				$trackKey = $schema->trackForClass($schoolId, $entityId);
				$title = TimetableClassLabel::fromRow($class ?: []);
				$subtitle = 'Class timetable';
			} else {
				$builder->where('te.staff_id', $entityId);
				$staff = $db->table('staffs')->select('fname,lname')->where('id', $entityId)->get(1)->getRowArray();
				$title = trim(($staff['fname'] ?? '') . ' ' . ($staff['lname'] ?? ''));
				$subtitle = 'Teacher timetable';
			}

			$entries = $builder->get()->getResultArray();
		}

		$dayLabels = $this->expandDayLabelsForEntries($dayLabels, $dayMap, $entries);
		$labelByDay = array_flip($dayMap);

		if ($mode === 'teacher') {
			$tracks = array_values(array_unique(array_map(
				function ($e) use ($schema, $schoolId) {
					return $schema->trackForClass($schoolId, (int) ($e['class_id'] ?? 0));
				},
				$entries
			)));
			if ($tracks === []) {
				$tracks = [TimetableTrack::ALL];
			}
			$slots = $this->unionSlotsForTracks($schema, $schoolId, $tracks);
			$specialMap = [];
			foreach ($tracks as $tk) {
				foreach ($schema->specialTimesMap($schoolId, $tk) as $key => $special) {
					$specialMap[$key] = $special;
				}
			}
		} else {
			$slots = $schema->allSlots($schoolId, $trackKey);
			$specialMap = $schema->specialTimesMap($schoolId, $trackKey);
		}

		$grid = $this->buildGridFromSlots($slots, $dayLabels, $specialMap, $labelByDay);

		if ($entries !== []) {
			$slotMaps = $this->buildSlotIndexMaps($slots);

			foreach ($entries as $entry) {
				$si = $this->resolveSlotRowIndex($slotMaps, $entry, $slots);
				$dayLabel = $labelByDay[(int) $entry['day_of_week']] ?? null;
				if ($si === null || $dayLabel === null) {
					continue;
				}
				if (!empty($slots[$si]['is_break'])) {
					continue;
				}
				if (!empty($grid[$si]['cells'][$dayLabel]['type']) && $grid[$si]['cells'][$dayLabel]['type'] === 'special') {
					continue;
				}
				$cell = [
					'type' => 'lesson',
					'entry_id' => (int) ($entry['id'] ?? 0),
					'day' => (int) ($entry['day_of_week'] ?? 0),
					'slot_id' => (int) ($entry['slot_id'] ?? 0),
					'staff_id' => (int) ($entry['staff_id'] ?? 0),
					'class_id' => (int) ($entry['class_id'] ?? 0),
					'course' => $entry['course_title'] ?? $entry['custom_label'] ?? '',
					'code' => $entry['course_code'] ?? '',
					'teacher' => $entry['teacher_name'] ?? '',
					'class' => TimetableClassLabel::fromRow($entry),
				];
				if ($mode === 'class') {
					$cell['line2'] = $entry['teacher_name'] ?? '';
				} else {
					$cell['line2'] = TimetableClassLabel::fromRow($entry);
				}
				$grid[$si]['cells'][$dayLabel] = $cell;
			}
		}

		$data['title'] = $title ?: 'Timetable';
		$data['subtitle'] = $subtitle;
		$data['grid'] = $grid;
		$data['day_labels'] = $dayLabels;
		$data['day_map'] = $dayMap;
		$data['schedule'] = $schedule;
		$data['mode'] = $mode;
		$data['entity_id'] = $entityId;
		$data['generated_at'] = $schedule['generated_at'] ?? null;
		$data['track_key'] = $trackKey;

		$data['classes'] = $this->fetchClassRows($db, $schoolId);

		$data['staffs'] = $db->table('staffs s')
			->select('s.id, s.fname, s.lname, p.title AS post_title')
			->join('posts p', 'p.id = s.post', 'left')
			->where('s.school_id', $schoolId)->whereIn('s.status', [1, 2])
			->orderBy('fname')->orderBy('lname')->get()->getResultArray();

		$data['school_name'] = $this->data['school_name'] ?? '';
		$data['editable'] = $editable && !empty($schedule);
		$data['day_map'] = $dayMap;
		$data['schedule_id'] = (int) ($schedule['id'] ?? 0);
		$data['staging_entries'] = [];
		$data['conflict_entry_ids'] = [];
		$data['staging_remaining'] = 0;

		if ($schedule && $entityId > 0) {
			try {
				$assignments = $this->loadAssignments($schoolId, $year, $term);
				$staging = new TimetableStagingService();
				if ($mode === 'class') {
					$staging->reconcile((int) $schedule['id'], $schoolId, $assignments, $entityId, 0);
					$staging->autoPlaceStaging((int) $schedule['id'], $schoolId, $schema, $entityId, 0);
				} else {
					$staging->reconcile((int) $schedule['id'], $schoolId, $assignments, 0, $entityId);
					$staging->autoPlaceStaging((int) $schedule['id'], $schoolId, $schema, 0, $entityId);
				}
				$stagingCounts = $staging->counts(
					(int) $schedule['id'],
					$assignments,
					$mode === 'class' ? $entityId : 0,
					$mode === 'teacher' ? $entityId : 0
				);
				$data['staging_remaining'] = (int) ($stagingCounts['remaining'] ?? 0);

				$stagingBuilder = $db->table('timetable_entries te')
					->select('te.*, c.title AS course_title, c.code AS course_code,
						cl.title AS class_title, l.title AS level_name, d.code AS dept_code,
						CONCAT(s.fname, " ", s.lname) AS teacher_name')
					->join('courses c', 'c.id = te.course_id', 'left')
					->join('classes cl', 'cl.id = te.class_id', 'left')
					->join('levels l', 'l.id = cl.level', 'left')
					->join('departments d', 'd.id = cl.department', 'left')
					->join('staffs s', 's.id = te.staff_id', 'left')
					->where('te.schedule_id', (int) $schedule['id'])
					->where('te.entry_type', 'lesson')
					->where('te.day_of_week', -1)
					->where('te.slot_id', 0);
				if ($mode === 'class') {
					$stagingBuilder->where('te.class_id', $entityId);
				} else {
					$stagingBuilder->where('te.staff_id', $entityId);
				}
				$data['staging_entries'] = $stagingBuilder->get()->getResultArray();

				// Only flag conflicts that touch this class/teacher (full-school scan is too heavy for preview).
				$checker = new TimetableConflictService();
				foreach ($checker->findScheduleConflicts((int) $schedule['id'], $schoolId, $schema) as $issue) {
					$eid = (int) ($issue['entry_id'] ?? 0);
					$oid = (int) ($issue['other_id'] ?? 0);
					if ($eid > 0) {
						$data['conflict_entry_ids'][$eid] = true;
					}
					if ($oid > 0) {
						$data['conflict_entry_ids'][$oid] = true;
					}
				}
			} catch (\Throwable $e) {
				log_message('error', 'Timetable staging/conflicts skipped: {msg}', ['msg' => $e->getMessage()]);
			}
		}

		return $data;
	}

	/** @param list<string> $tracks @return list<array<string,mixed>> */
	private function unionSlotsForTracks(TimetableSchemaModel $schema, int $schoolId, array $tracks): array
	{
		$seen = [];
		$all = [];
		foreach ($tracks as $track) {
			foreach ($schema->allSlots($schoolId, $track) as $slot) {
				$key = ($slot['start_time'] ?? '') . '|' . ($slot['end_time'] ?? '') . '|' . ($slot['label'] ?? '');
				if (isset($seen[$key])) {
					continue;
				}
				$seen[$key] = true;
				$all[] = $slot;
			}
		}
		usort($all, static function ($a, $b) {
			$c = strcmp((string) ($a['start_time'] ?? ''), (string) ($b['start_time'] ?? ''));
			return $c !== 0 ? $c : strcmp((string) ($a['end_time'] ?? ''), (string) ($b['end_time'] ?? ''));
		});
		return $all;
	}

	/** @return list<array{slot:array,cells:array}> */
	private function buildGridFromSlots(array $slots, array $dayLabels, array $specialMap, array $labelByDay): array
	{
		$grid = [];
		foreach ($slots as $slot) {
			$row = ['slot' => $slot, 'cells' => []];
			foreach ($dayLabels as $label) {
				$row['cells'][$label] = null;
			}
			if (!empty($slot['is_break'])) {
				$grid[] = $row;
				continue;
			}
			$slotId = (int) ($slot['id'] ?? 0);
			foreach ($dayLabels as $label) {
				$dayNum = $labelByDay[$label] ?? null;
				if ($dayNum === null) {
					continue;
				}
				$row['cells'][$label] = [
					'type' => 'empty',
					'day' => $dayNum,
					'slot_id' => $slotId,
				];
			}
			$grid[] = $row;
		}

		foreach ($specialMap as $key => $special) {
			list($dayNum, $slotId) = array_map('intval', explode(':', $key, 2));
			$dayLabel = $labelByDay[$dayNum] ?? null;
			if ($dayLabel === null) {
				continue;
			}
			$si = null;
			foreach ($slots as $i => $slot) {
				if ((int) $slot['id'] === $slotId) {
					$si = $i;
					break;
				}
			}
			if ($si === null || !empty($slots[$si]['is_break'])) {
				continue;
			}
			$grid[$si]['cells'][$dayLabel] = [
				'type' => 'special',
				'course' => $special['label'],
				'color' => $special['color'] ?? 'yellow',
				'line2' => '',
			];
		}

		return $grid;
	}

	/** @return array<string,mixed> */
	private function letterheadData(): array
	{
		$logo = (string) ($this->data['school_logo'] ?? '');
		$logoDataUri = null;
		$logoUrl = '';
		if (strlen($logo) > 4) {
			$logoUrl = base_url('assets/images/logo/' . $logo);
			$path = FCPATH . 'assets/images/logo/' . $logo;
			if (is_file($path)) {
				$mime = mime_content_type($path) ?: 'image/png';
				$logoDataUri = 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($path));
			}
		}

		return [
			'school_name' => $this->data['school_name'] ?? '',
			'school_slogan' => $this->data['school_moto'] ?? '',
			'school_address' => $this->data['school_address'] ?? '',
			'school_phone' => $this->data['school_phone'] ?? '',
			'school_email' => $this->data['school_email'] ?? '',
			'school_website' => $this->data['school_website'] ?? '',
			'school_pobox' => $this->data['school_pobox'] ?? '',
			'logo_url' => $logoUrl,
			'logo_data_uri' => $logoDataUri,
			'academic_year_title' => $this->data['academic_year_title'] ?? '',
			'term' => (int) ($this->data['term'] ?? 1),
		];
	}

	private function inlineTimetableCss(): string
	{
		$file = FCPATH . 'assets/css/timetable.css';
		return is_file($file) ? (string) file_get_contents($file) : '';
	}

	/** @param list<array<string,mixed>> $sheets */
	private function renderPdfBody(array $sheets, ?string $coverTitle = null, bool $includeCover = true): string
	{
		$letterhead = $this->letterheadData();
		foreach ($sheets as &$sheet) {
			$sheet['for_pdf'] = true;
			$sheet['letterhead'] = $letterhead;
		}
		unset($sheet);

		if (count($sheets) === 1 && !$includeCover) {
			return view('pages/timetable/_grid_body', $sheets[0]);
		}

		return view('pages/timetable/print_bulk', [
			'sheets' => $sheets,
			'school_name' => $this->data['school_name'] ?? '',
			'cover_title' => $coverTitle,
			'include_cover' => $includeCover && count($sheets) > 1,
			'letterhead' => $letterhead,
		]);
	}

	/** @param list<array<string,mixed>> $entries */
	private function expandDayLabelsForEntries(array $dayLabels, array &$dayMap, array $entries): array
	{
		$dayNames = [0 => 'Mon', 1 => 'Tue', 2 => 'Wed', 3 => 'Thu', 4 => 'Fri', 5 => 'Sat', 6 => 'Sun'];
		foreach ($entries as $entry) {
			$d = (int) ($entry['day_of_week'] ?? -1);
			if ($d < 0 || !isset($dayNames[$d])) {
				continue;
			}
			$label = $dayNames[$d];
			if (!isset($dayMap[$label])) {
				$dayMap[$label] = $d;
			}
		}
		$ordered = [];
		foreach ($dayNames as $d => $label) {
			if (isset($dayMap[$label])) {
				$ordered[] = $label;
			}
		}
		return $ordered;
	}

	/** @param list<array<string,mixed>> $slots @return array{by_id:array<int,int>,by_time:array<string,int>} */
	private function buildSlotIndexMaps(array $slots): array
	{
		$byId = [];
		$byTime = [];
		foreach ($slots as $i => $slot) {
			$byId[(int) ($slot['id'] ?? 0)] = $i;
			if (!empty($slot['is_break'])) {
				continue;
			}
			$key = substr((string) ($slot['start_time'] ?? ''), 0, 8) . '|' . substr((string) ($slot['end_time'] ?? ''), 0, 8);
			$byTime[$key] = $i;
		}
		return ['by_id' => $byId, 'by_time' => $byTime];
	}

	/** @param array{by_id:array<int,int>,by_time:array<string,int>} $maps @param list<array<string,mixed>> $slots */
	private function resolveSlotRowIndex(array $maps, array $entry, array $slots): ?int
	{
		$id = (int) ($entry['slot_id'] ?? 0);
		if (isset($maps['by_id'][$id])) {
			return $maps['by_id'][$id];
		}
		$key = substr((string) ($entry['start_time'] ?? ''), 0, 8) . '|' . substr((string) ($entry['end_time'] ?? ''), 0, 8);
		if ($key !== '|' && isset($maps['by_time'][$key])) {
			return $maps['by_time'][$key];
		}
		return null;
	}

	/** @return list<array<string,mixed>> */
	private function fetchClassRows(\CodeIgniter\Database\BaseConnection $db, int $schoolId): array
	{
		$rows = (new ClassesModel())->get_classes();
		$rows = array_values(array_filter($rows, static function (array $row): bool {
			$hay = strtolower(trim(
				(string) ($row['level_name'] ?? '') . ' '
				. (string) ($row['department_name'] ?? $row['dept_name'] ?? '') . ' '
				. (string) ($row['faculty_code'] ?? '') . ' '
				. (string) ($row['title'] ?? '')
			));
			return strpos($hay, 'holiday') === false;
		}));

		foreach ($rows as &$row) {
			if (!isset($row['dept_name']) && isset($row['department_name'])) {
				$row['dept_name'] = $row['department_name'];
			}
			$row['class_label'] = TimetableClassLabel::fromRow($row);
		}
		unset($row);

		return $rows;
	}

	/**
	 * @param list<array<string,mixed>> $sheets
	 * @return \CodeIgniter\HTTP\Response|string
	 */
	private function outputTimetablePdf(array $sheets, string $filenamePrefix, ?string $coverTitle = null, bool $includeCover = true)
	{
		if ($sheets === []) {
			$this->session->setFlashdata('error', 'No timetable generated yet.');
			header('Location: ' . base_url('timetable/dashboard'));
			exit;
		}

		$body = $this->renderPdfBody($sheets, $coverTitle, $includeCover);
		$html = view('pages/timetable/_pdf_document', [
			'doc_title' => $filenamePrefix,
			'inline_css' => $this->inlineTimetableCss(),
			'body' => $body,
		]);

		$dir = WRITEPATH . 'uploads/timetables';
		if (!is_dir($dir)) {
			@mkdir($dir, 0755, true);
		}

		$filename = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $filenamePrefix) . '_' . date('Y-m-d') . '.pdf';

		try {
			$wk = new Wkhtmltopdf(['path' => $dir]);
			$wk->setTitle($filenamePrefix);
			$wk->setHtml($html);
			$wk->setOrientation(Wkhtmltopdf::ORIENTATION_LANDSCAPE);
			$wk->setPageSize(Wkhtmltopdf::SIZE_A4);
			$wk->setMargins(['top' => 8, 'bottom' => 8, 'left' => 8, 'right' => 8]);
			$wk->setOptions(['encoding' => 'UTF-8']);
			$wk->output(Wkhtmltopdf::MODE_DOWNLOAD, $filename);
			return $this->response;
		} catch (\Throwable $e) {
			return $this->response
				->setHeader('Content-Type', 'text/html; charset=UTF-8')
				->setBody($html . '<script>window.onload=function(){window.print();}</script>');
		}
	}
}
