#!/usr/bin/env python3
from __future__ import annotations

import hashlib
import json
import shutil
import sys
import tempfile
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
DONOR = ROOT / 'app/extensions/BlogPilot'
TARGET = ROOT / 'app/extensions/TitanNova'
OVERLAY_URL = 'https://chilly-rain.miniup.app/Titan-Nova-Source-Overlay-v0.4.0.zip'
OVERLAY_SHA256 = 'cf425c0a20e76cb1d9682b11e6d10ceddba30389d65d2cdfb7978321bac4974d'


def fail(message: str) -> None:
    raise RuntimeError(message)


def download_overlay(destination: Path) -> None:
    with urllib.request.urlopen(OVERLAY_URL, timeout=60) as response:
        payload = response.read()
    digest = hashlib.sha256(payload).hexdigest()
    if digest != OVERLAY_SHA256:
        fail(f'Overlay checksum mismatch: expected {OVERLAY_SHA256}, got {digest}')
    destination.write_bytes(payload)


def materialise_extension() -> None:
    if not DONOR.is_dir():
        fail(f'BlogPilot donor is missing: {DONOR}')

    with tempfile.TemporaryDirectory(prefix='titan-nova-build-') as temporary:
        temporary_root = Path(temporary)
        archive = temporary_root / 'overlay.zip'
        download_overlay(archive)
        shutil.unpack_archive(str(archive), temporary_root / 'overlay')
        overlay = temporary_root / 'overlay/TitanNova'
        if not overlay.is_dir():
            fail('Overlay does not contain TitanNova/')

        donor_assets = DONOR / 'resources/assets'
        if donor_assets.is_dir():
            destination_assets = overlay / 'resources/assets'
            if destination_assets.exists():
                shutil.rmtree(destination_assets)
            destination_assets.parent.mkdir(parents=True, exist_ok=True)
            shutil.copytree(donor_assets, destination_assets)

        if TARGET.exists():
            shutil.rmtree(TARGET)
        shutil.copytree(overlay, TARGET)


def patch_marketplace_provider() -> None:
    provider = ROOT / 'app/Domains/Marketplace/MarketplaceServiceProvider.php'
    text = provider.read_text(encoding='utf-8')
    import_line = 'use App\\Extensions\\TitanNova\\System\\TitanNovaServiceProvider;'
    if import_line not in text:
        anchor = 'use App\\Extensions\\BlogPilot\\System\\BlogPilotServiceProvider;'
        text = text.replace(anchor, anchor + '\n' + import_line)
    registration = "        'titan-nova'                    => TitanNovaServiceProvider::class,"
    if registration not in text:
        anchor = "        'blogpilot'                     => BlogPilotServiceProvider::class,"
        text = text.replace(anchor, anchor + '\n' + registration)
    provider.write_text(text, encoding='utf-8')


def titan_nova_operator_template(donor: dict) -> dict:
    template = json.loads(json.dumps(donor))
    template['identity'] = {
        'name': 'Titan Nova Operator',
        'slug': 'titan-nova-operator',
        'icon': 'nova',
        'accent': 'var(--lqd-ext-chat-primary)',
    }
    template['navigation']['default_view'] = 'inbox'
    template['navigation']['primary'] = [
        {'id': 'inbox', 'label': 'Inbox', 'icon': 'messages', 'offline': True},
        {'id': 'bookings', 'label': 'Bookings', 'icon': 'bookings', 'offline': True},
        {'id': 'missions', 'label': 'Missions', 'icon': 'operations', 'offline': True},
        {'id': 'approvals', 'label': 'Approvals', 'icon': 'approvals', 'offline': True},
    ]
    template['navigation']['drawer'] = [
        {'id': 'leads', 'label': 'Leads', 'icon': 'chevron-right', 'offline': True},
        {'id': 'conversations', 'label': 'Conversations', 'icon': 'chevron-right', 'offline': True},
        {'id': 'tasks', 'label': 'Tasks', 'icon': 'chevron-right', 'offline': True},
        {'id': 'ventures', 'label': 'Ventures', 'icon': 'chevron-right', 'offline': True},
        {'id': 'pilots', 'label': 'Pilots', 'icon': 'chevron-right', 'offline': True},
        {'id': 'training', 'label': 'Training', 'icon': 'chevron-right', 'offline': True},
        {'id': 'payments', 'label': 'Payments', 'icon': 'chevron-right', 'offline': False},
        {'id': 'analytics', 'label': 'Analytics', 'icon': 'chevron-right', 'offline': False},
    ]
    template['home'] = {
        'widgets': ['reply_queue', 'today_bookings', 'mission_attention', 'pending_approvals'],
        'quick_actions': ['reply', 'create_task', 'open_booking'],
    }
    template['chat'] = {
        'persistent': True,
        'role': 'Titan Nova Operator',
        'suggested_prompts': [
            'Show replies needing attention',
            'What bookings changed today?',
            'Summarise active launch missions',
        ],
        'context_policy': {
            'minimum_scope': True,
            'send_full_record': False,
            'requires_permission': True,
        },
    }
    template['workcore'] = {
        'domains': ['CRM', 'Operations', 'Scheduling', 'Finance', 'Workforce', 'Compliance', 'Analytics'],
        'commands': [
            'conversation.reply',
            'booking.read',
            'booking.reschedule',
            'task.complete',
            'approval.decide',
            'lead.promote',
        ],
        'read_models': [
            'reply_queue',
            'bookings',
            'missions',
            'tasks',
            'approvals',
            'venture_health',
        ],
    }
    template['offline'] = {
        'records': ['reply_queue', 'bookings', 'missions', 'tasks', 'approvals'],
        'packs': ['titan-nova-operator-default'],
        'retention': {'completed_days': 30},
        'conflict_rules': {'server_authoritative': True, 'preserve_local_copy': True},
    }
    template['permissions'] = [
        'conversation.reply',
        'booking.read',
        'booking.reschedule',
        'task.complete',
        'approval.decide',
        'lead.promote',
    ]
    template['notifications'] = [
        'new_reply',
        'booking_change',
        'task_assignment',
        'approval',
        'sync_failure',
        'conflict',
    ]
    return template


