<?php
declare(strict_types=1);

namespace Core\Exception;

use RuntimeException;
use Throwable;

final class HttpException extends RuntimeException
{
	public const UNAUTHORIZED = 401;
	public const FORBIDDEN = 403;
	public const NOT_FOUND = 404;
	public const CONFLICT = 409;
	public const UNPROCESSABLE = 422;
	public const TOO_MANY_REQUEST = 429;
	public const SERVICE_UNAVAILABLE = 503;

	public function __construct(
		private readonly int $status,
		string $message,
		?Throwable $previous = null
	){
		parent::__construct($message, 0, $previous);
	}

	public function status(): int
	{
		return $this->status;
	}

	public static function unauthorized(string $m = 'Unauthorized'): self
	{
		return new self(self::UNAUTHORIZED, $m);
	}

	public static function forbidden(string $m = 'Forbidden'): self
	{
		return new self(self::FORBIDDEN, $m);
	}
	
	public static function notFound(string $m = 'Not found'): self
	{
		return new self(self::NOT_FOUND, $m);
	}

	/** Request is valid but unable to proceed */
	public static function conflict(string $m): self
	{
		return new self(self::CONFLICT, $m);
	}

	/** Request contains a value we cannot accept (fix payload, then retry) */
	public static function unprocessable(string $m): self
	{
		return new self(self::UNPROCESSABLE, $m);
	}

	public static function tooManyRequest(string $m): self
	{
		return new self(self::TOO_MANY_REQUEST, $m);
	}

	public static function serviceUnavailable(string $m): self
	{
		return new self(self::SERVICE_UNAVAILABLE, $m);
	}
}