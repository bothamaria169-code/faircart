-- ============================================================================
--  FairCart — content pivot to a sales-first shop
--
--  The original copy was written around the reseller/margin angle. This
--  rewrites the customer-facing content library so the site reads as a shop
--  first: product guides, buying help, delivery and returns. The reseller
--  programme still exists and still works — it is simply no longer the story
--  the site leads with.
--
--  Safe to re-run.
-- ============================================================================
SET NAMES utf8mb4;

-- ── Articles ─────────────────────────────────────────────────────────────
-- The two reseller-led pieces are replaced in place (same ids keep any
-- existing links working); the remaining two are re-angled at shoppers.
DELETE FROM `blogs`;

INSERT INTO `blogs` (`id`,`title`,`slug`,`image`,`excerpt`,`content`,`category`,`author_id`,`read_mins`,`is_featured`,`status`) VALUES

(1,'Which Bloom juice is actually for you?','which-bloom-juice-is-for-you','assets/img/editorial/juice-trio.jpg',
 'Lady Bloom, Luna Bloom and King Bloom look similar on the shelf and do quite different things. Here is how to pick without guessing.',
 '<p>Three bottles, three jobs. People regularly buy the wrong one because the branding is close and the labels are busy, so here is the short version.</p><h3>Lady Bloom Juice — the everyday women''s tonic</h3><p>This is the one most people start with. Hibiscus, cranberry and pomegranate, aimed at energy, circulation and hormonal balance, and it is the bottle customers come back for. It comes in hibiscus and pineapple — the flavour changes, the formulation behind it does not, so pick on taste.</p><h3>Luna Bloom Juice — cycle support</h3><p>A mixed-berry tonic built to sit alongside Lady Bloom rather than replace it. If your reason for buying is the monthly cycle specifically, this is the more targeted bottle of the two.</p><h3>King Bloom Juice — the men''s bottle</h3><p>Traditional African herbs — bangalala, mpesu and muvevha — with ashwagandha and shilajit, aimed at stamina, circulation and everyday stress. Not a version of Lady Bloom for men; a genuinely different formulation.</p><h3>How to take any of them</h3><p>Shake well, 30ml daily, refrigerate once opened and finish within about two weeks. A 500ml bottle is roughly a two-week supply at that dose, which is worth knowing before you order one bottle and expect a month out of it.</p><p>One honest note: these are Category D complementary medicines. They have not been evaluated by SAHPRA, they complement a diet rather than treat anything, and if you are pregnant, breastfeeding, under 18 or on chronic medication, ask a pharmacist before you start.</p>',
 'Product guides',1,5,1,'published'),

(2,'Reading a supplement label without falling for the hype','reading-a-supplement-label','assets/img/editorial/blossom-pricelist.jpg',
 'What Category D actually means, why "not evaluated by SAHPRA" appears on the bottle, and the questions worth asking before you take anything.',
 '<p>Every Blossom bottle carries the same line in small print: this unregistered medicine has not been evaluated by the South African Health Products Regulatory Authority for its quality, safety or intended use. That is not a warning that something is wrong — it is the legally required description of a Category D complementary medicine.</p><h3>What the category means</h3><p>Category D covers health supplements and traditional medicines. They are sold legally, they must be manufactured to standard, and they must be labelled honestly — but they have not gone through the clinical registration process a Schedule medicine goes through.</p><h3>What to look for on the label</h3><p>A real ingredient list with quantities. A batch number and expiry date. A manufacturer address in South Africa. Storage instructions. If any of those are missing, put it back.</p><h3>The questions to ask</h3><p>Are you on chronic medication? Pregnant or breastfeeding? Under 18? Each of those is a reason to speak to a pharmacist or doctor first — not because the product is dangerous, but because interactions are real and nobody on a WhatsApp group can assess yours.</p><p>Supplements complement a diet. They do not replace one, and nothing on this site treats or cures a disease.</p>',
 'Buying advice',1,6,1,'published'),

(3,'One bottle, twenty surfaces: getting the most out of Rise','getting-the-most-out-of-rise','assets/img/editorial/rise-results.jpg',
 'The oven photos are what sell it, but the trick is dwell time. A short field guide to the purple bottle.',
 '<p>Rise Multi-Purpose Cleaner is the product customers photograph. Ovens, irons, kettles, sneakers, mop heads, car engines — the before-and-after does the selling. What nobody explains is dwell time.</p><h3>Dwell time is the whole trick</h3><p>Spraying and wiping immediately gets you a clean-ish surface. Spraying, waiting, then wiping gets you the photo. On baked-on oven grease, give it two minutes. On a scorched iron plate, spray and leave it while you do something else.</p><h3>Where to be careful</h3><p>Spot-test coloured fabric and upholstery on a hidden seam first. Keep it off polished aluminium and unsealed natural stone. Ventilate while you work on ovens.</p><h3>What one bottle covers</h3><p>A 750ml trigger bottle is enough for a full kitchen deep-clean plus a bathroom, with plenty left over. Most households find it lasts a couple of months of normal use, which is why it tends to get reordered rather than bought once.</p>',
 'Product guides',1,4,0,'published'),

