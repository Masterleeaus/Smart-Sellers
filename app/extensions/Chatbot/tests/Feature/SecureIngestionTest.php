<?php

declare(strict_types=1);

namespace App\Extensions\Chatbot\Tests\Feature;

use App\Extensions\Chatbot\System\Services\SecureFileIngestionService;
use App\Extensions\Chatbot\System\Services\SecureUrlIngestionService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SecureIngestionTest extends TestCase
{
    private SecureUrlIngestionService $urlService;
    private SecureFileIngestionService $fileService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->urlService = app(SecureUrlIngestionService::class);
        $this->fileService = app(SecureFileIngestionService::class);
    }

    // URL Ingestion Tests
    public function test_rejects_non_http_scheme(): void
    {
        $validation = $this->urlService->validateUrl('ftp://example.com');
        $this->assertFalse($validation['valid']);
    }

    public function test_rejects_urls_with_embedded_credentials(): void
    {
        $validation = $this->urlService->validateUrl('https://user:pass@example.com');
        $this->assertFalse($validation['valid']);
    }

    public function test_rejects_private_ip_addresses(): void
    {
        $dnsValidation = $this->urlService->resolveDns('http://192.168.1.1');
        $this->assertFalse($dnsValidation['valid']);
    }

    public function test_rejects_localhost(): void
    {
        $dnsValidation = $this->urlService->resolveDns('http://localhost');
        $this->assertFalse($dnsValidation['valid']);
    }

    public function test_rejects_127_0_0_1(): void
    {
        $dnsValidation = $this->urlService->resolveDns('http://127.0.0.1');
        $this->assertFalse($dnsValidation['valid']);
    }

    public function test_rejects_aws_metadata_service(): void
    {
        $dnsValidation = $this->urlService->resolveDns('http://169.254.169.254');
        $this->assertFalse($dnsValidation['valid']);
    }

    public function test_rejects_link_local_addresses(): void
    {
        $dnsValidation = $this->urlService->resolveDns('http://169.254.1.1');
        $this->assertFalse($dnsValidation['valid']);
    }

    // File Ingestion Tests
    public function test_rejects_oversized_files(): void
    {
        $file = UploadedFile::fake()->create('test.txt', 60 * 1024); // 60MB

        $validation = $this->fileService->validateFile($file);
        $this->assertFalse($validation['valid']);
    }

    public function test_rejects_unsupported_file_types(): void
    {
        $file = UploadedFile::fake()->create('test.exe', 100);

        $validation = $this->fileService->validateFile($file);
        $this->assertFalse($validation['valid']);
    }

    public function test_accepts_allowed_file_types(): void
    {
        $validFiles = ['test.txt', 'test.pdf', 'test.csv', 'test.xlsx', 'test.json'];

        foreach ($validFiles as $filename) {
            $file = UploadedFile::fake()->create($filename, 100);
            $validation = $this->fileService->validateFile($file);
            $this->assertTrue($validation['valid'], "File $filename should be accepted");
        }
    }

    public function test_detects_macro_enabled_documents(): void
    {
        // Create a fake macro-enabled file
        $file = UploadedFile::fake()->create('test.xlsm', 100);

        $validation = $this->fileService->validateFile($file);
        // Fake files won't have real macros, but extension check should work
        $this->assertFalse($validation['valid']);
    }

    public function test_stores_files_with_server_generated_names(): void
    {
        $file = UploadedFile::fake()->create('important-file.txt', 100);

        $result = $this->fileService->storeFileSecurely($file);

        $this->assertTrue($result['success']);
        $this->assertNotContains('important-file', $result['filename']);
        $this->assertStringStartsWith('chatbot/quarantine/', $result['path']);
        $this->assertEquals('private', $result['disk']);
    }

    public function test_stores_files_in_private_disk_not_public(): void
    {
        $file = UploadedFile::fake()->create('test.txt', 100);

        $result = $this->fileService->storeFileSecurely($file);

        $this->assertTrue($result['success']);
        $this->assertEquals('private', $result['disk']);
    }

    public function test_calculates_file_hash_for_integrity(): void
    {
        $file = UploadedFile::fake()->create('test.txt', 100);
        $result = $this->fileService->storeFileSecurely($file);

        $this->assertTrue($result['success']);

        $hash = $this->fileService->calculateFileHash($result['path'], $result['disk']);
        $this->assertNotEmpty($hash);
        $this->assertEquals(64, strlen($hash)); // SHA256 = 64 hex chars
    }

    public function test_sanitizes_file_metadata(): void
    {
        $file = UploadedFile::fake()->create('confidential.pdf', 100);

        $metadata = $this->fileService->getFileSafeMetadata($file);

        $this->assertEquals('confidential.pdf', $metadata['original_name']);
        $this->assertEquals('pdf', $metadata['extension']);
        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('uploaded_at', $metadata);
    }
}
