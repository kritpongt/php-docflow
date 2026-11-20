<?php
declare(strict_types=1);

namespace App\Models;

use Core\Database;

class DocumentRepo
{
	public function __construct(private readonly Database $db){}

	public function findById(int $id): ?Document
	{
		$row = $this->db->selectOne('SELECT * FROM documents WHERE id = :id', ['id' => $id]);
		return $row === null ? null : $this->map($row);
	}

	public function findByIdForUpdate(int $id): ?Document
	{
		$row = $this->db->selectOne('SELECT * FROM documents WHERE id = :id FOR UPDATE', ['id' => $id]);
		return $row === null ? null : $this->map($row);
	}

	public function findByReferenceId(int $id, string $type): ?Document
	{
		$row = $this->db->selectOne(
			'SELECT * FROM documents WHERE reference_id = :reference_id AND type = :type', 
			[
				'reference_id' => $id, 
				'type' => $type
			]
		);
		return $row === null ? null : $this->map($row);
	}

	public function findByType(string $type, array $status = []): array
	{
		$sqlWhere = ' WHERE type = :type ';
		$params = ['type' => $type];

		if(count($status) > 0){
			$placeholders = [];
			foreach(array_values($status) as $i => $s){
				$placeholders[] = ":s{$i}";
				$params["s{$i}"] = $s;
			}
			$sqlWhere .= ' AND status IN('.implode(',', $placeholders).')';
		}

		$sql = "SELECT * FROM documents {$sqlWhere} ORDER BY id DESC";

		$rows = $this->db->select($sql, $params);

		return array_map($this->map(...), $rows);
	}

	public function findByIdAndType(int $id, string $type): ?Document
	{
		$row = $this->db->selectOne(
			'SELECT * FROM documents WHERE id = :id AND type = :type',
			[
				'id' => $id,
				'type' => $type
			]
		);
		return $row === null ? null : $this->map($row);
	}

	public function create(array $data): Document
	{
		$now = date('Y-m-d H:i:s');
		
		$this->db->execute(
			'INSERT INTO documents(
				document_no, customer_id, type, status, issue_date, due_date, subtotal, 
				operation_fee_rate, operation_fee_amount, discount_rate, discount_amount, 
				vat_rate, vat_amount, grand_total, title, notes, reference_id, reference_document_no, 
				created_at, updated_at
			) VALUES(
				:document_no, :customer_id, :type, :status, :issue_date, :due_date, :subtotal, 
				:operation_fee_rate, :operation_fee_amount, :discount_rate, :discount_amount,
				:vat_rate, :vat_amount, :grand_total, :title, :notes, :reference_id, :reference_document_no, 
				:created_at, :updated_at
			)',
			[
				'document_no' => $data['document_no'],
				'customer_id' => $data['customer_id'],
				'type' => $data['type'],
				'status' => $data['status'],
				'issue_date' => $data['issue_date'],
				'due_date' => $data['due_date'] ?? null,
				'subtotal' => $data['subtotal'] ?? 0.00,
				'operation_fee_rate' => $data['operation_fee_rate'] ?? 0.00,
				'operation_fee_amount' => $data['operation_fee_amount'] ?? 0.00,
				'discount_rate' => $data['discount_rate'] ?? 0.00,
				'discount_amount' => $data['discount_amount'] ?? 0.00,
				'vat_rate' => $data['vat_rate'] ?? 0.00,
				'vat_amount' => $data['vat_amount'] ?? 0.00,
				'grand_total' => $data['grand_total'] ?? 0.00,
				'title' => $data['title'] ?? null,
				'notes' => $data['notes'] ?? null,
				'reference_id' => $data['reference_id'] ?? null,
				'reference_document_no' => $data['reference_document_no'] ?? null,
				'created_at' => $now,
				'updated_at' => $now
			]
		);

