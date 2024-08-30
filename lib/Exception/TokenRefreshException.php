<?php

declare(strict_types=1);

namespace OCA\Trackmania\Exception;

use GuzzleHttp\Exception\ClientException;
use OCP\AppFramework\Http;

class TokenRefreshException extends \Exception {
	public function __construct(
		public ClientException $requestException,
		string $message = '',
		int $code = Http::STATUS_UNAUTHORIZED,
	) {
		parent::__construct($message, $code);
	}
}
