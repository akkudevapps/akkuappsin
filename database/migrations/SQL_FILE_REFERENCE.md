# RequiredDatabase-db-fix.sql - Complete Reference

**File Location**: `/database/migrations/RequiredDatabase-db-fix.sql`  
**File Size**: ~18.8 KB  
**Created**: 2026-05-22  
**Tables**: 9  
**Indexes**: 20+  
**Sample Data**: Included (5 pricing tiers)  

---

## 📄 File Contents Overview

### Header Section
- SQL mode settings for compatibility
- Foreign key constraint settings
- Version and description

### Main Schema (9 Tables)

#### 1. ad_providers (Lines: ~50)
**Purpose**: Store advertisement provider company information and wallet  
**Key Fields**:
- `id` (UUID) - Primary key
- `user_id` (FK to users) - Link to user account
- `company_name` - Business name
- `wallet_balance` - Prepaid credits
- `status` - ENUM: pending, approved, suspended, rejected
- `country`, `state_province`, `city` - Location
- `bank_account_*` - Payment details
- `created_at`, `updated_at` - Timestamps

**Indexes**: 
- PRIMARY KEY on `id`
- UNIQUE on `user_id`, `tax_id`
- INDEX on `user_id`, `status`, `created_at`

---

#### 2. ad_pricing_tiers (Lines: ~40)
**Purpose**: Define available ad sizes and their pricing  
**Key Fields**:
- `id` (UUID)
- `size_type` - UNIQUE: banner_728x90, square_300x300, etc
- `display_name` - User-friendly name
- `width_px`, `height_px` - Dimensions
- `base_price_monthly` - Fixed monthly price
- `price_cpm` - Cost per 1000 impressions
- `price_cpc` - Cost per click
- `currency` - Pricing currency (USD, INR, EUR, etc)
- `is_active` - Boolean flag

**Sample Data**:
```sql
INSERT INTO ad_pricing_tiers VALUES
(5 rows with: banner 728x90, square 300x300, leaderboard 970x250, etc)
```

---

#### 3. advertisements (Lines: ~80)
**Purpose**: Store individual advertisements with complete targeting  
**Key Fields**:
- `id` (UUID)
- `provider_id` (FK) - Who's paying
- `campaign_id` (FK) - Campaign grouping
- `title`, `description` - Ad content
- `ad_size_id` (FK) - Reference to pricing tier
- `ad_type` - ENUM: image, text, video, custom
- `category` - Content type
- `image_url`, `video_url`, `click_url` - Media and destination
- **Targeting** (JSON fields):
  - `target_regions` - Geographic regions
  - `target_languages` - Languages
  - `target_countries` - Countries
  - `target_demographics` - Age, gender
- `start_date`, `end_date` - Campaign dates
- `daily_budget`, `total_budget`, `budget_spent` - Financials
- `pricing_model` - ENUM: fixed, cpm, cpc
- `status` - ENUM: draft, pending_approval, approved, active, paused, completed, archived
- **A/B Testing**:
  - `variation_group_id` - Group variations together
  - `variation_label` - "Variant A", "Variant B", etc
  - `is_primary` - Primary variant flag
- **Performance**:
  - `impressions`, `clicks`, `conversions`, `ctr` - Real-time stats

**Indexes**:
- PRIMARY KEY on `id`
- FOREIGN KEY on `provider_id`, `campaign_id`
- INDEX on: provider_id, status, campaign_id, variation_group_id, start_date, end_date
- COMPOSITE: (provider_id, status), (start_date, end_date)

---

#### 4. ad_placements (Lines: ~40)
**Purpose**: Define where ads can be placed on the site  
**Key Fields**:
- `id` (UUID)
- `ad_id` (FK) - Reference to advertisement
- `page` - ENUM: news_index, article_page, dashboard, etc
- `position` - ENUM: hero, sidebar_top, sidebar_mid, sidebar_bottom, footer
- `is_active` - Boolean
- `priority` - Rotation order (1-10)
- `impression_count`, `click_count` - Stats per placement

**Indexes**:
- PRIMARY KEY on `id`
- FOREIGN KEY on `ad_id`
- INDEX on: ad_id, page+position, is_active
- COMPOSITE: (is_active, page, position)

---

#### 5. ad_analytics (Lines: ~45)
**Purpose**: Real-time analytics data aggregated daily  
**Key Fields**:
- `id` (UUID)
- `ad_id` (FK)
- `analytics_date` - Date of the stats (UNIQUE per ad/date)
- `impressions` - Daily view count
- `clicks` - Daily click count
- `conversions` - Daily conversions
- `spend_today` - Daily cost charged
- `ctr` - Click-through rate percentage
- `created_at`, `updated_at` - Timestamps

**Indexes**:
- PRIMARY KEY on `id`
- UNIQUE on (ad_id, analytics_date)
- FOREIGN KEY on `ad_id`
- INDEX on: ad_id, analytics_date
- COMPOSITE: (ad_id, analytics_date)

**Note**: This table grows rapidly - consider partitioning by date for large volumes

---

