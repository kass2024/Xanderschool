<?php

namespace App\Controllers\Traits;

use App\Models\AssetCategoryModel;
use App\Models\AssetLocationModel;
use App\Models\AssetModel;
use App\Models\AssetOpsSchema;
use App\Models\ClassesModel;
use App\Models\StaffModel;
use App\Models\StudentModel;
use App\Services\Assets\AssetAiAssistService;
use App\Services\Assets\AssetCirculationService;
use App\Services\Assets\AssetFinanceService;
use App\Services\Assets\AssetImportService;
use App\Services\Assets\AssetOperationsService;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Asset Management Phases 2–6 controller actions (PHP 7.4).
 * Used by AssetManagement.
 */
trait AssetManagementOpsTrait
{
	protected function bootOps()
	{
		$schoolId = $this->bootAssets();
		AssetOpsSchema::ensureAll();
		return $schoolId;
	}

	protected function actorId()
	{
		return (int) $this->session->get('soma_id');
	}

	protected function jsonResult(array $res)
	{
		if (!empty($res['error']) || (isset($res['success']) && $res['success'] === false)) {
			$msg = isset($res['error']) ? $res['error'] : 'Operation failed';
			return $this->response->setJSON(['error' => $msg]);
		}
		return $this->response->setJSON($res);
	}

	// ---------- Phase 2: Import / Export ----------

	public function import()
	{
		$schoolId = $this->bootOps();
		$this->denyUnless('asset_assets');
		$data = $this->data;
		$db = \Config\Database::connect();
		$data['title'] = 'Bulk Asset Import';
		$data['subtitle'] = 'Import';
		$data['page'] = 'asset_assets';
		$data['imports'] = $db->table('asset_imports')
			->where('school_id', $schoolId)
			->orderBy('id', 'DESC')
			->get(20)->getResultArray();
		$data['content'] = view('pages/assets/import', $data);
		return view('main', $data);
	}

	public function download_import_template()
	{
		$schoolId = $this->bootOps();
		$this->denyUnless('asset_assets');
		$svc = new AssetImportService();
		$spreadsheet = $svc->buildTemplate($schoolId);
		$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
		$filename = 'asset_import_template_' . date('Ymd') . '.xlsx';
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment;filename="' . $filename . '"');
		header('Cache-Control: max-age=0');
		$writer->save('php://output');
		exit;
	}

	public function upload_import()
	{
		$schoolId = $this->bootOps();
		$this->denyUnless('asset_assets');
		$file = $this->request->getFile('documents');
		$mode = $this->request->getPost('mode') ?: 'create_only';
		if (!$file || !$file->isValid()) {
			return $this->response->setJSON(['error' => 'Please upload a valid Excel file.']);
		}
		$ext = strtolower($file->getClientExtension());
		if (!in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
			return $this->response->setJSON(['error' => 'Only .xlsx, .xls or .csv files are allowed.']);
		}
		$path = WRITEPATH . 'uploads';
		if (!is_dir($path)) {
			@mkdir($path, 0755, true);
		}
		$newName = 'asset_import_' . $schoolId . '_' . time() . '.' . $ext;
		$file->move($path, $newName);
		$full = $path . DIRECTORY_SEPARATOR . $newName;
		$svc = new AssetImportService();
		try {
			$result = $svc->parseAndValidate($schoolId, $full, $mode);
			$result['preview_url'] = base_url('asset_management/import_preview/' . $result['import_id']);
			return $this->response->setJSON($result);
		} catch (\Throwable $e) {
			return $this->response->setJSON(['error' => 'Import failed: ' . $e->getMessage()]);
		}
	}

