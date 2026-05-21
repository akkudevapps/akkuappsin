# AkkuApps Ad System - Quick Reference Guide

## 📋 File Locations

```
Database Schema:
  /database/migrations/RequiredDatabase-db-fix.sql

Core Functions:
  /includes/ad-engine.php (14 functions)
  /includes/payment-engine.php (10 functions)

Tracking APIs:
  /api/track-ad-impression.php
  /api/track-ad-click.php
  /api/payment-callback-razorpay.php

JavaScript:
  /assets/js/ad-tracker.js
  /assets/js/ckeditor5-setup.js

Admin Tools:
  /admin/execute-db-migration.php
  /admin/run-ad-migration.php

Documentation:
  /database/migrations/DATABASE_SETUP_GUIDE.md
  /IMPLEMENTATION_SUMMARY.md (this file)
```

---

## 🚀 Quick Setup

### Step 1: Run Database Migration
```bash
# Option A: Command line
mysql -u root -p akkuapps < /database/migrations/RequiredDatabase-db-fix.sql

# Option B: phpMyAdmin
# Select akkuapps database → SQL tab → Copy & paste file → Run

# Option C: Admin panel
# Visit: /admin/execute-db-migration.php (admin login required)
```

### Step 2: Verify Installation
```sql
SHOW TABLES LIKE 'ad_%';
-- Should show 9 tables
```

### Step 3: Check Razorpay Config
```bash
# Ensure these are set in .env or environment
RAZORPAY_KEY_ID=your_key
RAZORPAY_KEY_SECRET=your_secret
```

---

## 📊 Database Tables (9)

| # | Table | Purpose | Key Fields |
|---|-------|---------|-----------|
| 1 | ad_providers | Provider info | wallet_balance, status, country |
| 2 | ad_pricing_tiers | Ad sizes | width_px, height_px, base_price_monthly |
| 3 | advertisements | Individual ads | provider_id, status, impressions, clicks |
| 4 | ad_placements | Where ads appear | page, position, is_active |
| 5 | ad_analytics | Daily stats | impressions, clicks, ctr, spend_today |
| 6 | ad_transactions | Payments | amount, status, invoice_number |
| 7 | ad_wallet_transactions | Wallet ledger | balance_before, balance_after |
| 8 | ad_campaigns | Ad grouping | campaign_name, budget_allocated |
| 9 | ad_tickets | Digital goods | duration_days, price, quantity_available |

---

## 💻 Key Functions

### Display Ads
```php
// Get active ads for region & language
$ads = akkuAdGetActiveAds($pdo, 'news_index', 'TamilNadu', 'ta', 5);

// Get random ad
$ad = akkuAdGetRandomAd($pdo, 'news_index', 'TamilNadu', 'ta');

// Get provider's ads
$ads = akkuAdGetProviderAds($pdo, $providerId, 'active');

// Get pricing tiers
$tiers = akkuAdGetPricingTiers($pdo);
```

### Track Impressions & Clicks
```php
// Server-side
akkuAdRecordImpression($pdo, $adId, 'sidebar_top');
akkuAdRecordClick($pdo, $adId, 'sidebar_top');

// Client-side (JavaScript)
AkkuAdTracker.trackImpression(adId, 'sidebar_top');
AkkuAdTracker.trackClick(adId, 'sidebar_top', redirectUrl);
```

### Wallet Operations
```php
// Add credits (from payment)
akkuAdAddWallet($pdo, $providerId, 500, 'Razorpay deposit', 'razorpay', $txnId);

// Deduct for ads
akkuAdDeductWallet($pdo, $providerId, 150, 'Ad display charge', $adId);

// Get provider
$provider = akkuAdGetProviderByUser($pdo, $userId);
echo $provider['wallet_balance']; // Check balance
```

### Payments
```php
// Create Razorpay order
$order = akkuAdCreateRazorpayOrder($amount, 'INR', $providerId);

// Handle webhook callback
$result = akkuAdProcessRazorpayCallback($pdo, $_POST);

// Get transactions
$txns = akkuAdGetProviderTransactions($pdo, $providerId, 50);
```

### Analytics
```php
// Get provider performance
$stats = akkuAdGetProviderAnalytics($pdo, $providerId, 30); // last 30 days
echo $stats['total_impressions'];
echo $stats['total_clicks'];
echo $stats['avg_ctr'];
```

---

## 🌍 Regions & Languages

