<?php

declare(strict_types=1);

namespace App\Extensions\Chatbot\System\Services;

use Illuminate\Support\Facades\Http;

class SecureUrlIngestionService
{
    private const CONNECT_TIMEOUT = 10; // seconds
    private const REQUEST_TIMEOUT = 30; // seconds
    private const MAX_RESPONSE_SIZE = 10 * 1024 * 1024; // 10MB
    private const MAX_REDIRECTS = 5;
    private const DNS_REBIND_TIMEOUT = 1; // seconds

    private array $privateCIDRs = [
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '127.0.0.0/8',
        '0.0.0.0/8',
        '169.254.0.0/16', // Link-local
        '224.0.0.0/4', // Multicast
        '240.0.0.0/4', // Reserved
    ];

    private array $metadataCIDRs = [
        '169.254.169.254/32', // AWS metadata
        '169.254.170.2/32', // AWS ECS metadata
        '127.0.0.53/32', // systemd-resolved
    ];

    /**
     * Validate and prepare URL for ingestion
     */
    public function validateUrl(string $url): array
    {
        $errors = [];

        // Only allow HTTP/HTTPS
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'], true)) {
            $errors[] = 'Only HTTP and HTTPS schemes are supported';
        }

        // Reject embedded credentials
        $user = parse_url($url, PHP_URL_USER);
        $pass = parse_url($url, PHP_URL_PASS);
        if ($user || $pass) {
            $errors[] = 'Embedded credentials are not allowed';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Resolve DNS and check for private IPs (SSRF protection)
     */
    public function resolveDns(string $url): array
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return [
                'valid' => false,
                'error' => 'Invalid URL host',
            ];
        }

        try {
            // Resolve DNS
            $ip = gethostbyname($host);

            if ($ip === $host) {
                // DNS resolution failed
                return [
                    'valid' => false,
                    'error' => 'Unable to resolve hostname',
                ];
            }

            // Check for private/reserved IP ranges
            if ($this->isPrivateIp($ip)) {
                return [
                    'valid' => false,
                    'error' => 'Access to private IP addresses is not allowed',
                ];
            }

            if ($this->isMetadataIp($ip)) {
                return [
                    'valid' => false,
                    'error' => 'Access to cloud metadata service is not allowed',
                ];
            }

            return [
                'valid' => true,
                'ip' => $ip,
                'host' => $host,
            ];
        } catch (\Throwable $exception) {
            return [
                'valid' => false,
                'error' => 'DNS resolution failed: ' . $exception->getMessage(),
            ];
        }
    }

    /**
     * Fetch URL with security protections
     */
    public function fetchUrl(string $url, array $options = []): array
    {
        // Validate URL
        $validation = $this->validateUrl($url);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => 'Invalid URL: ' . implode(', ', $validation['errors']),
            ];
        }

        // Resolve DNS and check for SSRF
        $dnsValidation = $this->resolveDns($url);
        if (!$dnsValidation['valid']) {
            return [
                'success' => false,
                'error' => 'DNS validation failed: ' . $dnsValidation['error'],
            ];
        }

        try {
            // Build secure HTTP client
            $client = Http::withOptions([
                'connect_timeout' => self::CONNECT_TIMEOUT,
                'timeout' => self::REQUEST_TIMEOUT,
                'allow_redirects' => [
                    'max' => self::MAX_REDIRECTS,
                    'strict' => true,
                    'referer' => true,
                ],
            ])
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; ChatbotBot/1.0)',
            ]);

            // Make request
            $response = $client->get($url);

            // Check if response is too large before fully reading it
            $contentLength = (int) ($response->header('Content-Length') ?? 0);
            if ($contentLength > 0 && $contentLength > self::MAX_RESPONSE_SIZE) {
                return [
                    'success' => false,
                    'error' => 'Response exceeds maximum size limit',
                ];
            }

            // Validate content type
            $contentType = $response->header('Content-Type') ?? '';
            if (!$this->isAllowedContentType($contentType)) {
                return [
                    'success' => false,
                    'error' => 'Unsupported content type: ' . $contentType,
                ];
            }

            // Get response body
            $body = $response->body();

            // Validate DNS rebinding on redirects
            $finalUrl = $response->getEffectiveUrl() ?? $url;
            if ($finalUrl !== $url) {
                $rebindValidation = $this->resolveDns($finalUrl);
                if (!$rebindValidation['valid']) {
                    return [
                        'success' => false,
                        'error' => 'DNS rebinding detected on redirect',
                    ];
                }
            }

            return [
                'success' => true,
                'url' => $url,
                'final_url' => $finalUrl,
                'content' => $body,
                'content_type' => $contentType,
                'size' => strlen($body),
                'resolved_ip' => $dnsValidation['ip'],
            ];
        } catch (\Throwable $exception) {
            return [
                'success' => false,
                'error' => 'Failed to fetch URL: ' . $exception->getMessage(),
            ];
        }
    }

    /**
     * Check if IP is in private range
     */
    private function isPrivateIp(string $ip): bool
    {
        foreach ($this->privateCIDRs as $cidr) {
            if ($this->isIpInCidr($ip, $cidr)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if IP is metadata service
     */
    private function isMetadataIp(string $ip): bool
    {
        foreach ($this->metadataCIDRs as $cidr) {
            if ($this->isIpInCidr($ip, $cidr)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if IP is in CIDR range
     */
    private function isIpInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - (int) $bits);
        $subnet &= $mask;
        return ($ip & $mask) === $subnet;
    }

    /**
     * Validate content type
     */
    private function isAllowedContentType(string $contentType): bool
    {
        $allowedTypes = [
            'text/html',
            'text/plain',
            'text/xml',
            'application/xhtml+xml',
            'application/xml',
        ];

        // Extract main type (before semicolon)
        $mainType = explode(';', $contentType)[0];
        return in_array($mainType, $allowedTypes, true);
    }
}
