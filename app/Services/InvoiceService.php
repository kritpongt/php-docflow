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

final class InvoiceService
{
	public function __construct(
		private readonly Database $db,
		private readonly DocumentRepo $documentRepo,
		private readonly DocumentItemRepo $itemRepo,
		private readonly CustomerRepo $customerRepo,
		private readonly ReceiptService $receiptService,
	){}

	public function getDetails(int $id): array
	{
		$document = $this->documentRepo->findByIdAndType($id, 'invoice');
		if($document === null){
			throw HttpException::notFound('Invoice not found');
		}

		$items = $this->itemRepo->findByDocumentId($id);
		$customer = $this->customerRepo->findById($document->customer_id);

		return [
			'document' => clone $document,
			'items' => $items,
			'baht_text' => NumberHelper::bahtText($document->grand_total),
			'customer' => $customer
		];
	}

	public function createFromQuotation(int $quotationId): Document
	{
		if(!$this->db->isInTransaction()){
			throw new \LogicException('createFromQuotation() must called inside a Transaction');
		}

		$quotation = $this->documentRepo->findById($quotationId);

		if($quotation === null || $quotation->type !== 'quotation'){
			throw HttpException::notFound('Quotation not found');
		}

		if($quotation->status !== 'approved'){
			throw HttpException::conflict('Can only create invoice from approved quotation');
		}

		$existingInvoice = $this->documentRepo->findByReferenceId($quotationId, 'invoice');
		if($existingInvoice !== null){
			throw HttpException::conflict('Invoice already exists for this Quotation (Document No: '.$quotation->document_no.')');
		}

		$items = $this->itemRepo->findByDocumentId($quotationId);

		$issueDate = date('Y-m-d');
		$dueDate = date('Y-m-d', strtotime('+30 days'));

		$invoiceData = [
			'document_no' => $this->documentRepo->generateNo('invoice'),
			'customer_id' => $quotation->customer_id,
			'type' => 'invoice',
			'status' => 'draft',
			'issue_date' => $issueDate,
			'due_date' => $dueDate,
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

		$invoice = $this->documentRepo->create($invoiceData);

		foreach($items as $item){
			$this->itemRepo->create([
				'document_id' => $invoice->id,
				'name' => $item->name,
				'description' => $item->description,
				'quantity' => $item->quantity,
				'unit_price' => $item->unit_price,
				'total_price' => $item->total_price
			]);
		}

		return $invoice;
	}

	public function updateStatus(int $id, string $newStatus): void
	{
		$document = $this->documentRepo->findById($id);
		if($document === null || $document->type !== 'invoice'){
			throw HttpException::notFound('Invoice not found');
		}

		if(!in_array($newStatus, Document::INV_STATUSES)){
			throw HttpException::unprocessable('Invalid status');
		}

		$currentStatus = $document->status;
		if($currentStatus === $newStatus){ return; }

		// state transitions
		$validTransitions = [
			'draft' => ['unpaid', 'paid', 'cancelled'],
			'unpaid' => ['draft', 'paid', 'cancelled'],
			'paid' => ['draft', 'unpaid', 'cancelled'],
			'cancelled' => [] // final state: can't change
		];
		if(!in_array($newStatus, $validTransitions[$currentStatus] ?? [])){
			throw HttpException::conflict("Cannot change status from '{$currentStatus}' to '{$newStatus}'");
		}

		if($newStatus === 'paid'){
			$this->receiptService->createFromInvoice($id);
		}else if($newStatus === 'cancelled'){
			$this->documentRepo->updateStatus($document->reference_id, $newStatus);
		}

		$this->documentRepo->updateStatus($id, $newStatus);
	}
}