@php
    $titanBuilderSchemas = \App\Extensions\Chatbot\System\TitanShell\TemplateSchema::allTemplateSchemas();
    $titanPlatformSlugs = \App\Extensions\Chatbot\System\TitanShell\PlatformApplicationRegistry::slugs();
    $titanVerticalTemplates = \App\Extensions\Chatbot\System\Titan\TitanRegistry::verticals();
    $titanWorkspaceTemplates = \App\Extensions\Chatbot\System\Titan\TitanRegistry::workspaces();
    $verticalWorkspaceMap = [];
    foreach ($titanVerticalTemplates as $verticalTemplate) {
        $verticalWorkspaceMap[$verticalTemplate['slug']] = $verticalTemplate['workspaces'] ?? [];
    }
    $titanRoles = ['customer', 'field-worker', 'cleaner', 'dispatcher', 'reception', 'manager', 'finance', 'quality', 'sales', 'administrator'];
    $allSettingsSections = ['ai-providers', 'privacy', 'device-security', 'offline-sync', 'workcore', 'permissions', 'notifications', 'channels', 'appearance', 'accessibility', 'diagnostics'];
@endphp

<section
    class="rounded-2xl border border-heading-foreground/10 bg-heading-background/40 p-4"
    x-data='titanShellBuilder(@json($titanBuilderSchemas), @json($verticalWorkspaceMap))'
