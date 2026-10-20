<?php

declare(strict_types=1);

namespace App\Extensions\Chatbot\System\Titan;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Registry for legacy Titan templates plus composable vertical, functional and workspace presets.
 *
 * Canonical platform applications remain owned by PlatformApplicationRegistry.
 * This registry supplies reusable template overlays for the shared Chatbot shell.
 */
class TitanRegistry
{
    public const VERSION = '2.2.0';

    protected static ?Collection $templates = null;
    protected static ?string $templatesPath = null;
    protected static ?string $cataloguesPath = null;

    public static function init(): void
    {
        $base = dirname(__DIR__, 2).'/resources/titan-apps';
        static::$templatesPath = $base.'/TitanSuiteTemplates';
        static::$cataloguesPath = $base.'/TemplateCatalogues';
        static::$templates = new Collection();

        static::loadLegacyTemplates();
        static::loadCatalogues();

        static::$templates = static::$templates->sortBy(
            static fn (array $template): string => sprintf(
                '%08d|%s',
                (int) ($template['order'] ?? 1000),
                mb_strtolower((string) ($template['name'] ?? $template['slug'])),
            ),
        );
    }

    protected static function loadLegacyTemplates(): void
    {
        $files = new Filesystem();

        if (! static::$templatesPath || ! $files->isDirectory(static::$templatesPath)) {
            return;
        }

        foreach ($files->directories(static::$templatesPath) as $dir) {
            $configFile = $dir.'/config.json';
            if (! $files->exists($configFile)) {
                continue;
            }

            $config = static::decodeJson($files->get($configFile), $configFile);
            static::register(static::normalise($config, 'legacy-template'));
        }
    }

    protected static function loadCatalogues(): void
    {
        $files = new Filesystem();

        if (! static::$cataloguesPath || ! $files->isDirectory(static::$cataloguesPath)) {
            return;
        }

        foreach ($files->glob(static::$cataloguesPath.'/*.json') as $catalogueFile) {
            $catalogue = static::decodeJson($files->get($catalogueFile), $catalogueFile);
            $category = (string) ($catalogue['category'] ?? 'template');
            $templates = $catalogue['templates'] ?? [];

            if (! is_array($templates)) {
                throw new RuntimeException("Titan template catalogue must contain a templates array: {$catalogueFile}");
            }

            foreach ($templates as $template) {
                if (! is_array($template)) {
                    throw new RuntimeException("Titan template catalogue entry must be an object: {$catalogueFile}");
                }
                static::register(static::normalise($template, $category));
            }
        }
    }

