# Multi-Tenant Architecture

## Overview

The application supports multi-tenancy with data isolation per tenant. Each tenant represents a separate educational institution.

## Database Structure

### Tables

- **tenants**: Main tenant table
  - `id`: Primary key
  - `name`: Tenant name
  - `slug`: Unique slug (used in subdomain)
  - `timezone`: Timezone (default: Europe/Amsterdam)

- **tenant_members**: User-tenant relationships
  - `id`: Primary key
  - `tenant_id`: Foreign key to tenants
  - `user_id`: Foreign key to users
  - `role_in_tenant`: owner|admin|staff
  - Unique constraint on (tenant_id, user_id)

### Tenant ID in Domain Tables

All domain tables have `tenant_id` column:
- groups, subgroups, subjects, rooms, time_slots
- schedule_versions, schedule_items, schedule_replacements, duty_shifts
- lessons, grades, grade_changes, attendance, grade_period_summaries
- materials, material_targets, material_reads
- assignments, assignment_targets, submissions, submission_files
- documents, doc_templates, document_routes, document_ack, document_registry
- notifications, notification_settings
- chat_threads, chat_members, chat_messages, chat_reports, chat_thread_settings
- tickets, ticket_messages
- rules, risks
- webhook_endpoints, webhook_deliveries
- settings, files

## Tenant Resolution

The `IdentifyTenant` middleware resolves the tenant in the following order:

1. **X-Tenant header**: Can be tenant ID (numeric) or slug (string)
2. **Query parameter** (`?tenant=slug`): Only in dev/local environments
3. **Subdomain**: `tenantSlug.example.com` (skips www, api, admin)
4. **Default**: In dev, falls back to 'demo' tenant

## Models

All domain models use the `HasTenant` trait which:
- Automatically sets `tenant_id` on creation
- Applies global scope to filter by current tenant
- Provides `tenant()` relationship

### Using Tenant Scope

```php
// Automatically scoped to current tenant
$groups = Group::all();

// Bypass scope (admin only)
$allGroups = Group::withoutTenant()->get();

// Query specific tenant
$groups = Group::withTenant($tenantId)->get();
```

## API Endpoints

### Tenant Context

- `GET /api/tenant/current`: Get current tenant context
- `POST /api/tenant/switch`: Switch tenant (admin only)
  - Body: `{tenant_id: 1}` or `{tenant_slug: "demo"}`
- `GET /api/tenant/list`: List all tenants (admin only)

## Seeding

The `TenantSeeder` creates a demo tenant:
- Slug: `demo`
- Name: `Demo Tenant`
- Timezone: `Europe/Amsterdam`
- Creates admin user: `admin@demo.local` (password: `password`)
- Adds admin as owner of demo tenant

All demo data seeders (`DirectoryDemoSeeder`, `DemoDataSeeder`) bind data to the demo tenant.

## Development

In development, you can:
1. Use subdomain: `demo.localhost`
2. Use query parameter: `?tenant=demo`
3. Use header: `X-Tenant: demo`

## Production

In production:
- Use subdomain routing: `tenant-slug.example.com`
- Or use `X-Tenant` header for API calls


