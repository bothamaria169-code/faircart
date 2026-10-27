# FairCart — going live with the new integrations

Everything below is set up from **Admin → Integrations** and **Admin → Social posting**.
API keys are typed into those pages, stored encrypted in the database, and never committed to git.

---

## 1. Deploy from GitHub (cPanel)

The repository is public, so **passwords are not in it**. They live in `config/secrets.php`,
which git ignores.

1. **One time:** upload `config/secrets.php` (it is on the development PC, next to
   `config/secrets.example.php`) to `public_html/config/` with cPanel File Manager.
   Without it the site shows "FairCart is not configured".
2. cPanel → **Git Version Control** → *Create* → Clone URL
   `https://github.com/bothamaria169-code/faircart.git`, repository path e.g. `repositories/faircart`.
3. *Manage* → **Pull or Deploy** → *Update from Remote*, then **Deploy HEAD Commit**.
   `.cpanel.yml` copies the files into `public_html` and never touches `config/secrets.php`
   or uploaded files.
4. Open the site once: the database upgrades itself (new columns for Pudo, iKhokha, scheduling,
   social posts). No SQL import needed.
5. Delete `deploy-run.php` and `deploy-check.php` from `public_html` if they are still there.

## 2. Email (cPanel mailbox)

`admin@faircart.co.za` is already in `config/secrets.php` (SMTP SSL on port 465 — the login was tested).
Admin → Integrations → Email → **Save & test** sends a test message.

For inbox delivery (not spam): cPanel → **Email Deliverability** → *Repair* / *Manage* until
**DKIM** and **SPF** both show valid. Also create `hello@faircart.co.za` and `orders@faircart.co.za`
as forwarders to admin@ (they are printed on the site), or change the contact email in Settings.

## 3. Cron job — one line runs everything

cPanel → **Cron Jobs** → Common settings *Once Per Five Minutes* → Command:

```
/usr/local/bin/php /home/wyimzdyd/public_html/scripts/cron.php >> /home/wyimzdyd/logs/faircart-cron.log 2>&1
```

Remove the old `refresh-tiers.php` cron if you added it — this one now does that nightly.
Each run: starts scheduled newsletters, sends newsletter batches (40 per run — keeps under cPanel's
hourly mail limit; change `cron_newsletter_batch` in the settings table), writes the weekly AI
newsletter, posts to Facebook/Instagram, makes reels. Admin → Integrations → *Cron job* shows the last run.

## 4. The Courier Guy · Pudo lockers

1. customer.pudo.co.za → **Settings → API keys → Create API Key** → copy it.
2. Admin → Integrations → Pudo: paste the key, set **Drop-off locker code** (the locker you take
   parcels to — find it on the checkout map), the box size you usually send, sender name, email
   (your Pudo account email) and cellphone (gets the drop-off PIN).
3. **Save & test** — it loads all lockers and quotes a sample rate.
4. Checkout now offers **Pudo locker** (map + search + "near me") with the live Pudo rate
   (or your fixed fee), plus **Courier Guy to your door** at the flat rate.
5. When an order is paid: Admin → Order → **Book Pudo shipment** creates the waybill,
   marks it "On the way" and emails the customer. Tracking appears on the customer's order page.

## 5. iKhokha card payments

1. iKhokha dashboard → **iK Pay API** → copy *Application ID* and *Application Secret*.
2. Admin → Integrations → iKhokha → paste both → **Save & test** (makes a R5 test link; nothing is
   charged unless someone pays it).
3. Checkout then offers **Card or Instant EFT — iKhokha** (default) alongside bank EFT.
   Orders are marked paid only after iKhokha's own status API confirms the payment.
   Webhook: `https://faircart.co.za/modules/payment/ikhokha-callback.php` (sent automatically).

## 6. Groq AI newsletters

1. console.groq.com → **API Keys** → Create → copy (`gsk_…`).
2. Admin → Integrations → Groq → paste → **Save & test**.
3. Newsletter → New campaign → pick template and products → **Write subject & copy**.
   Then *Send me a test*, and **Send** or **Schedule** (date/time; the cron job sends it).
4. Optional: *Automatic weekly newsletter* → *Write a draft for me* (you get an email to check it)
   or *Write it and send it automatically*.

## 7. Facebook & Instagram posting (Meta) + ImageKit reels

1. imagekit.io → sign up → **Developer options → API keys** → copy the private key and URL endpoint
   into Admin → Integrations → ImageKit → **Save & test**.
2. Admin → **Social posting** → open *Step-by-step: set up Meta posting* and follow the 8 steps
   (Instagram professional account linked to the Page → create the "FairCart Posting" app →
   redirect URI → App ID/secret → **Connect Facebook Page**).
3. Switch on **Post product photos automatically** (e.g. 3 a day, 08:00–19:00) and
   **Post product reels automatically** (e.g. 11:00, 17:30). Use *Post photos now* / *Queue a reel*
   to try one first.

## 8. Reset demo data for launch

`database/reset-for-launch.sql` clears demo orders, sold/view counters, messages and campaign logs,
keeping products, users and subscribers. Back up first (cPanel → Backup), then import it in phpMyAdmin.

## Before launch — content to check

- **Bank details**: Admin → Settings still shows account `0000000000` in the development database —
  make sure production has the real account.
- **Home banner** "Flash Sale … Valid 10 September only … get it tomorrow": expired and promises
  next-day delivery; replace it in Admin → Banners.
- **Testimonials / reviews** that were seeded as examples should be hidden until real ones arrive
  (see the commented lines in the reset script).
- **Company registration number** (Admin → Settings → Legal) is required by ECTA and iKhokha.
