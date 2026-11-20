<?php
declare(strict_types=1);

namespace Core;

final class MiddlewareSpec
{
	/** @var class-string<MiddlewareWithParams> $class */

	/** @var list<string> */
	public readonly array $params;

	public function __construct(
		public readonly string $class, // Constructor Property Promotion
		string ...$params
	){
		$this->params = array_values($params);
	}
}