<?php
declare(strict_types=1);

namespace Core;

final class UploadedFile
{
	public function __construct(
		public readonly string $originalName,
		public readonly string $tmpPath,
		public readonly int $error,
		public readonly int $size
	){}

	public function ok(): bool
	{
		return $this->error === UPLOAD_ERR_OK && $this->tmpPath !== '';
	}
}