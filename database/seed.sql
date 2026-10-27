-- ============================================================================
--  FairCart — seed catalogue
--
--  Prices are transcribed from the supplier price lists in /source-screenshots.
--  Where a sheet printed only one figure, that figure is treated as the RETAIL
--  price and the reseller price is set at ~45% off — the ratio the ML Group
--  sheet states outright (buy R55 / sell R110). Items marked NEEDS PRICE in
--  admin_notes had no legible price on any sheet; confirm them before launch.
-- ============================================================================
SET NAMES utf8mb4;

-- ── Accounts ─────────────────────────────────────────────────────────────
-- admin@faircart.co.za    / (demo password — see the local dev notes)   (change immediately)
-- reseller@faircart.co.za / (demo password — see the local dev notes)   (demo approved reseller)
-- shopper@faircart.co.za  / (demo password — see the local dev notes)   (demo retail customer)
INSERT INTO `users` (`name`,`email`,`password`,`role`,`phone`,`reseller_status`,`reseller_tier`,`reseller_code`,`business_name`,`approved_at`) VALUES
('FairCart Admin','admin@faircart.co.za','$2y$12$1BaL3ZQWEUteFsacatCsl.jMvG9nlMO7JUw5hRq56ksFQKQhuKxsW','admin','+27 60 850 8785','none','starter',NULL,NULL,NULL),
('Naledi Mokoena','reseller@faircart.co.za','$2y$12$s2Kvllz1ofvj3zBibVgOE.iv/YbcKeVk9qKAESnYqpOjNKLM0A.CO','reseller','+27 82 000 0001','approved','silver','FC-4K9TQ2','Naledi Wellness Supplies',NOW()),
('Sipho Dlamini','shopper@faircart.co.za','$2y$12$4RQjCFEArutDPjuwyxrXpetNroU/pFCwYhQ0zRseIMVo5/QEhqSTe','customer','+27 82 000 0002','none','starter',NULL,NULL,NULL)
ON DUPLICATE KEY UPDATE `email`=`email`;

-- ── Brands ───────────────────────────────────────────────────────────────
INSERT INTO `brands` (`id`,`name`,`slug`,`tagline`,`blurb`,`accent`,`sort_order`) VALUES
(1,'Blossom','blossom','Awaken the goddess. Awaken the king.','Plant-led wellness made in South Africa — juices, collagen, slimming shakes and body oils built around traditional African herbs and modern nutrition science.','#C2255C',1),
(2,'Pure Fragrances','pure-fragrances','Premium quality. Affordable prices.','Fine fragrance for the home and car, plus a household pesticide and cleaning range. Fast-moving lines with the widest reseller margins on the shelf.','#C9A227',2),
(3,'Rise','rise','Elevate your clean.','One purple bottle for the whole house — ovens, stoves, sneakers, irons, kettles, carpets, engines. The before-and-after product that sells itself.','#6B3FA0',3),
(4,'ML Group','ml-group','Building one another for a better tomorrow.','Specialist surface treatments. The Mold Stopper range clears mould and stains off tiles, grout and painted walls without bleach fumes.','#12805C',4)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- ── Categories ───────────────────────────────────────────────────────────
INSERT INTO `categories` (`id`,`name`,`slug`,`icon`,`blurb`,`sort_order`) VALUES
(1,'Health & Wellness','health-wellness','fa-heart-pulse','Juices, collagen, cleanses and daily supplements.',1),
(2,'Slimming & Fitness','slimming-fitness','fa-fire','Shakes, drops, teas and AM/PM capsules for weight management.',2),
(3,'Beauty & Body','beauty-body','fa-sparkles','Body oils and beauty drinks for skin, hair and nails.',3),
(4,'Home Fragrance','home-fragrance','fa-spray-can-sparkles','Room sprays, reed diffusers and fine perfume.',4),
(5,'Car Care','car-care','fa-car','Hanging bottles, droppers and mirror charms for the car.',5),
(6,'Cleaning','cleaning','fa-bubbles','Multi-purpose cleaners, mould removers and toilet treatments.',6),
(7,'Pest Control','pest-control','fa-bug-slash','Cockroach, fly and rodent solutions for the home.',7),
(8,'Bulk & Starter Packs','bulk-packs','fa-boxes-stacked','Case quantities priced for resale from day one.',8)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- ── Products ─────────────────────────────────────────────────────────────
INSERT INTO `products`
(`id`,`category_id`,`brand_id`,`name`,`slug`,`short_desc`,`benefits`,`usage_notes`,`type`,`retail_price`,`reseller_price`,`rrp`,`pack_size`,`sku`,`stock_status`,`stock_qty`,`min_reseller_qty`,`featured_image`,`is_featured`,`is_bestseller`,`is_new`,`tags`) VALUES