    /** @return array<string,mixed> */
    protected static function decodeJson(string $json, string $path): array
    {
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            throw new RuntimeException("Invalid Titan template JSON: {$path}");
        }
        return $decoded;
    }

    /** @param array<string,mixed> $template */
    protected static function register(array $template): void
    {
        $slug = (string) $template['slug'];
        if (static::$templates?->has($slug)) {
            throw new RuntimeException("Duplicate Titan template slug: {$slug}");
        }
        static::$templates?->put($slug, $template);
    }

    /** @param array<string,mixed> $template
     *  @return array<string,mixed>
     */
    protected static function normalise(array $template, string $defaultCategory): array
    {
        foreach (['slug', 'name', 'description'] as $required) {
            if (! isset($template[$required]) || trim((string) $template[$required]) === '') {
                throw new RuntimeException("Titan template is missing required field: {$required}");
            }
        }

        $template['category'] = (string) ($template['category'] ?? $defaultCategory);
        $template['type'] = (string) ($template['type'] ?? $template['category'].'-template');
        $template['icon'] = (string) ($template['icon'] ?? 'apps');
        $template['color'] = (string) ($template['color'] ?? '#00d4ff');
        $template['features'] = array_values((array) ($template['features'] ?? []));
        $template['roles'] = array_values((array) ($template['roles'] ?? []));
        $template['workspaces'] = array_values((array) ($template['workspaces'] ?? []));
        $template['functional_templates'] = array_values((array) ($template['functional_templates'] ?? []));
        $template['commerce_modes'] = array_values((array) ($template['commerce_modes'] ?? []));
        $template['terminology'] = (array) ($template['terminology'] ?? []);
        $template['role_presets'] = (array) ($template['role_presets'] ?? []);
        $template['workcore'] = (array) ($template['workcore'] ?? []);
        $template['offline'] = (array) ($template['offline'] ?? []);
        $template['chatbot'] = (array) ($template['chatbot'] ?? []);

        return $template;
    }

    public static function all(): Collection
    {
        if (! static::$templates) {
            static::init();
        }
        return static::$templates;
    }

    /** @return array<string,mixed>|null */
    public static function get(string $slug): ?array
    {
        return static::all()->get($slug);
    }

    /** @return list<string> */
    public static function list(): array
    {
        return static::all()->keys()->values()->all();
    }

    public static function has(string $slug): bool
    {
        return static::all()->has($slug);
    }

    public static function byType(string $type): Collection
    {
        return static::all()->filter(
            static fn (array $template): bool => ($template['type'] ?? null) === $type,
        );
    }

    public static function byCategory(string $category): Collection
    {
        return static::all()->filter(
            static fn (array $template): bool => ($template['category'] ?? null) === $category,
        );
    }

    /** @return list<array<string,mixed>> */
    public static function verticals(): array
    {
        return static::summaries(static::byCategory('vertical'));
    }

    /** @return list<array<string,mixed>> */
    public static function functional(): array
    {
        return static::summaries(static::byCategory('functional'));
    }

    /** @return list<array<string,mixed>> */
    public static function workspaces(): array
    {
        return static::summaries(static::byCategory('workspace'));
    }

    /** @return list<array<string,mixed>> */
    public static function legacy(): array
    {
        return static::summaries(static::byCategory('legacy-template'));
    }

    /**
     * Resolve a vertical into the template stack that the shared shell should expose.
     * A role preset can narrow the stack and choose the platform app without creating
     * a separate runtime, database, outbox or WorkCore authority.
     *
     * @return array<string,mixed>|null
     */
    public static function compose(string $verticalSlug, ?string $role = null): ?array
    {
        $vertical = static::get($verticalSlug);
        if (! $vertical || ($vertical['category'] ?? null) !== 'vertical') {
            return null;
        }

        $allTemplateSlugs = array_values((array) ($vertical['functional_templates'] ?? []));
        $rolePresets = (array) ($vertical['role_presets'] ?? []);
        $preset = $role !== null ? ($rolePresets[$role] ?? null) : null;

        $selectedSlugs = is_array($preset)
            ? array_values((array) ($preset['templates'] ?? $allTemplateSlugs))
            : $allTemplateSlugs;

        $defaultTemplate = is_array($preset)
            ? (string) ($preset['default_template'] ?? ($selectedSlugs[0] ?? ''))
            : (string) ($selectedSlugs[0] ?? '');

        if ($defaultTemplate !== '' && ! in_array($defaultTemplate, $selectedSlugs, true)) {
            array_unshift($selectedSlugs, $defaultTemplate);
        }

        $selectedSlugs = array_values(array_unique($selectedSlugs));
        $templates = [];

        foreach ($selectedSlugs as $templateSlug) {
            $definition = static::get((string) $templateSlug);
            if (! $definition || ! in_array($definition['category'] ?? null, ['functional', 'workspace'], true)) {
                throw new RuntimeException(
                    "Vertical {$verticalSlug} references unknown functional/workspace template: {$templateSlug}",
                );
            }
            $templates[] = $definition;
        }

        return [
            'vertical' => $verticalSlug,
            'name' => $vertical['name'],
            'role' => $role,
            'platform_app' => is_array($preset)
                ? (string) ($preset['platform_app'] ?? $vertical['platform_app'] ?? 'titan-zero')
                : (string) ($vertical['platform_app'] ?? 'titan-zero'),
            'default_template' => $defaultTemplate,
            'template_slugs' => $selectedSlugs,
            'templates' => $templates,
            'terminology' => (array) ($vertical['terminology'] ?? []),
            'role_presets' => $rolePresets,
            'workcore' => (array) ($vertical['workcore'] ?? []),
            'offline' => (array) ($vertical['offline'] ?? []),
        ];
    }

    /** @return array<string,mixed>|null */
    public static function getChatbot(string $slug): ?array
    {
        return static::get($slug)['chatbot'] ?? null;
    }

    /** @return array<string,mixed>|null */
    public static function getSchema(string $slug): ?array
    {
        return static::get($slug)['schema'] ?? null;
    }

    /** @return array<string,mixed> */
    public static function getNavigation(string $slug): array
    {
        $template = static::get($slug);
        return (array) ($template['navigation'] ?? $template['schema']['navigation'] ?? []);
    }

    /** @return array<string,string> */
    public static function getTerminology(string $slug): array
    {
        return (array) (static::get($slug)['terminology'] ?? []);
    }

    /** @return array<string,array<string,mixed>> */
    public static function getRolePresets(string $slug): array
    {
        return (array) (static::get($slug)['role_presets'] ?? []);
    }

    /** @return list<string> */
    public static function getFeatures(string $slug): array
    {
        return array_values((array) (static::get($slug)['features'] ?? []));
    }

    /** @return list<string> */
    public static function getRoutes(string $slug): array
    {
        return array_values((array) (static::get($slug)['api_routes'] ?? []));
    }

    /** @return array<int|string,mixed> */
    public static function getPermissions(string $slug): array
    {
        return (array) (static::get($slug)['permissions'] ?? []);
    }

    public static function create(string $slug, array $config = []): ?TitanApp
    {
        $template = static::get($slug);
        return $template ? new TitanApp($slug, $template, $config) : null;
    }

    /** @return list<array<string,mixed>> */
    public static function toArray(): array
    {
        return static::summaries(static::all());
    }

    /** @return list<array<string,mixed>> */
    protected static function summaries(Collection $templates): array
    {
        return $templates
            ->map(static function (array $config, string $slug): array {
                $workcore = (array) ($config['workcore'] ?? []);
                $offline = (array) ($config['offline'] ?? []);

                return [
                    'slug' => $slug,
                    'name' => $config['name'],
                    'type' => $config['type'],
                    'category' => $config['category'],
                    'description' => $config['description'],
                    'icon' => $config['icon'],
                    'color' => $config['color'],
                    'order' => (int) ($config['order'] ?? 1000),
                    'platform_app' => $config['platform_app'] ?? null,
                    'workspaces' => array_values((array) ($config['workspaces'] ?? [])),
                    'functional_templates' => array_values((array) ($config['functional_templates'] ?? [])),
                    'roles' => array_values((array) ($config['roles'] ?? [])),
                    'role_presets' => (array) ($config['role_presets'] ?? []),
                    'terminology' => (array) ($config['terminology'] ?? []),
                    'features' => array_values((array) ($config['features'] ?? [])),
                    'commerce_modes' => array_values((array) ($config['commerce_modes'] ?? [])),
                    'workcore_domains' => array_values((array) ($workcore['domains'] ?? [])),
                    'offline_enabled' => ! empty($offline),
                    'chatbot' => (array) ($config['chatbot'] ?? []),
                ];
            })
            ->values()
            ->all();
    }
}