	public function import_preview($importId = null)
	{
		$schoolId = $this->bootOps();
		$this->denyUnless('asset_assets');
		$importId = (int) $importId;
		$db = \Config\Database::connect();
		$import = $db->table('asset_imports')->where('school_id', $schoolId)->where('id', $importId)->get(1)->getRowArray();
		if (!$import) {
			$this->session->setFlashdata('error', 'Import batch not found.');
			return redirect()->to(base_url('asset_management/import'));
		}
		$rows = $db->table('asset_import_rows')->where('import_id', $importId)->orderBy('row_number', 'ASC')->get()->getResultArray();
		$data = $this->data;
		$data['title'] = 'Import preview #' . $importId;
		$data['subtitle'] = 'Import preview';
		$data['page'] = 'asset_assets';
		$data['import'] = $import;
		$data['rows'] = $rows;
		$data['content'] = view('pages/assets/import_preview', $data);
		return view('main', $data);
	}

	public function commit_import()
	{
		$schoolId = $this->bootOps();
		$this->denyUnless('asset_assets');
		$importId = (int) $this->request->getPost('import_id');
		$svc = new AssetImportService();
		try {
			return $this->jsonResult($svc->commitImport($schoolId, $importId, $this->actorId()));
		} catch (\Throwable $e) {
			return $this->response->setJSON(['error' => $e->getMessage()]);
		}
	}

	public function export_assets()
	{
		$schoolId = $this->bootOps();
		$this->denyUnless('asset_assets');
		$filters = [
			'status' => $this->request->getGet('status'),
			'category_id' => $this->request->getGet('category_id'),
			'location_id' => $this->request->getGet('location_id'),
			'q' => $this->request->getGet('q'),
		];
		$svc = new AssetImportService();
		$spreadsheet = $svc->exportAssets($schoolId, $filters);
		$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
		$filename = 'assets_export_' . date('Ymd_His') . '.xlsx';
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment;filename="' . $filename . '"');
		header('Cache-Control: max-age=0');
		$writer->save('php://output');
		exit;
	}

	// ---------- Phase 3: Assignments + Checkout ----------

	public function assignments()
	{
		$schoolId = $this->bootOps();
		$this->denyUnless('asset_assignments');
		$circ = new AssetCirculationService();
		$assetMdl = new AssetModel();
		$staffMdl = new StaffModel();
		$data = $this->data;
		$data['title'] = 'Asset Assignments';
		$data['subtitle'] = 'Assignments';
		$data['page'] = 'asset_assignments';
		$data['assignments'] = $circ->listAssignments($schoolId, ['status' => 'active']);
		$data['assets'] = $assetMdl->listDetailed($schoolId);
		$data['staffs'] = $staffMdl->select("id, concat(fname,' ',lname) as names")
			->where('school_id', $schoolId)->orderBy('fname')->get()->getResultArray();
		$data['content'] = view('pages/assets/assignments', $data);
		return view('main', $data);
	}

	public function save_assignment()
	{
		$schoolId = $this->bootOps();
		$this->denyUnless('asset_assignments');
		$circ = new AssetCirculationService();
		$res = $circ->assignStaff(
			$schoolId,
			(int) $this->request->getPost('asset_id'),
			(int) $this->request->getPost('staff_id'),
			(string) $this->request->getPost('role'),
			$this->actorId()
		);
		return $this->jsonResult($res);
	}

	public function end_assignment()
	{
		$schoolId = $this->bootOps();
		$this->denyUnless('asset_assignments');
		$circ = new AssetCirculationService();
		$res = $circ->endAssignment($schoolId, (int) $this->request->getPost('id'), $this->actorId());
		return $this->jsonResult($res);
	}

	public function checkout()
	{
		$schoolId = $this->bootOps();
		$this->denyUnless('asset_checkout');
		$circ = new AssetCirculationService();
		$classMdl = new ClassesModel();
		$staffMdl = new StaffModel();
		$assetMdl = new AssetModel();
		$data = $this->data;
		$data['title'] = 'Check-out / Check-in';
		$data['subtitle'] = 'RFID / scan kiosk';
		$data['page'] = 'asset_checkout';
		$data['open_loans'] = $circ->overdueLoans($schoolId);
		$db = \Config\Database::connect();
		$data['active_loans'] = $db->table('asset_loans l')
			->select('l.*, a.asset_code, a.name AS asset_name')
			->join('assets a', 'a.id = l.asset_id', 'left')
			->where('l.school_id', $schoolId)
			->whereIn('l.status', ['open', 'overdue'])
			->orderBy('l.id', 'DESC')
			->get(50)->getResultArray();
		$data['assets'] = $assetMdl->listDetailed($schoolId, ['status' => 'available']);
		$data['staffs'] = $staffMdl->select("id, concat(fname,' ',lname) as names")
			->where('school_id', $schoolId)->get()->getResultArray();
		$data['classes'] = $classMdl->get_classes();
		$data['content'] = view('pages/assets/checkout', $data);
		return view('main', $data);
	}

