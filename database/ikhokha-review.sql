-- ============================================================================
--  FairCart — settings for the iKhokha / FICA review
--  Import into the production database (phpMyAdmin → wyimzdyd_faircart → Import).
--  Safe to run more than once. It changes two settings and nothing else:
--  no orders, customers, products or prices are touched.
-- ============================================================================
SET NAMES utf8mb4;

-- 1. Open the shop to visitors ------------------------------------------------
--    The sign-up window currently covers the whole shop until a visitor hands
--    over an email address. The reviewer at iKhokha was sent
--    https://faircart.co.za/index.php, so they would hit that wall instead of
--    the shop. 'popup' shows the same offer a few seconds in, and lets anyone
--    close it and carry on browsing.
--    (Put it back later with 'locked' here, or in Admin → Newsletter → Sign-up form.)
INSERT INTO `settings` (`key_name`, `value`) VALUES ('newsletter_gate_mode', 'popup')
  ON DUPLICATE KEY UPDATE `value` = 'popup';

-- 2. Company registration number ----------------------------------------------
--    South African law (ECTA s43) requires an online shop to publish its
--    registration number, and FICA will look for it. Once set, it appears on
--    the terms, privacy, returns and reseller pages automatically.
--
--    Remove the two dashes at the start of the next two lines and replace the
--    number with your real CIPC number, e.g. 2024/123456/07.
--
-- INSERT INTO `settings` (`key_name`, `value`) VALUES ('company_reg_no', 'YOUR/CIPC/NUMBER')
--   ON DUPLICATE KEY UPDATE `value` = 'YOUR/CIPC/NUMBER';

-- 3. Check it worked ----------------------------------------------------------
SELECT `key_name`, `value` FROM `settings`
 WHERE `key_name` IN ('newsletter_gate_mode', 'company_reg_no', 'company_name');