-- Blossom — juices
(1,1,1,'Lady Bloom Juice','lady-bloom-juice',
 'A women''s health tonic built on hibiscus, cranberry and pomegranate. The bottle that started the range.',
 "Boosts everyday energy\nImproves blood circulation\nSupports hormone balance\nHelps settle PMS symptoms\nSupports reproductive health",
 'Shake well. Take 30ml twice daily, preferably before meals. Refrigerate after opening and finish within 14 days.',
 'variable',100.00,55.00,100.00,'500ml','BLM-LBJ-500','in_stock',180,6,'assets/img/products/lady-bloom-hibiscus.jpg',1,1,0,'juice,womens health,hibiscus,pms,hormones,blossom'),

(2,1,1,'Luna Bloom Juice','luna-bloom-juice',
 'The HERO bottle — a mixed-berry cycle-support tonic formulated to ride alongside Lady Bloom.',
 "Supports a steady monthly cycle\nBerry antioxidants for daily defence\nGentle on an empty stomach\nNo added cane sugar",
 'Shake well. 30ml daily. Keep refrigerated once opened.',
 'simple',100.00,55.00,100.00,'500ml','BLM-LUN-500','in_stock',120,6,'assets/img/products/luna-bloom-juice.svg',1,0,1,'juice,cycle,berry,womens health,blossom'),

(3,1,1,'King Bloom Juice','king-bloom-juice',
 'Men''s vitality tonic carrying the traditional African herbs Bangalala, Mpesu and Muvevha alongside ashwagandha and shilajit.',
 "Stronger stamina and endurance\nBetter circulation\nSupports healthy male hormonal balance\nHelps the body adapt to everyday stress\nTraditional herbs: Bangalala, Mpesu, Muvevha",
 'Shake well. 30ml daily, or before physical activity. Do not exceed 60ml in 24 hours. Not for under-18s.',
 'simple',150.00,85.00,150.00,'500ml','BLM-KBJ-500','in_stock',140,6,'assets/img/products/king-bloom-juice.jpg',1,1,0,'juice,mens health,stamina,bangalala,mpesu,blossom'),

-- Blossom — wellness
(4,1,1,'gutCleanse','gut-cleanse',
 'High-fibre daily cleanse in tangy orange with real ginger. The gentlest way into the range.',
 "Helps reduce bloating\nRelieves constipation\nHigh in fibre\nCurbs cravings between meals\nSupports a healthy digestion",
 'Stir one scoop into 250ml cold water once daily. Drink immediately and follow with a second glass of water.',
 'simple',300.00,165.00,300.00,'300g','BLM-GUT-300','in_stock',90,6,'assets/img/products/gut-cleanse.jpg',0,0,0,'gut,fibre,cleanse,bloating,ginger,blossom'),

(5,1,1,'Absolute Pure Hydrolysed Collagen','absolute-pure-collagen',
 'Unflavoured pasture-raised collagen peptides. Mixes clear into tea, coffee or juice with no aftertaste.',
 "Improves skin glow and elasticity\nStrengthens hair and nails\nSupports joint and bone health\nHelps muscle recovery\nEasy for the body to absorb",
 'One scoop daily in any hot or cold drink. Natural collagen production drops from about age 25 — daily use is what shows.',
 'simple',300.00,165.00,300.00,'300g','BLM-COL-300','in_stock',110,6,'assets/img/products/absolute-pure-collagen.webp',1,1,0,'collagen,peptides,skin,hair,nails,joints,blossom'),

-- Blossom — slimming
(6,2,1,'B.Drops Fat-Burn Serum','b-drops',
 'Concentrated raspberry-flavoured drops. The strongest appetite tool in the Blossom line-up.',
 "Suppresses appetite\nClean energy boost\nSupports fat burning\nPocket-sized 30ml bottle",
 '10 drops under the tongue 20 minutes before your two main meals. Do not exceed 30 drops daily.',
 'simple',450.00,250.00,450.00,'30ml','BLM-BDR-030','in_stock',70,6,'assets/img/products/b-drops.jpg',1,0,0,'drops,appetite,fat burner,slimming,blossom'),