	public function scan_person()
	{
		$schoolId = $this->bootOps();
		$this->denyUnless('asset_checkout');
		$card = (string) $this->request->getPost('card');
		$circ = new AssetCirculationService();
		$person = $circ->lookupPersonByCard($schoolId, $card);
		if (!$person) {
			return $this->response->setJSON(['error' => 'No student or staff found for this card.']);
		}
		return $this->response->setJSON(['success' => true, 'person' => $person]);
	}

	public function scan_asset()
	{
		$schoolId = $this->bootOps();
		$this->denyUnless('asset_checkout');
		$code = (string) $this->request->getPost('code');
		$circ = new AssetCirculationService();
		$asset = $circ->lookupAssetByScan($schoolId, $code);
		if (!$asset) {
			return $this->response->setJSON(['error' => 'Asset not found for this code/RFID.']);
		}
		return $this->response->setJSON(['success' => true, 'asset' => $asset]);
	}

	public function do_checkout()
	{
		$schoolId = $this->bootOps();
		$this->denyUnless('asset_checkout');
		$circ = new AssetCirculationService();
		$res = $circ->checkout(
			$schoolId,
			(int) $this->request->getPost('asset_id'),
			(string) $this->request->getPost('borrower_type'),
			(int) $this->request->getPost('borrower_id'),
			(string) $this->request->getPost('due_at'),
			$this->actorId(),
			$this->request->getPost('issue_condition'),
			$this->request->getPost('notes')
		);
		return $this->jsonResult($res);
	}

	public function do_checkin()
	{
		$schoolId = $this->bootOps();
		$this->denyUnless('asset_checkout');
		$circ = new AssetCirculationService();
		$res = $circ->checkin(
			$schoolId,
			(int) $this->request->getPost('loan_id'),
			$this->actorId(),
			$this->request->getPost('return_condition'),
			$this->request->getPost('notes')
		);
		return $this->jsonResult($res);
	}

	public function renew_loan()
	{
		$schoolId = $this->bootOps();
		$this->denyUnless('asset_checkout');
		$circ = new AssetCirculationService();
		$res = $circ->renewLoan($schoolId, (int) $this->request->getPost('loan_id'), (string) $this->request->getPost('due_at'), $this->actorId());
		return $this->jsonResult($res);
	}

	// ---------- Phase 4: Operations ----------

	public function transfers()
	{
		$schoolId = $this->bootOps();
		$this->denyUnless('asset_transfers');
		$ops = new AssetOperationsService();
		$locMdl = new AssetLocationModel();
		$staffMdl = new StaffModel();
		$assetMdl = new AssetModel();
		$data = $this->data;
		$data['title'] = 'Asset Transfers';
		$data['subtitle'] = 'Transfers';
		$data['page'] = 'asset_transfers';
		$data['transfers'] = $ops->listTransfers($schoolId);
		$data['locations'] = $locMdl->listForSchool($schoolId);
		$data['staffs'] = $staffMdl->select("id, concat(fname,' ',lname) as names")->where('school_id', $schoolId)->get()->getResultArray();
		$data['assets'] = $assetMdl->listDetailed($schoolId);
		$data['content'] = view('pages/assets/transfers', $data);
		return view('main', $data);
	}

