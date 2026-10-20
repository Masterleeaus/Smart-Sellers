from __future__ import annotations

import json
from pathlib import Path

CHATBOT_ROOT = Path(__file__).resolve().parents[2]
CATALOGUES = CHATBOT_ROOT / "resources" / "titan-apps" / "TemplateCatalogues"
REGISTRY = CHATBOT_ROOT / "System" / "Titan" / "TitanRegistry.php"
CONTROLLER = CHATBOT_ROOT / "System" / "Http" / "Controllers" / "Api" / "TitanController.php"

EXPECTED_FUNCTIONAL_TEMPLATES = {
    "template-booking",
    "template-ecommerce",
    "template-hire-rental",
    "template-accommodation",
}

REQUIRED_TERMINOLOGY = {
    "customer",
    "work_item",
    "worker",
    "location",
    "offering",
    "reservation",
}

EXPECTED_VERTICAL_FUNCTIONS = {
    "vertical-field-home-services": {"template-booking"},
    "vertical-accommodation-hospitality": {"template-accommodation", "template-booking"},
    "vertical-real-estate": {"template-booking"},
    "vertical-salons-personal-care": {"template-booking", "template-ecommerce"},
    "vertical-fitness-membership": {"template-booking", "template-ecommerce"},
    "vertical-automotive-services": {"template-booking", "template-ecommerce"},
    "vertical-ecommerce-retail": {"template-ecommerce"},
    "vertical-hire-rental": {"template-hire-rental", "template-ecommerce"},
    "vertical-booking-reservations": {"template-booking"},
}


def load_templates(category: str) -> dict[str, dict]:
    templates: dict[str, dict] = {}
    for path in sorted(CATALOGUES.glob("*.json")):
        payload = json.loads(path.read_text(encoding="utf-8"))
        if payload.get("category") != category:
            continue
        for template in payload.get("templates", []):
            slug = template["slug"]
            assert slug not in templates, f"duplicate template slug: {slug}"
            templates[slug] = template
    return templates


def test_functional_templates_are_registered() -> None:
    functional = load_templates("functional")
    assert set(functional) == EXPECTED_FUNCTIONAL_TEMPLATES

    for slug, template in functional.items():
        assert template["type"] == "functional-template", slug
        assert template["navigation"]["primary"], slug
        assert template["workcore"]["domains"], slug
        assert template["offline"]["conflict_rules"]["server_authoritative"] is True, slug


def test_every_vertical_composes_functional_and_workcore_templates() -> None:
    verticals = load_templates("vertical")

    assert set(verticals) == set(EXPECTED_VERTICAL_FUNCTIONS)

    for slug, template in verticals.items():
        functions = set(template["functional_templates"])
        assert EXPECTED_VERTICAL_FUNCTIONS[slug] <= functions, slug
        assert set(template["workspaces"]) <= functions, slug
        assert functions <= EXPECTED_FUNCTIONAL_TEMPLATES | {
            "workspace-crm",
            "workspace-jobs-projects",
            "workspace-finance",
            "workspace-crew-team",
        }, slug


def test_every_vertical_has_terminology_and_role_presets() -> None:
    for slug, template in load_templates("vertical").items():
        assert REQUIRED_TERMINOLOGY <= set(template["terminology"]), slug
        assert all(str(template["terminology"][key]).strip() for key in REQUIRED_TERMINOLOGY), slug

        role_presets = template["role_presets"]
        assert "owner" in role_presets, slug
        assert "customer" in role_presets, slug

        for role, preset in role_presets.items():
            assert preset["platform_app"] in {"titan-zero", "titan-go", "titan-desk", "titan-hub"}, (slug, role)
            assert preset["default_template"] in template["functional_templates"], (slug, role)
            assert set(preset["templates"]) <= set(template["functional_templates"]), (slug, role)

        assert role_presets["customer"]["platform_app"] == "titan-hub", slug


def test_registry_and_api_expose_composition_contract() -> None:
    registry = REGISTRY.read_text(encoding="utf-8")
    controller = CONTROLLER.read_text(encoding="utf-8")

    assert "function functional(" in registry
    assert "function compose(" in registry
    assert "functional_templates" in registry
    assert "terminology" in registry
    assert "role_presets" in registry

    assert "'functional_templates' => TitanRegistry::functional()" in controller
    assert "TitanRegistry::compose(" in controller
