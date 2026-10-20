# Titan Nova Agent Demo Data Seeder

This seeder creates fake agents and demo tasks for testing the Titan Nova Agent extension.

## Usage

Run the seeder using the Artisan command:

```bash
php artisan titan-nova:seed-demo-data
```

## Cleanup

To remove demo data, manually delete agents from the UI or database:

- Delete agents from `ext_titan_nova_agents` table
- Reset tasks from `blogs` table (only `is_titan-nova` column is `1`)