(7,2,1,'Bloom Detox Tea','bloom-detox-tea',
 'The slimming and detox tea — 14 sachets of a green-tea led blend that works overnight.',
 "Slimming and detox blend\nBoosts immunity\nImproves digestion\nAntioxidant rich\nDetox and purify",
 'Steep one sachet in boiling water for 3–5 minutes, every second evening. Not for use during pregnancy.',
 'simple',350.00,195.00,350.00,'14 sachets','BLM-TEA-014','in_stock',85,6,'assets/img/products/bloom-detox-tea.webp',1,1,0,'tea,detox,slimming,immunity,blossom'),

(8,2,1,'shrinkAM / shrinkPM','shrink-am-pm',
 'A day-and-night capsule pair: shrinkAM for daytime energy and appetite control, shrinkPM for evening calm and overnight burn.',
 "Shuts down cravings\nDaytime energy boost\nBurns fat around the clock\nEvening anxiety and stress relief\nBoosts mood",
 'One shrinkAM capsule with breakfast, one shrinkPM capsule an hour before bed. 30-day supply per pair.',
 'simple',400.00,220.00,400.00,'2 × 60 capsules','BLM-SHR-120','in_stock',60,6,'assets/img/products/shrink-am-pm.svg',0,0,0,'capsules,slimming,appetite,am pm,blossom'),

(9,2,1,'Collagen & Berberine Slimming Shake','collagen-berberine-shake',
 'A 25g-protein meal shake pairing hydrolysed collagen with berberine — fuel, firm and burn in one scoop.',
 "Supports weight management\nLowers cholesterol\nHelps balance blood sugar\n25g protein per serving\nDelicious, nutritious, effective",
 'Mix 30–35g with 300ml water, skimmed milk or low-fat milk. Shake or blend. 400g pouch = about 12 servings.',
 'variable',400.00,220.00,400.00,'400g','BLM-CBS-400','in_stock',95,6,'assets/img/products/collagen-berberine-berry.jpg',1,1,1,'shake,collagen,berberine,protein,slimming,blossom'),

-- Blossom — beauty
(10,3,1,'Brightening Body Oil','brightening-body-oil',
 'A fast-absorbing glow oil for dull, uneven skin — the one people repurchase.',
 "Brightens dull and uneven skin tone\nHelps fade stretch marks and scars\nDeeply moisturises and softens\nSupports collagen for firmer skin\nRepairs dry, damaged skin",
 'Massage into damp skin after showering, morning and night. Dermatologically tested.',
 'simple',110.00,60.00,110.00,'60ml','BLM-BBO-060','in_stock',150,6,'assets/img/products/brightening-body-oil.jpg',0,1,0,'body oil,brightening,stretch marks,glow,blossom'),

(11,3,1,'Firming Body Oil','firming-body-oil',
 'The tightening counterpart to Brightening — for sagging skin, fine lines and texture.',
 "Tightens sagging skin over time\nSmooths fine lines and improves texture\nBoosts skin elasticity and firmness\nHelps reduce dark spots\nLeaves a radiant finish",
 'Massage into damp skin after showering, morning and night. Dermatologically tested.',
 'simple',110.00,60.00,110.00,'60ml','BLM-FBO-060','in_stock',150,6,'assets/img/products/firming-body-oil.svg',0,0,0,'body oil,firming,elasticity,anti-ageing,blossom'),

(12,3,1,'Blossom Beauty Anti-Ageing Drink','blossom-beauty-antiaging',
 'A pomegranate beauty shot for skin taken from the inside out.',
 "Pomegranate antioxidants\nSupports skin renewal\nWorks alongside the collagen range\nOne shot a day",
 'One 30ml shot daily, morning or evening.',
 'simple',180.00,100.00,180.00,'250ml','BLM-BAD-250','in_stock',40,6,'assets/img/products/blossom-beauty-antiaging.jpg',0,0,1,'beauty,anti-ageing,pomegranate,collagen,blossom'),

-- Blossom — bulk
(13,8,1,'Lady Bloom Student Pack','lady-bloom-student-pack',
 'Twelve bottles of Lady Bloom Juice at the student rate — the standard starter case for a new reseller.',
 "12 × 500ml bottles\nThe fastest-moving line in the range\nPriced for immediate resale\nQualifies for depot collection or Runner pickup",
 'Store cool and out of direct sunlight. Rotate stock oldest-first.',
 'simple',499.00,275.00,1200.00,'12 × 500ml','BLM-LBJ-12PK','in_stock',35,1,'assets/img/products/lady-bloom-student-12.svg',1,1,0,'bulk,case,student pack,juice,starter,blossom'),

