-- ============================================================================
--  FairCart — fragrance, cleaning and pest-control range from the Oct 2026 posters
--  phpMyAdmin → wyimzdyd_faircart → Import. Safe to run more than once.
--
--  • New prices, names and descriptions for 12 existing products (matched by slug)
--  • New product: Scented Candle (180g, R130)
--  • Each gets the photo cut from its poster as the main image, and the poster
--    itself as the first gallery image (upload assets/img/products/*-photo.jpg
--    and *-poster.jpg first — they are in the update zip)
-- ============================================================================
SET NAMES utf8mb4;

-- ── New product ─────────────────────────────────────────────────────────────
INSERT INTO `products`
  (`category_id`, `brand_id`, `name`, `slug`, `short_desc`, `description`, `usage_notes`, `type`,
   `retail_price`, `reseller_price`, `rrp`, `pack_size`, `sku`, `stock_status`, `stock_qty`, `min_reseller_qty`,
   `featured_image`, `is_featured`, `is_new`, `status`, `tags`, `benefits`)
SELECT
  (SELECT id FROM categories WHERE slug = 'home-fragrance'), (SELECT id FROM brands WHERE name = 'ML Group'),
  'Scented Candle', 'scented-candle',
  'A 180g candle for a warm glow and a calm, beautifully scented home.',
  'A hand-poured 180g scented candle in a matt black glass with a gold label. Light it in the lounge or bedroom for a warm glow and a fragrance that fills the room — and keep the glass afterwards.',
  'Trim the wick to 5mm before each burn. Burn for up to 3 hours at a time, let the top melt evenly on the first burn, and never leave a lit candle unattended.',
  'simple', 130.00, 70.00, 130.00, '180g', 'ML-CND-180', 'in_stock', 20, 6,
  'assets/img/products/scented-candle-photo.jpg', 1, 1, 'published',
  'candle,scented candle,home fragrance,gift,ml group',
  'Warm glow and room-filling scent\nMatt black glass with gold label\n180g of wax for long burn time\nA gift that looks the part'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM products WHERE slug = 'scented-candle');

-- ── Home fragrance ──────────────────────────────────────────────────────────
UPDATE `products` SET `name` = 'Room & Linen Spray', `retail_price` = 150.00, `rrp` = 150.00, `pack_size` = '250ml',
  `short_desc` = 'Refresh bedding, curtains and clothes with a long-lasting, luxurious scent.',
  `description` = 'Pure Fragrances Room & Linen Spray in a 250ml black-and-gold bottle. A light mist over bedding, curtains, couches and clothes leaves a long-lasting, luxurious scent — the quick way to make a room feel freshly made up.',
  `featured_image` = 'assets/img/products/room-linen-spray-photo.jpg'
WHERE `slug` = 'room-linen-spray';

UPDATE `products` SET `name` = 'Home Diffuser', `retail_price` = 180.00, `rrp` = 180.00, `pack_size` = '150ml',
  `short_desc` = 'Elegant reed diffuser that fills your space with a gentle fragrance all day, no flame needed.',
  `description` = 'A 150ml Pure Fragrances reed diffuser in a clear glass bottle with a gold cap and black reeds. It scents a lounge, bedroom, bathroom or office gently all day, with no flame and nothing to switch on.',
  `featured_image` = 'assets/img/products/home-diffuser-photo.jpg'
WHERE `slug` = 'home-diffuser';

UPDATE `products` SET `name` = 'Perfume', `retail_price` = 120.00, `rrp` = 120.00, `pack_size` = '50ml',
  `short_desc` = 'Pure Fragrance eau de parfum. A refined scent for every day and every occasion.',
  `description` = 'Pure Fragrance eau de parfum in a 50ml glass bottle. A refined, long-lasting scent that works for the office, the weekend and a night out — two sprays carry you through the day.',
  `featured_image` = 'assets/img/products/signature-perfume-photo.jpg'
WHERE `slug` = 'signature-perfume';

-- ── Car fragrances ──────────────────────────────────────────────────────────
UPDATE `products` SET `name` = 'Car Diffuser', `retail_price` = 50.00, `rrp` = 50.00, `pack_size` = 'Single',
  `short_desc` = 'Hang it on your mirror and enjoy a fresh-smelling car on every trip.',
  `description` = 'A heart-shaped glass car diffuser with a wooden cap and cord. Hang it from the rear-view mirror and the scent slowly fills the car on every trip. Top it up with Dropper Oil when it fades.',
  `usage_notes` = 'Hang from the rear-view mirror. Tip the bottle briefly to wet the wooden cap, then let it hang upright. Refill with a few drops of Dropper Oil.',
  `benefits` = 'Fresh car on every trip\nHeart-shaped glass with wooden cap\nRefillable with Dropper Oil\nThe easy add-on at R50',
  `featured_image` = 'assets/img/products/car-mirror-hanger-photo.jpg'
WHERE `slug` = 'car-mirror-hanger';

UPDATE `products` SET `name` = 'Car Spray Perfume', `retail_price` = 80.00, `rrp` = 80.00,
  `short_desc` = 'One quick spritz instantly refreshes your car interior.',
  `description` = 'Pure Fragrances oil-based car spray perfume. One quick spritz over the seats and carpets instantly refreshes the whole interior — keep it in the cubby for after the gym, the kids or the dog.',
  `usage_notes` = 'Spray once or twice onto seats and carpets, not onto the dashboard, screens or paintwork.',
  `benefits` = 'Instant fresh interior\nOil-based for a longer-lasting scent\nFits in the cubby or door pocket\nA spritz or two is enough',
  `featured_image` = 'assets/img/products/car-perfume-photo.jpg'
WHERE `slug` = 'car-perfume';

UPDATE `products` SET `name` = 'Dropper Oil', `retail_price` = 80.00, `rrp` = 80.00,
  `short_desc` = 'Concentrated fragrance oil. A few drops go a long way.',
  `description` = 'Pure Fragrances concentrated oil-based perfume in an amber glass dropper bottle. A few drops refresh a Car Diffuser, a cotton pad in the cupboard or the reeds of a diffuser — a little goes a long way.',
  `usage_notes` = 'Add a few drops to a Car Diffuser or a cotton pad. Avoid contact with dashboard plastics and paint.',
  `benefits` = 'Concentrated — a few drops go a long way\nRefills the Car Diffuser\nAmber glass protects the oil\nUse in the car, cupboards or drawers',
  `featured_image` = 'assets/img/products/car-perfume-dropper-photo.jpg'
WHERE `slug` = 'car-perfume-dropper';

-- ── Cleaning & hygiene ──────────────────────────────────────────────────────
UPDATE `products` SET `name` = 'Wonke-Wonke Multi-Purpose Cleaner', `retail_price` = 130.00, `rrp` = 130.00,
  `short_desc` = 'One powerful cleaner for kitchens, bathrooms and everyday surfaces.',
  `description` = 'Wonke-Wonke ("all of it") multi-purpose cleaner in a 500ml trigger bottle. One powerful cleaner for kitchen counters, stoves, bathrooms, tiles and everyday surfaces.',
  `featured_image` = 'assets/img/products/wonke-wonke-cleaner-photo.jpg'
WHERE `slug` = 'wonke-wonke-cleaner';

UPDATE `products` SET `name` = 'Mould Stopper', `retail_price` = 130.00, `rrp` = 130.00,
  `short_desc` = 'Tackle mould and mildew on walls, tiles and bathroom surfaces.',
  `description` = 'Mould Stopper spray for the black mould and mildew that builds up on bathroom walls, tiles, grout and ceilings. Spray, leave it to work, and wipe away.',
  `featured_image` = 'assets/img/products/mold-stopper-remover-photo.jpg'
WHERE `slug` = 'mold-stopper-remover';

UPDATE `products` SET `name` = 'Pit Fresh Mzansi', `retail_price` = 130.00, `rrp` = 130.00, `pack_size` = '100g',
  `short_desc` = 'Advanced pit toilet treatment powder (100g) to help keep odours under control.',
  `description` = 'Pit Fresh Mzansi is a bio-enzyme powder for pit toilets and latrines. It breaks down waste, reduces odours, repels flies and improves hygiene. Initial dose: mix 100g with 5 litres of water, stir and pour into the pit.',
  `usage_notes` = 'Initial: mix 100g with 5L water, stir and pour into the pit. If the surface has hardened, break the layer with a stick and add warm water to soak it. Maintenance: mix 25g with water weekly.',
  `benefits` = 'Bio-enzyme breaks down waste\nReduces odours and repels flies\nImproves hygiene around the toilet\nSimple weekly maintenance dose',
  `featured_image` = 'assets/img/products/pit-mzansi-fresh-photo.jpg'
WHERE `slug` = 'pit-mzansi-fresh';

-- ── Pest control ────────────────────────────────────────────────────────────
UPDATE `products` SET `name` = 'Fly Repellent', `retail_price` = 130.00, `rrp` = 130.00,
  `short_desc` = 'Keep flies away from your home, kitchen and outdoor areas.',
  `description` = 'Fly Repellent in a trigger spray bottle. Spray around doorways, windows, bins and the braai area to keep flies away from your home, kitchen and outdoor spaces.',
  `featured_image` = 'assets/img/products/fly-repellent-photo.jpg'
WHERE `slug` = 'fly-repellent';

UPDATE `products` SET `name` = 'Cockroach Spray', `retail_price` = 100.00, `rrp` = 100.00,
  `short_desc` = 'Targets cockroaches where they hide, for a pest-free home.',
  `description` = 'Cockroach Spray in a trigger bottle that reaches cracks, drains, skirting and the back of cupboards — the places cockroaches hide — for a pest-free home.',
  `featured_image` = 'assets/img/products/cockroach-spray-photo.jpg'
WHERE `slug` = 'cockroach-spray';

UPDATE `products` SET `name` = 'Rat Butter', `retail_price` = 100.00, `rrp` = 100.00, `pack_size` = '200g',
  `short_desc` = 'Effective 200g rodent bait. Keep away from children and pets.',
  `description` = 'Rat Butter is a 200g rodent bait paste in a screw-top jar. Rats and mice take it readily where pellets get ignored. Use in bait stations, and always keep it away from children, pets and food.',
  `featured_image` = 'assets/img/products/rat-butter-photo.jpg'
WHERE `slug` = 'rat-butter';

-- ── Posters as the first gallery image ──────────────────────────────────────
INSERT INTO `product_gallery` (`product_id`, `image`, `caption`, `sort_order`)
SELECT p.id, CONCAT('assets/img/products/', p.slug, '-poster.jpg'), p.name, 0
FROM products p
WHERE p.slug IN ('room-linen-spray','home-diffuser','scented-candle','signature-perfume','car-mirror-hanger','car-perfume',
                 'car-perfume-dropper','wonke-wonke-cleaner','mold-stopper-remover','pit-mzansi-fresh','fly-repellent',
                 'cockroach-spray','rat-butter')
  AND NOT EXISTS (SELECT 1 FROM product_gallery g WHERE g.product_id = p.id AND g.image = CONCAT('assets/img/products/', p.slug, '-poster.jpg'));

SELECT p.name, p.pack_size, p.retail_price FROM products p
WHERE p.slug IN ('room-linen-spray','home-diffuser','scented-candle','signature-perfume','car-mirror-hanger','car-perfume',
                 'car-perfume-dropper','wonke-wonke-cleaner','mold-stopper-remover','pit-mzansi-fresh','fly-repellent',
                 'cockroach-spray','rat-butter')
ORDER BY p.category_id, p.name;
