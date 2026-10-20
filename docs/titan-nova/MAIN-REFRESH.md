# Titan Nova Main Refresh

This branch is based on the current `main` branch and replays only the isolated Titan Nova extension, operator template, plan and integration boundary files from `feature/titan-nova-integrated-build`.

## Conflict policy

- Current `main` remains authoritative for shared WorkCore, Chatbot, security and multi-tenant architecture.
- The old broad `TemplateSchemas/index.json` edits are not replayed.
- The current native Titan suite template is corrected in place.
- The mobile workspace is inventoried from committed source; no Flutter app is fabricated when `pubspec.yaml` is absent.
- WorkCore remains authoritative for operational records.
