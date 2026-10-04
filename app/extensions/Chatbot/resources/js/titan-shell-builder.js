(() => {
    'use strict';

    const DEVICES = ['mobile', 'tablet', 'desktop'];
    const STATES = ['online', 'offline', 'syncing', 'conflict', 'empty', 'populated'];
    const THEMES = ['light', 'dark', 'system'];
    const clone = value => JSON.parse(JSON.stringify(value || {}));
    const slugify = value => String(value || '').trim().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');

    function normaliseItem(item, index) {
        const label = String(item?.label || `Item ${index + 1}`).trim();
        return {
            id: String(item?.id || slugify(label) || `item-${index + 1}`),
            label,
            icon: String(item?.icon || 'circle'),
            offline: item?.offline !== false,
            roles: Array.isArray(item?.roles) ? item.roles : [],
            badge: item?.badge || null,
        };
    }

    function fromSchema(schema, stored = {}, recommendedWorkspaces = []) {
        const navigation = schema?.navigation || {};
        const config = clone(stored);
        const hasStoredWorkspaces = Object.prototype.hasOwnProperty.call(config, 'workspace_templates')
            && Array.isArray(config.workspace_templates);

        return {
            device: DEVICES.includes(config.device) ? config.device : 'mobile',
            state: STATES.includes(config.state) ? config.state : 'populated',
            theme: THEMES.includes(config.theme) ? config.theme : 'system',
            role: config.role || 'manager',
            default_view: config.default_view || navigation.default_view || navigation.primary?.[0]?.id || 'home',
            primary: (config.primary || navigation.primary || []).map(normaliseItem),
            drawer: (config.drawer || navigation.drawer || []).map(normaliseItem),
            settings_sections: config.settings_sections || schema?.settings_sections || [],
            home_widgets: config.home_widgets || schema?.home?.widgets || [],
            workspace_templates: hasStoredWorkspaces ? config.workspace_templates : [...recommendedWorkspaces],
            raised_action: config.raised_action || null,
            preview_surface: config.preview_surface || 'main',
        };
    }

    function validate(config) {
        const errors = [];
        if (!config.primary.length) errors.push('At least one primary navigation link is required.');
        if (config.primary.length > 6) errors.push('Primary navigation supports a maximum of six links.');
        const ids = config.primary.map(item => item.id);
        if (new Set(ids).size !== ids.length) errors.push('Primary navigation IDs must be unique.');
        if (!ids.includes(config.default_view)) errors.push('Default view must match a primary navigation item.');
        const drawerIds = config.drawer.map(item => item.id);
        if (new Set(drawerIds).size !== drawerIds.length) errors.push('Drawer link IDs must be unique.');
        const workspaces = config.workspace_templates || [];
        if (new Set(workspaces).size !== workspaces.length) errors.push('WorkCore workspace templates must be unique.');
        return errors;
    }

    function clearFlattenedShellConfig(chatbot) {
        Object.keys(chatbot || {}).forEach(key => {
            if (key.startsWith('shell_builder_config[')) delete chatbot[key];
        });
    }

    function writeNestedFormValue(chatbot, prefix, value) {
        if (value === null || value === undefined) return;
        if (Array.isArray(value)) {
            chatbot[prefix] = value;
            return;
        }
        if (typeof value === 'object') {
            Object.entries(value).forEach(([key, nested]) => {
                writeNestedFormValue(chatbot, `${prefix}[${key}]`, nested);
            });
            return;
        }
        chatbot[prefix] = value;
    }

    function stageShellConfig(chatbot, config) {
        if (!chatbot) return;
        const serialised = window.TitanShellBuilder.serialise(config);

        // Cache the structured form for component remounts without letting the
        // generic Object.keys/FormData serializer send it as "[object Object]".
        Object.defineProperty(chatbot, '_titan_shell_builder_config', {
            value: serialised,
            writable: true,
            configurable: true,
            enumerable: false,
        });
        delete chatbot.shell_builder_config;
        clearFlattenedShellConfig(chatbot);

        Object.entries(serialised).forEach(([key, value]) => {
            writeNestedFormValue(chatbot, `shell_builder_config[${key}]`, value);
        });
    }

    window.TitanShellBuilder = {
        devices: DEVICES,
        states: STATES,
        themes: THEMES,
        fromSchema,
        validate,
        stageShellConfig,
        serialise(config) {
            const safe = clone(config);
            safe.primary = (safe.primary || []).map(normaliseItem);
            safe.drawer = (safe.drawer || []).map(normaliseItem);
            safe.workspace_templates = Array.from(new Set(safe.workspace_templates || []));
            return safe;
        },
        move(list, from, to) {
            if (!Array.isArray(list) || from === to || from < 0 || to < 0 || from >= list.length || to >= list.length) return list;
            const next = list.slice();
            const [item] = next.splice(from, 1);
            next.splice(to, 0, item);
            return next;
        },
    };

    document.addEventListener('alpine:init', () => {
        Alpine.data('titanShellBuilder', (schemas = [], workspaceRecommendations = {}) => ({
            schemas,
            recommended_workspaces: workspaceRecommendations || {},
            selectedSlug: 'titan-zero',
            config: fromSchema(schemas[0] || {}, {}),
            errors: [],
            notice: '',
            init() {
                this.$watch('activeChatbot', chatbot => this.load(chatbot), { deep: false });
                this.load(this.activeChatbot || {});
            },
            schema() {
                return this.schemas.find(item => item?.identity?.slug === this.selectedSlug) || this.schemas[0] || {};
            },
            workspaceSchemas() {
                return this.schemas.filter(item => String(item?.identity?.slug || '').startsWith('workspace-'));
            },
            recommendedWorkspaces(slug = this.selectedSlug) {
                const recommended = this.recommended_workspaces?.[slug];
                return Array.isArray(recommended) ? recommended : [];
            },
            load(chatbot) {
                this.selectedSlug = chatbot?.titan_template || chatbot?.template_slug || this.selectedSlug || 'titan-zero';
                const stored = chatbot?.shell_builder_config || chatbot?._titan_shell_builder_config || {};
                this.config = fromSchema(this.schema(), stored, this.recommendedWorkspaces());
                this.commit(false);
            },
            selectTemplate(slug) {
                this.selectedSlug = slug;
                this.config = fromSchema(this.schema(), {}, this.recommendedWorkspaces(slug));
                this.commit();
            },
            toggleWorkspace(slug) {
                const allowed = this.workspaceSchemas().some(item => item?.identity?.slug === slug);
                if (!allowed) return;
                const list = this.config.workspace_templates || [];
                this.config.workspace_templates = list.includes(slug)
                    ? list.filter(item => item !== slug)
                    : [...list, slug];
                this.commit();
            },
            commit(showNotice = true) {
                this.errors = validate(this.config);
                if (this.activeChatbot) {
                    this.activeChatbot.titan_template = this.selectedSlug;
                    window.TitanShellBuilder.stageShellConfig(this.activeChatbot, this.config);
                }
                window.dispatchEvent(new CustomEvent('titan-builder-preview', { detail: {
                    schema: this.schema(), config: clone(this.config), errors: this.errors,
                }}));
                if (showNotice) this.notice = this.errors.length ? 'Resolve validation issues before publishing.' : 'Preview updated.';
            },
            addPrimary() {
                if (this.config.primary.length >= 6) return;
                const index = this.config.primary.length + 1;
                this.config.primary.push(normaliseItem({ label: `Link ${index}` }, index - 1));
                this.commit();
            },
            removePrimary(index) {
                this.config.primary.splice(index, 1);
                if (!this.config.primary.some(item => item.id === this.config.default_view)) this.config.default_view = this.config.primary[0]?.id || '';
                this.commit();
            },
            addDrawer() {
                const index = this.config.drawer.length + 1;
                this.config.drawer.push(normaliseItem({ label: `Drawer link ${index}` }, index - 1));
                this.commit();
            },
            movePrimary(index, direction) {
                this.config.primary = window.TitanShellBuilder.move(this.config.primary, index, index + direction);
                this.commit();
            },
            moveDrawer(index, direction) {
                this.config.drawer = window.TitanShellBuilder.move(this.config.drawer, index, index + direction);
                this.commit();
            },
            toggleList(key, value) {
                const list = this.config[key] || [];
                this.config[key] = list.includes(value) ? list.filter(item => item !== value) : [...list, value];
                this.commit();
            },
        }));

        Alpine.data('titanBuilderPreview', () => ({
            schema: null,
            config: { device: 'mobile', state: 'populated', theme: 'system', preview_surface: 'main', primary: [], drawer: [], home_widgets: [], workspace_templates: [] },
            init() {
                window.addEventListener('titan-builder-preview', event => {
                    this.schema = event.detail.schema;
                    this.config = event.detail.config;
                });
            },
            widthClass() {
                return { mobile: 'max-w-[390px]', tablet: 'max-w-[760px]', desktop: 'max-w-[1100px]' }[this.config.device] || 'max-w-[390px]';
            },
        }));
    });
})();