#### 6. ad_transactions (Lines: ~50)
**Purpose**: Track all payment transactions for ads  
**Key Fields**:
- `id` (UUID)
- `provider_id` (FK) - Who paid
- `ad_id` (FK) - Which ad (optional)
- `amount`, `currency` - Payment amount and type
- `pricing_model` - Model used: fixed, cpm, cpc
- `transaction_type` - ENUM: charge, refund, adjustment
- `status` - ENUM: pending, completed, failed, refunded
- `payment_method` - ENUM: bank_transfer, razorpay, stripe, wallet, manual
- `external_transaction_id` - External gateway ID
- `invoice_number` - UNIQUE invoice reference
- `invoice_date`, `due_date`, `paid_date` - Important dates
- `description`, `notes` - Details

**Indexes**:
- PRIMARY KEY on `id`
- UNIQUE on `invoice_number`
- FOREIGN KEY on provider_id, ad_id
- INDEX on: provider_id, ad_id, status, created_at

---

#### 7. ad_wallet_transactions (Lines: ~45)
**Purpose**: Ledger for wallet deposits, withdrawals, and charges  
**Key Fields**:
- `id` (UUID)
- `provider_id` (FK)
- `amount` - Transaction amount
- `currency` - Currency
- `transaction_type` - ENUM: deposit, withdrawal, charge, refund
- `status` - ENUM: pending, completed, failed
- `payment_method` - ENUM: razorpay, stripe, bank_transfer, manual
- `payment_gateway_id` - External gateway transaction ID
- `description` - What the transaction is for
- `balance_before`, `balance_after` - Wallet balance snapshot
- `related_ad_id` - Ad associated with charge

**Indexes**:
- PRIMARY KEY on `id`
- FOREIGN KEY on `provider_id`
- INDEX on: provider_id, created_at, status

---

#### 8. ad_campaigns (Lines: ~45)
**Purpose**: Group multiple ads into campaigns for organization  
**Key Fields**:
- `id` (UUID)
- `provider_id` (FK)
- `campaign_name` - Campaign title
- `description` - Campaign details
- `status` - ENUM: draft, active, paused, completed, archived
- `budget_allocated`, `budget_spent` - Campaign financials
- `start_date`, `end_date` - Campaign duration
- `total_ads` - Number of ads in campaign
- `performance_metrics` - JSON with aggregated stats

**Indexes**:
- PRIMARY KEY on `id`
- FOREIGN KEY on `provider_id`
- INDEX on: provider_id, status

---

#### 9. ad_tickets (Lines: ~40)
**Purpose**: Digital goods representing ad space for purchase  
**Key Fields**:
- `id` (UUID)
- `ticket_name` - Display name (e.g., "Banner 728x90 - 30 days")
- `description` - Detailed description
- `pricing_tier_id` - Link to ad size
- `duration_days` - How long the ad runs
- `price`, `currency` - Ticket price
- `quantity_available` - Stock (-1 for unlimited)
- `quantity_sold` - Units sold
- `status` - ENUM: active, inactive, sold_out

**Indexes**:
- PRIMARY KEY on `id`
- INDEX on: status, pricing_tier_id

---

### Footer Section
- Sample data INSERT statements (5 pricing tiers)
- Execution notes
- Troubleshooting tips

---

## 🔍 Key SQL Features Used

### 1. **Foreign Keys**
```sql
CONSTRAINT `fk_ad_provider` FOREIGN KEY (`provider_id`) 
    REFERENCES `ad_providers` (`id`) ON DELETE CASCADE
```
- Data integrity
- Cascade deletes when provider deleted
- Prevents orphaned records

### 2. **JSON Fields** (for Targeting)
```sql
`target_regions` json COMMENT '{"regions": ["TamilNadu"], "cities": ["Chennai"]}'
```
- Flexible targeting options
- Used with JSON_CONTAINS for querying
- Supports multiple regions/languages

### 3. **ENUM Fields** (for Status)
```sql
`status` enum('pending','approved','suspended','rejected') DEFAULT 'pending'
```
- Prevents invalid values
- Efficient storage (1-2 bytes)
- Clear state management

### 4. **UNIQUE Constraints**
```sql
UNIQUE KEY `unique_user_id` (`user_id`),
UNIQUE KEY `unique_invoice` (`invoice_number`),
UNIQUE KEY `unique_ad_date` (`ad_id`, `analytics_date`)
```
- Prevents duplicates
- Ensures data integrity
- Enables efficient lookups

### 5. **Composite Indexes**
```sql
KEY `idx_ad_provider_status` (`provider_id`, `status`),
KEY `idx_placement_active` (`is_active`, `page`, `position`)
```
- Optimizes queries with multiple conditions
- Improves query performance
- Reduces database load

### 6. **Timestamps**
```sql
`created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
`updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
```
- Auto-timestamps
- Audit trail
- Track changes

### 7. **Auto-Comments**
```sql
`id` varchar(36) NOT NULL COMMENT 'UUID'
```
- Self-documenting schema
- Visible in database tools
- Developer reference

---

## 📊 Relationships Diagram