	public function save_transfer()
	{
		$schoolId = $this->bootOps();
		$this->denyUnless('asset_transfers');
		$ops = new AssetOperationsService();
		$assetIds = $this->request->getPost('asset_ids');
		if (!is_array($assetIds)) {
			$assetIds = array_filter(array_map('intval', explode(',', (string) $assetIds)));
		}
		$payload = [
			'transfer_type' => $this->request->getPost('transfer_type') ?: 'location',
			'is_temporary' => $this->request->getPost('is_temporary'),
			'from_location_id' => $this->request->getPost('from_location_id'),
			'to_location_id' => $this->request->getPost('to_location_id'),
			'from_custodian_id' => $this->request->getPost('from_custodian_id'),
			'to_custodian_id' => $this->request->getPost('to_custodian_id'),
			'notes' => $this->request->getPost('notes'),
			'asset_ids' => $assetIds,
		];
		return $this->jsonResult($ops->createTransfer($schoolId, $payload, $this->actorId()));
	}

	public function transfer_action()
	{
		$schoolId = $this->bootOps();
		$this->denyUnless('asset_transfers');
		$ops = new AssetOperationsService();
		$id = (int) $this->request->getPost('id');
		$action = (string) $this->request->getPost('action');
		if ($action === 'submit') {
			return $this->jsonResult($ops->submitTransfer($schoolId, $id, $this->actorId()));
		}
		if ($action === 'approve') {
			return $this->jsonResult($ops->approveTransfer($schoolId, $id, $this->actorId()));
		}
		if ($action === 'dispatch') {
			return $this->jsonResult($ops->updateTransferStatus($schoolId, $id, 'dispatched', $this->actorId()));
		}
		if ($action === 'receive') {
			return $this->jsonResult($ops->receiveTransfer($schoolId, $id, $this->actorId()));
		}
		if ($action === 'reject') {
			return $this->jsonResult($ops->updateTransferStatus($schoolId, $id, 'rejected', $this->actorId()));
		}
		return $this->response->setJSON(['error' => 'Unknown action']);
	}

	public function maintenance()
	{
		$schoolId = $this->bootOps();
		$this->denyUnless('asset_maintenance');
		$ops = new AssetOperationsService();
		$assetMdl = new AssetModel();
		$staffMdl = new StaffModel();
		$data = $this->data;
		$data['title'] = 'Maintenance';
		$data['subtitle'] = 'Work orders';
		$data['page'] = 'asset_maintenance';
		$data['orders'] = $ops->listMaintenance($schoolId);
		$data['overdue'] = method_exists($ops, 'overdueMaintenance') ? $ops->overdueMaintenance($schoolId) : [];
		$data['assets'] = $assetMdl->listDetailed($schoolId);
		$data['staffs'] = $staffMdl->select("id, concat(fname,' ',lname) as names")->where('school_id', $schoolId)->get()->getResultArray();
		$data['content'] = view('pages/assets/maintenance', $data);
		return view('main', $data);
	}

	public function save_maintenance()
	{
		$schoolId = $this->bootOps();
		$this->denyUnless('asset_maintenance');
		$ops = new AssetOperationsService();
		$payload = [
			'asset_id' => (int) $this->request->getPost('asset_id'),
			'maintenance_type' => $this->request->getPost('maintenance_type') ?: 'corrective',
			'problem' => $this->request->getPost('problem'),
			'priority' => $this->request->getPost('priority') ?: 'normal',
			'assigned_to' => $this->request->getPost('assigned_to'),
			'provider_type' => $this->request->getPost('provider_type') ?: 'internal',
			'scheduled_date' => $this->request->getPost('scheduled_date'),
			'labour_cost' => $this->request->getPost('labour_cost'),
			'parts_cost' => $this->request->getPost('parts_cost'),
			'other_cost' => $this->request->getPost('other_cost'),
		];
		return $this->jsonResult($ops->createMaintenance($schoolId, $payload, $this->actorId()));
	}

