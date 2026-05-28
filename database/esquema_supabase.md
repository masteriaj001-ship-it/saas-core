## Table `assets`

### Columns

| Name | Type | Constraints |
|------|------|-------------|
| `id` | `uuid` | Primary |
| `tenant_id` | `uuid` |  |
| `contact_id` | `uuid` |  |
| `name` | `text` |  |
| `identifier` | `text` |  |
| `category` | `text` |  Nullable |
| `metadata` | `jsonb` |  Nullable |
| `created_at` | `timestamptz` |  Nullable |
| `updated_at` | `timestamptz` |  Nullable |

## Table `attribute_definitions`

### Columns

| Name | Type | Constraints |
|------|------|-------------|
| `id` | `uuid` | Primary |
| `tenant_id` | `uuid` |  |
| `entity_type` | `text` |  |
| `label` | `text` |  |
| `field_key` | `text` |  |
| `field_type` | `custom_field_type` |  |
| `is_required` | `bool` |  Nullable |
| `options` | `jsonb` |  Nullable |
| `sort_order` | `int4` |  Nullable |
| `created_at` | `timestamptz` |  Nullable |
| `updated_at` | `timestamptz` |  Nullable |

## Table `audit_logs`

### Columns

| Name | Type | Constraints |
|------|------|-------------|
| `id` | `uuid` | Primary |
| `tenant_id` | `uuid` |  Nullable |
| `user_id` | `uuid` |  Nullable |
| `action` | `text` |  |
| `entity_type` | `text` |  |
| `entity_id` | `text` |  Nullable |
| `old_data` | `jsonb` |  Nullable |
| `new_data` | `jsonb` |  Nullable |
| `ip_address` | `text` |  Nullable |
| `user_agent` | `text` |  Nullable |
| `created_at` | `timestamptz` |  Nullable |

## Table `categories`

### Columns

| Name | Type | Constraints |
|------|------|-------------|
| `id` | `uuid` | Primary |
| `tenant_id` | `uuid` |  |
| `parent_id` | `uuid` |  Nullable |
| `name` | `text` |  |
| `color` | `text` |  Nullable |
| `sort_order` | `int4` |  Nullable |
| `is_active` | `bool` |  Nullable |
| `created_at` | `timestamptz` |  Nullable |
| `updated_at` | `timestamptz` |  Nullable |

## Table `contacts`

### Columns

| Name | Type | Constraints |
|------|------|-------------|
| `id` | `uuid` | Primary |
| `tenant_id` | `uuid` |  |
| `role` | `contact_role` |  Nullable |
| `is_active` | `bool` |  Nullable |
| `identification_type` | `text` |  |
| `identification_number` | `text` |  |
| `dv` | `text` |  Nullable |
| `full_name` | `text` |  |
| `email` | `text` |  Nullable |
| `phone` | `text` |  Nullable |
| `address` | `text` |  Nullable |
| `city_code` | `text` |  Nullable |
| `fiscal_regimen` | `text` |  Nullable |
| `fiscal_responsibilities` | `_text` |  Nullable |
| `metadata` | `jsonb` |  Nullable |
| `created_at` | `timestamptz` |  Nullable |
| `updated_at` | `timestamptz` |  Nullable |

## Table `customers`

### Columns

| Name | Type | Constraints |
|------|------|-------------|
| `id` | `uuid` | Primary |
| `tenant_id` | `uuid` |  |
| `name` | `varchar` |  |
| `email` | `text` |  Nullable |
| `phone` | `text` |  Nullable |
| `identification_type` | `text` |  Nullable |
| `identification_number` | `text` |  Nullable |
| `address` | `text` |  Nullable |
| `is_active` | `bool` |  Nullable |
| `created_at` | `timestamptz` |  Nullable |
| `updated_at` | `timestamptz` |  Nullable |
| `deleted_at` | `timestamptz` |  Nullable |
| `data_consent_at` | `timestamptz` |  Nullable |
| `data_consent_ip` | `text` |  Nullable |
| `data_consent_version` | `text` |  Nullable |
| `industry_id` | `uuid` |  Nullable |

## Table `domain_events`