-- Pure Fragrances — home
(14,4,2,'Room & Linen Spray','room-linen-spray',
 'A 250ml fine mist for bedding, curtains and the air between them.',
 "Long-hold fragrance oil base\nSafe on most fabrics\nOne spray covers a room\nPremium quality, affordable price",
 'Hold 30cm from the surface and mist lightly. Spot-test delicate fabrics first.',
 'simple',160.00,90.00,160.00,'250ml','PF-RLS-250','in_stock',100,6,'assets/img/products/room-linen-spray.svg',1,0,0,'room spray,linen,home fragrance,pure fragrances'),

(15,4,2,'Home Reed Diffuser','home-diffuser',
 'A 150ml reed diffuser that holds a room for six to eight weeks without a flame.',
 "6–8 weeks of continuous fragrance\nFlame-free and child-safe\nSix natural rattan reeds included\nSlim glass vessel suits any shelf",
 'Insert the reeds and let them saturate for two hours. Flip the reeds weekly for a stronger throw.',
 'simple',180.00,100.00,180.00,'150ml','PF-DIF-150','in_stock',80,6,'assets/img/products/home-diffuser.svg',1,1,0,'diffuser,reeds,home fragrance,pure fragrances'),

(16,4,2,'Signature Perfume','signature-perfume',
 'A 30ml eau de parfum in the Pure Fragrances house accord. Handbag-sized, full strength.',
 "Eau de parfum concentration\nAll-day wear from two sprays\nUnisex house accord\nFits any handbag",
 'Spray on pulse points — wrists, neck, behind the ears. Do not rub.',
 'simple',130.00,70.00,130.00,'30ml','PF-PRF-030','in_stock',120,6,'assets/img/products/signature-perfume.svg',1,1,0,'perfume,edp,fragrance,pure fragrances'),

-- Pure Fragrances — car
(17,5,2,'Car Perfume','car-perfume',
 'The hanging 50ml glass bottle on a rope cord — the fastest-moving line on the sheet.',
 "50ml hanging glass bottle\nWooden cap and rope cord\nLasts 4–6 weeks per bottle\nRefillable with the dropper",
 'Hang from the rear-view mirror and loosen the cap a quarter-turn to open the wick.',
 'simple',80.00,45.00,80.00,'50ml','PF-CAR-050','in_stock',200,12,'assets/img/products/car-perfume.svg',1,1,0,'car perfume,hanging,fragrance,pure fragrances'),

(18,5,2,'Car Perfume Dropper','car-perfume-dropper',
 'Concentrated refill oil in an amber dropper bottle. Refills a Car Perfume twice.',
 "Refills a hanging bottle twice over\nAmber glass protects the oil\nMatches the Car Perfume accords\nSells as an add-on every time",
 'Top the hanging bottle to the shoulder. Avoid contact with dashboard plastics and paint.',
 'simple',80.00,45.00,80.00,'30ml','PF-DRP-030','in_stock',160,12,'assets/img/products/car-perfume-dropper.svg',0,0,0,'refill,dropper,car perfume,pure fragrances'),

(19,5,2,'Car Mirror Hanger','car-mirror-hanger',
 'A scented glass charm with a thank-you card — the impulse buy at the till.',
 "Lowest price point in the range\nGift-ready with a thank-you card\nHeart-shaped glass charm\nHighest sell-through of any line",
 'Hang from the mirror. Refresh with a few drops from the dropper when it fades.',
 'simple',60.00,35.00,60.00,'Single','PF-HNG-001','in_stock',240,12,'assets/img/products/car-mirror-hanger.svg',0,1,0,'hanger,charm,car,gift,pure fragrances'),

-- Pure Fragrances — pest
(20,7,2,'Cockroach Elimination Spray','cockroach-spray',
 'A 100ml targeted spray for cracks, drains and cupboard corners.',
 "Kills on contact\nTreats cracks, drains and skirting\nLow odour once dry\nSafe for indoor kitchen use when dry",
 'Spray directly into cracks and along skirting. Ventilate the room and keep children and pets away until dry.',
 'simple',100.00,55.00,100.00,'100ml','PF-CKR-100','in_stock',90,6,'assets/img/products/cockroach-spray.svg',0,0,0,'pest,cockroach,spray,pure fragrances'),

(21,7,2,'Fly Repellent Spray','fly-repellent',
 'A 500ml trigger spray for doorways, bins and braai areas.',
 "500ml trigger bottle\nRepels flies for hours per application\nUse indoors and outdoors\nBraai-season staple",
 'Spray around doorways, windows and bin areas. Reapply after rain or washing down.',
 'simple',130.00,70.00,130.00,'500ml','PF-FLY-500','in_stock',85,6,'assets/img/products/fly-repellent.svg',0,0,0,'pest,fly,repellent,pure fragrances'),