### Supported Regions
| Region | Language Code | Language |
|--------|---------------|----------|
| TamilNadu | ta | Tamil |
| Karnataka | ka | Kannada |
| Kerala | ml | Malayalam |
| Telangana | te | Telugu |
| Maharashtra | mr | Marathi |
| Gujarat | gu | Gujarati |
| WestBengal | bn | Bengali |
| AndhraPradesh | te | Telugu |
| International | en | English |

### Example Targeting
```json
{
  "regions": ["TamilNadu", "Karnataka"],
  "cities": ["Chennai", "Bangalore"],
  "countries": ["IN", "US"],
  "languages": ["ta", "en", "ka"]
}
```

---

## 💳 Pricing Models

### Fixed Monthly
```
Price: $100/month
Charged: Once per 30 days
Example: Banner 728x90 = $100/month
```

### CPM (Cost Per 1000 Impressions)
```
Price: $5 CPM
Calculation: ($5 / 1000) × impressions
Example: 50,000 impressions = $250 charge
```

### CPC (Cost Per Click)
```
Price: $0.50 CPC
Calculation: $0.50 × clicks
Example: 1,000 clicks = $500 charge
```

---

## 📱 Geolocation Detection

```php
// Get user's location and language
$location = akkuAdGetUserLocationAndLanguage($pdo, $userId);

// Returns:
// [
//   'country' => 'IN',
//   'state' => 'TamilNadu',
//   'city' => 'Chennai',
//   'region' => 'TamilNadu',
//   'language' => 'ta'
// ]
```

---

## 💱 Multi-Currency

### Supported Currencies
```
USD - US Dollar ($)
EUR - Euro (€)
GBP - British Pound (£)
INR - Indian Rupee (₹)
AUD - Australian Dollar (A$)
CAD - Canadian Dollar (C$)
JPY - Japanese Yen (¥)
```

### Format Currency
```php
echo akkuAdFormatCurrency(150, 'INR');  // ₹ 150.00
echo akkuAdFormatCurrency(100, 'USD');  // $ 100.00
echo akkuAdFormatCurrency(50, 'EUR');   // € 50.00
```

---

## 🔄 Workflow: Buy & Display Ad

### Provider Workflow
```
1. Visit Admin Shop → Buy "Ad Ticket" (e.g., Banner 728x90 - 30 days)
   └─ Payment: Razorpay/Bank transfer
   
2. Credits added to wallet (e.g., 500 INR)

3. Create Ad
   - Title, Description, Image
   - Select Size (from pricing tiers)
   - Choose Targeting (regions, languages, countries)
   - Set Budget & Dates
   
4. Submit for Approval
   - Status: pending_approval
   
5. Wait for Admin Approval
   - Admin reviews content
   - Approves or Rejects

6. If Approved: Ad Goes Live
   - Status: active
   - Starts tracking impressions & clicks
   - Wallet debited daily based on pricing model
```

### User Workflow
```
1. User visits /news/index.php or /news/article.php

2. Page detects user's location & language
   - Location: TamilNadu
   - Language: ta (Tamil)
   
3. Fetch active ads matching:
   - region: TamilNadu
   - language: ta
   
4. Display ads in sidebar/hero section
   - Track impression (page load)
   - Track click (user clicks ad)
   
5. Provider wallet debited for:
   - 1 impression (if CPM model)
   - 1 click (if CPC model)
   - Daily fixed rate (if fixed model)
```

---

## 🔧 API Endpoints

### Tracking
```
POST /api/track-ad-impression.php
  - ad_id (required)
  - placement (optional: "sidebar_top", "hero", etc)
  - Response: {"success": true}

POST /api/track-ad-click.php
  - ad_id (required)
  - placement (optional)
  - Response: {"success": true}
```

### Payment Webhook
```
POST /api/payment-callback-razorpay.php
  - Razorpay webhook payload
  - Verifies signature
  - Adds to wallet on success
```

---

## 📊 Sample Query: Get Ads for User

```php
// In /news/index.php or /news/article.php
require_once '../includes/ad-engine.php';

$userId = getCurrentUser()['id'] ?? null;
$location = akkuAdGetUserLocationAndLanguage($pdo, $userId);

$ads = akkuAdGetActiveAds(
    $pdo,
    'news_index',        // page
    $location['state'],  // region (TamilNadu, Karnataka, etc)
    $location['language'], // language (ta, ka, ml, en)
    3                    // show 3 ads
);

foreach ($ads as $ad) {
    // Render ad
}
```

