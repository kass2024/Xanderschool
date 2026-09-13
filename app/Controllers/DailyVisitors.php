<?php

namespace App\Controllers;

use App\Libraries\DailyVisitorsExporter;
use App\Libraries\Wkhtmltopdf;
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
		[$from, $to] = $this->reportDateRange();

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

	public function export_excel()
	{
		$this->_preset(1, 3, 4, 5, 6);
		$pack = $this->reportExportPack();
		$spreadsheet = DailyVisitorsExporter::buildExcel(
			$pack['school'],
			$pack['visits'],
			$pack['summary'],
			$pack['from'],
			$pack['to'],
			$pack['year_title'],
			$pack['term_label']
		);
		$filename = DailyVisitorsExporter::exportFilename($pack['school']['name'], $pack['from'], $pack['to'], 'xlsx');
		$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');

		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Cache-Control: max-age=0');
		$writer->save('php://output');
		exit;
	}

	public function export_pdf()
	{
		$this->_preset(1, 3, 4, 5, 6);
		$pack = $this->reportExportPack();
		$html = view('pages/reports/daily_visitors_export_pdf', [
			'school' => $pack['school'],
			'visits' => $pack['visits'],
			'summary' => $pack['summary'],
			'from_date' => $pack['from'],
			'to_date' => $pack['to'],
			'year_title' => $pack['year_title'],
			'term_label' => $pack['term_label'],
			'printed_at' => date('d M Y H:i'),
		]);

		try {
			$mask = FCPATH . 'assets/templates/*.html';
			array_map('unlink', glob($mask) ?: []);
			$wkhtmltopdf = new Wkhtmltopdf(['path' => FCPATH . 'assets/templates/']);
			$wkhtmltopdf->setTitle('Daily Visiting Report');
			$wkhtmltopdf->setHtml($html);
			$wkhtmltopdf->setOrientation(Wkhtmltopdf::ORIENTATION_LANDSCAPE);
			$wkhtmltopdf->setPageSize(Wkhtmltopdf::SIZE_A4);
			$wkhtmltopdf->setMargins(['top' => 8, 'left' => 8, 'right' => 8, 'bottom' => 8]);
			$filename = DailyVisitorsExporter::exportFilename($pack['school']['name'], $pack['from'], $pack['to'], 'pdf');
			$wkhtmltopdf->output(Wkhtmltopdf::MODE_EMBEDDED, $filename);
		} catch (\Exception $e) {
			return $this->response
				->setHeader('Content-Type', 'text/html; charset=UTF-8')
				->setBody($html . '<script>window.onload=function(){window.print();}</script>');
		}
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

	/** @return array{0:string,1:string} */
	private function reportDateRange(): array
	{
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

		return [$from, $to];
	}

	/**
	 * @return array{
	 *   school:array<string,string>,
	 *   visits:list<array<string,mixed>>,
	 *   summary:array{total:int,inside:int,checked_out:int},
	 *   from:string,
	 *   to:string,
	 *   year_title:string,
	 *   term_label:string
	 * }
	 */
	private function reportExportPack(): array
	{
		$schoolId = (int) ($this->data['school_id'] ?? 0);
		[$from, $to] = $this->reportDateRange();
		$report = (new GateVisitModel())->report($schoolId, $from, $to);

		return [
			'school' => $this->schoolMetaForExport(),
			'visits' => $report['visits'],
			'summary' => $report['summary'],
			'from' => $from,
			'to' => $to,
			'year_title' => (string) ($this->data['academic_year_title'] ?? ''),
			'term_label' => (string) self::TermToStr($this->data['term'] ?? 0),
		];
	}

	/** @return array{name:string,slogan:string,address:string,pobox:string,phone:string,email:string,website:string,logo:string} */
	private function schoolMetaForExport(): array
	{
		return [
			'name' => (string) ($this->data['school_name'] ?? 'School'),
			'slogan' => (string) ($this->data['school_moto'] ?? ''),
			'address' => (string) ($this->data['school_address'] ?? ''),
			'pobox' => (string) ($this->data['school_pobox'] ?? ''),
			'phone' => (string) ($this->data['school_phone'] ?? ''),
			'email' => (string) ($this->data['school_email'] ?? ''),
			'website' => (string) ($this->data['school_website'] ?? ''),
			'logo' => (string) ($this->data['school_logo'] ?? ''),
		];
	}
}
