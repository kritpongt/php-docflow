<?php
declare(strict_types=1);

use Core\Env;

return [
	'chrome_path' => Env::get('PDF_CHROME_PATH', ''),
	'node_module_path' => Env::get('PDF_NODE_MODULE_PATH', '')
];