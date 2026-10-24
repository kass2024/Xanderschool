<?php

namespace App\Controllers;

use App\Libraries\ConsentForms;

class ConsentSign extends Home
{
	private function forms(): ConsentForms
	{
		return new ConsentForms();
	}

	public function desk()
	{
		$this->_preset(1, 3, 4, 5, 6);
		$data = $this->data;
		$data['title'] = 'Consent forms';
		$data['subtitle'] = 'Parent signing links';
		$data['page'] = 'students';
		$catalog = $this->forms()->catalog((int) $this->session->get('soma_school_id'));
		foreach ($catalog['groups'] as &$group) {
			$group['url'] = $group['token'] !== '' ? base_url('consent-sign/' . $group['token']) : '';
		}
		unset($group);
		$data['catalog'] = $catalog;
		$data['content'] = view('pages/consent_forms', $data);
		return view('main', $data);
	}

	public function share()
	{
		$this->_preset(1, 3, 4, 5, 6);
		@ini_set('max_execution_time', '300');
		$schoolId = (int) $this->session->get('soma_school_id');
		$ids = $this->request->getPost('studentIds');
		if (is_string($ids)) {
			$ids = $ids === '' ? [] : preg_split('/[,\s]+/', $ids);
		}
		if (!is_array($ids)) {
			$ids = [];
		}
		$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
		if (count($ids) > 400) {
			return $this->response->setJSON(['success' => false, 'error' => 'Select at most 400 students at a time.']);
		}
		$prepared = $this->forms()->prepare($schoolId, $ids, (int) $this->session->get('soma_id'));
		if ($prepared['groups'] === []) {
			$why = $prepared['missing'] !== []
				? 'No consent form was found for the selected students.'
				: 'No consent forms are loaded for this school.';
			return $this->response->setJSON(['success' => false, 'error' => $why, 'missing' => $prepared['missing']]);
		}
		$sendSms = (string) $this->request->getPost('send_sms') === '1';
		$sentParents = 0;
		$sentMessages = 0;
		$noPhone = 0;
		$links = [];
		foreach ($prepared['groups'] as $group) {
			$url = base_url('consent-sign/' . $group['token']);
			$links[] = [
				'parent' => $group['parent'],
				'url' => $url,
				'students' => array_column($group['docs'], 'student'),
				'signed' => count(array_filter($group['docs'], static function ($doc) {
					return !empty($doc['signed']);
				})),
				'total' => count($group['docs']),
			];
			if (!$sendSms) {
				continue;
			}
			if ($group['phones'] === []) {
				$noPhone++;
				continue;
			}
			$message = $this->consentSms($group, $url);
			$receiver = (int) ($group['docs'][0]['student_id'] ?? 0);
			$sentForParent = 0;
			foreach ($group['phones'] as $phone) {
				if ($this->sendConsentSms((string) $phone, $message, $receiver)) {
					$sentForParent++;
					$sentMessages++;
				}
			}
			if ($sentForParent > 0) {
				$sentParents++;
			}
		}
		$parentCount = count($prepared['groups']);
		if ($sendSms) {
			$summary = 'Signing link sent for ' . $sentParents . ' parent' . ($sentParents === 1 ? '' : 's')
				. ' (' . $sentMessages . ' SMS).';
			if ($noPhone > 0) {
				$summary .= ' ' . $noPhone . ' parent' . ($noPhone === 1 ? ' has' : 's have') . ' no phone. Copy the link from Consent forms.';
			}
			if ($prepared['missing'] !== []) {
				$summary .= ' Skipped: ' . implode(', ', array_slice($prepared['missing'], 0, 5)) . '.';
			}
			return $this->response->setJSON([
				'success' => $sentParents > 0 || $noPhone > 0,
				'message' => $summary,
				'links' => $parentCount <= 8 ? $links : [],
				'error' => $sentParents > 0 || $noPhone > 0 ? null : 'No SMS was sent.',
			]);
		}
		$summary = $parentCount . ' signing link' . ($parentCount === 1 ? '' : 's') . ' ready. Each link stays open for 30 days.';
		if ($prepared['missing'] !== []) {
			$summary .= ' Skipped: ' . implode(', ', array_slice($prepared['missing'], 0, 5)) . '.';
		}
		return $this->response->setJSON([
			'success' => true,
			'message' => $summary,
			'links' => $links,
		]);
	}