class TitanApp
{
    public function __construct(
        protected string $slug,
        protected array $template,
        protected array $config = [],
    ) {
        $this->config = array_replace_recursive($template, $config);
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function name(): string
    {
        return (string) ($this->config['name'] ?? $this->slug);
    }

    /** @return array<string,mixed> */
    public function config(): array
    {
        return $this->config;
    }

    /** @return array<string,mixed> */
    public function chatbotConfig(): array
    {
        return (array) ($this->config['chatbot'] ?? []);
    }

    /** @return array<string,mixed> */
    public function uiConfig(): array
    {
        return [
            'name' => $this->name(),
            'icon' => $this->config['icon'] ?? 'apps',
            'color' => $this->config['color'] ?? '#00d4ff',
            'theme' => $this->config['theme'] ?? [],
            'features' => $this->config['features'] ?? [],
            'schema' => $this->config['schema'] ?? null,
        ];
    }

    public function installUrl(): string
    {
        return route('api.v2.titan.install', ['app' => $this->slug]);
    }

    /** @return array<string,mixed> */
    public function manifest(): array
    {
        $shortName = (string) ($this->config['short_name'] ?? $this->name());

        return [
            'name' => $this->name(),
            'short_name' => mb_substr($shortName, 0, 24),
            'description' => $this->config['description'] ?? '',
            'start_url' => $this->installUrl(),
            'scope' => '/titan/'.$this->slug,
            'display' => 'standalone',
            'orientation' => 'portrait-primary',
            'background_color' => '#ffffff',
            'theme_color' => $this->config['color'] ?? '#00d4ff',
            'icons' => [
                [
                    'src' => '/images/'.$this->slug.'-192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => '/images/'.$this->slug.'-512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
            ],
        ];
    }
}
