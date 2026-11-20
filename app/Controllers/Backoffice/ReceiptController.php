<?php
declare(strict_types=1);

namespace App\Controllers\Backoffice;

use App\Models\DocumentRepo;
use Core\Request;
use Core\Response;
use Core\View;

final class ReceiptController
{
	public function __construct(
		private readonly DocumentRepo $documentRepo,
		private readonly View $view
	){}

	public function index(Request $request): Response
	{
		$type = 'receipt';
		$documents = $this->documentRepo->findByType($type);

		$data = [
			'documents' => $documents,
			'type' => $type
		];

		if($request->isHtmx()){
			return Response::html($this->view->renderPartial('backoffice/receipt/index', $data));
		}

		return Response::html($this->view->renderPage('backoffice/receipt/index', $data));
	}
}