# AkkuApps Ad System - Database Setup Guide

## 📋 Overview

This document provides instructions for setting up the complete Ad System database schema for AkkuApps. The system includes:

- **Ad Providers Management** - Companies/users who buy ad space
- **Advertisement Management** - Individual ads with targeting
- **Pricing Tiers** - Different ad sizes and pricing models
- **Geographic & Language Targeting** - Regional ads in Tamil, Kannada, Malayalam, English
- **Real-time Analytics** - Track impressions, clicks, conversions
- **Payment Processing** - Razorpay integration + wallet system
- **A/B Testing** - Multiple ad variations per campaign
- **Digital Goods Integration** - Buy ad tickets from admin shop

## 📊 Database Schema (9 Tables)

### 1. **ad_providers** - Stores ad provider information
```sql
- id (UUID, Primary Key)
- user_id (FK to users)
- company_name, email, phone
- country, state_province, city
- wallet_balance (prepaid credits)
- status (pending, approved, suspended, rejected)
- tax_id, bank details, UPI
- Created/Updated timestamps
```

### 2. **ad_pricing_tiers** - Ad size and pricing definitions
```sql
- id (UUID)
- size_type (banner_728x90, square_300x300, etc)
- display_name, width_px, height_px
- base_price_monthly, price_cpm, price_cpc
- currency, is_active
- Supported formats
```

### 3. **advertisements** - Individual ads
```sql
- id (UUID)
- provider_id (FK), campaign_id (FK)
- title, description, ad_size_id (FK)
- ad_type (image, text, video, custom)
- category, image_url, click_url
- Geographic targeting (regions, languages, countries)
- start_date, end_date
- daily_budget, total_budget, budget_spent
- pricing_model (fixed, cpm, cpc)
- status (draft, pending_approval, approved, active, paused, completed, archived)
- A/B variations (variation_group_id, variation_label)
- Performance metrics (impressions, clicks, conversions, ctr)
```

### 4. **ad_placements** - Where ads appear on site
```sql
- id (UUID)
- ad_id (FK), page, position
- is_active, priority
- impression_count, click_count
```

### 5. **ad_analytics** - Daily analytics tracking
```sql
- id (UUID)
- ad_id (FK), analytics_date (unique per ad/date)
- impressions, clicks, conversions
- spend_today, ctr
- Real-time updates
```

### 6. **ad_transactions** - Payment records
```sql
- id (UUID)
- provider_id (FK), ad_id (FK)
- amount, currency
- pricing_model, transaction_type
- status (pending, completed, failed, refunded)
- payment_method (bank_transfer, razorpay, stripe, wallet, manual)
- invoice_number, invoice_date, due_date, paid_date
```

### 7. **ad_wallet_transactions** - Wallet/credits ledger
```sql
- id (UUID)
- provider_id (FK)
- amount, currency
- transaction_type (deposit, withdrawal, charge, refund)
- status, payment_method
- balance_before, balance_after
- Tracks all wallet movements
```

### 8. **ad_campaigns** - Group ads into campaigns
```sql
- id (UUID)
- provider_id (FK)
- campaign_name, description
- status, budget_allocated, budget_spent
- start_date, end_date
- performance_metrics (JSON)
```

### 9. **ad_tickets** - Digital goods for ad purchases
```sql
- id (UUID)
- ticket_name (e.g., "Banner 728x90 - 30 days")
- pricing_tier_id (FK), duration_days
- price, currency
- quantity_available, quantity_sold
- status (active, inactive, sold_out)
```

## 🔧 Installation Methods

### Method 1: Using phpMyAdmin
1. Log in to phpMyAdmin
2. Select your "akkuapps" database
3. Click "SQL" tab
4. Copy contents of `RequiredDatabase-db-fix.sql`
5. Paste and click "Go"
6. Check for success message

### Method 2: Using MySQL Command Line
```bash
mysql -u root -p akkuapps < RequiredDatabase-db-fix.sql
```

