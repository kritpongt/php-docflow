<?php
declare(strict_types=1);

namespace App\Controllers\Backoffice;

use Core\Request;
use Core\Response;
use Core\SessionInterface;
use Core\View;

final class ReportSettingsController
{
	public function __construct(
		private readonly SessionInterface $session,
		private readonly View $view
	){}

	public function index(Request $request): Response
	{
		$data = [
			'success' => $this->session->getFlash('roles_success'),
			'error' => $this->session->getFlash('roles_error'),
		];

		if($request->isHtmx()){
			return Response::html($this->view->renderPartial('backoffice/roles/index', $data));
		}

		return Response::html($this->view->renderPage('backoffice/roles/index', $data));
	}
}