def patch_chatbot_template_registry() -> None:
    schemas = ROOT / 'app/extensions/Chatbot/resources/titan-apps/TemplateSchemas'
    donor_path = schemas / 'titan-zero.json'
    legacy_index = schemas / 'legacy-index-v1.json'
    index_path = schemas / 'index.json'
    if not donor_path.is_file() or not legacy_index.is_file():
        fail('Chatbot Titan template donors are missing')

    donor = json.loads(donor_path.read_text(encoding='utf-8-sig'))
    template = titan_nova_operator_template(donor)
    output_path = schemas / 'titan-nova-operator.json'
    output_path.write_text(json.dumps(template, indent=2) + '\n', encoding='utf-8')

    registry = json.loads(legacy_index.read_text(encoding='utf-8-sig'))
    templates = registry.setdefault('templates', [])
    templates = [item for item in templates if item.get('identity', {}).get('slug') != 'titan-nova-operator']
    templates.append(template)
    registry['templates'] = templates
    rendered = json.dumps(registry, indent=2) + '\n'
    legacy_index.write_text(rendered, encoding='utf-8')
    index_path.write_text(rendered, encoding='utf-8')


def inventory_flutter_apps() -> None:
    pubspecs = sorted(ROOT.rglob('pubspec.yaml'))
    apps = []
    for pubspec in pubspecs:
        relative = pubspec.relative_to(ROOT).as_posix()
        if any(part in {'.git', 'vendor', 'node_modules'} for part in pubspec.parts):
            continue
        apps.append({'pubspec': relative, 'root': pubspec.parent.relative_to(ROOT).as_posix()})
    report_dir = ROOT / 'docs/titan-nova'
    report_dir.mkdir(parents=True, exist_ok=True)
    (report_dir / 'flutter-app-inventory.json').write_text(
        json.dumps({'apps': apps, 'count': len(apps)}, indent=2) + '\n',
        encoding='utf-8',
    )


def write_architecture_note() -> None:
    note = ROOT / 'docs/titan-nova/INTEGRATION-BOUNDARIES.md'
    note.parent.mkdir(parents=True, exist_ok=True)
    note.write_text(
        '# Titan Nova integration boundaries\n\n'
        '- Laravel admin and venture control live in `app/extensions/TitanNova`.\n'
        '- The Chatbot Titan app shell supplies the operator PWA through `titan-nova-operator.json`.\n'
        '- WorkCore remains authoritative for customers, leads, jobs, bookings, schedules, workforce, invoices and payments.\n'
        '- Titan Nova owns opportunities, ventures, genomes, missions, experiments, simulations, decision gates and portfolio learning.\n'
        '- Flutter customer-hub changes are deferred until the detected app inventory is reviewed; no guessed mobile path is created.\n'
        '- All operational writes must use WorkCore governed actions and carry tenant, actor, causation, correlation and idempotency context.\n',
        encoding='utf-8',
    )


def verify() -> None:
    required = [
        TARGET / 'extension.json',
        TARGET / 'System/TitanNovaServiceProvider.php',
        TARGET / 'System/Models/TitanNovaAgent.php',
        TARGET / 'System/Models/TitanNovaTask.php',
        ROOT / 'app/extensions/Chatbot/resources/titan-apps/TemplateSchemas/titan-nova-operator.json',
    ]
    missing = [str(path.relative_to(ROOT)) for path in required if not path.is_file()]
    if missing:
        fail('Missing generated files: ' + ', '.join(missing))

    forbidden = []
    for path in TARGET.rglob('*'):
        if not path.is_file() or path.suffix.lower() in {'.png', '.jpg', '.jpeg', '.gif', '.mp4', '.webp'}:
            continue
        text = path.read_text(encoding='utf-8', errors='ignore')
        if 'App\\Extensions\\BlogPilot' in text or 'Wordpress' in text or 'WordPress' in text:
            forbidden.append(path.relative_to(ROOT).as_posix())
    if forbidden:
        fail('Legacy identifiers remain in TitanNova: ' + ', '.join(forbidden[:20]))

    json.loads((TARGET / 'extension.json').read_text(encoding='utf-8-sig'))
    json.loads((ROOT / 'app/extensions/Chatbot/resources/titan-apps/TemplateSchemas/index.json').read_text(encoding='utf-8-sig'))


def main() -> int:
    materialise_extension()
    patch_marketplace_provider()
    patch_chatbot_template_registry()
    inventory_flutter_apps()
    write_architecture_note()
    verify()
    print('Titan Nova extension and operator PWA template materialised successfully.')
    return 0


if __name__ == '__main__':
    try:
        raise SystemExit(main())
    except Exception as error:
        print(f'ERROR: {error}', file=sys.stderr)
        raise
