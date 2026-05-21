-- ============================================================================
-- AkkuApps Ad System Database Schema
-- Database Updates & Migration Script
-- Version: 1.0
-- Created: 2026-05-22
-- Description: Complete ad system with providers, pricing, payments, analytics
-- ============================================================================

-- Set SQL mode for compatibility
SET SQL_MODE = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- TABLE 1: Ad Providers (Who gives/pays for ads)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `ad_providers` (
  `id` varchar(36) NOT NULL COMMENT 'UUID',
  `user_id` varchar(36) NOT NULL UNIQUE COMMENT 'Reference to users table',
  `company_name` varchar(255) NOT NULL COMMENT 'Business/Company name',
  `company_logo_url` varchar(500) COMMENT 'Company logo URL',
  `email` varchar(255) NOT NULL UNIQUE COMMENT 'Contact email',
  `phone` varchar(20) COMMENT 'Contact phone',
  `country` varchar(2) COMMENT 'ISO country code (US, IN, UK, etc)',
  `state_province` varchar(100) COMMENT 'State/Province',
  `city` varchar(100) COMMENT 'City',
  `postal_code` varchar(20) COMMENT 'Postal code',
  `website` varchar(500) COMMENT 'Company website',
  `tax_id` varchar(50) UNIQUE COMMENT 'GST/Tax ID',
  `bank_account_holder` varchar(255) COMMENT 'Bank account holder name',
  `bank_account_number` varchar(50) COMMENT 'Bank account number (encrypted)',
  `bank_ifsc_code` varchar(20) COMMENT 'IFSC code',
  `bank_name` varchar(100) COMMENT 'Bank name',
  `upi_id` varchar(100) COMMENT 'UPI ID for payments (optional)',
  `status` enum('pending','approved','suspended','rejected') DEFAULT 'pending' COMMENT 'Provider status',
  `approval_notes` text COMMENT 'Admin approval/rejection notes',
  `verified_at` timestamp NULL COMMENT 'When verified by admin',
  `approved_by` varchar(36) COMMENT 'Admin user ID who approved',
  `total_ads_created` int DEFAULT 0 COMMENT 'Total ads created by provider',
  `active_ads_count` int DEFAULT 0 COMMENT 'Currently active ads',
  `wallet_balance` decimal(12,2) DEFAULT 0 COMMENT 'Wallet credit balance',
  `currency` varchar(3) DEFAULT 'USD' COMMENT 'Preferred currency',
  `preferred_language` varchar(10) DEFAULT 'en' COMMENT 'Preferred language',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_id` (`user_id`),
  UNIQUE KEY `unique_tax_id` (`tax_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ad provider accounts and information';

-- ============================================================================
-- TABLE 2: Ad Pricing Tiers (What sizes and prices)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `ad_pricing_tiers` (
  `id` varchar(36) NOT NULL COMMENT 'UUID',
  `size_type` varchar(50) NOT NULL UNIQUE COMMENT 'Banner, Square, Leaderboard, etc',
  `display_name` varchar(100) NOT NULL COMMENT 'Display name (e.g., "Banner 728x90")',
  `width_px` int COMMENT 'Width in pixels',
  `height_px` int COMMENT 'Height in pixels',
  `description` text COMMENT 'Description and recommendations',
  `base_price_monthly` decimal(10,2) NOT NULL COMMENT 'Monthly fixed price',
  `price_cpm` decimal(10,2) COMMENT 'Cost Per Mille (1000 impressions)',
  `price_cpc` decimal(10,2) COMMENT 'Cost Per Click',
  `currency` varchar(3) DEFAULT 'USD' COMMENT 'Currency',
  `is_active` boolean DEFAULT 1 COMMENT 'Is this tier available?',
  `min_duration_days` int DEFAULT 1 COMMENT 'Minimum campaign duration',
  `max_ads_per_tier` int DEFAULT 5 COMMENT 'Max ads allowed per provider for this tier',
  `image_format_allowed` varchar(255) DEFAULT 'jpg,jpeg,png,gif,webp' COMMENT 'Allowed formats',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_size_type` (`size_type`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ad size pricing tiers';

-- ============================================================================
-- TABLE 3: Advertisements (The actual ads)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `advertisements` (
  `id` varchar(36) NOT NULL COMMENT 'UUID',
  `provider_id` varchar(36) NOT NULL COMMENT 'FK to ad_providers',
  `campaign_id` varchar(36) COMMENT 'FK to ad_campaigns (if part of campaign)',
  `title` varchar(255) NOT NULL COMMENT 'Ad title/name',
  `description` text COMMENT 'Ad description',
  `ad_size_id` varchar(36) NOT NULL COMMENT 'FK to ad_pricing_tiers',
  `ad_type` enum('image','text','video','custom') DEFAULT 'image' COMMENT 'Ad type',
  `category` varchar(100) COMMENT 'Content category (tech, deals, guides, general)',
  `image_url` varchar(500) COMMENT 'Ad image/media URL',
  `video_url` varchar(500) COMMENT 'Video URL (if video type)',
  `click_url` varchar(500) COMMENT 'Where ad links to',
  `alt_text` varchar(255) COMMENT 'Alt text for images',
  `custom_html` longtext COMMENT 'Custom HTML (if custom type)',
  
  `target_regions` json COMMENT '{"regions": ["TamilNadu", "Karnataka", "Kerala"], "cities": ["Chennai", "Bangalore"]}',
  `target_languages` json COMMENT '{"languages": ["ta", "en", "ka", "ml"]}',
  `target_countries` json COMMENT '{"countries": ["IN", "US", "UK"]}',
  `target_demographics` json COMMENT '{"age_min": 18, "age_max": 65, "gender": "all"}',
  
  `start_date` date COMMENT 'Campaign start date',
  `end_date` date COMMENT 'Campaign end date',
  `daily_budget` decimal(10,2) COMMENT 'Daily budget limit',
  `total_budget` decimal(12,2) COMMENT 'Total budget allocated',
  `budget_spent` decimal(12,2) DEFAULT 0 COMMENT 'Amount spent so far',
  `pricing_model` enum('fixed','cpm','cpc') DEFAULT 'fixed' COMMENT 'How provider is charged',
  
  `status` enum('draft','pending_approval','approved','active','paused','completed','archived') DEFAULT 'draft' COMMENT 'Ad status',
  `approval_notes` text COMMENT 'Admin approval notes',
  `approved_by` varchar(36) COMMENT 'Admin user ID',
  `approved_at` timestamp NULL COMMENT 'When approved',
  
  `is_primary` boolean DEFAULT 1 COMMENT 'Is this primary ad in variations?',
  `variation_group_id` varchar(36) COMMENT 'Group ID for A/B testing variations',
  `variation_label` varchar(100) COMMENT 'Label for variation (e.g., "Variant A", "Variant B")',
  
  `impressions` int DEFAULT 0 COMMENT 'Total impressions',
  `clicks` int DEFAULT 0 COMMENT 'Total clicks',
  `conversions` int DEFAULT 0 COMMENT 'Total conversions',
  `ctr` decimal(5,2) DEFAULT 0 COMMENT 'Click-through rate %',
  
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_provider_id` (`provider_id`),
  KEY `idx_status` (`status`),
  KEY `idx_campaign_id` (`campaign_id`),
  KEY `idx_variation_group_id` (`variation_group_id`),
  KEY `idx_start_date` (`start_date`),
  KEY `idx_end_date` (`end_date`),
  KEY `idx_ad_provider_status` (`provider_id`, `status`),
  KEY `idx_ad_dates` (`start_date`, `end_date`),
  CONSTRAINT `fk_ad_provider` FOREIGN KEY (`provider_id`) REFERENCES `ad_providers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Individual advertisements';

-- ============================================================================
-- TABLE 4: Ad Placements (Where ads appear)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `ad_placements` (
  `id` varchar(36) NOT NULL COMMENT 'UUID',
  `ad_id` varchar(36) NOT NULL COMMENT 'FK to advertisements',
  `page` varchar(100) NOT NULL COMMENT 'Page location (news_index, article_page, dashboard, etc)',
  `position` varchar(100) NOT NULL COMMENT 'Position on page (hero, sidebar_top, sidebar_mid, etc)',
  `is_active` boolean DEFAULT 1 COMMENT 'Is this placement active?',
  `priority` int DEFAULT 1 COMMENT 'Priority for rotation (1-10)',
  `impression_count` int DEFAULT 0 COMMENT 'Impressions for this placement',
  `click_count` int DEFAULT 0 COMMENT 'Clicks for this placement',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ad_id` (`ad_id`),
  KEY `idx_page_position` (`page`, `position`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_placement_active` (`is_active`, `page`, `position`),
  CONSTRAINT `fk_placement_ad` FOREIGN KEY (`ad_id`) REFERENCES `advertisements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ad placement locations';

-- ============================================================================
-- TABLE 5: Ad Analytics (Real-time tracking)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `ad_analytics` (
  `id` varchar(36) NOT NULL COMMENT 'UUID',
  `ad_id` varchar(36) NOT NULL COMMENT 'FK to advertisements',
  `analytics_date` date NOT NULL COMMENT 'Date of analytics',
  `impressions` int DEFAULT 0 COMMENT 'Daily impressions',
  `clicks` int DEFAULT 0 COMMENT 'Daily clicks',
  `conversions` int DEFAULT 0 COMMENT 'Daily conversions',
  `spend_today` decimal(10,2) DEFAULT 0 COMMENT 'Cost for today',
  `ctr` decimal(5,2) DEFAULT 0 COMMENT 'Click-through rate %',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_ad_date` (`ad_id`, `analytics_date`),
  KEY `idx_ad_id` (`ad_id`),
  KEY `idx_analytics_date` (`analytics_date`),
  KEY `idx_analytics_ad_date` (`ad_id`, `analytics_date`),
  CONSTRAINT `fk_analytics_ad` FOREIGN KEY (`ad_id`) REFERENCES `advertisements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Daily ad analytics';

-- ============================================================================
-- TABLE 6: Ad Transactions (Payment tracking)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `ad_transactions` (
  `id` varchar(36) NOT NULL COMMENT 'UUID',
  `provider_id` varchar(36) NOT NULL COMMENT 'FK to ad_providers',
  `ad_id` varchar(36) COMMENT 'FK to advertisements',
  `amount` decimal(12,2) NOT NULL COMMENT 'Transaction amount',
  `currency` varchar(3) DEFAULT 'USD' COMMENT 'Transaction currency',
  `pricing_model` enum('fixed','cpm','cpc') DEFAULT 'fixed' COMMENT 'Pricing model used',
  `transaction_type` enum('charge','refund','adjustment') DEFAULT 'charge' COMMENT 'Type of transaction',
  `status` enum('pending','completed','failed','refunded') DEFAULT 'pending' COMMENT 'Payment status',
  `payment_method` enum('bank_transfer','razorpay','stripe','wallet','manual') DEFAULT 'wallet' COMMENT 'Payment method',
  `external_transaction_id` varchar(100) COMMENT 'Razorpay/Stripe transaction ID',
  `invoice_number` varchar(50) UNIQUE COMMENT 'Invoice number for reference',
  `invoice_date` date COMMENT 'Invoice date',
  `due_date` date COMMENT 'Payment due date',
  `paid_date` timestamp NULL COMMENT 'When payment was completed',
  `description` varchar(500) COMMENT 'Transaction description',
  `notes` text COMMENT 'Admin notes',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_invoice` (`invoice_number`),
  KEY `idx_provider_id` (`provider_id`),
  KEY `idx_ad_id` (`ad_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_transaction_provider` FOREIGN KEY (`provider_id`) REFERENCES `ad_providers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_transaction_ad` FOREIGN KEY (`ad_id`) REFERENCES `advertisements` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ad payment transactions';

-- ============================================================================
-- TABLE 7: Ad Wallet/Credits (Prepaid balance)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `ad_wallet_transactions` (
  `id` varchar(36) NOT NULL COMMENT 'UUID',
  `provider_id` varchar(36) NOT NULL COMMENT 'FK to ad_providers',
  `amount` decimal(12,2) NOT NULL COMMENT 'Transaction amount',
  `currency` varchar(3) DEFAULT 'USD' COMMENT 'Currency',
  `transaction_type` enum('deposit','withdrawal','charge','refund') DEFAULT 'deposit' COMMENT 'Type of transaction',
  `status` enum('pending','completed','failed') DEFAULT 'pending' COMMENT 'Transaction status',
  `payment_method` enum('razorpay','stripe','bank_transfer','manual') DEFAULT 'razorpay' COMMENT 'Payment method',
  `payment_gateway_id` varchar(100) COMMENT 'External gateway transaction ID',
  `description` varchar(500) COMMENT 'Transaction description',
  `balance_before` decimal(12,2) COMMENT 'Balance before transaction',
  `balance_after` decimal(12,2) COMMENT 'Balance after transaction',
  `related_ad_id` varchar(36) COMMENT 'Related ad if charge type',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_provider_id` (`provider_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_wallet_provider` FOREIGN KEY (`provider_id`) REFERENCES `ad_providers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ad wallet/credit transactions';

-- ============================================================================
-- TABLE 8: Ad Campaigns (Group multiple ads)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `ad_campaigns` (
  `id` varchar(36) NOT NULL COMMENT 'UUID',
  `provider_id` varchar(36) NOT NULL COMMENT 'FK to ad_providers',
  `campaign_name` varchar(255) NOT NULL COMMENT 'Campaign name',
  `description` text COMMENT 'Campaign description',
  `status` enum('draft','active','paused','completed','archived') DEFAULT 'draft' COMMENT 'Campaign status',
  `budget_allocated` decimal(12,2) COMMENT 'Total budget for campaign',
  `budget_spent` decimal(12,2) DEFAULT 0 COMMENT 'Budget spent so far',
  `start_date` date COMMENT 'Campaign start date',
  `end_date` date COMMENT 'Campaign end date',
  `total_ads` int DEFAULT 0 COMMENT 'Number of ads in campaign',
  `performance_metrics` json COMMENT '{"impressions": 0, "clicks": 0, "conversions": 0, "ctr": 0}',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_provider_id` (`provider_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_campaign_provider` FOREIGN KEY (`provider_id`) REFERENCES `ad_providers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ad campaigns';

-- ============================================================================
-- TABLE 9: Ad Tickets (Digital goods for buying ad space)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `ad_tickets` (
  `id` varchar(36) NOT NULL COMMENT 'UUID',
  `ticket_name` varchar(255) NOT NULL COMMENT 'Ticket name (e.g., "Banner 728x90 - 30 days")',
  `description` text COMMENT 'Ticket description',
  `pricing_tier_id` varchar(36) COMMENT 'FK to ad_pricing_tiers',
  `duration_days` int DEFAULT 30 COMMENT 'How many days of ad space',
  `price` decimal(10,2) NOT NULL COMMENT 'Price of ticket',
  `currency` varchar(3) DEFAULT 'USD' COMMENT 'Currency',
  `quantity_available` int DEFAULT -1 COMMENT '-1 for unlimited',
  `quantity_sold` int DEFAULT 0 COMMENT 'Quantity sold',
  `status` enum('active','inactive','sold_out') DEFAULT 'active' COMMENT 'Ticket status',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_pricing_tier_id` (`pricing_tier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ad tickets (digital goods)';

-- ============================================================================
-- INDEXES FOR PERFORMANCE (Duplicate indexes removed)
-- ============================================================================
-- Already created in table definitions

-- ============================================================================
-- SAMPLE DATA (Optional - for initial setup)
-- ============================================================================

-- Insert sample pricing tiers
INSERT IGNORE INTO `ad_pricing_tiers` (`id`, `size_type`, `display_name`, `width_px`, `height_px`, `base_price_monthly`, `price_cpm`, `price_cpc`, `currency`, `description`) VALUES
('tier-banner-728', 'banner_728x90', 'Banner 728x90', 728, 90, 100.00, 5.00, 0.50, 'USD', 'Standard banner ad size'),
('tier-square-300', 'square_300x300', 'Square 300x300', 300, 300, 150.00, 8.00, 0.75, 'USD', 'Medium square ad'),
('tier-leaderboard', 'leaderboard_970x250', 'Leaderboard 970x250', 970, 250, 250.00, 12.00, 1.00, 'USD', 'Large leaderboard'),
('tier-skyscraper', 'skyscraper_160x600', 'Skyscraper 160x600', 160, 600, 120.00, 6.00, 0.60, 'USD', 'Wide skyscraper ad'),
('tier-custom', 'custom', 'Custom Size', NULL, NULL, 50.00, 3.00, 0.30, 'USD', 'Custom size ads');

-- ============================================================================
-- END OF DATABASE SCHEMA
-- ============================================================================
-- Execution Notes:
-- 1. Run this script with: mysql -u root -p akkuapps < RequiredDatabase-db-fix.sql
-- 2. Or paste into phpMyAdmin SQL tab
-- 3. Requires database "akkuapps" to exist
-- 4. Creates 9 new tables with proper relationships
-- 5. Includes sample pricing tiers for reference
-- 6. All tables use InnoDB engine for transaction support
-- 7. Foreign key constraints are enabled
-- ============================================================================
