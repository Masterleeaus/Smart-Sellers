<?php

declare(strict_types=1);

namespace App\Extensions\Chatbot\System\TitanShell;

use App\Extensions\Chatbot\System\Titan\TitanRegistry;
use RuntimeException;

final class TemplateSchema
{
    public const VERSION = '2.2.0';

    /**
     * Resolve canonical app schemas, legacy aliases, vertical presets,
     * functional templates and workspace presets into the shared-shell schema.
     *
     * @return array<string,mixed>
     */
    public static function resolve(?string $slug): array
    {
        $requestedSlug = trim((string) ($slug ?: 'titan-zero'));
        $canonicalSlug = PlatformApplicationRegistry::canonicalSlug($requestedSlug);
        $resolvedSlug = $canonicalSlug ?? $requestedSlug;

        $template = TitanRegistry::get($resolvedSlug);
        $schema = self::read($resolvedSlug)
            ?? (is_array($template) ? self::fromTemplate($template) : null)
            ?? self::generic($resolvedSlug);

        $schema = self::normalise($schema, $resolvedSlug);

        if ($canonicalSlug !== null && $requestedSlug !== $canonicalSlug) {
            $schema['migration'] = [
                'requested_slug' => $requestedSlug,
                'canonical_slug' => $canonicalSlug,
                'legacy' => true,
            ];
        }

        return $schema;
    }

    /** @return list<array<string,mixed>> */
    public static function all(): array
    {
        return array_map(
            static fn (string $slug): array => self::resolve($slug),
            PlatformApplicationRegistry::slugs(),
        );
    }

    /** @return list<array<string,mixed>> */
    public static function allTemplateSchemas(): array
    {
        $schemas = [];

        foreach (glob(self::directory().DIRECTORY_SEPARATOR.'*.json') ?: [] as $path) {
            if (basename($path) === 'index.json') {
                continue;
            }

            $decoded = json_decode((string) file_get_contents($path), true);
            if (! is_array($decoded)) {
                throw new RuntimeException('Invalid Titan template schema: '.basename($path));
            }

            $slug = (string) ($decoded['identity']['slug'] ?? pathinfo($path, PATHINFO_FILENAME));
            $schemas[$slug] = self::normalise($decoded, $slug);
        }

        foreach (TitanRegistry::all() as $slug => $template) {
            if (($template['category'] ?? null) === 'legacy-template') {
                continue;
            }
            $schemas[$slug] = self::normalise(self::fromTemplate($template), $slug);
        }

        ksort($schemas);
        return array_values($schemas);
    }

    /** @return array<string,mixed>|null */
    private static function read(string $slug): ?array
    {
        $path = self::directory().DIRECTORY_SEPARATOR.$slug.'.json';
        if (! is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Invalid Titan template schema: '.$slug);
        }
        return $decoded;
    }

    private static function directory(): string
    {
        return dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'titan-apps'
            .DIRECTORY_SEPARATOR.'TemplateSchemas';
    }

    /** @param array<string,mixed> $template
     *  @return array<string,mixed>
     */
    private static function fromTemplate(array $template): array
    {
        $slug = (string) $template['slug'];
        $chatbot = (array) ($template['chatbot'] ?? []);
        $workcore = (array) ($template['workcore'] ?? []);
        $offline = (array) ($template['offline'] ?? []);

        return [
            'schema_version' => self::VERSION,
            'identity' => [
                'name' => (string) $template['name'],
                'slug' => $slug,
                'icon' => (string) ($template['icon'] ?? 'apps'),
                'accent' => (string) ($template['color'] ?? 'var(--lqd-ext-chat-primary)'),
            ],
            'template' => [
                'category' => (string) ($template['category'] ?? 'template'),
                'type' => (string) ($template['type'] ?? 'template'),
                'platform_app' => $template['platform_app'] ?? null,
                'functional_templates' => array_values((array) ($template['functional_templates'] ?? [])),
                'workspaces' => array_values((array) ($template['workspaces'] ?? [])),
                'terminology' => (array) ($template['terminology'] ?? []),
                'role_presets' => (array) ($template['role_presets'] ?? []),
            ],
            'navigation' => (array) ($template['navigation'] ?? []),
            'home' => (array) ($template['home'] ?? ['widgets' => [], 'quick_actions' => []]),
            'chat' => [
                'persistent' => true,
                'role' => (string) ($chatbot['role'] ?? $template['name']),
                'suggested_prompts' => array_values((array) ($chatbot['suggested_prompts'] ?? [])),
                'context_policy' => [
                    'minimum_scope' => true,
                    'send_full_record' => false,
                    'requires_permission' => true,
                ],
            ],
            'workcore' => [
                'domains' => array_values((array) ($workcore['domains'] ?? [])),
                'commands' => array_values((array) ($workcore['commands'] ?? [])),
                'read_models' => array_values((array) ($workcore['read_models'] ?? [])),
            ],
            'offline' => [
                'records' => array_values((array) ($offline['records'] ?? [])),
                'packs' => array_values((array) ($offline['packs'] ?? [])),
                'commands' => array_values((array) ($offline['commands'] ?? [])),
                'retention' => ['completed_days' => 30],
                'conflict_rules' => (array) ($offline['conflict_rules'] ?? [
                    'server_authoritative' => true,
                    'preserve_local_copy' => true,
                ]),
            ],
            'permissions' => array_values((array) ($template['permissions'] ?? [])),
            'privacy' => [
                'default_mode' => 'device-first',
                'redact_before_cloud' => true,
                'attachment_confirmation' => true,
                'provider_allowlist' => [],
            ],
            'notifications' => ['assignment', 'approval', 'sync_failure', 'conflict'],
            'settings_sections' => [
                'ai-providers', 'privacy', 'device-security', 'offline-sync', 'workcore',
                'permissions', 'notifications', 'channels', 'appearance', 'accessibility', 'diagnostics',
            ],
            'preview_states' => [
                'mobile', 'tablet', 'desktop', 'drawer-open', 'settings-open',
                'offline', 'syncing', 'conflict', 'empty', 'populated',
            ],
        ];
    }