(4,'Delivery, depot or Runner — which one to choose','delivery-depot-or-runner','assets/img/editorial/juice-trio.jpg',
 'Three ways to get your order, and the honest trade-off between speed, cost and convenience for each.',
 '<p>Every FairCart order gets to you one of three ways. None of them is best for everyone.</p><h3>Courier delivery</h3><p>Door to door, 2–4 working days to main centres and up to 5 to outlying areas. It costs the most, needs nobody to travel, and gives you a tracking number. Free once your order goes over the delivery threshold, which is where most two-or-three-item orders land anyway.</p><h3>Depot collection</h3><p>Free at the Randburg depot, R20 at regional points. You collect when it suits you within business hours. Choose it when you are close by and would rather keep the delivery fee.</p><h3>Runner pickup</h3><p>An approved Runner collects your packed order from the depot and hands it to you. R20. Every assignment, pack scan and handover is logged against your order, so you always know who has your parcel and where it is. Choose it when you cannot get to the depot yourself but still want your order this week.</p><h3>What happens either way</h3><p>You place the order, pay by EFT using your order number as the reference, and upload your proof of payment. We confirm within one working day and pack the same day. Your stock is held for 48 hours while we wait for payment.</p>',
 'Ordering',1,4,0,'published'),

(5,'Five products worth keeping in the house','five-products-worth-keeping','assets/img/editorial/rise-results.jpg',
 'The items customers reorder most, and the specific job each one actually does well.',
 '<p>After a few thousand orders you notice which products come back on the second and third order. These five do.</p><h3>Rise Multi-Purpose Cleaner</h3><p>The one bottle that handles the oven, the stovetop, the iron plate and the sneakers. It replaces three or four specialised cleaners under the sink, which is most of why it gets reordered.</p><h3>Mold Stopper Remover</h3><p>Bathroom grout and painted walls, without the bleach headache. One application on a corner that has been black for a year is usually enough to convert somebody permanently.</p><h3>Lady Bloom Juice</h3><p>The best-selling bottle in the catalogue. A 500ml bottle is about two weeks at the recommended dose, so most regulars order two at a time.</p><h3>gutCleanse</h3><p>Tangy orange with real ginger, high fibre, and the gentlest first step into the wellness range. People who are unsure about supplements generally start here.</p><h3>Car Perfume</h3><p>R80, lasts four to six weeks, and refillable with the dropper. It is the cheapest thing in the catalogue that people genuinely notice every day.</p>',
 'Product guides',1,4,0,'published'),

(6,'How to pay by EFT and get your order moving the same day','how-to-pay-by-eft','assets/img/editorial/blossom-pricelist.jpg',
 'Proof of payment is the step that holds most orders up. Here is how to get past it in about two minutes.',
 '<p>FairCart takes bank EFT with a proof-of-payment upload. It is not the fastest checkout in the world, but it means no card details ever touch this site. The whole thing takes about two minutes if you know the order.</p><h3>1. Place the order first</h3><p>Nothing is charged on the site. Placing the order generates your order number and reserves your stock for 48 hours.</p><h3>2. Use the order number as your reference</h3><p>This is the step that goes wrong most often. Your reference must be the order number — something like FC260908AB12C — not your name. Matching a payment without a reference means somebody reading bank statements by hand, which is where the delay comes from.</p><h3>3. Upload the proof of payment</h3><p>A screenshot from your banking app or a PDF from internet banking, straight onto your order page. We confirm within one working day and pack the same day.</p><h3>What if you forget the reference?</h3><p>Send us the proof of payment anyway with your order number in the message and we will match it manually. It just takes longer.</p>',
 'Ordering',1,3,0,'published');

-- ── FAQs ─────────────────────────────────────────────────────────────────
-- Rebalanced: shopping, delivery, payment and product questions lead; the
-- reseller topic shrinks to the two questions trade buyers actually ask.
DELETE FROM `faqs`;

INSERT INTO `faqs` (`question`,`answer`,`topic`,`sort_order`) VALUES
('How long does delivery take?','Courier delivery is 2–4 working days to main centres and up to 5 working days to outlying areas. You get a tracking reference by email the moment your order is packed.','Delivery',1),
('How much is delivery?','Courier delivery is R120, and free on orders over R1,500. Collection from the Randburg depot is free; regional collection points are R20. Runner pickup is R20.','Delivery',2),
('Can I collect my order myself?','Yes. Choose depot collection at checkout and pick a collection point. Collection from the Randburg depot is free; regional points are R20. We message you when the order is packed and ready.','Delivery',3),
('What is Runner pickup?','A Runner is an approved FairCart member who collects packed orders from a depot and hands them to you. You pay first, request a Runner from your order page, and every assignment, pack scan and handover is logged in your account. It costs R20 and is often faster than a courier.','Delivery',4),
('Do you deliver everywhere in South Africa?','Yes, nationwide by courier. Main centres are 2–4 working days; outlying areas can take up to 5. We do not currently ship outside South Africa.','Delivery',5),

