<?php
declare(strict_types=1);

namespace App\Controllers\Backoffice;

use App\Models\CustomerRepo;
use App\Models\DocumentRepo;
use App\Services\PdfService;
use App\Services\QuotationService;
use Core\Exception\HttpException;
use Core\Request;
use Core\Response;
use Core\View;

final class QuotationController
{
	public function __construct(
		private readonly QuotationService $quotationService,
		private readonly DocumentRepo $documentRepo,
		private readonly CustomerRepo $customerRepo,
		private readonly View $view,
		private readonly PdfService $pdfService
	){}

	public function index(Request $request): Response
	{
		$page = (int) ($request->query('page') ?? 1);
		$filters = [
			'search' => (string) $request->query('q') ?? '',
			'status' => (string) $request->query('status') ?? '',
			'documentNo' => (string) $request->query('document_no') ?? '',
			'dateFrom' => (string) $request->query('date_from') ?? '',
			'dateTo' => (string) $request->query('date_to') ?? '',
		];

		$type = 'quotation';
		$documents = $this->documentRepo->search($type, $filters);
		$data = [
			'documents' => $documents['items'],
			'total' => $documents['total'],
			'statuses' => \App\Models\Document::STATUSES,
			'type' => $type,
			'filters' => $filters
		];

		if($request->isHtmx()){
			return Response::html($this->view->renderPartial('backoffice/quotation/index', $data));
		}
		return Response::html($this->view->renderPage('backoffice/quotation/index', $data));
	}

	/** GET /quotation/create */
	public function create(Request $request): Response
	{
		$customers = $this->customerRepo->all();
		
		$data = [
			'customers' => $customers
		];

		if($request->isHtmx()){
			return Response::html($this->view->renderPartial('backoffice/quotation/create', $data));
		}

		return Response::html($this->view->renderPage('backoffice/quotation/create', $data));
	}

	/** POST /quotation - store */
	public function store(Request $request): Response
	{
		$customerId = (int) $request->input('customer_id');

		if ($request->input('is_new_customer') === '1') {
			$customer = $this->customerRepo->create(
				name: (string) $request->input('new_customer_name'),
				tax_id: $request->input('new_customer_tax_id') ?: null,
				address: $request->input('new_customer_address') ?: null,
				phone: $request->input('new_customer_phone') ?: null,
				email: null
			);
			$customerId = $customer->id;
		}

		$quotationData = [
			'customer_id' => $customerId,
			'type' => 'quotation',
			'status' => 'draft',
			'issue_date' => $request->input('issue_date'),
			'due_date' => $request->input('due_date') ?: null,
			'operation_fee_rate' => (float) $request->input('operation_fee_rate', 0),
			'vat_rate' => (float) $request->input('vat_rate', 7),
			'discount_amount' => (float) $request->input('discount_amount', 0),
			'title' => $request->input('title'),
			'notes' => $request->input('notes'),
		];

		$item_names = $request->input('item_name') ?? [];
		$quantities = $request->input('item_quantity') ?? [];
		$prices = $request->input('item_price') ?? [];
		$labor_prices = $request->input('labor_price') ?? [];

		$itemsData = [];
		if (is_array($item_names)) {
			foreach ($item_names as $index => $name) {
				if (!empty($name)) {
					$itemsData[] = [
						'name' => $name,
						'quantity' => (float) ($quantities[$index] ?? 1),
						'unit_price' => (float) ($prices[$index] ?? 0),
						'labor_price' => (float) ($labor_prices[$index] ?? 0),
					];
				}
			}
		}

		$this->quotationService->createQuotation($quotationData, $itemsData);

		return $request->isHtmx()
			? (new Response('', 204))->withHeader('HX-Location', '{"path": "/backoffice/quotation", "target": "#main-content"}')
			: Response::redirect('/backoffice/quotation');
	}

	public function show(Request $request, string $id): Response
	{
		$docId = (int) $id;
		$details = $this->quotationService->getDetails($docId);

		if($request->isHtmx()){
			return Response::html($this->view->renderPartial('backoffice/quotation/show', $details));
		}

		return Response::html($this->view->renderPage('backoffice/quotation/show', $details));
	}