    /** @param array<string,mixed> $schema
     *  @return array<string,mixed>
     */
    private static function normalise(array $schema, string $slug): array
    {
        $identity = (array) ($schema['identity'] ?? []);
        $identity['slug'] = (string) ($identity['slug'] ?? $slug);
        $identity['name'] = (string) ($identity['name'] ?? 'Chatbot');
        $identity['icon'] = (string) ($identity['icon'] ?? 'message');
        $identity['accent'] = (string) ($identity['accent'] ?? 'var(--lqd-ext-chat-primary)');

        $navigation = (array) ($schema['navigation'] ?? []);
        $navigation['primary'] = array_values((array) ($navigation['primary'] ?? []));
        $navigation['drawer'] = array_values((array) ($navigation['drawer'] ?? []));
        $navigation['default_view'] = (string) (
            $navigation['default_view']
            ?? $navigation['primary'][0]['id']
            ?? 'home'
        );
        $navigation['header_actions'] = array_values(
            (array) ($navigation['header_actions'] ?? ['sync', 'notifications', 'settings']),
        );

        return [
            'schema_version' => (string) ($schema['schema_version'] ?? self::VERSION),
            ...$schema,
            'identity' => $identity,
            'template' => (array) ($schema['template'] ?? []),
            'navigation' => $navigation,
            'home' => (array) ($schema['home'] ?? ['widgets' => [], 'quick_actions' => []]),
            'chat' => (array) ($schema['chat'] ?? ['persistent' => true, 'role' => 'Chatbot']),
            'workcore' => (array) ($schema['workcore'] ?? ['domains' => [], 'commands' => [], 'read_models' => []]),
            'offline' => (array) ($schema['offline'] ?? ['records' => [], 'packs' => [], 'commands' => []]),
            'permissions' => array_values((array) ($schema['permissions'] ?? [])),
            'privacy' => (array) ($schema['privacy'] ?? ['default_mode' => 'device-first']),
            'notifications' => array_values((array) ($schema['notifications'] ?? [])),
            'settings_sections' => array_values((array) ($schema['settings_sections'] ?? [
                'privacy', 'device-security', 'offline-sync', 'appearance', 'diagnostics',
            ])),
            'preview_states' => array_values((array) ($schema['preview_states'] ?? [
                'mobile', 'desktop', 'offline',
            ])),
        ];
    }

    /** @return array<string,mixed> */
    private static function generic(string $slug): array
    {
        return [
            'schema_version' => self::VERSION,
            'identity' => [
                'name' => 'Chatbot',
                'slug' => $slug,
                'icon' => 'message',
                'accent' => 'var(--lqd-ext-chat-primary)',
            ],
            'template' => [],
            'navigation' => [
                'default_view' => 'home',
                'primary' => [['id' => 'home', 'label' => 'Home', 'icon' => 'home', 'offline' => true]],
                'drawer' => [],
                'header_actions' => ['settings'],
            ],
            'home' => ['widgets' => [], 'quick_actions' => []],
            'chat' => [
                'persistent' => true,
                'role' => 'Chatbot',
                'suggested_prompts' => [],
                'context_policy' => ['minimum_scope' => true],
            ],
            'workcore' => ['domains' => [], 'commands' => [], 'read_models' => []],
            'offline' => [
                'records' => [],
                'packs' => [],
                'commands' => [],
                'retention' => ['completed_days' => 0],
                'conflict_rules' => ['server_authoritative' => true],
            ],
            'permissions' => [],
            'privacy' => ['default_mode' => 'device-first'],
            'notifications' => [],
            'settings_sections' => ['privacy', 'device-security', 'offline-sync', 'appearance', 'diagnostics'],
            'preview_states' => ['mobile', 'desktop', 'offline'],
        ];
    }
}
