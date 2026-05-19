# AkkuApps Project Status

## சுருக்கம்
இந்த project `akkuapps.in` website-ஐ ஒரு social/community platform + computer sales/service marketplace ஆக மாற்றும் வேலைக்கான current working summary ஆகும்.

இந்த repository-ல் தற்போது:
- public website
- user dashboard
- admin dashboard
- news/blog
- services
- marketplace
- chatbot integration links
என்பன உள்ளன.

## தற்போதைய முக்கிய modules

### 1. Core site
- `index.php`
- `public-index.php`
- `includes/config.php`
- `includes/functions.php`

இவை login routing, public homepage, DB connection, session/auth helpers போன்றவற்றை handle செய்கின்றன.

### 2. User side
- `user/dashboard.php`
- `components/header.php`
- `components/sidebar.php`
- `marketplace/index.php`
- `marketplace/product.php`
- `marketplace/sell.php`

User side-ல்:
- marketplace catalog browse
- product detail view
- cart / order request flow
- chatbot link access
இப்போது integrate செய்யப்பட்டுள்ளது.

### 3. Admin side
- `admin/dashboard.php`
- `admin/marketplace.php`
- `admin/marketplace-categories.php`
- `components/admin-header.php`
- `components/admin-sidebar.php`

Admin side-ல்:
- marketplace control center
- products
- brands & categories
- customers
- carts / orders
- invoices / payments
- stock
- service tickets
என்பன section-wise அமைக்கப்பட்டுள்ளன.

### 4. Marketplace backend logic
- `includes/marketplace.php`

இந்த file-ல் almost எல்லா marketplace business logic-மும் உள்ளது:
- brands
- categories
- customers
- carts
- cart items
- products
- product images
- purchase entries
- stock movements
- invoice creation
- payment recording
- service ticket creation

### 5. DB repair / seed support
- `marketplace-db-fix.sql`

இந்த SQL file:
- imported integer-ID tables-க்கு `AUTO_INCREMENT` sync செய்வதற்கும்
- basic brand/category seed செய்வதற்கும்
உருவாக்கப்பட்டது.

## Database schema பற்றி கண்டறிந்தவை

Reference files:
- `akkuapps_maui (11).txt`
- `DBStructure.txt`

### Important `cs_*` tables
- `cs_brands`
- `cs_categories`
- `cs_customers`
- `cs_carts`
- `cs_cart_items`
- `cs_products`
- `cs_product_images`
- `cs_inventory_movements`
- `cs_invoices`
- `cs_invoice_items`
- `cs_payments`
- `cs_service_tickets`
- `cs_vw_product_stock`

### Export-ல் ஏற்கனவே data இருந்தது
- `cs_brands`-ல் HP, Dell, Lenovo போன்ற entries இருந்தன
- `cs_categories`-ல் Laptops, Desktops, Components போன்ற entries இருந்தன
- `cs_products`-ல் sample products இருந்தன

அதனால் problem “schema missing” இல்லை.  
Problem mainly:
- imported DB behavior
- auto increment mismatch
- unique constraint conflicts
- collation mismatch
இவற்றால் வந்தது.

## இதுவரை சரி செய்யப்பட்ட பிரச்சனைகள்

### 1. Marketplace customer auto-create bug
Problem:
- every user-க்கும் `N/A` phone வைத்து customer create ஆகி duplicate key error வந்தது

Fix:
- unique placeholder phone generation
- existing email/user reuse logic

### 2. Integer ID insert issues
Problem:
- imported tables auto increment சரியாக வேலை செய்யாமல் insert failures வந்தன

Fix:
- PHP side-ல் next numeric ID manually generate செய்யும் logic சேர்க்கப்பட்டது

Applied for:
- customers
- carts
- cart items
- products
- product images
- stock movements
- invoices
- invoice items
- payments
- service tickets
- brands
- categories

### 3. Admin marketplace collation-related load issue
Problem:
- `Illegal mix of collations` error காரணமாக full page data load fail ஆனது

Fix:
- `admin/marketplace.php` இப்போது section-wise data மட்டும் load செய்கிறது
- admin page-ல் `cs_vw_product_stock` மீது dependency குறைக்கப்பட்டது

### 4. Duplicate brand/category save experience
Problem:
- duplicate brand/category save செய்யும் போது raw SQL error காட்டியது

Fix:
- user-friendly validation/error message சேர்க்கப்பட்டது