	public function maintenance_status()
	{
		$schoolId = $this->bootOps();
		$this->denyUnless('asset_maintenance');
		$ops = new AssetOperationsService();
		return $this->jsonResult($ops->updateMaintenanceStatus(
			$schoolId,
			(int) $this->request->getPost('id'),
			(string) $this->request->getPost('status'),
			$this->actorId(),
			[
				'work_performed' => $this->request->getPost('work_performed'),
				'result' => $this->request->getPost('result'),
				'next_maintenance_date' => $this->request->getPost('next_maintenance_date'),
			]
		));
	}

	public function inspections()
	{
		$schoolId = $this->bootOps();
		$this->denyUnless('asset_inspections');
		$ops = new AssetOperationsService();
		$assetMdl = new AssetModel();
		$data = $this->data;
		$data['title'] = 'Inspections';
		$data['subtitle'] = 'Inspections';
		$data['page'] = 'asset_inspections';
		$data['inspections'] = method_exists($ops, 'listInspections') ? $ops->listInspections($schoolId) : [];
		$data['assets'] = $assetMdl->listDetailed($schoolId);
		$data['content'] = view('pages/assets/inspections', $data);
		return view('main', $data);
	}

	public function save_inspection()
	{
		$schoolId = $this->bootOps();
		$this->denyUnless('asset_inspections');
		$ops = new AssetOperationsService();
		return $this->jsonResult($ops->createInspection($schoolId, [
			'asset_id' => (int) $this->request->getPost('asset_id'),
			'inspection_date' => $this->request->getPost('inspection_date') ?: date('Y-m-d'),
			'result' => $this->request->getPost('result') ?: 'pass',
			'condition_code' => $this->request->getPost('condition_code'),
			'notes' => $this->request->getPost('notes'),
			'next_inspection_date' => $this->request->getPost('next_inspection_date'),
		], $this->actorId()));
	}

	public function incidents()
	{
		$schoolId = $this->bootOps();
		$this->denyUnless('asset_incidents');
		$ops = new AssetOperationsService();
		$assetMdl = new AssetModel();
		$locMdl = new AssetLocationModel();
		$data = $this->data;
		$data['title'] = 'Incidents and Losses';
		$data['subtitle'] = 'Incidents';
		$data['page'] = 'asset_incidents';
		$data['incidents'] = $ops->listIncidents($schoolId);
		$data['assets'] = $assetMdl->listDetailed($schoolId);
		$data['locations'] = $locMdl->listForSchool($schoolId);
		$data['content'] = view('pages/assets/incidents', $data);
		return view('main', $data);
	}

	public function save_incident()
	{
		$schoolId = $this->bootOps();
		$this->denyUnless('asset_incidents');
		$ops = new AssetOperationsService();
		return $this->jsonResult($ops->createIncident($schoolId, [
			'asset_id' => (int) $this->request->getPost('asset_id'),
			'incident_type' => $this->request->getPost('incident_type') ?: 'damage',
			'incident_at' => $this->request->getPost('incident_at') ?: date('Y-m-d H:i:s'),
			'location_id' => $this->request->getPost('location_id'),
			'description' => $this->request->getPost('description'),
			'estimated_loss' => $this->request->getPost('estimated_loss'),
			'police_ref' => $this->request->getPost('police_ref'),
			'insurance_ref' => $this->request->getPost('insurance_ref'),
			'immediate_action' => $this->request->getPost('immediate_action'),
		], $this->actorId()));
	}

	public function audits()
	{
		$schoolId = $this->bootOps();
		$this->denyUnless('asset_audits');
		$ops = new AssetOperationsService();
		$locMdl = new AssetLocationModel();
		$catMdl = new AssetCategoryModel();
		$data = $this->data;
		$data['title'] = 'Inventory Audits';
		$data['subtitle'] = 'Audits';
		$data['page'] = 'asset_audits';
		$data['audits'] = $ops->listAudits($schoolId);
		$data['locations'] = $locMdl->listForSchool($schoolId);
		$data['categories'] = $catMdl->listForSchool($schoolId);
		$data['content'] = view('pages/assets/audits', $data);
		return view('main', $data);
	}

