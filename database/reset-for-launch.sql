-- ============================================================================
--  FairCart — reset the shop for launch
--  phpMyAdmin → wyimzdyd_faircart → Import (or the SQL tab → paste → Go).
--
--  REMOVES the demo/test activity so the shop starts clean:
--    • every order, its lines, timeline and proof-of-payment records
--    • "sold" and "viewed" counters on products (they were seeded by
--      scripts/seed-demo-orders.php and feed "best sellers" and badges)
--    • contact-form messages, wishlists, back-in-stock alerts
--    • newsletter campaigns and their send logs (NOT the subscribers)
--    • social-post logs and the AI/cron bookkeeping
--
--  KEEPS: products, prices, photos, categories, brands, coupons, banners,
--  articles, settings, API keys, user accounts, resellers and every
--  newsletter subscriber.
--
--  Take a backup first (cPanel → Backup → Download a MySQL Database Backup).
--  This cannot be undone.
-- ============================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE `order_events`;
TRUNCATE TABLE `order_items`;
TRUNCATE TABLE `orders`;

UPDATE `products` SET `sold_count` = 0, `view_count` = 0;

TRUNCATE TABLE `contact_messages`;
TRUNCATE TABLE `wishlist`;
TRUNCATE TABLE `stock_alerts`;

TRUNCATE TABLE `newsletter_sends`;
TRUNCATE TABLE `newsletter_campaigns`;

-- Created automatically by the new code; skip these two lines if they error
-- because the tables do not exist yet (open the site once first).
TRUNCATE TABLE `social_posts`;
DELETE FROM `settings` WHERE `key_name` IN ('cron_last_output', 'auto_newsletter_week', 'auto_newsletter_products', 'reel_slots_done', 'reel_queue');

-- Reviews and testimonials: the seeded ones read as real customers, which the
-- Consumer Protection Act treats as misleading. Hide them until real ones come in.
-- (Remove the two dashes to run.)
-- UPDATE `reviews` SET `is_approved` = 0;
-- UPDATE `testimonials` SET `status` = 'draft';

SET FOREIGN_KEY_CHECKS = 1;

SELECT 'Shop reset for launch' AS done,
       (SELECT COUNT(*) FROM orders) AS orders,
       (SELECT COUNT(*) FROM products) AS products,
       (SELECT COUNT(*) FROM newsletter_subscribers) AS subscribers;
