<?php

declare(strict_types=1);

namespace OCA\Trackmania\Exception;

use GuzzleHttp\Exception\BadResponseException;
use OCP\AppFramework\Http;

class TmApiRequestException extends \Exception {
	public function __construct(
		public BadResponseException $requestException,
		public string $audience,
		public string $endpoint,
		public string $method,
		public array $params,
		string $message = '',
		int $code = Http::STATUS_UNAUTHORIZED,
	) {
		parent::__construct($message, $code);
	}
}