### Method 3: Using PHP Admin Interface (Recommended)
1. Visit: `https://akkuapps.in/admin/execute-db-migration.php`
2. Must be logged in as admin
3. Migration runs automatically
4. Shows detailed results
5. Logs all changes

### Method 4: Verify Installation
```sql
-- Check if all tables exist
SHOW TABLES LIKE 'ad_%';

-- Should show 9 tables:
-- ad_providers
-- ad_pricing_tiers
-- advertisements
-- ad_placements
-- ad_analytics
-- ad_transactions
-- ad_wallet_transactions
-- ad_campaigns
-- ad_tickets
```

## 🔐 Security Considerations

✅ **Implemented:**
- Foreign key constraints for data integrity
- UNIQUE constraints on email, tax_id, invoice_number
- Timestamps (created_at, updated_at) for auditing
- Status enums to prevent invalid values
- Bank account number field (marked for encryption in app)
- Admin approval workflow for providers and ads

⚠️ **To Implement in Code:**
- Encrypt bank account numbers before storing
- Sanitize all user inputs
- Implement rate limiting on payment APIs
- Add CORS headers for API endpoints
- Use prepared statements (already in PHP code)

## 💱 Multi-Currency Support

All pricing and transactions support multiple currencies:
- `ad_providers.currency` - Provider's preferred currency
- `ad_pricing_tiers.currency` - Tier pricing currency
- `ad_tickets.currency` - Ticket pricing currency
- `ad_transactions.currency` - Transaction currency
- `ad_wallet_transactions.currency` - Wallet currency

Supported currencies: USD, EUR, GBP, INR, AUD, CAD, JPY

## 🌍 Geographic & Language Targeting

### Supported Regions (India focus + International)
- **Tamil Nadu** → Language: `ta` (Tamil)
- **Karnataka** → Language: `ka` (Kannada)
- **Kerala** → Language: `ml` (Malayalam)
- **Andhra Pradesh** → Language: `te` (Telugu)
- **Maharashtra** → Language: `mr` (Marathi)
- **Gujarat** → Language: `gu` (Gujarati)
- **West Bengal** → Language: `bn` (Bengali)
- **Telangana** → Language: `te` (Telugu)
- **International** → Language: `en` (English)

### Example Targeting JSON
```json
{
  "regions": ["TamilNadu", "Karnataka", "Kerala"],
  "cities": ["Chennai", "Bangalore", "Kochi"],
  "countries": ["IN", "US", "UK"],
  "languages": ["ta", "en", "ka", "ml"]
}
```

## 📈 Real-time Analytics

Analytics update in real-time:
1. **Impression Tracking** - `/api/track-ad-impression.php`
2. **Click Tracking** - `/api/track-ad-click.php`
3. **Daily Aggregation** - `ad_analytics` table stores daily stats
4. **CTR Calculation** - Auto-calculated as `(clicks / impressions) * 100`

### Analytics Query
```sql
SELECT 
    SUM(impressions) as total_impressions,
    SUM(clicks) as total_clicks,
    AVG(ctr) as avg_ctr,
    analytics_date
FROM ad_analytics
WHERE ad_id = ?
GROUP BY analytics_date
ORDER BY analytics_date DESC;
```

## 💳 Payment Integration

### Payment Methods
1. **Razorpay** - Automatic payments (webhook at `/api/payment-callback-razorpay.php`)
2. **Bank Transfer** - Manual invoices generated
3. **Stripe** - Alternative gateway (future)
4. **Wallet** - Prepaid credits system

### Pricing Models
1. **Fixed** - Monthly fee per ad size
2. **CPM** - Cost Per 1000 impressions
3. **CPC** - Cost Per Click

### Example: Buy Ad Ticket Workflow
1. Admin creates ad tickets (digital goods)
2. Provider buys ticket via digital goods shop
3. Provider receives ad credits in wallet
4. Provider creates ad and submits for approval
5. Admin approves → Ad goes live
6. Credits deducted daily/per impression based on pricing model

