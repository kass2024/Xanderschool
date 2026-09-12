<?php

namespace App\Controllers;

use App\Models\GateVisitModel;

/**
 * Web dashboard for daily gate visitors (not parent visiting).
 */
class DailyVisitors extends Home
{
	public function inside()
	{
		$this->_preset(1, 3, 4, 5, 6);
		session_write_close();
		$data = $this->data;
		$schoolId = (int) ($data['school_id'] ?? 0);
		$model = new GateVisitModel();
		$board = $model->todayBoard($schoolId);

		$data['title'] = lang('app.dailyVisitors') . ' — ' . lang('app.dailyVisitorsInside');
		$data['subtitle'] = lang('app.dailyVisitorsInside');
		$data['page'] = 'daily_visitors_inside';
		$data['inside'] = $board['inside'];
		$data['today'] = $board['today'];
		$data['counts'] = $board['counts'];
		$data['content'] = view('pages/daily_visitors/inside', $data);
		return view('main', $data);
	}

	public function report()
	{
		$this->_preset(1, 3, 4, 5, 6);
		session_write_close();
		$data = $this->data;
		$schoolId = (int) ($data['school_id'] ?? 0);

		$from = trim((string) $this->request->getGet('from'));
		$to = trim((string) $this->request->getGet('to'));
		if ($from === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
			$from = date('Y-m-d');
		}
		if ($to === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
			$to = date('Y-m-d');
		}
		if ($from > $to) {
			[$from, $to] = [$to, $from];
		}

		$model = new GateVisitModel();
		$report = $model->report($schoolId, $from, $to);

		$data['title'] = lang('app.dailyVisitors') . ' — ' . lang('app.dailyVisitorsReport');
		$data['subtitle'] = lang('app.dailyVisitorsReport');
		$data['page'] = 'daily_visitors_report';
		$data['from_date'] = $from;
		$data['to_date'] = $to;
		$data['visits'] = $report['visits'];
		$data['summary'] = $report['summary'];
		$data['content'] = view('pages/daily_visitors/report', $data);
		return view('main', $data);
	}

	public function inside_json()
	{
		$schoolId = (int) ($this->session->get('soma_school_id') ?: 0);
		$denied = $this->_beginJsonAction();
		if ($denied) {
			return $denied;
		}
		$model = new GateVisitModel();
		return $this->response->setJSON([
			'success' => true,
			'board' => $model->todayBoard($schoolId),
		]);
	}
}
