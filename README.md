# AkkuApps – Social + Commerce + Service Platform

**AkkuApps** is a full‑stack web platform where users can:

- 💬 Post content and earn coins from likes/comments/views
- 🛒 Buy computer hardware online (laptops, desktops, components)
- 🔧 Raise PC repair service tickets with tracking
- 🎁 Send gifts, collect badges, and trade tokens (AKU, Diamond, Gold, Silver)
- 👑 Subscribe to creators for exclusive content
- 💰 Use a coin economy with UPI‑based purchases

Built with PHP/MySQL, featuring an admin dashboard for managing users, products, inventory, payments, and platform commissions.

📁 This repository contains the full source code (publicly accessible). See `INSTALL.md` for setup.

Project akkuapps.in – Platform Flow & Architecture Overview
🎯 Target & Purpose
AkkuApps is a hybrid web platform combining:

Content & Social – News, blogs, user posts with coin‑based engagement (likes/comments cost coins, reward creators)

E‑commerce – Computer hardware store (products, brands, categories, inventory, invoices)

Service Management – PC repair/service tickets

Gamified Economy – Tokens (AKU, Diamond, Gold, etc.), gifts, badges, collection box, revenue sharing

Creator Subscriptions – Paid monthly subscriptions to creators

Designed for tech enthusiasts, PC buyers, gamers, and content creators in India (UPI payments, INR pricing).

🔄 Core Flow (User Journey)
1. Onboarding & Coins
User signs up (email / Google OAuth) → gets a welcome bonus (e.g., 100 coins)

Coins are the primary currency. Buy coins via UPI payment (coin packages: Starter, Pro, Elite, Whale, Legend)

2. Content Creation & Engagement
Create a post → costs post_creation_cost (2 coins) → post appears on feed

Like/comment on a post → costs coins (e.g., 2 coins for like) → post owner earns a reward (1 coin)

Post views → post owner earns post_view_reward (0.1 coin per unique view)

Repost → costs coins, rewards original creator

All coin flows are recorded in coin_transactions with collection_box_fee going to platform treasury

3. Marketplace (Computer Store)
Browse products (laptops, desktops, components) – cs_products, cs_categories, cs_brands

Add to cart (cs_carts, cs_cart_items)

Checkout → creates cs_customers and cs_invoices (with GST, payment tracking)

Inventory movements (cs_inventory_movements) track stock

4. Service Tickets (PC Repair)
User can raise a service ticket (cs_service_tickets) with device details, issue description

Technician assigned, diagnosis, estimated/final cost → status lifecycle (received → diagnosed → in_progress → ready → delivered)

5. Gamified & Social Features
Gifts – Buy gifts with coins and send to other users (gift_transactions)

Badges – Purchase badges (e.g., “Economy100”) using coins, appear in user inventory

Tokens (Akku Legend, Diamond, Gold, Silver) – Can be converted to coins (with commission)

Collection Box – Platform treasury tracking fees from all transactions (akku_collection_box)

User follows, groups, private messaging (conversations, messages)

Creator subscriptions – Users pay monthly coin price to subscribe to a creator

6. Admin Dashboard
Manage coin packages, commission settings (commission_settings)

Verify UPI payments (upi_payments), adjust user balances

Moderate flagged content (flagged_content)

View treasury summary (treasury_summary view)

Manage products, inventory, invoices, service tickets

🧱 System Structure (Key Modules)
Module	Tables	Purpose
User & Auth	users, user_sessions, Google OAuth	Login, roles (admin/user), coin balance
Content	user_posts, post_likes, post_comments, post_views, post_reposts	Social feed with coin economics
News/Blogs	news_blogs	Articles (blog/news) with SEO fields
Economy	coin_transactions, coin_packages, akku_collection_box, commission_settings	All coin movements, purchases, fees
Tokens & Gifts	akku_tokens, token_conversions, gifts, gift_transactions, user_inventory	Virtual assets
E‑commerce	cs_* tables (products, brands, categories, carts, invoices, customers, inventory)	PC hardware sales
Service	cs_service_tickets	Repair job management
Social	user_follows, groups, conversations, messages, notifications	Community interaction
System	config, badges, donation_cards, events	Platform configuration & extras
💡 Key Business Logic (from commission_settings)
Action	Cost to User	Reward to Creator	Platform Fee (via Collection Box)
Create post	2 coins	–	–
Like a post	2 coins	1 coin	1 coin (implicitly part of fee)
Comment on post	2 coins	1 coin	1 coin
View post	–	0.1 coin	–
Send gift	gift price + 5% commission	–	5%
Token → Coin conversion	–	–	10% commission
Game winnings	–	–	10% commission
All fees are tracked in akku_collection_box – platform’s revenue pool.

🚀 Deployment Target
Backend: PHP + MySQL (as seen from GitHub repo stats)

Frontend: HTML/CSS/JS (responsive, incognito‑accessible)

Payments: UPI (QR / screenshot verification) – manual admin verification

Environment: Shared hosting or VPS (supports PHP sessions, file uploads for post images & payment screenshots)