(22,7,2,'Rat Butter Bait','rat-butter',
 'A 200g bait paste that rodents take readily where pellets get ignored.',
 "200g paste in a screw-top jar\nHighly palatable to rats and mice\nSets into bait stations cleanly\nUse indoors and in ceilings",
 'Place pea-sized amounts in bait stations along runs. Wear gloves. Keep away from children, pets and food.',
 'simple',110.00,60.00,110.00,'200g','PF-RAT-200','in_stock',75,6,'assets/img/products/rat-butter.svg',0,0,0,'pest,rodent,rat,bait,pure fragrances'),

-- Pure Fragrances — cleaning
(23,6,2,'Wonke Wonke Multi-Purpose Cleaner','wonke-wonke-cleaner',
 'A 500ml trigger cleaner for kitchens, bathrooms and everything in between.',
 "One bottle for the whole house\nCuts grease on contact\nStreak-free on glass and tiles\nFresh scent that lingers",
 'Spray onto the surface, leave 30 seconds, wipe with a damp cloth. Dilute 1:5 for floors.',
 'simple',130.00,70.00,130.00,'500ml','PF-WWC-500','in_stock',110,6,'assets/img/products/wonke-wonke-cleaner.svg',0,1,0,'cleaner,multi purpose,kitchen,bathroom,pure fragrances'),

(24,6,2,'Pit Mzansi Fresh','pit-mzansi-fresh',
 'A 500g advanced pit-toilet treatment powder that digests waste and kills the smell.',
 "Digests waste and reduces sludge\nEnables water reuse where safe\nControls odour and improves hygiene\n500g treats a household pit for months",
 'Initial dose: mix 100g with 5L of water and pour into the pit. Maintenance: 25g with water weekly.',
 'simple',130.00,70.00,130.00,'500g','PF-PMF-500','in_stock',65,6,'assets/img/products/pit-mzansi-fresh.svg',0,0,0,'toilet,pit,sanitation,powder,pure fragrances'),

-- Rise
(25,6,3,'Rise Multi-Purpose Cleaner','rise-multipurpose-cleaner',
 'The 750ml purple bottle that cleans ovens, sneakers, irons, kettles, carpets and car engines. The before-and-after product.',
 "Glass, windows, wood and tiles\nCouches, mattresses and carpets\nStoves, ovens, pots and air-fryers\nIrons, kettles, showers and baths\nSneakers, clothes and car interiors",
 'Spray on, leave 1–2 minutes on baked-on grease, then wipe. Always spot-test coloured fabrics first.',
 'simple',120.00,65.00,120.00,'750ml','RSE-MPC-750','in_stock',130,6,'assets/img/products/rise-multipurpose-cleaner.jpg',1,1,0,'cleaner,multi purpose,oven,sneakers,carpet,rise'),

-- ML Group
(26,6,4,'Mold Stopper Remover','mold-stopper-remover',
 'A 500ml deep-clean formula that lifts mould and black stains off tiles, grout and painted walls.',
 "Powerful mould removal\nDeep-clean formula, no bleach fumes\nSafe on most surfaces\nHealthier bathrooms and kitchens\nVisible result in one application",
 'Spray onto the affected area, leave 10 minutes, rinse or wipe. Ventilate the room while working.',
 'simple',110.00,55.00,110.00,'500ml','MLG-MSR-500','in_stock',140,6,'assets/img/products/mold-stopper-remover.jpg',1,1,0,'mould,mold,bathroom,tiles,grout,ml group')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- ── Variants ─────────────────────────────────────────────────────────────
INSERT INTO `product_variants` (`product_id`,`option_label`,`option_value`,`swatch_hex`,`image`,`stock_qty`,`sku`,`sort_order`) VALUES
(1,'Flavour','Hibiscus','#C2255C','assets/img/products/lady-bloom-hibiscus.jpg',60,'BLM-LBJ-HIB',1),
(1,'Flavour','Cranberry Pomegranate','#8E1F3D','assets/img/products/lady-bloom-cranberry.svg',50,'BLM-LBJ-CRP',2),
(1,'Flavour','Mango Passion','#E9A21B','assets/img/products/lady-bloom-mango.svg',45,'BLM-LBJ-MGP',3),
(1,'Flavour','Pineapple','#F1C40F','assets/img/products/lady-bloom-mango.svg',25,'BLM-LBJ-PIN',4),
(9,'Flavour','Berry Blast','#A61E6B','assets/img/products/collagen-berberine-berry.jpg',55,'BLM-CBS-BER',1),
(9,'Flavour','French Vanilla','#E8DCC0','assets/img/products/collagen-berberine-vanilla.jpg',40,'BLM-CBS-VAN',2);

