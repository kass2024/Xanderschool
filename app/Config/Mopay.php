<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * MoPay Gateway V1 settings.
 * All credentials and endpoints come from local .env only — never commit secrets.
 */
class Mopay extends BaseConfig
{
	public $projectSlug = '';
	public $messagePrefix = '';
	public $accountId = '';
	public $authKey = '';
	public $bearerToken = '';
	public $serverBaseUrl = '';
	public $tokenUrl = '';
	public $category = '';
	public $callbackSigningKey = '';
	public $callbackUrl = '';
	public $defaultCountryCode = 'rw';
	public $defaultMno = 'mtn';
	public $defaultCurrency = 'RWF';
	/** Fallback receive MSISDN when school settings have no mtn_momo_phone */
	public $receiverAccountNo = '';
	public $paymentTitle = '';
	public $paymentDetails = '';

	public function __construct()
	{
		parent::__construct();

		$this->projectSlug = (string) env('MOPAY_PROJECT_SLUG', $this->projectSlug);
		$this->messagePrefix = (string) env('MOPAY_MESSAGE_PREFIX', $this->messagePrefix);
		$this->accountId = (string) env('MOPAY_ACCOUNT_ID', $this->accountId);
		$this->authKey = (string) env('MOPAY_AUTH_KEY', $this->authKey);
		$this->bearerToken = (string) env('MOPAY_BEARER_TOKEN', $this->bearerToken);
		$this->serverBaseUrl = rtrim((string) env('MOPAY_SERVER_BASE_URL', $this->serverBaseUrl), '/');
		$this->tokenUrl = (string) env('MOPAY_TOKEN_URL', $this->tokenUrl);
		$this->category = (string) env('MOPAY_CATEGORY', $this->category !== '' ? $this->category : 'BIZAO');
		$this->callbackSigningKey = (string) env('MOPAY_CALLBACK_SIGNING_KEY', $this->callbackSigningKey);
		$this->callbackUrl = (string) env('MOPAY_CALLBACK_URL', $this->callbackUrl);
		$this->defaultCountryCode = (string) env('MOPAY_DEFAULT_COUNTRY_CODE', $this->defaultCountryCode);
		$this->defaultMno = (string) env('MOPAY_DEFAULT_MNO', $this->defaultMno);
		$this->defaultCurrency = (string) env('MOPAY_DEFAULT_CURRENCY', $this->defaultCurrency);
		$this->receiverAccountNo = (string) env('MOPAY_RECEIVER_ACCOUNT_NO', $this->receiverAccountNo);
		$this->paymentTitle = (string) env('MOPAY_PAYMENT_TITLE', $this->paymentTitle);
		$this->paymentDetails = (string) env('MOPAY_PAYMENT_DETAILS', $this->paymentDetails);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return [
			'project_slug' => $this->projectSlug,
			'message_prefix' => $this->messagePrefix,
			'account_id' => $this->accountId,
			'auth_key' => $this->authKey,
			'bearer_token' => $this->bearerToken,
			'server_base_url' => $this->serverBaseUrl,
			'token_url' => $this->tokenUrl,
			'category' => $this->category,
			'callback_signing_key' => $this->callbackSigningKey,
			'callback_url' => $this->callbackUrl,
			'default_country_code' => $this->defaultCountryCode,
			'default_mno' => $this->defaultMno,
			'default_currency' => $this->defaultCurrency,
			'receiver_account_no' => $this->receiverAccountNo,
			'payment_title' => $this->paymentTitle,
			'payment_details' => $this->paymentDetails,
		];
	}
}
