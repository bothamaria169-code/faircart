<?php
declare(strict_types=1);
$dash_role   = 'admin';
$dash_active = 'integrations';
$page_title  = 'Integrations — FairCart admin';
require_once __DIR__ . '/../../includes/dash-head.php';

/**
 * API keys and settings for every outside service. Keys typed here are stored
 * encrypted (secret_set) and never shown again — a saved key shows as ••••1234,
 * and leaving the box empty keeps it.
 *
 * Each section: [title, icon, intro, fields]. Field: key => [label, type, hint, options].
 * Types: text, secret, toggle, number, select, email.
 */
$sections = [
    'email' => ['Email (cPanel mailbox)', 'fa-envelope', 'Every order email, newsletter and password reset goes out through this mailbox. In cPanel → Email Accounts → Connect Devices you will find the same server and port.', [
        'smtp_host'         => ['Mail server', 'text', 'Usually mail.faircart.co.za', null, SMTP_HOST],
        'smtp_port'         => ['Port', 'select', '465 with SSL is cPanel\'s default. Use 587 + TLS if 465 is blocked.', ['465' => '465', '587' => '587'], (string) SMTP_PORT],
        'smtp_secure'       => ['Encryption', 'select', null, ['ssl' => 'SSL (port 465)', 'tls' => 'STARTTLS (port 587)'], SMTP_SECURE],
        'smtp_user'         => ['Mailbox (username)', 'email', 'The full address, e.g. admin@faircart.co.za. Emails are sent from this address.', null, SMTP_USER],
        'smtp_pass'         => ['Mailbox password', 'secret', SMTP_PASS !== '' ? 'Already set in config/secrets.php — only type here to override it.' : null],
        'smtp_from_name'    => ['Sender name', 'text', 'What people see in their inbox.', null, SMTP_FROM_NAME],
        'admin_alert_email' => ['Send order alerts to', 'email', 'New orders, POP uploads, reseller applications and contact messages.', null, ADMIN_EMAIL],
    ]],
    'pudo' => ['The Courier Guy · Pudo lockers', 'fa-box-archive', 'Shoppers pick a Pudo locker on a map at checkout and pay a live Pudo rate. Get the API key at customer.pudo.co.za → Settings → API keys → Create API Key.', [
        'pudo_enabled'       => ['Offer Pudo lockers at checkout', 'toggle', 'Shows only once an API key is saved.'],
        'pudo_api_key'       => ['Pudo API key', 'secret', null],
        'pudo_sandbox'       => ['Sandbox (testing) mode', 'toggle', 'Only with a sandbox key from sandbox.pudo.co.za. Leave off for real orders.'],
        'pudo_source_type'   => ['Parcels start from', 'select', 'Locker = you drop parcels at a Pudo locker (cheapest, locker-to-locker). Address = Pudo collects from your door.', ['locker' => 'A locker I drop parcels at', 'door' => 'My street address (collection)']],
        'pudo_source_locker' => ['Drop-off locker code', 'text', 'The code of your nearest locker, e.g. CG54. Find it on the checkout map or at pudo.co.za.'],
        'pudo_from_street'   => ['Collection street address', 'text', 'Only for "street address": e.g. 152 Bram Fischer Drive', null, null, 'door'],
        'pudo_from_suburb'   => ['Suburb', 'text', null, null, null, 'door'],
        'pudo_from_city'     => ['City', 'text', null, null, null, 'door'],
        'pudo_from_postcode' => ['Postal code', 'text', null, null, null, 'door'],
        'pudo_from_province' => ['Province code', 'select', null, ['GP' => 'Gauteng', 'WC' => 'Western Cape', 'KZN' => 'KwaZulu-Natal', 'EC' => 'Eastern Cape', 'FS' => 'Free State', 'LP' => 'Limpopo', 'MP' => 'Mpumalanga', 'NW' => 'North West', 'NC' => 'Northern Cape'], null, 'door'],
        'pudo_from_lat'      => ['Latitude', 'text', 'From Google Maps (right-click your address).', null, null, 'door'],
        'pudo_from_lng'      => ['Longitude', 'text', null, null, null, 'door'],
        'pudo_box'           => ['Box size you usually send', 'select', 'Rates are quoted for this size.', array_map(fn($b) => $b['label'] . ' — ' . $b['dims'] . ', up to ' . $b['kg'] . ' kg', Pudo::BOXES)],
        'pudo_fixed_fee'     => ['Fixed locker fee (R)', 'number', '0 = charge the live Pudo rate. Set e.g. 60 to charge one flat price everywhere.'],
        'pudo_markup'        => ['Add to the live rate (R)', 'number', 'Covers packaging. Ignored when a fixed fee is set.'],
        'pudo_fallback_fee'  => ['Fee if Pudo cannot quote (R)', 'number', 'Used when the rate API is down, so checkout never stops.'],
        'pudo_free_threshold'=> ['Free over the free-delivery amount', 'toggle', 'Same threshold as courier delivery (Settings → Free delivery from).'],
        'pudo_sender_name'   => ['Sender name', 'text', 'On the waybill.', null, SITE_NAME],
        'pudo_sender_email'  => ['Sender email', 'email', 'Must be your Pudo account email.'],
        'pudo_sender_phone'  => ['Sender cellphone', 'text', 'Pudo sends the drop-off PIN here, e.g. 0608508785.'],
    ]],
    'ikhokha' => ['iKhokha card payments', 'fa-credit-card', 'Card and Instant EFT at checkout through iKhokha\'s secure page. In the iKhokha dashboard: iK Pay API → copy the Application ID and Application Secret. Orders are only marked paid after iKhokha itself confirms the payment.', [
        'ikhokha_enabled'    => ['Offer card payment at checkout', 'toggle', 'Shows only once both keys are saved.'],
        'ikhokha_app_id'     => ['Application ID', 'secret', null],
        'ikhokha_app_secret' => ['Application secret', 'secret', null],
    ]],
    'groq' => ['Groq AI writer', 'fa-wand-magic-sparkles', 'Writes newsletter copy (and, if you like, social captions) in seconds. Free key at console.groq.com → API Keys.', [
        'groq_api_key'        => ['Groq API key', 'secret', 'Starts with gsk_'],
        'groq_model'          => ['Model', 'select', null, ['llama-3.3-70b-versatile' => 'Llama 3.3 70B (best writing)', 'llama-3.1-8b-instant' => 'Llama 3.1 8B (fastest)', 'openai/gpt-oss-120b' => 'GPT-OSS 120B']],
        'auto_newsletter'     => ['Automatic weekly newsletter', 'select', 'The cron job writes a newsletter with AI from your best sellers, new and sale products.', ['off' => 'Off', 'draft' => 'Write a draft for me to check', 'send' => 'Write it and send it automatically']],
        'auto_newsletter_day' => ['On', 'select', null, ['1' => 'Monday', '2' => 'Tuesday', '3' => 'Wednesday', '4' => 'Thursday', '5' => 'Friday', '6' => 'Saturday', '7' => 'Sunday'], '4'],
        'auto_newsletter_hour'=> ['At (hour, 0–23)', 'number', 'South African time.', null, '9'],
        'social_ai_captions'  => ['AI captions for Facebook & Instagram', 'toggle', 'Adds a fresh AI-written line to each social post.'],
    ]],
    'imagekit' => ['ImageKit (images & reels)', 'fa-photo-film', 'Hosts product photos as Instagram-ready JPEGs and turns reels into MP4 for Instagram and Facebook. imagekit.io → Developer options → API keys.', [
        'imagekit_private_key' => ['Private key', 'secret', 'Starts with private_'],
        'imagekit_public_key'  => ['Public key', 'text', 'Starts with public_ (optional, for reference).'],
        'imagekit_endpoint'    => ['URL endpoint', 'text', 'e.g. https://ik.imagekit.io/yourid'],
        'imagekit_folder'      => ['Folder', 'text', 'Where FairCart files go in your ImageKit media library.', null, 'faircart'],
    ]],
];

