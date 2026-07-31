<?php

namespace App\Services\Budget;

class DocumentStorageService
{
	public function storeUpload($file, $subdir = 'budget')
	{
		if (!$file || !$file->isValid()) {
			return ['success' => false, 'error' => 'Invalid file.'];
		}
		$ext = strtolower($file->getClientExtension() ?: $file->getExtension() ?: '');
		$allowed = ['pdf', 'jpg', 'jpeg', 'png', 'xlsx', 'xls', 'doc', 'docx'];
		if (!in_array($ext, $allowed, true)) {
			return ['success' => false, 'error' => 'File type not allowed.'];
		}
		if ($file->getSize() > 10 * 1024 * 1024) {
			return ['success' => false, 'error' => 'File exceeds 10MB limit.'];
		}
		$dir = WRITEPATH . 'uploads/' . trim($subdir, '/') . '/';
		if (!is_dir($dir)) {
			mkdir($dir, 0755, true);
		}
		$name = bin2hex(random_bytes(16)) . '.' . $ext;
		$file->move($dir, $name);
		return [
			'success' => true,
			'stored_path' => 'writable/uploads/' . trim($subdir, '/') . '/' . $name,
			'original_name' => $file->getClientName(),
			'checksum' => hash_file('sha256', $dir . $name),
		];
	}
}