### Columns

| Name | Type | Constraints |
|------|------|-------------|
| `id` | `uuid` | Primary |
| `tenant_id` | `uuid` |  Nullable |
| `event_type` | `text` |  |
| `entity_type` | `text` |  |
| `entity_id` | `uuid` |  |
| `payload` | `jsonb` |  Nullable |
| `occurred_at` | `timestamptz` |  Nullable |
| `processed_at` | `timestamptz` |  Nullable |
| `created_at` | `timestamptz` |  Nullable |

## Table `industries`

### Columns

| Name | Type | Constraints |
|------|------|-------------|
| `id` | `uuid` | Primary |
| `slug` | `text` |  Unique |
| `name` | `text` |  |
| `description` | `text` |  Nullable |
| `icon` | `text` |  Nullable |
| `is_active` | `bool` |  Nullable |
| `created_at` | `timestamptz` |  Nullable |
| `updated_at` | `timestamptz` |  Nullable |

## Table `industry_specialties`

### Columns

| Name | Type | Constraints |
|------|------|-------------|
| `id` | `uuid` | Primary |
| `industry_slug` | `varchar` |  Nullable |
| `name` | `text` |  |
| `slug` | `text` |  Unique |
| `description` | `text` |  Nullable |
| `is_active` | `bool` |  Nullable |
| `created_at` | `timestamptz` |  Nullable |

## Table `inventory_items`

### Columns

| Name | Type | Constraints |
|------|------|-------------|
| `id` | `uuid` | Primary |
| `tenant_id` | `uuid` |  Nullable |
| `location_id` | `uuid` |  Nullable |
| `stock` | `numeric` |  Nullable |
| `min_stock` | `numeric` |  Nullable |
| `max_stock` | `numeric` |  Nullable |
| `metadata` | `jsonb` |  Nullable |
| `created_at` | `timestamptz` |  Nullable |
| `updated_at` | `timestamptz` |  Nullable |
| `deleted_at` | `timestamptz` |  Nullable |
| `item_id` | `uuid` |  Nullable |

## Table `inventory_movements`

### Columns

| Name | Type | Constraints |
|------|------|-------------|
| `id` | `uuid` | Primary |
| `tenant_id` | `uuid` |  Nullable |
| `type` | `text` |  Nullable |
| `quantity` | `int4` |  Nullable |
| `reference_type` | `text` |  Nullable |
| `reference_id` | `uuid` |  Nullable |
| `created_by` | `uuid` |  Nullable |
| `created_at` | `timestamptz` |  Nullable |
| `reason` | `text` |  Nullable |
| `item_id` | `uuid` |  Nullable |

## Table `items`

### Columns

| Name | Type | Constraints |
|------|------|-------------|
| `id` | `uuid` | Primary |
| `tenant_id` | `uuid` |  |
| `category_id` | `uuid` |  Nullable |
| `sku` | `text` |  Nullable |
| `name` | `text` |  |
| `description` | `text` |  Nullable |
| `type` | `text` |  |
| `cost_price` | `numeric` |  Nullable |
| `base_price` | `numeric` |  |
| `wholesale_price` | `numeric` |  Nullable |
| `stock` | `numeric` |  Nullable |
| `min_stock` | `numeric` |  Nullable |
| `unit` | `text` |  Nullable |
| `duration_minutes` | `int4` |  Nullable |
| `is_active` | `bool` |  Nullable |
| `is_featured` | `bool` |  Nullable |
| `created_at` | `timestamptz` |  Nullable |
| `updated_at` | `timestamptz` |  Nullable |
| `bar_code` | `text` |  Nullable |
| `image_url` | `text` |  Nullable |
| `deleted_at` | `timestamptz` |  Nullable |
| `is_blocked` | `bool` |  |
| `blocked_reason` | `text` |  Nullable |
| `threshold_low` | `int4` |  |
| `threshold_critical` | `int4` |  |
| `metadata` | `jsonb` |  |
| `state` | `text` |  |

## Table `locations`

### Columns