	public function staffDownload($studentId = 0)
	{
		$this->_preset(1, 3, 4, 5, 6);
		$schoolId = (int) $this->session->get('soma_school_id');
		$studentId = (int) $studentId;
		$doc = $this->staffDoc($schoolId, $studentId);
		if ($doc === null) {
			return $this->response->setStatusCode(404)->setBody('Form not found');
		}
		$this->sendPdf($doc['path'], $this->forms()->downloadName($doc['student'], $doc['regno']), true);
	}

	public function staffDownloadAll()
	{
		$this->_preset(1, 3, 4, 5, 6);
		$schoolId = (int) $this->session->get('soma_school_id');
		$catalog = $this->forms()->catalog($schoolId);
		$files = [];
		foreach ($catalog['groups'] as $group) {
			foreach ($group['docs'] as $doc) {
				$path = $this->forms()->currentPath($schoolId, (string) $doc['regno']);
				if ($path !== '') {
					$files[] = ['name' => $doc['student'], 'regno' => $doc['regno'], 'path' => $path];
				}
			}
		}
		$this->sendZip($files, 'consent-forms.zip');
	}

	public function open($token = '')
	{
		helper(['url', 'qonics']);
		$lang = strtolower(trim((string) $this->request->getGet('lang')));
		if (!in_array($lang, ['en', 'rw'], true)) {
			$lang = 'en';
		}
		$envelope = $this->forms()->envelope((string) $token);
		return view('pages/consent_sign', [
			'lang' => $lang,
			'envelope' => $envelope,
		]);
	}

	public function file($token = '', $studentId = 0)
	{
		$found = $this->parentDoc((string) $token, (int) $studentId, false);
		if ($found === null) {
			return $this->response->setStatusCode(404)->setBody('Form not found');
		}
		$this->sendPdf($found['path'], $this->forms()->downloadName($found['doc']['student'], $found['doc']['regno']), false);
	}

	public function download($token = '', $studentId = 0)
	{
		$found = $this->parentDoc((string) $token, (int) $studentId, false);
		if ($found === null) {
			return $this->response->setStatusCode(404)->setBody('Form not found');
		}
		$this->sendPdf($found['path'], $this->forms()->downloadName($found['doc']['student'], $found['doc']['regno']), true);
	}

	public function downloadAll($token = '')
	{
		$envelope = $this->forms()->envelope((string) $token);
		if (!$envelope || !empty($envelope['expired']) || $envelope['docs'] === []) {
			return $this->response->setStatusCode(404)->setBody('Form not found');
		}
		$files = [];
		foreach ($envelope['docs'] as $doc) {
			$path = $this->forms()->currentPath((int) $envelope['school_id'], (string) $doc['regno']);
			if ($path !== '') {
				$files[] = ['name' => $doc['student'], 'regno' => $doc['regno'], 'path' => $path];
			}
		}
		$this->sendZip($files, 'consent-forms.zip');
	}