## 🔄 A/B Testing

Multiple ad variations tracked via:
- `variation_group_id` - Groups related variations
- `variation_label` - "Variant A", "Variant B", etc.
- `is_primary` - Primary version flag

All variations tracked separately in `ad_analytics`.

## 📝 Sample Queries

### Get Active Ads for User's Region
```sql
SELECT a.* FROM advertisements a
JOIN ad_placements ap ON a.id = ap.ad_id
WHERE ap.page = 'news_index'
  AND ap.is_active = 1
  AND a.status = 'active'
  AND CURDATE() BETWEEN a.start_date AND a.end_date
  AND JSON_CONTAINS(a.target_languages, '"ta"', '$.languages')
LIMIT 5;
```

### Provider Performance Report
```sql
SELECT 
    ad.title,
    SUM(ana.impressions) as total_impressions,
    SUM(ana.clicks) as total_clicks,
    AVG(ana.ctr) as avg_ctr,
    SUM(ana.spend_today) as total_spend
FROM advertisements ad
LEFT JOIN ad_analytics ana ON ad.id = ana.ad_id
WHERE ad.provider_id = ?
  AND ana.analytics_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
GROUP BY ad.id
ORDER BY total_spend DESC;
```

### Pending Approvals
```sql
SELECT * FROM advertisements
WHERE status = 'pending_approval'
ORDER BY created_at ASC;
```

## ⚡ Performance Tips

1. **Indexing** - All frequently queried columns indexed
2. **Partitioning** - Consider date-based partitioning for `ad_analytics` if volume grows large
3. **Archive Old Analytics** - Move analytics older than 1 year to archive table
4. **Query Optimization** - Use `EXPLAIN ANALYZE` on slow queries

## 🐛 Troubleshooting

### Tables Not Created?
```bash
# Check table creation
SHOW TABLES LIKE 'ad_%';

# Check for errors
SHOW ENGINE INNODB STATUS;
```

### Foreign Key Errors?
```sql
-- Check foreign keys
SELECT CONSTRAINT_NAME, TABLE_NAME, COLUMN_NAME 
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
WHERE REFERENCED_TABLE_NAME = 'ad_providers';

-- Enable foreign keys
SET FOREIGN_KEY_CHECKS = 1;
```

### Wallet Balance Incorrect?
```sql
-- Verify wallet balance
SELECT provider_id, SUM(CASE 
    WHEN transaction_type IN ('deposit', 'refund') THEN amount
    WHEN transaction_type IN ('withdrawal', 'charge') THEN -amount
    ELSE 0 END) as calculated_balance
FROM ad_wallet_transactions
WHERE status = 'completed'
GROUP BY provider_id;

-- Compare with actual
SELECT id, wallet_balance FROM ad_providers;
```

## 📚 Related Files

- **PHP Functions**: `/includes/ad-engine.php`
- **Payment Engine**: `/includes/payment-engine.php`
- **Tracking APIs**: `/api/track-ad-impression.php`, `/api/track-ad-click.php`
- **Admin Pages** (to be created): `/admin/advertisements.php`, `/admin/ad-providers.php`
- **JavaScript**: `/assets/js/ad-tracker.js`, `/assets/js/ckeditor5-setup.js`

## ✅ Verification Checklist

After running migration, verify:

- [ ] 9 tables created successfully
- [ ] Sample pricing tiers inserted
- [ ] Foreign key constraints active
- [ ] Indexes created
- [ ] All columns have correct types
- [ ] DEFAULT values set correctly
- [ ] TIMESTAMP columns auto-updating
- [ ] ENUM values correct
- [ ] JSON columns support targeting data
- [ ] No duplicate key errors

## 📞 Support

For issues:
1. Check error logs: `/admin/execute-db-migration.php`
2. Review MySQL error log
3. Verify database user permissions
4. Check disk space availability
5. Contact: support@akkuapps.in

---

**Last Updated**: 2026-05-22
**Version**: 1.0
**Status**: Ready for Production