		return clone $this->findById((int) $this->db->lastInsertId());
	}

	public function updateStatus(int $id, string $status): void
	{
		$now = date('Y-m-d H:i:s');

		$this->db->execute(
			'UPDATE documents SET status = :status, updated_at = :updated_at WHERE id = :id',
			[
				'id' => $id,
				'status' => $status,
				'updated_at' => $now
			]
		);
	}

	public function update(int $id, array $data): void
	{
		$now = date('Y-m-d H:i:s');
		$this->db->execute(
			'UPDATE documents SET
				customer_id = :customer_id,
				issue_date = :issue_date,
				due_date = :due_date,
				subtotal = :subtotal,
				operation_fee_rate = :operation_fee_rate,
				operation_fee_amount = :operation_fee_amount,
				discount_rate = :discount_rate,
				discount_amount = :discount_amount,
				vat_rate = :vat_rate,
				vat_amount = :vat_amount,
				grand_total = :grand_total,
				title = :title,
				notes = :notes,
				updated_at = :updated_at 
			WHERE id = :id',
			[
				'id' => $id,
				'customer_id' => $data['customer_id'],
				'issue_date' => $data['issue_date'],
				'due_date' => $data['due_date'] ?? null,
				'subtotal' => $data['subtotal'] ?? 0.00,
				'operation_fee_rate' => $data['operation_fee_rate'] ?? 0.00,
				'operation_fee_amount' => $data['operation_fee_amount'] ?? 0.00,
				'discount_rate' => $data['discount_rate'] ?? 0.00,
				'discount_amount' => $data['discount_amount'] ?? 0.00,
				'vat_rate' => $data['vat_rate'] ?? 0.00,
				'vat_amount' => $data['vat_amount'] ?? 0.00,
				'grand_total' => $data['grand_total'] ?? 0.00,
				'title' => $data['title'] ?? null,
				'notes' => $data['notes'] ?? null,
				'updated_at' => $now,
			]
		);
	}

	public function generateNo(string $type): string
	{
		$prefix = match($type){
			'quotation' => 'QU',
			// 'delivery' => 'DO',
			'invoice' => 'INV',
			default => 'DOC'
		};

		$yearMonth = date('Ym'); // e.g., 202608
		$searchPrefix = "{$prefix}-{$yearMonth}-%";

		// Find the latest document number for this month
		$row = $this->db->selectOne(
			'SELECT document_no FROM documents WHERE document_no LIKE :prefix ORDER BY id DESC LIMIT 1',
			['prefix' => $searchPrefix]
		);

		if($row === null){
			return "{$prefix}-{$yearMonth}-001";
		}

		// Example: QU-202608-001 -> Extract '001' and increment
		$parts = explode('-', (string) $row['document_no']);
		$lastNumber = (int) end($parts);
		$newNumber = str_pad((string) ($lastNumber + 1), 3, '0', STR_PAD_LEFT);

		return "{$prefix}-{$yearMonth}-{$newNumber}";
	}

	public function search(string $type ,array $filters = [], int $page = 1, int $perPage = 15): array
	{
		$search = (string) $filters['search'] ?? '';
		$status = (string) $filters['status'] ?? '';
		$documentNo = (string) $filters['documentNo'] ?? '';
		$dateFrom = (string) $filters['dateFrom'] ?? '';
		$dateTo = (string) $filters['dateTo'] ?? '';

		$where = [];
		$params = [];
		$joinSql = '';

		$where[] = 'type = :type';
		$params['type'] = $type;
		if($search !== ''){
			$where[] = '(document_no LIKE :search OR title LIKE :search OR notes LIKE :search)';
			$params['search'] = "%{$search}%";
		}
		if($documentNo !== ''){
			$where[] = 'document_no = :documentNo';
			$params['documentNo'] = $documentNo;
		}
		if($status !== ''){
			$where[] = 'status = :status';
			$params['status'] = $status;
		}

		$sqlWhere = $where ? ' WHERE '.implode(' AND ', $where) : '';

		$total = (int) ($this->db->selectOne("SELECT COUNT(DISTINCT a.id) as aggregate FROM documents a {$sqlWhere}", $params))['aggregate'] ?? 0;

		$offset = ($page - 1) * $perPage;
		$params['limit'] = $perPage;
		$params['offset'] = $offset;

		$rows = $this->db->select("SELECT DISTINCT a.*
			FROM documents a {$sqlWhere}
			ORDER BY a.id DESC
			LIMIT :limit OFFSET :offset", $params
		);

		if(empty($rows)){
			return ['items' => [], 'total' => $total];
		}

		return [
			'items' => array_map($this->map(...), $rows),
			'total' => $total
		];
	}

	private function map(array $row): Document
	{
		return new Document(
			id: (int) $row['id'],
			document_no: (string) $row['document_no'],
			customer_id: (int) $row['customer_id'],
			type: (string) $row['type'],
			status: (string) $row['status'],
			issue_date: (string) $row['issue_date'],
			due_date: $row['due_date'] !== null ? (string) $row['due_date'] : null,
			subtotal: (float) $row['subtotal'],
			operation_fee_rate: (float) $row['operation_fee_rate'],
			operation_fee_amount: (float) $row['operation_fee_amount'],
			discount_rate: (float) $row['discount_rate'],
			discount_amount: (float) $row['discount_amount'],
			vat_rate: (float) $row['vat_rate'],
			vat_amount: (float) $row['vat_amount'],
			grand_total: (float) $row['grand_total'],
			title: $row['title'] !== null ? (string) $row['title'] : null,
			notes: $row['notes'] !== null ? (string) $row['notes'] : null,
			reference_id: $row['reference_id'] !== null ? (int) $row['reference_id'] : null,
			reference_document_no: $row['reference_document_no'] !== null ? (string) $row['reference_document_no'] : null,
			created_at: (string) $row['created_at'],
			updated_at: (string) $row['updated_at']
		);
	}
}