| Name | Type | Constraints |
|------|------|-------------|
| `id` | `uuid` | Primary |
| `tenant_id` | `uuid` |  |
| `name` | `text` |  |
| `address` | `text` |  Nullable |
| `is_main` | `bool` |  Nullable |
| `is_active` | `bool` |  Nullable |
| `created_at` | `timestamptz` |  Nullable |

## Table `modules_catalog`

### Columns

| Name | Type | Constraints |
|------|------|-------------|
| `id` | `uuid` | Primary |
| `slug` | `text` |  Unique |
| `name` | `text` |  |
| `description` | `text` |  Nullable |
| `is_active` | `bool` |  Nullable |
| `created_at` | `timestamptz` |  Nullable |

## Table `plan_modules`

### Columns

| Name | Type | Constraints |
|------|------|-------------|
| `id` | `uuid` | Primary |
| `plan_slug` | `text` |  |
| `module_slug` | `text` |  |
| `is_active` | `bool` |  Nullable |
| `created_at` | `timestamptz` |  Nullable |

## Table `plans`

### Columns

| Name | Type | Constraints |
|------|------|-------------|
| `id` | `uuid` | Primary |
| `slug` | `text` |  Unique |
| `name` | `text` |  |
| `price_monthly` | `int4` |  Nullable |
| `max_users` | `int4` |  Nullable |
| `max_locations` | `int4` |  Nullable |
| `features` | `jsonb` |  Nullable |
| `created_at` | `timestamptz` |  Nullable |
| `is_active` | `bool` |  Nullable |

## Table `profiles`

### Columns

| Name | Type | Constraints |
|------|------|-------------|
| `id` | `uuid` | Primary |
| `tenant_id` | `uuid` |  |
| `email` | `text` |  |
| `full_name` | `text` |  Nullable |
| `avatar_url` | `text` |  Nullable |
| `app_role` | `text` |  Nullable |
| `is_active` | `bool` |  Nullable |
| `created_at` | `timestamptz` |  Nullable |
| `updated_at` | `timestamptz` |  Nullable |

## Table `purchase_order_items`

### Columns

| Name | Type | Constraints |
|------|------|-------------|
| `id` | `uuid` | Primary |
| `tenant_id` | `uuid` |  |
| `purchase_id` | `uuid` |  |
| `item_id` | `uuid` |  |
| `quantity` | `numeric` |  |
| `unit_cost` | `numeric` |  |
| `total_cost` | `numeric` |  |

## Table `purchase_orders`

### Columns

| Name | Type | Constraints |
|------|------|-------------|
| `id` | `uuid` | Primary |
| `tenant_id` | `uuid` |  |
| `contact_id` | `uuid` |  |
| `order_number` | `text` |  |
| `status` | `text` |  Nullable |
| `total_amount` | `numeric` |  Nullable |
| `notes` | `text` |  Nullable |
| `created_at` | `timestamptz` |  Nullable |
| `updated_at` | `timestamptz` |  Nullable |

## Table `sale_items`

### Columns

| Name | Type | Constraints |
|------|------|-------------|
| `id` | `uuid` | Primary |
| `sale_id` | `uuid` |  |
| `item_id` | `uuid` |  |
| `tenant_id` | `uuid` |  |
| `quantity` | `int4` |  |
| `unit_price` | `int4` |  |
| `tax_rate` | `int4` |  Nullable |
| `tax_amount` | `int4` |  |
| `discount_amount` | `int4` |  Nullable |
| `total_item_amount` | `int4` |  |

## Table `sales`

### Columns

| Name | Type | Constraints |
|------|------|-------------|
| `id` | `uuid` | Primary |
| `tenant_id` | `uuid` |  |
| `customer_id` | `uuid` |  Nullable |
| `invoice_number` | `text` |  |
| `cufe` | `text` |  Nullable Unique |
| `status` | `text` |  |
| `subtotal` | `int4` |  |
| `total_tax` | `int4` |  |
| `total_retentions` | `int4` |  |
| `total_amount` | `int4` |  |
| `payment_method` | `text` |  Nullable |
| `notes` | `text` |  Nullable |
| `created_at` | `timestamptz` |  |
| `created_by` | `uuid` |  Nullable |
| `deleted_at` | `timestamptz` |  Nullable |

