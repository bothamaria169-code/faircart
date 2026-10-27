-- ============================================================================
--  FairCart — promotional banners
--
--  Drives the rotating hero carousel and the standalone promo tiles.
--  A banner can appear on more than one page, so placement is three flags
--  rather than a single enum — a reseller-recruitment banner belongs on both
--  the homepage and the reseller page without being duplicated as two rows.
--
--  `aspect` decides how a row is rendered, not just how it looks:
--    wide  -> a slide in the page's hero carousel (3:1 artwork)
--    tall  -> a standalone promo tile (portrait artwork, e.g. shop sidebar)
-- ============================================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `banners` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`         VARCHAR(160) NOT NULL,
  `image`         VARCHAR(500) NOT NULL,
  `alt_text`      VARCHAR(200) DEFAULT NULL,
  `link_url`      VARCHAR(255) DEFAULT NULL,
  `aspect`        ENUM('wide','tall') NOT NULL DEFAULT 'wide',
  `show_home`     TINYINT(1) NOT NULL DEFAULT 1,
  `show_shop`     TINYINT(1) NOT NULL DEFAULT 0,
  `show_reseller` TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order`    INT NOT NULL DEFAULT 0,
  `status`        ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_aspect` (`aspect`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