	public function save_audit()
	{
		$schoolId = $this->bootOps();
		$this->denyUnless('asset_audits');
		$ops = new AssetOperationsService();
		return $this->jsonResult($ops->createAudit($schoolId, [
			'title' => $this->request->getPost('title') ?: ('Audit ' . date('Y-m-d')),
			'location_id' => $this->request->getPost('location_id'),
			'category_id' => $this->request->getPost('category_id'),
			'custodian_id' => $this->request->getPost('custodian_id'),
		], $this->actorId()));
	}

	public function audit_scan()
	{
		$schoolId = $this->bootOps();
		$this->denyUnless('asset_audits');
		$ops = new AssetOperationsService();
		return $this->jsonResult($ops->scanAuditItem(
			$schoolId,
			(int) $this->request->getPost('audit_id'),
			(string) $this->request->getPost('code'),
			$this->actorId(),
			[
				'condition_code' => $this->request->getPost('condition_code'),
				'notes' => $this->request->getPost('notes'),
			]
		));
	}

	public function close_audit()
	{
		$schoolId = $this->bootOps();
		$this->denyUnless('asset_audits');
		$ops = new AssetOperationsService();
		$method = method_exists($ops, 'closeAudit') ? 'closeAudit' : null;
		if (!$method) {
			return $this->response->setJSON(['error' => 'Close audit not available']);
		}
		return $this->jsonResult($ops->closeAudit($schoolId, (int) $this->request->getPost('id'), $this->actorId()));
	}

	public function audit_view($id = null)
	{
		$schoolId = $this->bootOps();
		$this->denyUnless('asset_audits');
		$db = \Config\Database::connect();
		$audit = $db->table('asset_audits')->where('school_id', $schoolId)->where('id', (int) $id)->get(1)->getRowArray();
		if (!$audit) {
			return redirect()->to(base_url('asset_management/audits'));
		}
		$items = $db->table('asset_audit_items ai')
			->select('ai.*, a.asset_code, a.name AS asset_name')
			->join('assets a', 'a.id = ai.asset_id', 'left')
			->where('ai.audit_id', (int) $id)
			->orderBy('ai.id', 'ASC')
			->get()->getResultArray();
		$ai = new AssetAiAssistService();
		$data = $this->data;
		$data['title'] = 'Audit ' . $audit['audit_no'];
		$data['page'] = 'asset_audits';
		$data['audit'] = $audit;
		$data['items'] = $items;
		$data['ai_summary'] = $ai->summarizeAuditDiscrepancies((int) $id);
		$data['content'] = view('pages/assets/audit_view', $data);
		return view('main', $data);
	}

	public function disposals()
	{
		$schoolId = $this->bootOps();
		$this->denyUnless('asset_assets');
		$ops = new AssetOperationsService();
		$assetMdl = new AssetModel();
		$db = \Config\Database::connect();
		$data = $this->data;
		$data['title'] = 'Disposals';
		$data['page'] = 'asset_assets';
		$data['disposals'] = method_exists($ops, 'listDisposals')
			? $ops->listDisposals($schoolId)
			: $db->table('asset_disposals d')->select('d.*, a.asset_code, a.name AS asset_name')
				->join('assets a', 'a.id = d.asset_id', 'left')
				->where('d.school_id', $schoolId)->orderBy('d.id', 'DESC')->get()->getResultArray();
		$data['assets'] = $assetMdl->listDetailed($schoolId);
		$data['content'] = view('pages/assets/disposals', $data);
		return view('main', $data);
	}

	public function save_disposal()
	{
		$schoolId = $this->bootOps();
		$this->denyUnless('asset_assets');
		$ops = new AssetOperationsService();
		return $this->jsonResult($ops->requestDisposal($schoolId, [
			'asset_id' => (int) $this->request->getPost('asset_id'),
			'method' => $this->request->getPost('method') ?: 'write_off',
			'reason' => $this->request->getPost('reason'),
			'proceeds' => $this->request->getPost('proceeds'),
		], $this->actorId()));
	}