-- ── Product gallery ──────────────────────────────────────────────────────
INSERT INTO `product_gallery` (`product_id`,`image`,`caption`,`sort_order`) VALUES
(1,'assets/img/editorial/juice-trio.jpg','The Bloom juice line-up',1),
(2,'assets/img/editorial/juice-trio.jpg','Luna, Lady and King together',1),
(3,'assets/img/editorial/juice-trio.jpg','The Bloom juice line-up',1),
(7,'assets/img/editorial/detox-tea-lifestyle.jpg','The tea that sheds the kilos off',1),
(9,'assets/img/products/collagen-berberine-vanilla.jpg','Both flavours, 400g each',1),
(9,'assets/img/products/collagen-berberine-duo.jpg','Berry Blast and French Vanilla',2),
(25,'assets/img/editorial/rise-results.jpg','Before and after: ovens, irons, sneakers, kettles',1);

-- ── Pickup points ────────────────────────────────────────────────────────
INSERT INTO `pickup_points` (`id`,`name`,`address`,`city`,`province`,`hours`,`fee`,`sort_order`) VALUES
(1,'Randburg Depot','152 Bram Fischer Drive, Blairgowrie','Randburg','Gauteng','Mon–Fri 08:30–17:00, Sat 09:00–13:00',0.00,1),
(2,'Johannesburg CBD Pickup','12 Kerk Street, Marshalltown','Johannesburg','Gauteng','Mon–Fri 09:00–16:00',20.00,2),
(3,'Pretoria Collection Point','Shop 4, 285 Francis Baard Street','Pretoria','Gauteng','Mon–Fri 09:00–16:30',20.00,3),
(4,'Durban Collection Point','44 Dr Pixley KaSeme Street','Durban','KwaZulu-Natal','Mon–Fri 09:00–16:00',20.00,4)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- ── Coupons ──────────────────────────────────────────────────────────────
INSERT INTO `coupons` (`code`,`description`,`discount_type`,`discount_value`,`min_order`,`applies_to`,`max_uses`,`expiry_date`) VALUES
('WELCOME10','10% off a first retail order','percentage',10.00,250.00,'retail',500,'2027-12-31'),
('FREESHIP','Free courier delivery over R900','fixed',120.00,900.00,'all',NULL,'2027-12-31'),
('STARTERKIT','R150 off a first reseller stock order','fixed',150.00,1500.00,'reseller',300,'2027-12-31')
ON DUPLICATE KEY UPDATE `code`=`code`;

-- ── Testimonials ─────────────────────────────────────────────────────────
INSERT INTO `testimonials` (`name`,`role`,`location`,`quote`,`rating`,`is_reseller`,`sort_order`) VALUES
('Naledi M.','Silver reseller','Soweto, Gauteng','I started with one student pack of Lady Bloom. Three months later I collect a full case every second Friday and the margin covers my rent. The price list on FairCart is the reason — I can see my profit per bottle before I order.',5,1,1),
('Thabo S.','Retail customer','Kempton Park','Ordered the Rise cleaner because of the oven photos. It is not an exaggeration. My air fryer looks new.',5,0,2),
('Zanele K.','Bronze reseller','Umlazi, KZN','The Runner pickup changed everything for me. I pay, someone collects, I get my stock the same week without leaving my stall.',5,1,3),
('Lerato D.','Retail customer','Centurion','King Bloom for my husband, Lady Bloom for me. Delivery was two days and the bottles were packed properly, no leaks.',5,0,4),
('Sibusiso N.','Gold reseller','Mamelodi, Pretoria','Car perfumes and mirror hangers are what pay my weekly bills — R60 in, R120 out, and they never sit on the shelf.',5,1,5),
('Amanda P.','Retail customer','Randburg','I collect from the depot on my way home. No delivery fee, no waiting for a courier. Simple.',4,0,6);

