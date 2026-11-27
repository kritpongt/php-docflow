<?php
declare(strict_types=1);

use Core\Env;

$root = dirname(__DIR__).'/storage';

return [
	'twig_cache' => $root.'/cache/twig',
	'logs' => $root.'/logs',
	'log_retention_days' => (int) Env::get('LOG_RETENTION_DAYS', '14'),
	'log_min_level' => Env::get('LOG_MIN_LEVEL', 'ERROR'),

	'uploads' => $root.'/uploads',
	'uploads_tmp' => $root.'/uploads/tmp',
	'upload' => [
		'max_width' => 4096,
		'max_height' => 4096,
		'max_pixels' => 20000000,
	]
];