	public function disposal_action()
	{
		$schoolId = $this->bootOps();
		$this->denyUnless('asset_assets');
		$ops = new AssetOperationsService();
		$id = (int) $this->request->getPost('id');
		$action = (string) $this->request->getPost('action');
		if ($action === 'approve') {
			return $this->jsonResult($ops->approveDisposal($schoolId, $id, $this->actorId()));
		}
		if ($action === 'complete') {
			return $this->jsonResult($ops->completeDisposal($schoolId, $id, $this->actorId()));
		}
		return $this->response->setJSON(['error' => 'Unknown action']);
	}

	// ---------- Phase 5: Finance + Reports ----------

	public function reports()
	{
		$schoolId = $this->bootOps();
		$this->denyUnless('asset_reports');
		$fin = new AssetFinanceService();
		$report = $fin->assetRegisterReport($schoolId);
		$data = $this->data;
		$data['title'] = 'Asset Reports';
		$data['subtitle'] = 'Reports';
		$data['page'] = 'asset_reports';
		$data['report'] = $report;
		$data['period_ym'] = date('Y-m');
		$data['content'] = view('pages/assets/reports', $data);
		return view('main', $data);
	}

	public function run_depreciation()
	{
		$schoolId = $this->bootOps();
		$this->denyUnless('asset_reports');
		$period = $this->request->getPost('period_ym') ?: date('Y-m');
		$fin = new AssetFinanceService();
		return $this->jsonResult($fin->runStraightLineMonth($schoolId, $period, $this->actorId()));
	}

	public function export_report_csv()
	{
		$schoolId = $this->bootOps();
		$this->denyUnless('asset_reports');
		$type = (string) $this->request->getGet('type');
		$fin = new AssetFinanceService();
		$report = $fin->assetRegisterReport($schoolId);
		$map = [
			'by_location' => 'by_location',
			'by_category' => 'by_category',
			'by_custodian' => 'by_custodian',
			'overdue_loans' => 'overdue_loans',
			'maintenance_due' => 'maintenance_due',
			'warranty_expiry' => 'warranty_expiry',
			'missing_damaged' => 'missing_damaged',
			'depreciation_schedule' => 'depreciation_schedule',
		];
		$key = isset($map[$type]) ? $map[$type] : 'by_category';
		$rows = isset($report[$key]) ? $report[$key] : [];
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="asset_report_' . $key . '_' . date('Ymd') . '.csv"');
		$out = fopen('php://output', 'w');
		fwrite($out, "\xEF\xBB\xBF");
		if (!empty($rows)) {
			fputcsv($out, array_keys($rows[0]));
			foreach ($rows as $row) {
				// formula injection guard
				$safe = [];
				foreach ($row as $v) {
					$v = (string) $v;
					if ($v !== '' && in_array($v[0], ['=', '+', '-', '@'], true)) {
						$v = "'" . $v;
					}
					$safe[] = $v;
				}
				fputcsv($out, $safe);
			}
		} else {
			fputcsv($out, ['message']);
			fputcsv($out, ['No rows']);
		}
		fclose($out);
		exit;
	}

	// ---------- Phase 6: AI assists ----------

	public function ai_suggest_category()
	{
		$schoolId = $this->bootOps();
		$this->denyUnless('asset_assets');
		$ai = new AssetAiAssistService();
		$res = $ai->suggestCategory($schoolId, (string) $this->request->getPost('name'), (string) $this->request->getPost('description'));
		$res['label'] = 'AI suggestion — confirm before saving';
		return $this->response->setJSON(['success' => true, 'suggestion' => $res]);
	}

	public function ai_detect_duplicates()
	{
		$schoolId = $this->bootOps();
		$this->denyUnless('asset_assets');
		$ai = new AssetAiAssistService();
		$res = $ai->detectLikelyDuplicates($schoolId, (string) $this->request->getPost('name'), (string) $this->request->getPost('serial'));
		return $this->response->setJSON(['success' => true, 'duplicates' => $res, 'label' => 'AI suggestion — review manually']);
	}
}