	public function sign($token = '', $studentId = 0)
	{
		$found = $this->parentDoc((string) $token, (int) $studentId, true);
		if ($found === null) {
			return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'error' => 'This link is not available.']);
		}
		$raw = (string) $this->request->getPost('image');
		$marker = 'base64,';
		$pos = strpos($raw, $marker);
		if ($pos !== false) {
			$raw = substr($raw, $pos + strlen($marker));
		}
		$raw = str_replace(' ', '+', $raw);
		$bytes = base64_decode($raw, true);
		if ($bytes === false) {
			return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'error' => 'Draw a signature first.']);
		}
		$result = $this->forms()->applySignature(
			(int) $found['envelope']['school_id'],
			(int) $studentId,
			(string) $found['doc']['regno'],
			$bytes
		);
		if (empty($result['ok'])) {
			return $this->response->setStatusCode(400)->setJSON($result);
		}
		return $this->response->setJSON([
			'ok' => true,
			'signed_at' => date('Y-m-d H:i:s'),
		]);
	}

	private function sendConsentSms(string $phone, string $message, int $receiverId): bool
	{
		$phone = trim($phone);
		$message = trim($message);
		if ($message === '' || strlen((string) preg_replace('/\D/', '', $phone)) < 9) {
			return false;
		}
		$perSms = defined('PER_SMS') ? (int) PER_SMS : 160;
		$length = function_exists('mb_strlen') ? mb_strlen($message, 'UTF-8') : strlen($message);
		$smsCount = (int) ceil($length / max(1, $perSms));
		if ($smsCount < 1) {
			$smsCount = 1;
		}
		$smsResult = null;
		$ok = $this->sendSMS($phone, $message, $smsResult);
		$fail = '';
		if (!$ok) {
			$fail = is_array($smsResult) ? (string) ($smsResult['content'] ?? json_encode($smsResult)) : (string) $smsResult;
			$smsCount = 0;
		}
		$termId = (int) ($this->data['active_term'] ?? 0);
		if ($termId > 0) {
			$this->_save_sms($termId, $phone, $message, 'Consent sign link', $receiverId, 0, $smsCount, $fail);
		}
		return $ok;
	}

	/**
	 * @param array<string, mixed> $group
	 */
	private function consentSms(array $group, string $url): string
	{
		$names = [];
		foreach ($group['docs'] as $doc) {
			$names[] = (string) $doc['student'];
		}
		$label = implode(', ', array_slice($names, 0, 3));
		if (count($names) > 3) {
			$label .= ' +' . (count($names) - 3);
		}
		$count = count($names);
		return $label . ': Sign the consent form' . ($count === 1 ? '' : 's (' . $count . ')')
			. '. Expires in 30 days: ' . $url
			. "\nSinyisha uburenganzira. Iyi link irangira mu minsi 30.";
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function staffDoc(int $schoolId, int $studentId): ?array
	{
		if ($studentId < 1) {
			return null;
		}
		$catalog = $this->forms()->catalog($schoolId);
		foreach ($catalog['groups'] as $group) {
			foreach ($group['docs'] as $doc) {
				if ((int) $doc['student_id'] !== $studentId) {
					continue;
				}
				$path = $this->forms()->currentPath($schoolId, (string) $doc['regno']);
				if ($path === '') {
					return null;
				}
				return ['path' => $path, 'student' => $doc['student'], 'regno' => $doc['regno']];
			}
		}
		return null;
	}

	/**
	 * @return array{envelope: array<string, mixed>, doc: array<string, mixed>, path: string}|null
	 */
	private function parentDoc(string $token, int $studentId, bool $requireOpen): ?array
	{
		$envelope = $this->forms()->envelope($token);
		if (!$envelope || $studentId < 1) {
			return null;
		}
		if ($requireOpen && !empty($envelope['expired'])) {
			return null;
		}
		if (!$requireOpen && !empty($envelope['expired'])) {
			return null;
		}
		$doc = $this->forms()->docInEnvelope($envelope, $studentId);
		if ($doc === null) {
			return null;
		}
		$path = $this->forms()->currentPath((int) $envelope['school_id'], (string) $doc['regno']);
		if ($path === '') {
			return null;
		}
		return ['envelope' => $envelope, 'doc' => $doc, 'path' => $path];
	}

	private function sendPdf(string $path, string $filename, bool $attachment): void
	{
		$filename = str_replace(['"', "\r", "\n"], '', $filename);
		header('Content-Type: application/pdf');
		header('Content-Disposition: ' . ($attachment ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
		header('Content-Length: ' . (string) filesize($path));
		header('Cache-Control: private, no-store');
		header('X-Content-Type-Options: nosniff');
		readfile($path);
		exit;
	}

	/**
	 * @param array<int, array{name: string, path: string, regno?: string}> $files
	 */
	private function sendZip(array $files, string $filename): void
	{
		$tmp = $this->forms()->zip($files);
		if ($tmp === null) {
			header('Content-Type: text/plain; charset=UTF-8', true, 404);
			echo 'Nothing to download yet.';
			exit;
		}
		$filename = str_replace(['"', "\r", "\n"], '', $filename);
		header('Content-Type: application/zip');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Content-Length: ' . (string) filesize($tmp));
		header('Cache-Control: private, no-store');
		readfile($tmp);
		@unlink($tmp);
		exit;
	}
}
