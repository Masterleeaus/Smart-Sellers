from __future__ import annotations

from pathlib import Path

CHATBOT_ROOT = Path(__file__).resolve().parents[2]


def read(relative: str) -> str:
    return (CHATBOT_ROOT / relative).read_text(encoding="utf-8")


def test_shell_builder_loads_platform_vertical_and_workspace_templates() -> None:
    blade = read("resources/views/home/edit-window/edit-steps/titan-shell-builder.blade.php")

    assert "TemplateSchema::allTemplateSchemas()" in blade
    assert "Titan platform apps" in blade
    assert "Business verticals" in blade
    assert "WorkCore workspaces" in blade
    assert "vertical-" in blade
    assert "workspace-" in blade


def test_shell_builder_persists_workspace_composition() -> None:
    runtime = read("resources/js/titan-shell-builder.js")

    assert "workspace_templates" in runtime
    assert "toggleWorkspace" in runtime
    assert "recommended_workspaces" in runtime
    assert "config.workspace_templates" in runtime


def test_chatbot_requests_accept_vertical_and_workspace_configuration() -> None:
    store = read("System/Http/Requests/ChatbotStoreRequest.php")
    customize = read("System/Http/Requests/ChatbotCustomizeRequest.php")

    for source in (store, customize):
        assert "'titan_template'" in source
        assert "'shell_builder_config'" in source
        assert "'shell_builder_config.workspace_templates'" in source
        assert "'shell_builder_config.workspace_templates.*'" in source


def test_verticals_remain_templates_not_new_operational_authorities() -> None:
    registry = read("System/Titan/TitanRegistry.php")
    schema = read("System/TitanShell/TemplateSchema.php")

    assert "vertical-template" in registry or "byCategory('vertical')" in registry
    assert "fromTemplate" in schema
    assert "workcore" in schema.lower()