## இப்போது project-ல் likely working features

### Public / user
- chatbot links visible
- marketplace catalog page available
- product details page available
- cart page available

### Admin
- marketplace masters section
- product creation section
- orders section
- invoices section
- stock section
- services section

## இன்னும் verify செய்ய வேண்டியவை

இந்த workflow முழுவதையும் live server-ல் end-to-end verify செய்ய வேண்டும்:

1. Brand create
2. Category create
3. Product create
4. User add to cart
5. Admin convert cart to invoice
6. Admin record payment
7. Stock reduction confirm

## Open risks / pending issues

### 1. Live DB collation normalization
சில tables / views இன்னும் `utf8mb4_general_ci` மற்றும் `utf8mb4_unicode_ci` mixed state-ல் இருக்கலாம்.

Future cleanup:
- full database collation audit
- all relevant text columns same collation-க்கு migrate செய்ய வேண்டும்

### 2. `cs_vw_product_stock` view compatibility
Admin page-ல் fallback logic போடப்பட்டாலும், DB view clean-up இன்னும் செய்யலாம்.

### 3. Product image workflow
இப்போது primary image URL manual entry mode-ல் உள்ளது.
Future:
- image upload
- gallery management
- thumbnail automation

### 4. Vendor / supplier architecture
Current status:
- vendor portal இல்லை
- vendor data free-text / manual notes மாதிரி உள்ளது

Future:
- `cs_vendors`
- `cs_purchase_orders`
- purchase invoice tracking
- supplier payable tracking

### 5. Credit / debit note architecture
Current:
- stock adjustment / invoice adjustment style workaround

Future:
- separate `credit_notes`
- separate `debit_notes`
- accounting-friendly ledger links

### 6. Customer-facing checkout
Current:
- cart exists
- admin converts cart to invoice

Future:
- self-checkout
- order history
- payment gateway
- shipment / delivery status

## Business features already planned / needed

### Marketplace
- structured category hierarchy
- brand mapping
- product spec templates
- inventory tracking
- low stock alerts
- featured products

### Sales / billing
- invoice generation
- payment collection
- due tracking
- partial payment
- warranty period

### Service center
- repair tickets
- diagnosis
- technician tracking
- ready / delivered lifecycle

### CRM / customer management
- customer master
- linked users
- phone/email history
- billing/shipping address
- corporate / dealer support

## Recommended next discussion topics

### Phase 1
- confirm masters page and product creation are fully working
- verify end-to-end order workflow

### Phase 2
- define exact taxonomy for:
  - brands
  - parent categories
  - subcategories
  - specification templates

### Phase 3
- vendor management design
- purchase order architecture
- goods inward / stock valuation logic

### Phase 4
- customer checkout and order history
- payment gateway integration

### Phase 5
- reporting dashboard
- GST / invoice print format
- sales summary / profit summary / service summary

## Important files for future work
- `D:\Projects\akkuapps2026\07\includes\marketplace.php`
- `D:\Projects\akkuapps2026\07\admin\marketplace.php`
- `D:\Projects\akkuapps2026\07\marketplace\index.php`
- `D:\Projects\akkuapps2026\07\marketplace\product.php`
- `D:\Projects\akkuapps2026\07\marketplace\sell.php`
- `D:\Projects\akkuapps2026\07\marketplace-db-fix.sql`
- `C:\Users\ELCOT\Downloads\akkuapps_maui (11).txt`

## தற்போதைய practical status

Project completely fresh-start இல்லை.  
Already:
- schema உள்ளது
- sample masters உள்ளது
- sample products உள்ளது
- dashboards உள்ளது
- marketplace UI உள்ளது

அதனால் next step என்பது “build from zero” இல்லை.  
Correct next step:
- stabilize
- verify workflow
- normalize DB
- improve taxonomy
- complete commerce architecture

## Discussion-ready conclusion

இந்த project தற்போது:
- social platform + commerce platform hybrid நிலையில் உள்ளது
- base architecture உள்ளது
- marketplace backend structure உள்ளது
- DB imported data உள்ளது
- சில integration / schema consistency பிரச்சனைகள் gradual ஆக fix செய்யப்பட்டுள்ளன

இப்போது focus பண்ண வேண்டியது:
- end-to-end workflow validation
- DB consistency
- product taxonomy finalization
- vendor/purchase/client/order architecture completion