---

## 🎯 HTML: Display Ad with Tracking

```html
<div data-ad-id="<?= $ad['id'] ?>" data-placement="sidebar_top">
    <a href="<?= $ad['click_url'] ?>" data-ad-link>
        <img src="<?= $ad['image_url'] ?>" 
             alt="<?= $ad['alt_text'] ?>"
             width="<?= $ad['width_px'] ?>"
             height="<?= $ad['height_px'] ?>">
    </a>
</div>

<script src="/assets/js/ad-tracker.js"></script>
<!-- Automatically tracked via AkkuAdTracker -->
```

---

## ⚙️ CKEditor 5 Setup

```html
<!-- Include CKEditor library -->
<script src="https://cdn.ckeditor.com/ckeditor5/latest/classic/ckeditor.js"></script>
<script src="/assets/js/ckeditor5-setup.js"></script>

<!-- Initialize -->
<textarea id="editor"></textarea>

<script>
CKEditor5Setup.initEditor('editor', '/admin/news.php?action=upload_file&folder=articles')
    .then(editor => {
        console.log('Editor ready');
        // Editor is ready
    })
    .catch(error => {
        console.error(error);
    });
</script>
```

---

## 📈 Real-time Analytics Query

```sql
-- Get ad performance (last 7 days)
SELECT 
    a.title,
    SUM(ana.impressions) as total_impressions,
    SUM(ana.clicks) as total_clicks,
    AVG(ana.ctr) as avg_ctr,
    SUM(ana.spend_today) as total_spend
FROM advertisements a
LEFT JOIN ad_analytics ana ON a.id = ana.ad_id
WHERE a.provider_id = 'provider-123'
  AND ana.analytics_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
GROUP BY a.id
ORDER BY total_spend DESC;
```

---

## 🔐 Security

✅ **Always use**:
- Prepared statements (prevent SQL injection)
- Input validation & sanitization
- Admin role verification
- CORS headers on APIs
- Rate limiting on payment APIs

⚠️ **Never do**:
- Store unencrypted bank account numbers
- Log sensitive payment data
- Use direct string concatenation in SQL
- Allow unauthenticated access to admin functions

---

## 📞 Troubleshooting

### Problem: "Database tables not found"
```bash
# Run migration
php /admin/execute-db-migration.php

# Or via CLI
mysql -u root -p akkuapps < /database/migrations/RequiredDatabase-db-fix.sql
```

### Problem: "Wallet balance negative"
```sql
-- Verify wallet transactions
SELECT provider_id, 
       SUM(CASE 
           WHEN transaction_type IN ('deposit','refund') THEN amount
           WHEN transaction_type IN ('charge','withdrawal') THEN -amount
       END) as calculated_balance
FROM ad_wallet_transactions
WHERE status = 'completed'
GROUP BY provider_id;
```

### Problem: "Razorpay payment not processing"
```bash
# Check:
# 1. RAZORPAY_KEY_ID in .env
# 2. RAZORPAY_KEY_SECRET in .env
# 3. Webhook URL in Razorpay dashboard: /api/payment-callback-razorpay.php
# 4. Server error logs
```

### Problem: "Ads not showing for user region"
```php
// Check:
$location = akkuAdGetUserLocationAndLanguage($pdo, $userId);
var_dump($location); // Should show TamilNadu, ta, etc

// Verify ad targeting
SELECT * FROM advertisements WHERE id = 'ad-123';
// Check target_regions, target_languages JSON fields
```

---

## ✅ Deployment Checklist

- [ ] Database schema created (9 tables)
- [ ] Sample pricing tiers inserted
- [ ] Razorpay credentials configured
- [ ] API endpoints accessible
- [ ] JavaScript files included
- [ ] Tracking working
- [ ] Payment webhook configured
- [ ] Tested with 10+ ads
- [ ] Tested with multiple regions
- [ ] Tested wallet operations
- [ ] Tested Razorpay (sandbox mode first)

---

## 📚 Related Documentation

- Detailed Setup: `/database/migrations/DATABASE_SETUP_GUIDE.md`
- Full Implementation: `/IMPLEMENTATION_SUMMARY.md`
- Database Schema: `/database/migrations/RequiredDatabase-db-fix.sql`

---

**Last Updated**: 2026-05-22  
**Version**: 1.0  
**Status**: Ready for Production ✅
