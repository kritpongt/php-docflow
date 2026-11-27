<?php
declare(strict_types=1);

namespace App\Services;

use Core\Config;
use Spatie\Browsershot\Browsershot;

final class PdfService
{
	public function __construct(private readonly Config $config){}

	public function generateFromHtml(string $html, string $filename = 'document.pdf', bool $stream = true, ?string $headerHtml = null, ?string $footerHtml = null): ?string
	{
		$browsershot = Browsershot::html($html)
			->format('A4')
			->margins(40, 10, 15, 10)
			->showBackground()
			->showBrowserHeaderAndFooter()
			->headerHtml($headerHtml ?? '<div></div>')
			->footerHtml($footerHtml ?? '<div></div>');

		$chrome = (string) $this->config->get('browsershot.chrome_path', '');
		if($chrome !== ''){
			$browsershot->setChromePath($chrome)->addChromiumArguments(['no-sandbox', 'disable-dev-shm-usage']);
		}
		$nodeModules = (string) $this->config->get('browsershot.node_module_path', '');
		if($nodeModules !== ''){
			$browsershot->setNodeModulePath($nodeModules);
		}

		$pdfContent = $browsershot->pdf();

		if($stream){
			header('Content-Type: application/pdf');
			header('Content-Disposition: inline; filename="'.$filename.'"');
			echo $pdfContent;
			exit;
		}

		return $pdfContent;
	}

	public function getLocalAssetAsBase64(string $relativePath, string $mimeType = 'application/octet-stream'): string
	{
		$fullPath = dirname(__DIR__, 2) . '/public/' . ltrim($relativePath, '/');

		if (!file_exists($fullPath)) {
				return '';
		}

		$base64 = base64_encode(file_get_contents($fullPath));
		return "data:{$mimeType};charset=utf-8;base64,{$base64}";
	}
}