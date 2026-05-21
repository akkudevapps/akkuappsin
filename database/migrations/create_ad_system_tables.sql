-- AkkuApps Ad System Database Schema
-- Created for comprehensive ad management with providers, pricing, and geographic targeting

-- 1. Ad Providers Table (Who gives ads)
CREATE TABLE IF NOT EXISTS ad_providers (
    id VARCHAR(36) PRIMARY KEY COMMENT 'UUID',
    user_id VARCHAR(36) NOT NULL UNIQUE COMMENT 'Reference to users table',
    company_name VARCHAR(255) NOT NULL COMMENT 'Business/Company name',
    company_logo_url VARCHAR(500) COMMENT 'Company logo URL',
    email VARCHAR(255) NOT NULL UNIQUE COMMENT 'Contact email',
    phone VARCHAR(20) COMMENT 'Contact phone',
    country VARCHAR(2) COMMENT 'ISO country code (US, IN, UK, etc)',
    state_province VARCHAR(100) COMMENT 'State/Province',
    city VARCHAR(100) COMMENT 'City',
    postal_code VARCHAR(20) COMMENT 'Postal code',
    website VARCHAR(500) COMMENT 'Company website',
    tax_id VARCHAR(50) UNIQUE COMMENT 'GST/Tax ID',
    bank_account_holder VARCHAR(255) COMMENT 'Bank account holder name',
    bank_account_number VARCHAR(50) COMMENT 'Bank account number (encrypted)',
    bank_ifsc_code VARCHAR(20) COMMENT 'IFSC code',
    bank_name VARCHAR(100) COMMENT 'Bank name',
    upi_id VARCHAR(100) COMMENT 'UPI ID for payments (optional)',
    status ENUM('pending', 'approved', 'suspended', 'rejected') DEFAULT 'pending' COMMENT 'Provider status',
    approval_notes TEXT COMMENT 'Admin approval/rejection notes',
    verified_at TIMESTAMP NULL COMMENT 'When verified by admin',
    approved_by VARCHAR(36) COMMENT 'Admin user ID who approved',
    total_ads_created INT DEFAULT 0 COMMENT 'Total ads created by provider',
    active_ads_count INT DEFAULT 0 COMMENT 'Currently active ads',
    wallet_balance DECIMAL(12,2) DEFAULT 0 COMMENT 'Wallet credit balance',
    currency VARCHAR(3) DEFAULT 'USD' COMMENT 'Preferred currency',
    preferred_language VARCHAR(10) DEFAULT 'en' COMMENT 'Preferred language',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_user_id (user_id),
    KEY idx_status (status),
    KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Ad Pricing Tiers Table (What sizes and prices)
CREATE TABLE IF NOT EXISTS ad_pricing_tiers (
    id VARCHAR(36) PRIMARY KEY COMMENT 'UUID',
    size_type VARCHAR(50) NOT NULL UNIQUE COMMENT 'Banner, Square, Leaderboard, etc',
    display_name VARCHAR(100) NOT NULL COMMENT 'Display name (e.g., "Banner 728x90")',
    width_px INT COMMENT 'Width in pixels',
    height_px INT COMMENT 'Height in pixels',
    description TEXT COMMENT 'Description and recommendations',
    base_price_monthly DECIMAL(10,2) NOT NULL COMMENT 'Monthly fixed price',
    price_cpm DECIMAL(10,2) COMMENT 'Cost Per Mille (1000 impressions)',
    price_cpc DECIMAL(10,2) COMMENT 'Cost Per Click',
    currency VARCHAR(3) DEFAULT 'USD' COMMENT 'Currency',
    is_active BOOLEAN DEFAULT 1 COMMENT 'Is this tier available?',
    min_duration_days INT DEFAULT 1 COMMENT 'Minimum campaign duration',
    max_ads_per_tier INT DEFAULT 5 COMMENT 'Max ads allowed per provider for this tier',
    image_format_allowed VARCHAR(255) DEFAULT 'jpg,jpeg,png,gif,webp' COMMENT 'Allowed formats',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Advertisements Table (The actual ads)
CREATE TABLE IF NOT EXISTS advertisements (
    id VARCHAR(36) PRIMARY KEY COMMENT 'UUID',
    provider_id VARCHAR(36) NOT NULL COMMENT 'FK to ad_providers',
    campaign_id VARCHAR(36) COMMENT 'FK to ad_campaigns (if part of campaign)',
    title VARCHAR(255) NOT NULL COMMENT 'Ad title/name',
    description TEXT COMMENT 'Ad description',
    ad_size_id VARCHAR(36) NOT NULL COMMENT 'FK to ad_pricing_tiers',
    ad_type ENUM('image', 'text', 'video', 'custom') DEFAULT 'image' COMMENT 'Ad type',
    category VARCHAR(100) COMMENT 'Content category (tech, deals, guides, general)',
    image_url VARCHAR(500) COMMENT 'Ad image/media URL',
    video_url VARCHAR(500) COMMENT 'Video URL (if video type)',
    click_url VARCHAR(500) COMMENT 'Where ad links to',
    alt_text VARCHAR(255) COMMENT 'Alt text for images',
    custom_html LONGTEXT COMMENT 'Custom HTML (if custom type)',
    
    -- Targeting
    target_regions JSON COMMENT '{"regions": ["TamilNadu", "Karnataka", "Kerala"], "cities": ["Chennai", "Bangalore"]}',
    target_languages JSON COMMENT '{"languages": ["ta", "en", "ka", "ml"]}',
    target_countries JSON COMMENT '{"countries": ["IN", "US", "UK"]}',
    target_demographics JSON COMMENT '{"age_min": 18, "age_max": 65, "gender": "all"}',
    
    -- Dates & Budget
    start_date DATE COMMENT 'Campaign start date',
    end_date DATE COMMENT 'Campaign end date',
    daily_budget DECIMAL(10,2) COMMENT 'Daily budget limit',
    total_budget DECIMAL(12,2) COMMENT 'Total budget allocated',
    budget_spent DECIMAL(12,2) DEFAULT 0 COMMENT 'Amount spent so far',
    pricing_model ENUM('fixed', 'cpm', 'cpc') DEFAULT 'fixed' COMMENT 'How provider is charged',
    
    -- Status
    status ENUM('draft', 'pending_approval', 'approved', 'active', 'paused', 'completed', 'archived') DEFAULT 'draft' COMMENT 'Ad status',
    approval_notes TEXT COMMENT 'Admin approval notes',
    approved_by VARCHAR(36) COMMENT 'Admin user ID',
    approved_at TIMESTAMP NULL COMMENT 'When approved',
    
    -- Variations (A/B Testing)
    is_primary BOOLEAN DEFAULT 1 COMMENT 'Is this primary ad in variations?',
    variation_group_id VARCHAR(36) COMMENT 'Group ID for A/B testing variations',
    variation_label VARCHAR(100) COMMENT 'Label for variation (e.g., "Variant A", "Variant B")',
    
    -- Performance
    impressions INT DEFAULT 0 COMMENT 'Total impressions',
    clicks INT DEFAULT 0 COMMENT 'Total clicks',
    conversions INT DEFAULT 0 COMMENT 'Total conversions',
    ctr DECIMAL(5,2) DEFAULT 0 COMMENT 'Click-through rate %',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_provider_id (provider_id),
    KEY idx_status (status),
    KEY idx_campaign_id (campaign_id),
    KEY idx_variation_group_id (variation_group_id),
    KEY idx_start_date (start_date),
    KEY idx_end_date (end_date),
    FOREIGN KEY (provider_id) REFERENCES ad_providers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Ad Placements Table (Where ads appear)
CREATE TABLE IF NOT EXISTS ad_placements (
    id VARCHAR(36) PRIMARY KEY COMMENT 'UUID',
    ad_id VARCHAR(36) NOT NULL COMMENT 'FK to advertisements',
    page VARCHAR(100) NOT NULL COMMENT 'Page location (news_index, article_page, dashboard, etc)',
    position VARCHAR(100) NOT NULL COMMENT 'Position on page (hero, sidebar_top, sidebar_mid, etc)',
    is_active BOOLEAN DEFAULT 1 COMMENT 'Is this placement active?',
    priority INT DEFAULT 1 COMMENT 'Priority for rotation (1-10)',
    impression_count INT DEFAULT 0 COMMENT 'Impressions for this placement',
    click_count INT DEFAULT 0 COMMENT 'Clicks for this placement',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_ad_id (ad_id),
    KEY idx_page_position (page, position),
    KEY idx_is_active (is_active),
    FOREIGN KEY (ad_id) REFERENCES advertisements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Ad Analytics Table (Real-time tracking)
CREATE TABLE IF NOT EXISTS ad_analytics (
    id VARCHAR(36) PRIMARY KEY COMMENT 'UUID',
    ad_id VARCHAR(36) NOT NULL COMMENT 'FK to advertisements',
    analytics_date DATE NOT NULL COMMENT 'Date of analytics',
    impressions INT DEFAULT 0 COMMENT 'Daily impressions',
    clicks INT DEFAULT 0 COMMENT 'Daily clicks',
    conversions INT DEFAULT 0 COMMENT 'Daily conversions',
    spend_today DECIMAL(10,2) DEFAULT 0 COMMENT 'Cost for today',
    ctr DECIMAL(5,2) DEFAULT 0 COMMENT 'Click-through rate %',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_ad_date (ad_id, analytics_date),
    KEY idx_ad_id (ad_id),
    KEY idx_analytics_date (analytics_date),
    FOREIGN KEY (ad_id) REFERENCES advertisements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Ad Transactions Table (Payment tracking)
CREATE TABLE IF NOT EXISTS ad_transactions (
    id VARCHAR(36) PRIMARY KEY COMMENT 'UUID',
    provider_id VARCHAR(36) NOT NULL COMMENT 'FK to ad_providers',
    ad_id VARCHAR(36) COMMENT 'FK to advertisements',
    amount DECIMAL(12,2) NOT NULL COMMENT 'Transaction amount',
    currency VARCHAR(3) DEFAULT 'USD' COMMENT 'Transaction currency',
    pricing_model ENUM('fixed', 'cpm', 'cpc') DEFAULT 'fixed' COMMENT 'Pricing model used',
    transaction_type ENUM('charge', 'refund', 'adjustment') DEFAULT 'charge' COMMENT 'Type of transaction',
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending' COMMENT 'Payment status',
    payment_method ENUM('bank_transfer', 'razorpay', 'stripe', 'wallet', 'manual') DEFAULT 'wallet' COMMENT 'Payment method',
    external_transaction_id VARCHAR(100) COMMENT 'Razorpay/Stripe transaction ID',
    invoice_number VARCHAR(50) UNIQUE COMMENT 'Invoice number for reference',
    invoice_date DATE COMMENT 'Invoice date',
    due_date DATE COMMENT 'Payment due date',
    paid_date TIMESTAMP NULL COMMENT 'When payment was completed',
    description VARCHAR(500) COMMENT 'Transaction description',
    notes TEXT COMMENT 'Admin notes',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_provider_id (provider_id),
    KEY idx_ad_id (ad_id),
    KEY idx_status (status),
    KEY idx_created_at (created_at),
    FOREIGN KEY (provider_id) REFERENCES ad_providers(id) ON DELETE CASCADE,
    FOREIGN KEY (ad_id) REFERENCES advertisements(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Ad Wallet/Credits Table (Prepaid balance)
CREATE TABLE IF NOT EXISTS ad_wallet_transactions (
    id VARCHAR(36) PRIMARY KEY COMMENT 'UUID',
    provider_id VARCHAR(36) NOT NULL COMMENT 'FK to ad_providers',
    amount DECIMAL(12,2) NOT NULL COMMENT 'Transaction amount',
    currency VARCHAR(3) DEFAULT 'USD' COMMENT 'Currency',
    transaction_type ENUM('deposit', 'withdrawal', 'charge', 'refund') DEFAULT 'deposit' COMMENT 'Type of transaction',
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending' COMMENT 'Transaction status',
    payment_method ENUM('razorpay', 'stripe', 'bank_transfer', 'manual') DEFAULT 'razorpay' COMMENT 'Payment method',
    payment_gateway_id VARCHAR(100) COMMENT 'External gateway transaction ID',
    description VARCHAR(500) COMMENT 'Transaction description',
    balance_before DECIMAL(12,2) COMMENT 'Balance before transaction',
    balance_after DECIMAL(12,2) COMMENT 'Balance after transaction',
    related_ad_id VARCHAR(36) COMMENT 'Related ad if charge type',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_provider_id (provider_id),
    KEY idx_created_at (created_at),
    KEY idx_status (status),
    FOREIGN KEY (provider_id) REFERENCES ad_providers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Ad Campaigns Table (Group multiple ads)
CREATE TABLE IF NOT EXISTS ad_campaigns (
    id VARCHAR(36) PRIMARY KEY COMMENT 'UUID',
    provider_id VARCHAR(36) NOT NULL COMMENT 'FK to ad_providers',
    campaign_name VARCHAR(255) NOT NULL COMMENT 'Campaign name',
    description TEXT COMMENT 'Campaign description',
    status ENUM('draft', 'active', 'paused', 'completed', 'archived') DEFAULT 'draft' COMMENT 'Campaign status',
    budget_allocated DECIMAL(12,2) COMMENT 'Total budget for campaign',
    budget_spent DECIMAL(12,2) DEFAULT 0 COMMENT 'Budget spent so far',
    start_date DATE COMMENT 'Campaign start date',
    end_date DATE COMMENT 'Campaign end date',
    total_ads INT DEFAULT 0 COMMENT 'Number of ads in campaign',
    performance_metrics JSON COMMENT '{"impressions": 0, "clicks": 0, "conversions": 0, "ctr": 0}',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_provider_id (provider_id),
    KEY idx_status (status),
    FOREIGN KEY (provider_id) REFERENCES ad_providers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Ad Tickets Table (Digital goods for buying ad space)
CREATE TABLE IF NOT EXISTS ad_tickets (
    id VARCHAR(36) PRIMARY KEY COMMENT 'UUID',
    ticket_name VARCHAR(255) NOT NULL COMMENT 'Ticket name (e.g., "Banner 728x90 - 30 days")',
    description TEXT COMMENT 'Ticket description',
    pricing_tier_id VARCHAR(36) COMMENT 'FK to ad_pricing_tiers',
    duration_days INT DEFAULT 30 COMMENT 'How many days of ad space',
    price DECIMAL(10,2) NOT NULL COMMENT 'Price of ticket',
    currency VARCHAR(3) DEFAULT 'USD' COMMENT 'Currency',
    quantity_available INT DEFAULT -1 COMMENT '-1 for unlimited',
    quantity_sold INT DEFAULT 0 COMMENT 'Quantity sold',
    status ENUM('active', 'inactive', 'sold_out') DEFAULT 'active' COMMENT 'Ticket status',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_status (status),
    KEY idx_pricing_tier_id (pricing_tier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Indexes for common queries
CREATE INDEX idx_ad_provider_status ON advertisements(provider_id, status);
CREATE INDEX idx_ad_dates ON advertisements(start_date, end_date);
CREATE INDEX idx_analytics_ad_date ON ad_analytics(ad_id, analytics_date);
CREATE INDEX idx_provider_active_ads ON ad_providers(id, status);
CREATE INDEX idx_placement_active ON ad_placements(is_active, page, position);