-- ── FAQs ─────────────────────────────────────────────────────────────────
INSERT INTO `faqs` (`question`,`answer`,`topic`,`sort_order`) VALUES
('How long does delivery take?','Courier delivery is 2–4 working days to main centres and up to 5 working days to outlying areas. You get a tracking reference by email the moment your order is packed.','Delivery',1),
('What is Runner pickup?','A Runner is an approved FairCart member who collects packed orders from a depot and hands them to you. You pay first, request a Runner from your order page, and every assignment, pack scan and handover is logged in your account. It costs R20 and is often faster than a courier.','Delivery',2),
('Can I collect my order myself?','Yes. Choose depot collection at checkout and pick a collection point. Collection from the Randburg depot is free; regional points are R20. You will get a message when the order is packed and ready.','Delivery',3),
('How do I pay?','Bank EFT with a proof-of-payment upload. Place the order, transfer the total to the account shown on your order page, then upload the POP. We confirm within one working day. Card payment via PayFast is being added.','Payment',1),
('Is my order confirmed before I pay?','Your order is reserved as soon as it is placed and confirmed once we match your proof of payment. Stock is held for 48 hours while we wait.','Payment',2),
('What does it cost to become a reseller?','Nothing. Registration is free — you apply, we verify your details, and once approved you see reseller pricing across the whole catalogue. There is no joining fee and no monthly subscription.','Reseller',1),
('How much can I actually make?','Margins run from about 45% on the wellness range up to 55% on fast-moving car and home lines. Every product page shows your cost, the recommended selling price and the rand profit per unit once you are logged in as a reseller.','Reseller',2),
('How do the reseller tiers work?','You start at Starter. Your tier moves up automatically on your rolling 90-day spend: Bronze at R2,500, Silver at R7,500 and Gold at R20,000. Each tier increases your discount off retail — Starter 25%, Bronze 32%, Silver 40%, Gold 48%.','Reseller',3),
('Is there a minimum order for resellers?','Most lines have a minimum of six units so we can pack a clean case. Bulk packs have no minimum — one case is one order.','Reseller',4),
('Are the health products registered?','The Blossom supplements are Category D complementary medicines. They are not registered with SAHPRA for quality, safety or intended use, and are intended to complement the diet, not to treat or cure disease. Speak to a healthcare professional before starting if you are on medication, pregnant or breastfeeding.','Products',1),
('Can I return something?','Unopened, unused stock in its original packaging can be returned within 7 days of delivery for a refund or exchange. Opened consumables cannot be returned for hygiene reasons. Anything damaged in transit is replaced free — send us a photo within 48 hours.','Returns',1);

-- ── Blog / Learn hub ─────────────────────────────────────────────────────
INSERT INTO `blogs` (`title`,`slug`,`image`,`excerpt`,`content`,`category`,`author_id`,`read_mins`,`is_featured`) VALUES
('How to price your stock so it actually sells','how-to-price-your-stock','assets/img/editorial/pure-fragrances-pricelist.jpg',
 'The mistake most new resellers make is pricing off feeling instead of off the sheet. Here is the arithmetic that decides whether a line moves.',
 '<p>Every product in the FairCart catalogue carries three numbers: what you pay, what we recommend you sell at, and the gap between them. The gap is your business.</p><h3>Start from the recommended price, not your cost</h3><p>New resellers tend to take their cost and add a number that feels fair. That is backwards. Customers do not know your cost — they know what the product is worth to them. Start at the recommended selling price and only move down if your area genuinely will not carry it.</p><h3>Know which lines are volume and which are margin</h3><p>A car mirror hanger at R60 will never make you rich per unit, but it sells at every single stop. A bottle of B.Drops at R450 moves slowly and pays for a week. A healthy stall carries both — the small items bring people to you and the big ones pay the bills.</p><h3>Do not discount your fastest movers</h3><p>If something sells out every week, the market is telling you the price is right, or low. Discounting it costs you money and teaches your regulars to wait for a sale.</p><h3>Work in cases, not units</h3><p>Order six or twelve of a line rather than two of six lines. You get a deeper tier discount, you learn what actually sells in your area, and you are never the person who has one of everything and enough of nothing.</p>',
 'Reseller',1,5,1),

('Reading a supplement label without falling for the hype','reading-a-supplement-label','assets/img/editorial/blossom-pricelist.jpg',
 'What Category D actually means, why "not evaluated by SAHPRA" appears on the bottle, and the questions worth asking before you take anything.',
 '<p>Every Blossom bottle carries the same line in small print: this unregistered medicine has not been evaluated by the South African Health Products Regulatory Authority for its quality, safety or intended use. That is not a warning that something is wrong — it is the legally required description of a Category D complementary medicine.</p><h3>What the category means</h3><p>Category D covers health supplements and traditional medicines. They are sold legally, they must be manufactured to standard, and they must be labelled honestly — but they have not gone through the clinical registration process a Schedule medicine goes through.</p><h3>What to look for on the label</h3><p>A real ingredient list with quantities. A batch number and expiry date. A manufacturer address in South Africa. Storage instructions. If any of those are missing, put it back.</p><h3>The questions to ask</h3><p>Are you on chronic medication? Pregnant or breastfeeding? Under 18? Each of those is a reason to speak to a pharmacist or doctor first — not because the product is dangerous, but because interactions are real and nobody on a WhatsApp group can assess yours.</p><p>Supplements complement a diet. They do not replace one, and nothing on this site treats or cures a disease.</p>',
 'Products',1,6,1),