## Table `service_order_items`

### Columns

| Name | Type | Constraints |
|------|------|-------------|
| `id` | `uuid` | Primary |
| `tenant_id` | `uuid` |  |
| `order_id` | `uuid` |  |
| `item_id` | `uuid` |  Nullable |
| `description` | `text` |  |
| `quantity` | `numeric` |  Nullable |
| `unit_price` | `numeric` |  |
| `is_service` | `bool` |  Nullable |
| `tax_amount` | `numeric` |  Nullable |
| `total_price` | `numeric` |  |
| `created_at` | `timestamptz` |  Nullable |

## Table `service_orders`

### Columns

| Name | Type | Constraints |
|------|------|-------------|
| `id` | `uuid` | Primary |
| `tenant_id` | `uuid` |  |
| `contact_id` | `uuid` |  |
| `asset_id` | `uuid` |  |
| `order_number` | `int4` |  |
| `status` | `order_status` |  Nullable |
| `notes` | `text` |  Nullable |
| `total_amount` | `numeric` |  Nullable |
| `metadata` | `jsonb` |  Nullable |
| `created_at` | `timestamptz` |  Nullable |
| `updated_at` | `timestamptz` |  Nullable |

## Table `subscriptions`

### Columns

| Name | Type | Constraints |
|------|------|-------------|
| `id` | `uuid` | Primary |
| `tenant_id` | `uuid` |  Unique |
| `plan_slug` | `text` |  |
| `status` | `text` |  |
| `billing_cycle` | `text` |  Nullable |
| `current_period_start` | `timestamptz` |  Nullable |
| `current_period_end` | `timestamptz` |  Nullable |
| `trial_ends_at` | `timestamptz` |  Nullable |
| `created_at` | `timestamptz` |  Nullable |
| `updated_at` | `timestamptz` |  Nullable |

## Table `tenant_modules`

### Columns

| Name | Type | Constraints |
|------|------|-------------|
| `id` | `uuid` | Primary |
| `tenant_id` | `uuid` |  |
| `module_slug` | `text` |  |
| `is_active` | `bool` |  Nullable |
| `config` | `jsonb` |  Nullable |
| `activated_at` | `timestamptz` |  Nullable |
| `expires_at` | `timestamptz` |  Nullable |

## Table `tenant_subscription_items`

### Columns

| Name | Type | Constraints |
|------|------|-------------|
| `id` | `uuid` | Primary |
| `tenant_id` | `uuid` |  |
| `module_slug` | `text` |  |
| `monthly_price` | `int4` |  Nullable |
| `activated_at` | `timestamptz` |  Nullable |

## Table `tenants`

### Columns

| Name | Type | Constraints |
|------|------|-------------|
| `id` | `uuid` | Primary |
| `name` | `text` |  |
| `slug` | `text` |  Unique |
| `industry_type` | `text` |  Nullable |
| `plan_slug` | `text` |  Nullable |
| `settings` | `jsonb` |  Nullable |
| `is_active` | `bool` |  Nullable |
| `created_at` | `timestamptz` |  Nullable |
| `updated_at` | `timestamptz` |  Nullable |
| `deleted_at` | `timestamptz` |  Nullable |
| `plan` | `text` |  Nullable |
| `active_modules` | `_text` |  Nullable |
| `branding` | `jsonb` |  Nullable |
| `feature_flags` | `_text` |  Nullable |

## Table `webhook_events`

Registro de eventos entrantes de integraciones externas: DIAN, MercadoPago, n8n, Alegra

### Columns

| Name | Type | Constraints |
|------|------|-------------|
| `id` | `uuid` | Primary |
| `tenant_id` | `uuid` |  Nullable |
| `source` | `text` |  |
| `event_type` | `text` |  |
| `payload` | `jsonb` |  |
| `status` | `text` |  |
| `attempts` | `int4` |  |
| `last_error` | `text` |  Nullable |
| `external_id` | `text` |  Nullable |
| `processed_at` | `timestamptz` |  Nullable |
| `created_at` | `timestamptz` |  Nullable |
| `updated_at` | `timestamptz` |  Nullable |

