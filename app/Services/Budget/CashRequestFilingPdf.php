<?php

namespace App\Services\Budget;

use App\Libraries\MpdfReport;

/**
 * Saves the finished expense request as a PDF once the Director of Finance has authorized it.
 */
class CashRequestFilingPdf
{
	public static function isApproved(string $status): bool
	{
		return in_array($status, ['FINANCE_AUTHORIZED', 'PAID', 'PARTIALLY_PAID', 'RECEIPT_CONFIRMED', 'CLOSED'], true);
	}

	/**
	 * @return array<string,mixed>|null the cash_request_documents row
	 */
	public static function ensure(int $requestId, bool $refresh = false): ?array
	{
		if ($requestId < 1) {
			return null;
		}
		$db = \Config\Database::connect();
		$req = $db->table('cash_requests')->where('id', $requestId)->get(1)->getRowArray();
		if (!$req || !self::isApproved((string) ($req['status'] ?? ''))) {
			return null;
		}
		$existing = $db->table('cash_request_documents')
			->where('cash_request_id', $requestId)
			->where('doc_type', 'approved_form')
			->orderBy('id', 'DESC')
			->get(1)->getRowArray();
		if ($existing && !$refresh) {
			$path = self::absolutePath((string) ($existing['stored_path'] ?? ''));
			if (is_file($path)) {
				return $existing;
			}
		}

		$html = view('pages/budget/cash_request_filing_pdf', self::viewData($db, $req));
		$safeNo = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($req['request_no'] ?? ('request-' . $requestId))) ?: ('request-' . $requestId);
		$original = 'Expense-Request-' . $safeNo . '.pdf';
		$pdf = MpdfReport::render($html, $original, [
			'title' => 'Expense request ' . ($req['request_no'] ?? $requestId),
			'margin' => 10,
			'margin_top' => 10,
			'margin_bottom' => 14,
			'footer' => '<table width="100%" style="font-size:8pt;color:#64748b;border-top:1px solid #9aa7b0;"><tr><td>Approved expense request for filing</td><td style="text-align:right;">Page {PAGENO} / {nbpg}</td></tr></table>',
		]);
		$dir = rtrim(WRITEPATH, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'budget' . DIRECTORY_SEPARATOR . 'cash_requests';
		if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
			throw new \RuntimeException('Could not store the filing PDF.');
		}
		$name = bin2hex(random_bytes(16)) . '.pdf';
		$full = $dir . DIRECTORY_SEPARATOR . $name;
		if (file_put_contents($full, $pdf) === false) {
			throw new \RuntimeException('Could not store the filing PDF.');
		}
		$stored = 'writable/uploads/budget/cash_requests/' . $name;
		$row = [
			'cash_request_id' => $requestId,
			'doc_type' => 'approved_form',
			'original_name' => $original,
			'stored_path' => $stored,
			'uploaded_by' => (int) ($req['updated_by'] ?? $req['created_by'] ?? 0),
			'created_at' => date('Y-m-d H:i:s'),
		];
		if ($existing) {
			$old = self::absolutePath((string) ($existing['stored_path'] ?? ''));
			if (is_file($old)) {
				@unlink($old);
			}
			$db->table('cash_request_documents')->where('id', (int) $existing['id'])->update($row);
			$row['id'] = (int) $existing['id'];
			return $row;
		}
		$db->table('cash_request_documents')->insert($row);
		$row['id'] = (int) $db->insertID();
		return $row;
	}

	private static function absolutePath(string $stored): string
	{
		return rtrim(WRITEPATH, '/\\') . DIRECTORY_SEPARATOR . str_replace(['writable/', '\\'], ['', DIRECTORY_SEPARATOR], $stored);
	}

	/** @param array<string,mixed> $req */
	private static function viewData($db, array $req): array
	{
		$requestId = (int) $req['id'];
		$lines = $db->table('cash_request_lines crl')
			->select('crl.*, bl.category as budget_category')
			->join('budget_lines bl', 'bl.id = crl.budget_line_id', 'left')
			->where('crl.cash_request_id', $requestId)
			->orderBy('crl.sort_order', 'ASC')
			->orderBy('crl.id', 'ASC')
			->get()->getResultArray();
		$actions = $db->table('cash_request_actions')->where('cash_request_id', $requestId)->orderBy('id')->get()->getResultArray();
		$when = [];
		foreach ($actions as $act) {
			$when[$act['action']] = $act['created_at'] ?? '';
		}
		$creator = $db->table('staffs s')
			->select('s.fname, s.lname, s.phone, s.signature, p.title as post_title')
			->join('posts p', 'p.id = s.post', 'left')
			->where('s.id', (int) ($req['created_by'] ?? 0))
			->get(1)->getRowArray() ?: [];
		$branch = $db->table('branches')->where('id', (int) ($req['branch_id'] ?? 0))->get(1)->getRowArray();
		$school = [];
		if (!empty($branch['school_id'])) {
			$school = $db->table('schools')->select('name, slogan, website, logo')
				->where('id', (int) $branch['school_id'])->get(1)->getRowArray() ?: [];
		}
		$schoolName = strtoupper(trim((string) ($school['name'] ?? ($branch['name'] ?? 'Wisdom School'))));
		$logoSrc = '';
		$logoFile = trim((string) ($school['logo'] ?? ''));
		if ($logoFile !== '') {
			$logoSrc = MpdfReport::imageFileForPdf(FCPATH . 'assets/images/logo/' . $logoFile, 160);
		}
		$sig = static function ($file) {
			$file = function_exists('staff_signature_file') ? staff_signature_file($file) : trim((string) $file);
			if ($file === '') {
				return '';
			}
			return MpdfReport::imageFileForPdf(FCPATH . 'assets/images/signatures/' . $file, 280);
		};
		$total = 0.0;
		foreach ($lines as $ln) {
			$total += (float) ($ln['amount'] ?? 0);
		}
		return [
			'req' => $req,
			'lines' => $lines,
			'when' => $when,
			'creator' => $creator,
			'schoolName' => $schoolName,
			'motto' => trim((string) ($school['slogan'] ?? '')),
			'website' => trim((string) ($school['website'] ?? '')),
			'logoSrc' => $logoSrc,
			'prepSig' => $sig($creator['signature'] ?? ''),
			'headSig' => $sig($req['headteacher_signature'] ?? ''),
			'chiefSig' => $sig($req['chief_accountant_signature'] ?? ''),
			'financeSig' => $sig($req['finance_signature'] ?? ''),
			'total' => $total,
			'words' => self::words($total),
		];
	}

	private static function words(float $amount): string
	{
		$n = (int) round($amount);
		if ($n <= 0) {
			return '';
		}
		$ones = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
		$tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
		$chunk = static function ($num) use (&$chunk, $ones, $tens) {
			$num = (int) $num;
			if ($num < 20) {
				return $ones[$num];
			}
			if ($num < 100) {
				return $tens[(int) floor($num / 10)] . ($num % 10 ? '-' . $ones[$num % 10] : '');
			}
			return $ones[(int) floor($num / 100)] . ' hundred' . ($num % 100 ? ' ' . $chunk($num % 100) : '');
		};
		$parts = [];
		foreach ([1000000000 => 'billion', 1000000 => 'million', 1000 => 'thousand'] as $scale => $label) {
			$v = (int) floor($n / $scale);
			if ($v > 0) {
				$parts[] = $chunk($v) . ' ' . $label;
				$n %= $scale;
			}
		}
		if ($n > 0) {
			$parts[] = $chunk($n);
		}
		return ucfirst(implode(' ', $parts)) . ' Rwandan francs';
	}
}