('One bottle, twenty surfaces: getting the most out of Rise','getting-the-most-out-of-rise','assets/img/editorial/rise-results.jpg',
 'The oven photos are what sell it, but the trick is knowing dwell time. A short field guide to the purple bottle.',
 '<p>Rise Multi-Purpose Cleaner is the product customers photograph. Ovens, irons, kettles, sneakers, mop heads, car engines — the before-and-after does the selling. What nobody explains is dwell time.</p><h3>Dwell time is the whole trick</h3><p>Spraying and wiping immediately gets you a clean-ish surface. Spraying, waiting, then wiping gets you the photo. On baked-on oven grease, give it two minutes. On a scorched iron plate, spray and leave it while you do something else.</p><h3>Where to be careful</h3><p>Spot-test coloured fabric and upholstery on a hidden seam first. Keep it off polished aluminium and unsealed natural stone. Ventilate while you work on ovens.</p><h3>Selling it at a stall</h3><p>Bring a dirty iron and a clean cloth. Do it in front of people. No claim you make will beat sixty seconds of demonstration.</p>',
 'Products',1,4,0),

('Delivery, depot or Runner — which one to choose','delivery-depot-or-runner','assets/img/editorial/juice-trio.jpg',
 'Three ways to get your order, and the honest trade-off between speed, cost and control for each.',
 '<p>Every FairCart order gets to you one of three ways. None of them is best for everyone.</p><h3>Courier delivery</h3><p>Door to door, 2–4 working days to main centres. Costs the most, needs nobody to travel, and gives you a tracking number. Choose it when the order is big or you are far from a depot.</p><h3>Depot collection</h3><p>Free at the Randburg depot, R20 at regional points. You collect when it suits you within business hours. Choose it when you are close by and the saving matters.</p><h3>Runner pickup</h3><p>An approved Runner collects your packed order from the depot and hands it to you. R20. Every assignment, pack scan and handover is logged in your account, so you always know who has your stock and where it is. Choose it when you cannot leave your stall but still want your order this week.</p><p>Resellers ordering cases usually settle into a rhythm: Runner for the weekly restock, courier for the big monthly order.</p>',
 'Ordering',1,4,0);

-- ── Settings ─────────────────────────────────────────────────────────────
INSERT INTO `settings` (`key_name`,`value`) VALUES
('site_name','FairCart'),
('site_tagline','Real products. Fair prices. Real profit.'),
('currency_symbol','R'),
('free_shipping_threshold','1500'),
('shipping_flat_rate','120'),
('runner_fee','20'),
('bank_name','FNB (First National Bank)'),
('bank_account_holder','FairCart Trading (Pty) Ltd'),
('bank_account_number','0000000000'),
('bank_branch_code','250655'),
('bank_account_type','Business Cheque'),
('contact_phone','+27 60 850 8785'),
('contact_whatsapp','27608508785'),
('contact_email','hello@faircart.co.za'),
('contact_address','152 Bram Fischer Drive, Blairgowrie, Randburg, 2194'),
('facebook_url',''),
('instagram_url',''),
('tiktok_url',''),
('maintenance_mode','0'),
('announcement','Free courier delivery on retail orders over R1,500 · Reseller registration is free')
ON DUPLICATE KEY UPDATE `value`=VALUES(`value`);

-- ── A little social proof on the demo catalogue ──────────────────────────
INSERT INTO `reviews` (`product_id`,`user_id`,`rating`,`title`,`comment`,`is_approved`) VALUES
(1,3,5,'Two weeks in and I feel it','Bought the hibiscus one. Energy is genuinely better and the cramps were milder this month. Bottle lasts me about two weeks.',1),
(25,3,5,'The oven photos are real','I did not believe it. Sprayed my oven door, left it while I made supper, wiped it off. Looks new.',1),
(26,2,5,'Cleared the shower grout','Bathroom corner that has been black for a year came off in one go. No bleach smell either.',1),
(5,2,4,'Mixes properly in coffee','No lumps, no taste. Only knocking a star because the scoop is buried at the bottom of a full tub.',1),
(17,2,5,'Sells itself','I buy these twelve at a time. They never last a full week at my stall.',1);

UPDATE `products` SET `sold_count` = FLOOR(RAND()*180)+20, `view_count` = FLOOR(RAND()*900)+120;