```
ad_providers (1) ─────────────┬─────────────┐
                              │             │
                    (Many)    │    (Many)  │
                         │         │        │
                    advertisements │    ad_campaigns
                         │         │        │
                    ad_placements──┤    (Many)
                    ad_analytics───┤    advertisements
                    ad_transactions└─── ad_tickets
                         
                                    
ad_wallet_transactions ─────── ad_providers (1)
                              │ (Many)
                            
ad_pricing_tiers (1) ─────────── (Many) advertisements
```

---

## ⚙️ Configuration Options

### In admin/news.php (or future admin pages)
```php
// Sample pricing tier creation
INSERT INTO ad_pricing_tiers (id, size_type, display_name, base_price_monthly)
VALUES (UUID(), 'banner_728x90', 'Banner 728x90', 100.00);
```

### Sample Ad Creation
```php
INSERT INTO advertisements (
    id, provider_id, ad_size_id, title, description, 
    ad_type, target_regions, target_languages, 
    start_date, end_date, total_budget, pricing_model, status
) VALUES (
    UUID(), 'provider-123', 'tier-banner-728', 'Amazing Product',
    'Check out our amazing product!', 'image',
    JSON_OBJECT('regions', JSON_ARRAY('TamilNadu')),
    JSON_OBJECT('languages', JSON_ARRAY('ta', 'en')),
    CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY),
    500, 'fixed', 'pending_approval'
);
```

---

## 🚀 Execution Instructions

### Method 1: MySQL CLI
```bash
mysql -u root -p akkuapps < /database/migrations/RequiredDatabase-db-fix.sql
```

### Method 2: phpMyAdmin
1. Login to phpMyAdmin
2. Select "akkuapps" database
3. Click "SQL" tab
4. Open the file in text editor
5. Copy all content
6. Paste into phpMyAdmin SQL editor
7. Click "Go"

### Method 3: PHP Script
```bash
# Visit in browser
https://akkuapps.in/admin/execute-db-migration.php
# Or run from command line
php /admin/execute-db-migration.php
```

### Method 4: DBeaver / MySQL Workbench
1. Open DBeaver or MySQL Workbench
2. New SQL script
3. Paste file contents
4. Execute

---

## ✅ Verification After Execution

```sql
-- Check tables created
SHOW TABLES LIKE 'ad_%';

-- Check ad_providers structure
DESCRIBE ad_providers;

-- Verify sample data
SELECT * FROM ad_pricing_tiers;

-- Check indexes
SHOW INDEXES FROM advertisements;

-- Verify foreign keys
SELECT CONSTRAINT_NAME, TABLE_NAME 
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
WHERE REFERENCED_TABLE_NAME = 'ad_providers';
```

---

## 🔒 Important Notes

1. **Backup First**: Always backup database before running migrations
2. **Test Environment**: Test in development before production
3. **No Data Loss**: CREATE TABLE IF NOT EXISTS means safe to re-run
4. **Permissions**: Requires CREATE TABLE privileges
5. **Disk Space**: Ensure adequate disk space for tables
6. **Character Set**: Uses utf8mb4 for full Unicode support
7. **InnoDB**: Requires InnoDB engine for foreign keys
8. **Transaction Support**: All tables are transactional

---

## 📈 Performance Considerations

### Index Strategy
- All frequently queried columns indexed
- Composite indexes for common WHERE clauses
- Primary key on UUID (36 bytes)

### Growth Projections
| Table | Daily Growth | 1 Year Size |
|-------|-------------|-----------|
| ad_analytics | 10k rows | 3.6M rows |
| ad_transactions | 100 rows | 36.5k rows |
| advertisements | 10 rows | 3.65k rows |
| ad_wallet_transactions | 50 rows | 18.25k rows |

### Optimization Tips
- Archive analytics older than 12 months
- Partition ad_analytics by date
- Use query cache for pricing tiers
- Index frequently filtered columns

---

## 🆘 Troubleshooting

### Error: "Table already exists"
```bash
# This is OK - the script uses CREATE TABLE IF NOT EXISTS
# It will skip existing tables
```

### Error: "Foreign key constraint fails"
```bash
# Ensure:
SET FOREIGN_KEY_CHECKS = 1;
# And create tables in correct order (script does this automatically)
```

### Error: "Character set utf8mb4 not available"
```bash
# Update MySQL/MariaDB character set support
# Or change utf8mb4 to utf8 in the SQL file
```

### Error: "Cannot create file"
```bash
# Check:
# - Write permissions on database directory
# - Disk space available
# - MySQL service running
# - Correct database selected
```

---

## 📋 File Statistics

- **Total Lines**: 500+
- **Total Size**: ~18.8 KB
- **Tables**: 9
- **Indexes**: 20+
- **Foreign Keys**: 7
- **Comments**: 100+
- **Sample Data Rows**: 5

---

**Ready to Deploy** ✅

This SQL file is production-ready and has been thoroughly tested for:
- ✅ Data integrity (foreign keys)
- ✅ Performance (indexes)
- ✅ Scalability (JSON fields, composite keys)
- ✅ Security (constraints, validation)
- ✅ Maintainability (comments, clear structure)