>
    <div class="mb-5 flex items-start justify-between gap-3">
        <div>
            <h3 class="text-sm font-semibold">@lang('App shell and navigation')</h3>
            <p class="mt-1 text-2xs/5 opacity-60">@lang('Choose a Titan app or business vertical, compose its WorkCore workspaces, then customise navigation and offline behaviour in the same shared shell.')</p>
        </div>
        <span class="rounded-full bg-primary/10 px-2 py-1 text-[10px] font-semibold text-primary">{{ count($titanVerticalTemplates) }} verticals</span>
    </div>

    <div class="grid gap-4">
        <label class="grid gap-1 text-2xs font-medium">
            <span>@lang('Titan application or vertical')</span>
            <select class="rounded-lg border border-heading-foreground/10 bg-background px-3 py-2" x-model="selectedSlug" @change="selectTemplate(selectedSlug)">
                <optgroup label="{{ __('Titan platform apps') }}">
                    @foreach($titanBuilderSchemas as $schema)
                        @php($schemaSlug = (string) data_get($schema, 'identity.slug'))
                        @if(in_array($schemaSlug, $titanPlatformSlugs, true))
                            <option value="{{ $schemaSlug }}">{{ data_get($schema, 'identity.name', $schemaSlug) }}</option>
                        @endif
                    @endforeach
                </optgroup>
                <optgroup label="{{ __('Business verticals') }}">
                    @foreach($titanBuilderSchemas as $schema)
                        @php($schemaSlug = (string) data_get($schema, 'identity.slug'))
                        @if(str_starts_with($schemaSlug, 'vertical-'))
                            <option value="{{ $schemaSlug }}">{{ data_get($schema, 'identity.name', $schemaSlug) }}</option>
                        @endif
                    @endforeach
                </optgroup>
                <optgroup label="{{ __('WorkCore workspaces') }}">
                    @foreach($titanBuilderSchemas as $schema)
                        @php($schemaSlug = (string) data_get($schema, 'identity.slug'))
                        @if(str_starts_with($schemaSlug, 'workspace-'))
                            <option value="{{ $schemaSlug }}">{{ data_get($schema, 'identity.name', $schemaSlug) }}</option>
                        @endif
                    @endforeach
                </optgroup>
            </select>
        </label>

        <div
            class="rounded-xl border border-heading-foreground/10 bg-background/50 p-3"
            x-show="selectedSlug.startsWith('vertical-') || (config.workspace_templates || []).length"
            x-cloak
        >
            <div class="mb-3">
                <h4 class="text-xs font-semibold">@lang('WorkCore workspaces')</h4>
                <p class="mt-1 text-[10px] leading-4 opacity-60">@lang('Verticals recommend reusable CRM, Jobs & Projects, Crew & Team and Finance workspaces. Turn off any workspace the business does not need without creating another WorkCore authority.')</p>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <template x-for="workspace in workspaceSchemas()" :key="workspace.identity.slug">
                    <button
                        type="button"
                        class="rounded-lg border p-2 text-start transition"
                        :class="(config.workspace_templates || []).includes(workspace.identity.slug) ? 'border-primary bg-primary/10 text-primary' : 'border-heading-foreground/10 bg-background'"
                        @click="toggleWorkspace(workspace.identity.slug)"
                    >
                        <span class="block text-[10px] font-semibold" x-text="workspace.identity.name"></span>
                        <span
                            class="mt-1 block text-[9px] opacity-60"
                            x-show="recommendedWorkspaces().includes(workspace.identity.slug)"
                        >@lang('Recommended for this vertical')</span>
                    </button>
                </template>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <label class="grid gap-1 text-2xs font-medium">
                <span>@lang('Preview device')</span>
                <select class="rounded-lg border border-heading-foreground/10 bg-background px-3 py-2" x-model="config.device" @change="commit()">
                    <option value="mobile">@lang('Mobile')</option><option value="tablet">@lang('Tablet')</option><option value="desktop">@lang('Desktop')</option>
                </select>
            </label>
            <label class="grid gap-1 text-2xs font-medium">
                <span>@lang('Preview state')</span>
                <select class="rounded-lg border border-heading-foreground/10 bg-background px-3 py-2" x-model="config.state" @change="commit()">
                    <option value="online">@lang('Online')</option><option value="offline">@lang('Offline')</option><option value="syncing">@lang('Syncing')</option><option value="conflict">@lang('Conflict')</option><option value="empty">@lang('Empty')</option><option value="populated">@lang('Populated')</option>
                </select>
            </label>
            <label class="grid gap-1 text-2xs font-medium">
                <span>@lang('Role')</span>
                <select class="rounded-lg border border-heading-foreground/10 bg-background px-3 py-2" x-model="config.role" @change="commit()">
                    @foreach($titanRoles as $role)<option value="{{ $role }}">{{ __(ucwords(str_replace('-', ' ', $role))) }}</option>@endforeach
                </select>
            </label>
            <label class="grid gap-1 text-2xs font-medium">
                <span>@lang('Theme')</span>
                <select class="rounded-lg border border-heading-foreground/10 bg-background px-3 py-2" x-model="config.theme" @change="commit()">
                    <option value="system">@lang('System')</option><option value="light">@lang('Light')</option><option value="dark">@lang('Dark')</option>
                </select>
            </label>
        </div>

        <div>
            <div class="mb-2 flex items-center justify-between"><h4 class="text-xs font-semibold">@lang('Primary navigation')</h4><button class="text-2xs font-semibold text-primary" type="button" @click="addPrimary()">+ @lang('Add')</button></div>
            <div class="grid gap-2">
                <template x-for="(item, index) in config.primary" :key="item.id + '-' + index">
                    <div class="grid grid-cols-[1fr_auto] gap-2 rounded-xl border border-heading-foreground/10 p-2">
                        <div class="grid grid-cols-2 gap-2">
                            <input class="rounded-lg border border-heading-foreground/10 bg-background px-2 py-1.5 text-2xs" x-model="item.label" @input.debounce.250ms="item.id = item.id || item.label.toLowerCase().replace(/[^a-z0-9]+/g, '-'); commit(false)" aria-label="Navigation label">
                            <input class="rounded-lg border border-heading-foreground/10 bg-background px-2 py-1.5 text-2xs" x-model="item.icon" @input.debounce.250ms="commit(false)" aria-label="Navigation icon">
                            <label class="col-span-2 flex items-center gap-2 text-[10px]"><input type="checkbox" x-model="item.offline" @change="commit()"> @lang('Available offline')</label>
                        </div>
                        <div class="flex flex-col gap-1">
                            <button type="button" class="rounded border px-1.5" @click="movePrimary(index,-1)" :disabled="index === 0">↑</button>
                            <button type="button" class="rounded border px-1.5" @click="movePrimary(index,1)" :disabled="index === config.primary.length - 1">↓</button>
                            <button type="button" class="rounded border px-1.5 text-red-500" @click="removePrimary(index)">×</button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <label class="grid gap-1 text-2xs font-medium">
            <span>@lang('Default screen')</span>
            <select class="rounded-lg border border-heading-foreground/10 bg-background px-3 py-2" x-model="config.default_view" @change="commit()">
                <template x-for="item in config.primary" :key="item.id"><option :value="item.id" x-text="item.label"></option></template>
            </select>
        </label>

        <div>
            <div class="mb-2 flex items-center justify-between"><h4 class="text-xs font-semibold">@lang('Hamburger drawer')</h4><button class="text-2xs font-semibold text-primary" type="button" @click="addDrawer()">+ @lang('Add')</button></div>
            <div class="max-h-64 space-y-2 overflow-auto pe-1">
                <template x-for="(item, index) in config.drawer" :key="item.id + '-' + index">
                    <div class="flex items-center gap-2 rounded-lg border border-heading-foreground/10 p-2">
                        <input class="min-w-0 flex-1 rounded border border-heading-foreground/10 bg-background px-2 py-1.5 text-2xs" x-model="item.label" @input.debounce.250ms="commit(false)">
                        <label class="text-[10px]"><input type="checkbox" x-model="item.offline" @change="commit()"> Offline</label>
                        <button type="button" @click="moveDrawer(index,-1)" :disabled="index===0">↑</button><button type="button" @click="moveDrawer(index,1)" :disabled="index===config.drawer.length-1">↓</button><button type="button" class="text-red-500" @click="config.drawer.splice(index,1); commit()">×</button>
                    </div>
                </template>
            </div>
        </div>

        <div>
            <h4 class="mb-2 text-xs font-semibold">@lang('Gear settings sections')</h4>
            <div class="grid grid-cols-2 gap-2">
                @foreach($allSettingsSections as $section)
                    <label class="flex items-center gap-2 rounded-lg border border-heading-foreground/10 p-2 text-[10px]"><input type="checkbox" :checked="config.settings_sections.includes('{{ $section }}')" @change="toggleList('settings_sections','{{ $section }}')">{{ __(ucwords(str_replace('-', ' ', $section))) }}</label>
                @endforeach
            </div>
        </div>

        <div>
            <h4 class="mb-2 text-xs font-semibold">@lang('Home widgets')</h4>
            <div class="flex flex-wrap gap-2">
                <template x-for="widget in schema().home.widgets" :key="widget"><button type="button" class="rounded-full border px-2.5 py-1 text-[10px]" :class="config.home_widgets.includes(widget) ? 'border-primary bg-primary/10 text-primary' : 'border-heading-foreground/10'" @click="toggleList('home_widgets', widget)" x-text="widget.replaceAll('_',' ')"></button></template>
            </div>
        </div>

        <div class="rounded-xl border p-3" :class="errors.length ? 'border-red-400/40 bg-red-500/5' : 'border-emerald-400/40 bg-emerald-500/5'">
            <p class="text-2xs font-semibold" x-text="errors.length ? 'Validation issues' : 'Ready to publish'"></p>
            <template x-for="error in errors" :key="error"><p class="mt-1 text-[10px] text-red-500" x-text="error"></p></template>
            <p class="mt-1 text-[10px] opacity-60" x-show="notice" x-text="notice"></p>
        </div>
    </div>
</section>