<?php
declare(strict_types=1);

namespace App\Services;

use Core\Database;

final class ReceiptService
{
	public function __construct(
		private readonly Database $db
	){}

	public function createFromInvoice(int $invoiceId): Document
	{
		// if(!$this->db->isInTransaction()){
		// 	throw new \LogicException('createFromInvoice() must called inside a Transaction');
		// }

		$invoice = $this->documentRepo->findById($invoiceId);

	}
}