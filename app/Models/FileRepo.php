<?php
declare(strict_types=1);

namespace App\Models;

class FileRepo
{
	public function __construct(private readonly Database $db){}

	public function findById(int $id): ?File
	{
		$row = $this->db->selectOne('SELECT * FROM files WHERE id = :id', ['id' => $id]);
		return $row === null ? null : $this->map($row);
	}

	public function findByScope(string $scope, ?int $ownerId = null): ?File
	{
		$sql = 'SELECT * FROM files WHERE scope = :scope';
		$params = ['scope' => $scope];
		if($ownerId !== null){
			$sql .= ' AND owner_id = :owner_id';
			$params['owner_id'] = $ownerId;
		}
		$row = $this->db->selectOne($sql.' ORDER BY id DESC LIMIT 1', $params);
		return $row === null ? null : $this->map($row);
	}

	public function create(array $data): File
	{
		$this->db->execute(
			'INSERT INTO files(kind, scope, owner_id, path, original_name, mime, size, width, height, checksum, uploaded_by, created_at)
			 VALUES(:kind, :scope, :owner_id, :path, :original_name, :mime, :size, :width, :height, :checksum, :uploaded_by, :created_at)',
			[
				'kind' => $data['kind'], 'scope' => $data['scope'],
				'owner_id' => $data['owner_id'], 'path' => $data['path'],
				'original_name' => $data['original_name'], 'mime' => $data['mime'],
				'size' => $data['size'], 'width' => $data['width'], 'height' => $data['height'],
				'checksum' => $data['checksum'], 'uploaded_by' => $data['uploaded_by'],
				'created_at' => date('Y-m-d H:i:s'),
			]
		);
		return clone $this->findById((int) $this->db->lastInsertId());
	}

	public function delete(int $id): void
	{
		$this->db->execute('DELETE FROM files WHERE id = :id', ['id' => $id]);
	}

	private function map(array $row): File
	{
		return new File(
			id: (int) $row['id'], kind: (string) $row['kind'],
			scope: (string) $row['scope'],
			owner_id: $row['owner_id'] !== null ? (int) $row['owner_id'] : null,
			path: (string) $row['path'], originalName: (string) $row['original_name'],
			mime: (string) $row['mime'], size: (int) $row['size'],
			width: $row['width'] !== null ? (int) $row['width'] : null,
			height: $row['height'] !== null ? (int) $row['height'] : null,
			checksum: (string) $row['checksum'], uploadedBy: (int) $row['uploaded_by'],
			created_at: (string) $row['created_at']
		);
	}
}