$flash = $_SESSION['int_flash'] ?? null;
unset($_SESSION['int_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
    $section = (string) ($_POST['section'] ?? '');
    $action  = (string) ($_POST['action'] ?? 'save');
    if (isset($sections[$section])) {
        foreach ($sections[$section][3] as $key => $f) {
            $type = $f[1];
            if ($type === 'toggle') {
                set_setting($key, !empty($_POST[$key]) ? '1' : '0');
            } elseif ($type === 'secret') {
                $v = trim((string) ($_POST[$key] ?? ''));
                if (!empty($_POST[$key . '__clear'])) {
                    secret_set($key, '');
                } elseif ($v !== '') {
                    secret_set($key, $v);
                }
            } elseif (array_key_exists($key, $_POST)) {
                $v = trim((string) $_POST[$key]);
                if ($type === 'select' && !array_key_exists($v, $f[3])) {
                    continue;
                }
                set_setting($key, $type === 'number' ? (string) max(0, (float) $v) : mb_substr(strip_tags($v), 0, 255));
            }
        }
        if ($section === 'pudo') {
            // A new key or mode may use a different API path or locker list.
            set_setting('pudo_prefix', '');
            @unlink(__DIR__ . '/../../uploads/cache/pudo-lockers.json');
        }
        $msg = ['ok', $sections[$section][0] . ' saved.'];

        try {
            if ($action === 'test') {
                $msg = match ($section) {
                    'email' => (function () {
                        $to = safe_email((string) ($_POST['test_to'] ?? '')) ?: (string) current_user()['email'];
                        $ok = Mailer::send($to, 'FairCart test email', email_wrap('<p>This is a test from FairCart. If you can read it, email is working.</p><p>Sent ' . date('j M Y H:i') . ' via ' . e(Mailer::usingSmtp() ? mail_config()['host'] : 'PHP mail()') . '.</p>'));
                        return $ok ? ['ok', "Saved, and a test email was sent to $to. Check the inbox and the spam folder."]
                                   : ['danger', 'Saved, but the test failed: ' . (Mailer::$lastError ?: 'the server refused to send.')];
                    })(),
                    'pudo' => (function () {
                        $n = count(Pudo::lockers(true));
                        $line = "Connected to Pudo: $n lockers loaded.";
                        if (get_setting('pudo_source_type', 'locker') === 'door' || get_setting('pudo_source_locker')) {
                            $lockers = Pudo::lockers();
                            $sample  = $lockers[intdiv(count($lockers), 2)]['code'] ?? '';
                            $rate    = $sample ? Pudo::liveRate($sample) : null;
                            $line   .= $rate ? ' Sample rate to ' . $sample . ': ' . format_price($rate['rate']) . ' (' . $rate['name'] . ').' : ' Could not get a sample rate — check the drop-off locker code.';
                        }
                        return ['ok', $line];
                    })(),
                    'ikhokha' => (function () {
                        if (!IKhokha::configured()) {
                            return ['danger', 'Add both iKhokha keys first.'];
                        }
                        $fake = ['id' => 0, 'order_number' => 'TEST' . date('His'), 'total_amount' => 5];
                        // createPaylink() updates order 0, which does not exist — harmless.
                        $url = IKhokha::createPaylink($fake);
                        return ['ok', 'iKhokha accepted the keys and made a R5.00 test payment link: ' . $url . ' (nothing is charged unless someone pays it).'];
                    })(),
                    'groq' => (function () {
                        $d = GroqWriter::json('Reply with JSON {"line": "..."}', 'Write one friendly sentence welcoming shoppers to FairCart, a South African online shop.');
                        return ['ok', 'Groq is working: "' . ($d['line'] ?? '?') . '"'];
                    })(),
                    'imagekit' => ['ok', ImageKit::test()],
                    default => $msg,
                };
            }
        } catch (Throwable $e) {
            $msg = ['danger', 'Saved, but the test failed: ' . $e->getMessage()];
        }
        $_SESSION['int_flash'] = $msg;
    }
    redirect('dashboards/admin/integrations.php#' . $section);
}

$status = [
    'email'    => Mailer::usingSmtp(),
    'pudo'     => Pudo::configured(),
    'ikhokha'  => IKhokha::configured(),
    'groq'     => GroqWriter::configured(),
    'imagekit' => ImageKit::configured(),
];
$phpBin = IS_PRODUCTION ? '/usr/local/bin/php' : PHP_BINARY;
$cronPath = realpath(__DIR__ . '/../../scripts/cron.php');
?>

<div class="dash-head">
  <div>
    <h1>Integrations</h1>
    <p>API keys for email, delivery, payments, AI and media. Keys are stored encrypted and never shown again.</p>
  </div>
  <a href="social.php" class="btn-fc btn-fc--light"><i class="fa-brands fa-meta"></i> Facebook &amp; Instagram</a>
</div>

<?php if ($flash): ?>
  <div class="alert-fc alert-fc--<?= e($flash[0]) ?>" style="word-break:break-word"><i class="fa-solid fa-circle-info"></i><span><?= e($flash[1]) ?></span></div>
<?php endif; ?>

<div class="d-flex flex-wrap gap-2 mb-3">
  <?php foreach ($sections as $id => $s): ?>
    <a href="#<?= $id ?>" class="chip"><i class="fa-solid <?= $s[1] ?>"></i> <?= e(explode(' (', $s[0])[0]) ?>
      <span class="ms-1" style="color:<?= $status[$id] ? 'var(--leaf)' : 'var(--text-3)' ?>"><i class="fa-solid <?= $status[$id] ? 'fa-circle-check' : 'fa-circle' ?>"></i></span></a>
  <?php endforeach; ?>
  <a href="#cron" class="chip"><i class="fa-solid fa-clock"></i> Cron job</a>
</div>

<div class="row g-3">
<?php foreach ($sections as $id => [$title, $icon, $intro, $fields]): ?>
  <div class="col-xl-6" id="<?= $id ?>">
    <form method="post" class="card-fc card-fc--pad h-100" autocomplete="off">
      <?= csrf_field() ?>
      <input type="hidden" name="section" value="<?= $id ?>">
      <div class="d-flex justify-content-between align-items-start gap-2">
        <h2 style="font-size:1.05rem"><i class="fa-solid <?= $icon ?> me-1 text-leaf"></i><?= e($title) ?></h2>
        <span class="pill <?= $status[$id] ? 'pill--ok' : 'pill--muted' ?>"><?= $status[$id] ? 'Connected' : 'Not set up' ?></span>
      </div>
      <p class="text-muted-2" style="font-size:.85rem"><?= e($intro) ?></p>

      <?php foreach ($fields as $key => $f):
        [$label, $type] = $f;
        $hint    = $f[2] ?? null;
        $opts    = $f[3] ?? [];
        $default = $f[4] ?? null;
        $showIf  = $f[5] ?? null;
        $value   = $type === 'secret' ? '' : (string) get_setting($key, $default === null ? '' : (string) $default); ?>
        <div class="field" <?= $showIf ? 'data-show-if="' . e($showIf) . '"' : '' ?>>
          <?php if ($type === 'toggle'): ?>
            <label style="display:flex;gap:.55rem;align-items:center;font-size:.87rem;cursor:pointer;font-weight:600">
              <input type="checkbox" name="<?= $key ?>" value="1" <?= get_setting($key, in_array($key, ['pudo_enabled', 'ikhokha_enabled', 'pudo_free_threshold'], true) ? '1' : '0') === '1' ? 'checked' : '' ?> style="accent-color:var(--leaf)">
              <?= e($label) ?>
            </label>
          <?php else: ?>
            <label for="<?= $key ?>"><?= e($label) ?></label>
            <?php if ($type === 'select'): ?>
              <select class="select-fc" id="<?= $key ?>" name="<?= $key ?>">
                <?php foreach ($opts as $ov => $ol): ?>
                  <option value="<?= e((string) $ov) ?>" <?= (string) $ov === $value ? 'selected' : '' ?>><?= e($ol) ?></option>
                <?php endforeach; ?>
              </select>
            <?php elseif ($type === 'secret'): ?>
              <?php $saved = secret_hint($key); ?>
              <input class="input-fc" type="password" id="<?= $key ?>" name="<?= $key ?>" autocomplete="new-password"
                     placeholder="<?= $saved !== '' ? 'Saved (' . e($saved) . ') — type to replace' : 'Paste it here' ?>">
              <?php if ($saved !== ''): ?>
                <label class="mt-1" style="font-size:.76rem;color:var(--text-3);display:flex;gap:.35rem;align-items:center">
                  <input type="checkbox" name="<?= $key ?>__clear" value="1"> Remove the saved key
                </label>
              <?php endif; ?>
            <?php else: ?>
              <input class="input-fc" type="<?= $type === 'number' ? 'number' : ($type === 'email' ? 'email' : 'text') ?>" <?= $type === 'number' ? 'step="1" min="0"' : '' ?>
                     id="<?= $key ?>" name="<?= $key ?>" value="<?= e($value) ?>">
            <?php endif; ?>
          <?php endif; ?>
          <?php if ($hint): ?><div class="field__hint"><?= e($hint) ?></div><?php endif; ?>
        </div>
      <?php endforeach; ?>

      <?php if ($id === 'email'): ?>
        <div class="field">
          <label for="test_to">Send the test to</label>
          <input class="input-fc" type="email" id="test_to" name="test_to" value="<?= e((string) $user['email']) ?>">
        </div>
      <?php endif; ?>

      <div class="d-flex flex-wrap gap-2 mt-2">
        <button class="btn-fc" type="submit" name="action" value="save"><i class="fa-solid fa-floppy-disk"></i> Save</button>
        <button class="btn-fc btn-fc--ghost" type="submit" name="action" value="test"><i class="fa-solid fa-plug-circle-check"></i> Save &amp; test</button>
      </div>
    </form>
  </div>
<?php endforeach; ?>

  <div class="col-xl-6" id="cron">
    <div class="card-fc card-fc--pad h-100">
      <h2 style="font-size:1.05rem"><i class="fa-solid fa-clock me-1 text-leaf"></i>Cron job (one line runs everything)</h2>
      <p class="text-muted-2" style="font-size:.85rem">
        Sends scheduled newsletters in batches, writes the weekly AI newsletter, posts to Facebook &amp; Instagram,
        makes reels, refreshes reseller tiers and the Pudo locker list. In cPanel → <b>Cron Jobs</b> → Common settings
        <b>Once Per Five Minutes</b>, then paste this command:
      </p>
      <pre style="background:var(--ink);color:#E7F4EF;padding:.8rem;border-radius:var(--r-sm);font-size:.78rem;white-space:pre-wrap;word-break:break-all"><?= e($phpBin . ' ' . ($cronPath ?: '/home/wyimzdyd/public_html/scripts/cron.php') . ' >> ' . (IS_PRODUCTION ? '/home/wyimzdyd/logs/faircart-cron.log' : 'cron.log') . ' 2>&1') ?></pre>
      <?php $last = get_setting('cron_last_run'); ?>
      <div class="summary-row"><span>Last run</span><b><?= $last ? e(time_ago($last)) . ' · ' . e(date('j M H:i', strtotime($last))) : 'Never — add the cron job above' ?></b></div>
      <?php if ($out = get_setting('cron_last_output')): ?>
        <pre class="mt-2" style="background:var(--paper-2);padding:.6rem;border-radius:var(--r-sm);font-size:.74rem;white-space:pre-wrap;max-height:200px;overflow:auto"><?= e($out) ?></pre>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
// Address fields for Pudo only matter when parcels start from a street address.
(function () {
  const src = document.getElementById('pudo_source_type');
  if (!src) return;
  const sync = () => document.querySelectorAll('[data-show-if="door"]').forEach((el) => { el.hidden = src.value !== 'door'; });
  src.addEventListener('change', sync);
  sync();
})();
</script>

<?php require_once __DIR__ . '/../../includes/dash-foot.php'; ?>
