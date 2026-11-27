<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\File;
use App\Models\FileRepo;
use Core\Config;
use Core\Exception\HttpException;
use Core\UploadedFile;

final class UploadService
{
	public function __construct(
		private readonly Config $config,
		private readonly FileRepo $files
	){}

	public function storeDocument(UploadedFile $upload, array $context): File
	{
		if(!$upload->ok()){
			throw HttpException::unprocessable(match($upload->error){
				UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File too large',
				UPLOAD_ERR_PARTIAL => 'Upload incomplete',
				UPLOAD_ERR_NO_FILE => 'No file uploaded',
				default => 'Invalid upload',
			});
		}

		$mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($upload->tmpPath);
		$ext = match($mime){
			'application/pdf' => 'pdf',
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
			default => throw HttpException::unprocessable('Only PDF, DOCX, XLSX are allowed'),
		};

		$tmpDir = (string) $this->config->get('storage.uploads_tmp');
		if(!is_dir($tmpDir) && !@mkdir($tmpDir, 0775, true)){
			throw HttpException::serviceUnavailable('Upload directory unavailable');
		}

		$id = bin2hex(random_bytes(16));
		$staged = $tmpDir.'/'.$id.'.'.$ext;
		if(!@copy($upload->tmpPath, $staged)){
			throw HttpException::serviceUnavailable('Cannot stage upload');
		}

		$relative = 'documents/'.date('Y/m').'/'.$id.'.'.$ext;

		$safeName = preg_replace('/[\x00-\x1F\x7F]/u', '', $upload->originalName) ?: 'file.'.$ext;

		return $this->persist($staged, $relative, [
			'kind' => 'document',
			'scope' => $context['scope'],
			'owner_id' => $context['owner_id'] ?? null,
			'path' => $relative,
			'original_name' => mb_substr($safeName, 0, 255),
			'mime' => $mime,
			'size' => (int) filesize($staged),
			'width' => null,
			'height' => null,
			'checksum' => hash_file('sha256', $staged),
			'uploaded_by' => $context['uploaded_by'],
		]);
	}

	public function storeImage(UploadedFile $upload, array $context): File
	{
		if(!$upload->ok()){
			throw HttpException::unprocessable(match($upload->error){
				UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File too large',
				UPLOAD_ERR_PARTIAL => 'Upload incomplete',
				UPLOAD_ERR_NO_FILE => 'No file uploaded',
				default => 'Invalid upload',
			});
		}

		$mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($upload->tmpPath);
		if(!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)){
			throw HttpException::unprocessable('Only JPG, PNG, WEBP are allowed');
		}

		$dims = @getimagesize($upload->tmpPath);
		if($dims === false
			|| $dims[0] > (int) $this->config->get('upload.max_width', 4096)
			|| $dims[1] > (int) $this->config->get('upload.max_height', 4096)
			|| ($dims[0] * $dims[1]) > (int) $this->config->get('upload.max_pixels', 20000000)){
			throw HttpException::unprocessable('Image dimensions too large');
		}

		$source = match($mime){
			'image/jpeg' => @imagecreatefromjpeg($upload->tmpPath),
			'image/png'  => @imagecreatefrompng($upload->tmpPath),
			'image/webp' => @imagecreatefromwebp($upload->tmpPath),
		};
		if($source === false){
			throw HttpException::unprocessable('Unreadable image');
		}

		$canvas = imagecreatetruecolor($dims[0], $dims[1]);
		imagealphablending($canvas, false);
		imagesavealpha($canvas, true);
		imagecopyresampled($canvas, $source, 0, 0, 0, 0, $dims[0], $dims[1], $dims[0], $dims[1]);

		$root = (string) $this->config->get('storage.uploads');
		$tmpDir = (string) $this->config->get('storage.uploads_tmp');
		if(!is_dir($tmpDir) && !@mkdir($tmpDir, 0775, true)){
			throw HttpException::serviceUnavailable('Upload directory unavailable');
		}

		$id = bin2hex(random_bytes(16));
		$tmpOut = $tmpDir.'/'.$id.'.webp';
		$relative = 'images/'.date('Y/m').'/'.$id.'.webp';

		if(!@copy($upload->tmpPath, $tmpOut)){
			throw HttpException::serviceUnavailable('Cannot stage upload');
		}

		if(!imagewebp($canvas, $tmpOut, 85)){
			imagedestroy($source); imagedestroy($canvas);
			throw HttpException::serviceUnavailable('Cannot encode image');
		}
		imagedestroy($source);
		imagedestroy($canvas);

		$bytes = (int) filesize($tmpOut);
		$checksum = hash_file('sha256', $tmpOut);

		return $this->persist($tmpOut, $relative, [
			'kind' => 'image',
			'scope' => $context['scope'],
			'owner_id' => $context['owner_id'] ?? null,
			'path' => $relative,
			'original_name' => mb_substr($upload->originalName, 0, 255),
			'mime' => 'image/webp',
			'size' => $bytes,
			'width' => $dims[0],
			'height' => $dims[1],
			'checksum' => $checksum,
			'uploaded_by' => $context['uploaded_by'],
		]);
	}

	private function persist(string $staged, string $relative, array $meta): File
	{
		$root = (string) $this->config->get('storage.uploads');
		$final = $root.'/'.$relative;
		$record = null;

		try{
			$record = $this->files->create($meta);

			if(!is_dir(dirname($final)) && !@mkdir(dirname($final), 0775, true)){
				throw new \RuntimeException('Cannot create folder');
			}
			if(!@rename($staged, $final)){
				throw new \RuntimeException('Cannot move file');
			}
			return $record;
		}catch(\Throwable $e){
			if($record !== null){ $this->files->delete($record->id); }
			@unlink($staged);
			@unlink($final);
			throw $e;
		}
	}

	public function absolutePath(string $relative): string
	{
		$root = realpath((string) $this->config->get('storage.uploads'));
		$path = realpath($root.'/'.ltrim($relative, '/'));

		if($root === false || $path === false || !str_starts_with($path, $root.'/')){
			throw HttpException::notFound('File not found');
		}

		return $path;
	}
}