('How do I pay?','Bank EFT with a proof-of-payment upload. Place the order, transfer the total to the account shown on your order page, then upload the POP. We confirm within one working day. Card payment via PayFast is being added.','Payment',1),
('Is my order confirmed before I pay?','Your order is reserved as soon as it is placed and confirmed once we match your proof of payment. Stock is held for 48 hours while we wait.','Payment',2),
('What reference must I use?','Always your order number — it looks like FC260908AB12C. Paying with your name as the reference means we have to match it by hand, which delays your order.','Payment',3),
('Do you take card payments?','Not yet. Card and Instant EFT through PayFast is wired into the checkout and will switch on shortly. For now everything goes through bank EFT, which means no card details are ever stored on this site.','Payment',4),

('Do I need an account to order?','No. You can check out as a guest with just a name, email and phone number. An account is only needed if you want to track orders, save items or reorder quickly.','Ordering',1),
('Can I change or cancel an order?','Yes, as long as it has not been packed yet. Contact us with your order number and we will cancel and refund in full. Once it is with the courier it becomes a return.','Ordering',2),
('How do I know something is in stock?','Stock is live on this site. The product page shows In stock, Low stock with the quantity left, or Out of stock. If something is out you can use Notify me and we will email you when it lands.','Ordering',3),

('Are the health products registered?','The Blossom supplements are Category D complementary medicines. They are not registered with SAHPRA for quality, safety or intended use, and are intended to complement the diet, not to treat or cure disease. Speak to a healthcare professional before starting if you are on medication, pregnant or breastfeeding.','Products',1),
('How long does a 500ml juice bottle last?','At the recommended 30ml daily dose, about two weeks. Most regular customers order two bottles at a time for that reason. Keep refrigerated once opened.','Products',2),
('What is the difference between Lady Bloom and King Bloom?','Lady Bloom is the women''s health tonic built on hibiscus, cranberry and pomegranate. King Bloom is a men''s vitality tonic using traditional African herbs — bangalala, mpesu and muvevha — plus ashwagandha and shilajit. Different formulations, not the same drink in different packaging.','Products',3),
('Can I use the Rise cleaner on any surface?','Almost. It handles glass, tiles, wood, fabric, ovens, stoves, carpets, sneakers and car interiors. Spot-test coloured fabric on a hidden seam first, and keep it off polished aluminium and unsealed natural stone.','Products',4),

('Can I return something?','Unopened, unused stock in its original packaging can be returned within 7 days of delivery for a refund or exchange. Opened consumables cannot be returned for hygiene reasons. Anything damaged in transit is replaced free — send us a photo within 48 hours.','Returns',1),
('What if my order arrives damaged?','Send us a photograph within 48 hours of delivery. We replace it free, including the delivery cost, and we arrange the collection. You never pay to send back something that was our mistake or the courier''s.','Returns',2),
('How long does a refund take?','Once the returned item reaches us and we have inspected it, refunds go back to the account you paid from within 5 working days.','Returns',3),

('Do you supply shops, salons and stalls?','Yes. We supply trade customers at wholesale prices across the whole catalogue. Registration is free, there is no joining fee or monthly subscription, and most lines have a six-unit minimum so we can pack a clean case.','Trade',1),
('How do I get trade pricing?','Create an account, then apply on the trade page with your trading name, area and contact number. We verify by hand — usually the same working day — and wholesale pricing switches on across the site as soon as you are approved.','Trade',2);

-- ── Testimonials ─────────────────────────────────────────────────────────
-- Weighted toward shoppers; two trade voices kept for the trade page.
DELETE FROM `testimonials`;

INSERT INTO `testimonials` (`name`,`role`,`location`,`quote`,`rating`,`is_reseller`,`sort_order`) VALUES
('Thabo S.','Verified buyer','Kempton Park','Ordered the Rise cleaner because of the oven photos. It is not an exaggeration. My air fryer looks new and I have barely used a third of the bottle.',5,0,1),
('Lerato D.','Verified buyer','Centurion','King Bloom for my husband, Lady Bloom for me. Delivery was two days and the bottles were packed properly, no leaks. We are on our fourth order now.',5,0,2),
('Amanda P.','Verified buyer','Randburg','I collect from the depot on my way home. No delivery fee, no waiting for a courier, and the order was ready when they said it would be.',5,0,3),
('Nomsa K.','Verified buyer','Soweto','The gutCleanse actually tastes good, which I did not expect from something high fibre. Three weeks in and the bloating is genuinely better.',5,0,4),
('Farhaan C.','Verified buyer','Lenasia','Mold Stopper cleared a shower corner that had been black for over a year. One application. No bleach smell either, which matters with kids in the house.',5,0,5),
('Sibusiso N.','Verified buyer','Mamelodi','The car perfume is R80 and lasts about six weeks. I have reordered it four times — it is the small thing I notice every single day.',5,0,6),
('Naledi M.','Trade customer','Soweto, Gauteng','I stock the juices and the cleaners for my shop. Ordering is simple, the prices are clear before I commit, and the stock arrives packed and ready to sell.',5,1,7),
('Zanele K.','Trade customer','Umlazi, KZN','The Runner pickup changed everything for me. I pay, someone collects, I get my stock the same week without leaving my stall.',5,1,8);
