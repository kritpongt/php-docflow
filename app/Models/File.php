<?php
declare(strict_types=1);

namespace App\Models;

final class File
{
	public function __construct(
		public readonly int $id,
		public readonly string $kind,
		public readonly string $scope,
		public readonly ?int $owner_id,
		public readonly string $path,
		public readonly string $original_name,
		public readonly string $mime,
		public readonly ?int $width,
		public readonly ?int $height,
		public readonly string $checksum,
		public readonly int $uploaded_by,
		public readonly string $create_at = '',
	){}
}