	public function streamPdf(Request $request, string $id): Response
	{
		$details = $this->quotationService->getDetails((int) $id);
		$document_no = $details['document']->document_no;

		$data = [
			'document' => $details['document'],
			'customer' => $details['customer'],
			'items' => $details['items'],
		];

		$data['logo_base64'] = $this->pdfService->getLocalAssetAsBase64('assets/imgs/logo2.jpg', 'image/jpeg');
		$data['font_reg_base64'] = $this->pdfService->getLocalAssetAsBase64('assets/fonts/Sarabun/Sarabun-Regular.ttf', 'font/truetype');
		$data['font_bold_base64'] = $this->pdfService->getLocalAssetAsBase64('assets/fonts/Sarabun/Sarabun-Bold.ttf', 'font/truetype');

		$pdfHeader = $this->view->renderPage('backoffice/quotation/_pdf_header', $data);
		$pdfFooter = $this->view->renderPage('backoffice/quotation/_pdf_footer', $data);
		$pdfHtml = $this->view->renderPage('backoffice/quotation/_pdf', $data);

		$this->pdfService->generateFromHtml($pdfHtml, "{$document_no}_quotation.pdf", true, $pdfHeader, $pdfFooter);

		return new Response('', 200, ['Content-Type' => 'application/pdf']);
	}

	public function edit(Request $request, string $id): Response
	{
		$docId = ctype_digit($id) ? (int) $id : 0;
		$details = $this->quotationService->getDetails($docId);
		$customers = $this->customerRepo->all();

		$data = [
			'targetDoc' => $details['document'],
			'items' => $details['items'],
			'customers' => $customers
		];

		if ($request->isHtmx()) {
			return Response::html($this->view->renderPartial("backoffice/quotation/edit", $data));
		}

		return Response::html($this->view->renderPage("backoffice/quotation/edit", $data));
	}

	public function update(Request $request, string $id): Response
	{
		$docId = ctype_digit($id) ? (int) $id : 0;

		$quotationData = [
			'customer_id' => (int) $request->input('customer_id'),
			'type' => 'quotation',
			'status' => 'draft',
			'issue_date' => $request->input('issue_date'),
			'due_date' => $request->input('due_date') ?: null,
			'operation_fee_rate' => (float) $request->input('operation_fee_rate', 0),
			'operation_fee_amount' => (float) $request->input('operation_fee_amount', 0),
			'discount_rate' => (float) $request->input('discount_rate', 0),
			'discount_amount' => (float) $request->input('discount_amount', 0),
			'vat_rate' => (float) $request->input('vat_rate', 7),
			'title' => $request->input('title'),
			'notes' => $request->input('notes'),
		];

		$errors = [];

		$item_names = $request->input('item_name') ?? [];
		$quantities = $request->input('item_quantity') ?? [];
		$prices = $request->input('item_price') ?? [];
		$labor_prices = $request->input('labor_price') ?? [];

		$itemsData = [];
		if(is_array($item_names)){
			foreach($item_names as $index => $name){
				if(!empty($name)){
					$itemsData[] = [
						'name' => $name,
						'quantity' => (float) ($quantities[$index] ?? 1),
						'unit_price' => (float) ($prices[$index] ?? 0),
						'labor_price' => (float) ($labor_prices[$index] ?? 0),
					];
				}
			}
		}

		$this->quotationService->updateQuotation($docId, $quotationData, $itemsData);

		return $request->isHtmx()
			? (new Response('', 204))->withHeader('HX-Location', '{"path": "/backoffice/quotation", "target": "#main-content"}')
			: Response::redirect('/backoffice/quotation');
	}

	// private function backToEdit(Request $request, array $errors, int $docId): Response
	// {
	// }

	public function status(Request $request, string $id): Response
	{
		$documentId = (int) $id;
		$newStatus = (string) $request->input('status');

    try{
			$this->quotationService->updateStatus($documentId, $newStatus);
    }catch(HttpException){
			// $this->session->flash('quotation_error', 'backoffice.quotation.error_invalid_transition'');

			// return $this->redirectToUsers($request);
    }

		return $request->isHtmx()
			? (new Response('', 204))->withHeader('HX-Location', '{"path": "/backoffice/quotation", "target": "#main-content"}')
			: Response::redirect('/backoffice/quotation');
	}

	public function revise(Request $request, string $id): Response
	{
		$documentId = (int) $id;
		$this->quotationService->createRevision($documentId);

		return $request->isHtmx()
			? (new Response('', 204))->withHeader('HX-Location', '{"path": "/backoffice/quotation", "target": "#main-content"}')
			: Response::redirect('/backoffice/quotation');
	}
}