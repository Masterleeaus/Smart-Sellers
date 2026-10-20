<?php

declare(strict_types=1);

namespace App\Extensions\Chatbot\System\Http\Controllers\Api;

use App\Extensions\Chatbot\System\Titan\TitanRegistry;
use App\Extensions\Chatbot\System\TitanAI\WorkCoreApps\WorkCoreAppBridge;
use App\Extensions\Chatbot\System\TitanShell\PlatformApplicationRegistry;
use App\Extensions\Chatbot\System\TitanShell\TemplateSchema;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TitanController extends Controller
{
    public function index(WorkCoreAppBridge $workcore): JsonResponse
    {
        $applications = $this->applications($workcore);

        return response()->json([
            'catalogue_version' => TitanRegistry::VERSION,
            'applications' => $applications,
            'templates' => $applications,
            'vertical_templates' => TitanRegistry::verticals(),
            'functional_templates' => TitanRegistry::functional(),
            'workspace_templates' => TitanRegistry::workspaces(),
            'legacy_templates' => TitanRegistry::legacy(),
            'legacy_slug_map' => PlatformApplicationRegistry::legacyMap(),
            'workcore_apps' => $this->workCoreApplications($applications),
            'legacy_workcore_apps' => $workcore->apps(),
            'workcore_runtime_available' => $workcore->runtimeAvailable(),
        ]);
    }

    public function show(string $app, WorkCoreAppBridge $workcore): JsonResponse
    {
        $canonical = PlatformApplicationRegistry::canonicalSlug($app);

        if ($canonical !== null) {
            return response()->json($this->application($canonical, $workcore, $app));
        }

        $template = TitanRegistry::get($app);
        if (! $template) {
            return response()->json(['message' => 'Titan application or template not found.'], 404);
        }

        $payload = [
            'kind' => $template['category'].'-template',
            ...$template,
            'schema' => TemplateSchema::resolve($app),
            'workcore_runtime_available' => $workcore->runtimeAvailable(),
        ];

        if (($template['category'] ?? null) === 'vertical') {
            $payload['composition'] = TitanRegistry::compose($app);
        }

        return response()->json($payload);
    }

    public function install(Request $request, string $app): JsonResponse
    {
        $canonical = PlatformApplicationRegistry::canonicalSlug($app);

        if ($canonical !== null) {
            $application = PlatformApplicationRegistry::get($canonical);

            return response()->json([
                'installed' => true,
                'kind' => 'platform-application',
                'application' => $canonical,
                'requested_slug' => $app,
                'name' => $request->string('name')->toString() ?: $application['name'],
                'schema' => TemplateSchema::resolve($canonical),
                'config' => $request->input('config', []),
            ]);
        }

        $template = TitanRegistry::get($app);
        if (! $template) {
            return response()->json(['message' => 'Titan application or template not found.'], 404);
        }

        $role = trim($request->string('role')->toString());
        $composition = ($template['category'] ?? null) === 'vertical'
            ? TitanRegistry::compose($app, $role !== '' ? $role : null)
            : null;

        return response()->json([
            'installed' => true,
            'kind' => $template['category'].'-template',
            'template' => $app,
            'name' => $request->string('name')->toString() ?: $template['name'],
            'platform_app' => $composition['platform_app'] ?? $template['platform_app'] ?? 'titan-zero',
            'workspaces' => $template['workspaces'] ?? [],
            'functional_templates' => $template['functional_templates'] ?? [],
            'roles' => $template['roles'] ?? [],
            'role_presets' => $template['role_presets'] ?? [],
            'terminology' => $template['terminology'] ?? [],
            'chatbot' => $template['chatbot'] ?? [],
            'composition' => $composition,
            'schema' => TemplateSchema::resolve($app),
            'config' => $request->input('config', []),
        ]);
    }

    public function manifest(string $app): JsonResponse
    {
        $canonical = PlatformApplicationRegistry::canonicalSlug($app);

        if ($canonical !== null) {
            $application = PlatformApplicationRegistry::get($canonical);

            return response()->json([
                'name' => $application['name'],
                'short_name' => str_replace('Titan ', '', $application['name']),
                'description' => $application['purpose'],
                'start_url' => route('api.v2.titan.install', ['app' => $canonical]),
                'scope' => '/titan/'.$canonical,
                'display' => 'standalone',
                'orientation' => 'portrait-primary',
                'background_color' => '#ffffff',
                'theme_color' => '#00d4ff',
                'icons' => [
                    ['src' => '/images/'.$canonical.'-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                    ['src' => '/images/'.$canonical.'-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ],
            ]);
        }

        $template = TitanRegistry::create($app);

        return $template
            ? response()->json($template->manifest())
            : response()->json(['message' => 'Titan application or template not found.'], 404);
    }

    /** @return list<array<string,mixed>> */
    private function applications(WorkCoreAppBridge $workcore): array
    {
        return array_map(
            fn (string $slug): array => $this->application($slug, $workcore),
            PlatformApplicationRegistry::slugs(),
        );
    }

    /** @return array<string,mixed> */
    private function application(string $slug, WorkCoreAppBridge $workcore, ?string $requestedSlug = null): array
    {
        $definition = PlatformApplicationRegistry::get($slug);

        return [
            'kind' => 'platform-application',
            ...$definition,
            'requested_slug' => $requestedSlug ?? $slug,
            'schema' => TemplateSchema::resolve($slug),
            'workcore' => $workcore->app($slug),
        ];
    }

    /** @param list<array<string,mixed>> $applications
     *  @return array<string,mixed>
     */
    private function workCoreApplications(array $applications): array
    {
        $mapped = [];
        foreach ($applications as $application) {
            $mapped[$application['slug']] = $application['workcore'];
        }
        return $mapped;
    }
}
