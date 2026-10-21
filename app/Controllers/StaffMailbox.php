<?php

namespace App\Controllers;

use App\Libraries\StaffMailboxExporter;
use App\Models\StaffModel;
use PhpOffice\PhpSpreadsheet\IOFactory;

/** Excel of the current school's mailboxes: webmail link, address, and default password. */
class StaffMailbox extends Home
{
	public function excel()
	{
		$this->_preset(1, 3);
		$schoolId = (int) $this->session->get('soma_school_id');
		$staffMdl = new StaffModel();
		$staffMdl->ensureSchoolEmailColumns();
		$staffs = $staffMdl->select('id, fname, lname, school_email, school_email_password')
			->where('school_id', $schoolId)
			->where('school_email !=', '')
			->orderBy('fname', 'ASC')
			->orderBy('lname', 'ASC')
			->findAll();

		$school = [
			'name' => (string) ($this->data['school_name'] ?? 'School'),
			'slogan' => (string) ($this->data['school_moto'] ?? ''),
			'address' => (string) ($this->data['school_address'] ?? ''),
			'pobox' => (string) ($this->data['school_pobox'] ?? ''),
			'phone' => (string) ($this->data['school_phone'] ?? ''),
			'email' => (string) ($this->data['school_email'] ?? ''),
			'website' => (string) ($this->data['school_website'] ?? ''),
			'logo' => (string) ($this->data['school_logo'] ?? ''),
		];
		$yearTitle = (string) ($this->data['academic_year_title'] ?? '');
		$spreadsheet = StaffMailboxExporter::build($school, $staffs, $yearTitle);
		$filename = StaffMailboxExporter::exportFilename($school['name']);
		$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');

		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Cache-Control: max-age=0');
		$writer->save('php://output');
		exit;
	}
}
