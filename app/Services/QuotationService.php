<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\CustomerRepo;
use App\Models\Document;
use App\Models\DocumentItemRepo;
use App\Models\DocumentRepo;
use App\Utils\NumberHelper;
use Core\Database;
use Core\Exception\HttpException;

final class QuotationService
{
	public function __construct(
		private readonly DocumentRepo $documentRepo,
		private readonly DocumentItemRepo $itemRepo,
		private readonly CustomerRepo $customerRepo,
		private readonly InvoiceService $invoiceService,
		private readonly Database $db
	){}

	public function getDetails(int $id): array
	{
		$document = $this->documentRepo->findById($id);
		if($document === null){
			throw HttpException::notFound('Quotation not found');
		}

		$items = $this->itemRepo->findByDocumentId($id);
		$customer = $this->customerRepo->findById($document->customer_id);

		return [
			'document' => clone $document,
			'items' => $items,
			'customer' => $customer,
			'baht_text' => NumberHelper::bahtText($document->grand_total)
		];
	}

	public function createQuotation(array $docData, array $itemsData): Document
	{
		$resultData = $this->calculateTotals($docData, $itemsData);

		// db transaction
		return $this->db->transaction(function() use($resultData, $itemsData){
			if(empty($resultData['document_no'])){
				$resultData['document_no'] = $this->documentRepo->generateNo($resultData['type']);
			}
			$document = $this->documentRepo->create($resultData);

			$this->insertItems($document->id, $itemsData);

			return $document;
		});
	}

	public function updateStatus(int $id, string $newStatus): void
	{
		// db transaction
		$this->db->transaction(function() use($id, $newStatus){
			$document = $this->documentRepo->findByIdForUpdate($id);

			if($document === null || $document->type !== 'quotation'){
				throw HttpException::notFound('Quotation not found');
			}
	
			if(!in_array($newStatus, Document::STATUSES)){
				throw HttpException::unprocessable('Invalid status');
			}
	
			$currentStatus = $document->status;
			if($currentStatus === $newStatus){ return; }
	
			// state transitions
			$validTransitions = [
				'draft' => ['issued', 'approved', 'cancelled'],
				'issued' => ['draft', 'approved', 'cancelled'],
				'approved' => ['cancelled'],
				'cancelled' => [],
			];
			if(!in_array($newStatus, $validTransitions[$currentStatus] ?? [])){
				throw HttpException::conflict("Cannot change status from '{$currentStatus}' to '{$newStatus}'");
			}
	
			$this->documentRepo->updateStatus($id, $newStatus);
	
			if($newStatus === 'approved'){
				$this->invoiceService->createFromQuotation($id);
			}
		});
	}

	public function createRevision(int $id): Document
	{
		return $this->db->transaction(function() use($id){
			$quotation = $this->documentRepo->findByIdAndType($id, 'quotation');
			if($quotation === null){
				throw HttpException::notFound('Quotation not found');
			}

			$reviseData = [
				'document_no' => $this->documentRepo->generateNo('quotation'),
				'customer_id' => $quotation->customer_id,
				'type' => $quotation->type,
				'status' => 'draft',
				'issue_date' => $quotation->issue_date,
				'due_date' => $quotation->due_date,
				'subtotal' => $quotation->subtotal,
				'operation_fee_rate' => $quotation->operation_fee_rate,
				'operation_fee_amount' => $quotation->operation_fee_amount,
				'discount_rate' => $quotation->discount_rate,
				'discount_amount' => $quotation->discount_amount,
				'vat_rate' => $quotation->vat_rate,
				'vat_amount' => $quotation->vat_amount,
				'grand_total' => $quotation->grand_total,
				'title'=> $quotation->title,
				'notes'=> $quotation->notes,
				'reference_id'=> $quotation->id,
				'reference_document_no' => $quotation->document_no,
			];

			$document = $this->documentRepo->create($reviseData);
			$items = $this->itemRepo->findByDocumentId($quotation->id);
			$this->copyItems($document->id, $items);

			return $document;
		});
	}

	private function copyItems(int $id, array $items): void
	{
		foreach($items as $item){
			$this->itemRepo->create([
				'document_id'	=> $id,
				'product_id' => $item->product_id,
				'name' => $item->name,
				'description' => $item->description,
				'quantity' => $item->quantity,
				'unit_price' => $item->unit_price,
				'labor_price' => $item->labor_price,
				'total_price' => $item->total_price,
			]);
		}
	}

	public function updateQuotation(int $id, array $docData, array $itemsData): void
	{
		$resultData = $this->calculateTotals($docData, $itemsData);

		// db transaction
		$this->db->transaction(function() use($id, $resultData, $itemsData){
			$this->documentRepo->update($id, $resultData);

			$this->itemRepo->deleteByDocumentId($id);

			$this->insertItems($id, $itemsData);
		});
	}

	private function calculateTotals(array $data, array $items): array
	{
		$subtotal = 0;
		foreach($items as $item){
			$labor = (float) ($item['labor_price'] ?? 0);
			$subtotal += ($item['quantity'] * ($item['unit_price'] + $labor));
		}

		$operationFeeRate = (float) ($data['operation_fee_rate'] ?? 0);
		$operationFeeAmount = $operationFeeRate > 0 
			? ($subtotal * $operationFeeRate / 100) 
			: (float) $data['operation_fee_amount'];

		$base = $subtotal + $operationFeeAmount;

		$discountRate = (float) ($data['discount_rate'] ?? 0);
		$discountAmount = $discountRate > 0 
			? ($base * $discountRate / 100) 
			: (float) $data['discount_amount'];

		$afterDiscount = $base - $discountAmount;

		$vatRate = (float) ($data['vat_rate'] ?? 7);
		$vatAmount = $vatRate > 0 ? ($afterDiscount * $vatRate / 100) : 0;
		$grandTotal = $afterDiscount + $vatAmount;

		$data['subtotal'] = $subtotal;
		$data['operation_fee_amount'] = $operationFeeAmount;
		$data['discount_amount'] = $discountAmount;
		$data['vat_amount'] = $vatAmount;
		$data['grand_total'] = $grandTotal;

		return $data;
	}

	private function insertItems(int $id, array $items): void
	{
		foreach($items as $item){
			$item['document_id'] = $id;
			$item['labor_price'] = (float) ($item['labor_price'] ?? 0);
			$item['total_price'] = $item['quantity'] * ($item['unit_price'] + $item['labor_price']);
			$this->itemRepo->create($item);
		}
	}
}