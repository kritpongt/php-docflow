<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use Core\Request;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
	private function makeRequest(array $headers = []): Request
	{
		return new Request('GET', '/', [], [], [], $headers, [], '127.0.0.1');
	}

	public function test_header_lookup_is_case_insensitive(): void
	{
		$request = $this->makeRequest(['hx-request' => 'true']);

		$this->assertSame('true', $request->header('HX-Request'));
	}

	public function test_is_htmx(): void
	{
		$this->assertTrue($this->makeRequest(['hx-request' => 'true'])->isHtmx());
		$this->assertFalse($this->makeRequest()->isHtmx());
	}

	public function test_input_falls_back_to_default(): void
	{
		$request = new Request('POST', '/x', [], ['name' => 'abc'], [], [], [], '127.0.0.1');

		$this->assertSame('abc', $request->input('name'));
		$this->assertSame('xxx', $request->input('nope', 'xxx'));
	}

	public function test_with_files_keeps_the_original_path(): void
	{
		$request = (new Request('POST', '/documents', [], [], [], [], [], '127.0.0.1'))
			->withFiles(['file' => new \Core\UploadedFile('a.pdf', '/tmp/a.pdf', UPLOAD_ERR_OK, 1)]);

		$this->assertSame('/documents', $request->path());
		$this->assertArrayHasKey('file', $request->all());
	}
}