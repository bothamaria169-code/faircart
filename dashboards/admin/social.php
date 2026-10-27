<?php
declare(strict_types=1);
$dash_role   = 'admin';
$dash_active = 'social';
$page_title  = 'Social posting — FairCart admin';
require_once __DIR__ . '/../../includes/dash-head.php';
require_once __DIR__ . '/../../lib/Video/ProductReel.php';

$flash = $_SESSION['social_flash'] ?? null;
unset($_SESSION['social_flash']);
$back = fn(string $tone, string $msg) => ($_SESSION['social_flash'] = [$tone, $msg]) && redirect('dashboards/admin/social.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
    $action = (string) ($_POST['action'] ?? '');
    @set_time_limit(240);
    switch ($action) {
        case 'app':
            $id = preg_replace('/\D/', '', (string) ($_POST['app_id'] ?? '')) ?? '';
            set_setting('meta_app_id', $id);
            set_setting('meta_config_id', preg_replace('/\D/', '', (string) ($_POST['config_id'] ?? '')) ?? '');
            if (trim((string) ($_POST['app_secret'] ?? '')) !== '') {
                secret_set('meta_app_secret', trim((string) $_POST['app_secret']));
            }
            $back('ok', 'Meta app saved. Now press "Connect Facebook Page".');
        case 'connect':
            $c = SocialPoster::config();
            if ($c['app_id'] === '' || $c['app_secret'] === '') {
                $back('warn', 'Save the Meta app ID and secret first (step 4 below).');
            }
            $_SESSION['meta_state'] = bin2hex(random_bytes(16));
            redirect(SocialPoster::loginUrl($_SESSION['meta_state']));
        case 'page':
            $pick = null;
            foreach ($_SESSION['meta_pages'] ?? [] as $p) {
                if ((string) $p['id'] === (string) ($_POST['page_id'] ?? '')) {
                    $pick = $p;
                }
            }
            if (!$pick) {
                $back('warn', 'That Page was not in the list — connect again.');
            }
            SocialPoster::savePage($pick);
            unset($_SESSION['meta_pages']);
            $back('ok', 'Connected to ' . $pick['name'] . (isset($pick['instagram_business_account']) ? ' and Instagram @' . $pick['instagram_business_account']['username'] : ' (no Instagram linked to this Page yet)') . '.');
        case 'disconnect':
            SocialPoster::disconnect();
            $back('ok', 'Disconnected. Automatic posting is off.');
        case 'settings':
            foreach (['social_auto', 'social_use_facebook', 'social_use_instagram', 'reel_auto', 'reel_use_facebook', 'reel_use_instagram'] as $k) {
                set_setting($k, !empty($_POST[$k]) ? '1' : '0');
            }
            set_setting('social_per_day', (string) max(1, min(24, (int) ($_POST['social_per_day'] ?? 3))));
            set_setting('social_start_hour', (string) max(0, min(23, (int) ($_POST['social_start_hour'] ?? 8))));
            set_setting('social_end_hour', (string) max(1, min(24, (int) ($_POST['social_end_hour'] ?? 19))));
            set_setting('social_repeat_days', (string) max(1, min(90, (int) ($_POST['social_repeat_days'] ?? 10))));
            set_setting('reel_times', implode(',', array_filter(array_map('trim', explode(',', (string) ($_POST['reel_times'] ?? ''))), fn($t) => (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t))) ?: '11:00,17:30');
            $back('ok', 'Posting settings saved.');
        case 'post':
            $p = (new ProductRepository())->getById((int) ($_POST['product_id'] ?? 0));
            if (!$p) {
                $back('warn', 'Product not found.');
            }
            $lines = [];
            foreach (array_filter(['facebook', 'instagram'], fn($pl) => !empty($_POST[$pl]) && SocialPoster::connected($pl)) as $pl) {
                $lines[] = SocialPoster::postPhotos($p, $pl);
            }
            $back($lines && !preg_grep('/failed/', $lines) ? 'ok' : 'warn', $lines ? implode(' · ', $lines) : 'Tick Facebook and/or Instagram (and connect them) first.');
        case 'reel':
            $queue = json_decode((string) get_setting('reel_queue', '[]'), true) ?: [];
            $queue[] = (int) ($_POST['product_id'] ?? 0);
            set_setting('reel_queue', json_encode(array_values(array_unique($queue))));
            $back('ok', 'Reel queued — the cron job makes and posts it within 5 minutes (it takes a minute or two to render).');
        case 'diagnose':
            $d = SocialPoster::diagnose();
            $back('info', implode(' · ', array_map(fn($k, $v) => "$k: $v", array_keys($d), $d)));
    }
}

$c         = SocialPoster::config();
$fb        = SocialPoster::connected('facebook');
$ig        = SocialPoster::connected('instagram');
$stats     = SocialPoster::stats();
$pages     = $_SESSION['meta_pages'] ?? [];
$products  = db()->query("SELECT p.id, p.name, p.slug FROM products p WHERE p.status = 'published' ORDER BY p.name")->fetchAll();
$nextId    = (int) ($_GET['product'] ?? 0);
$next      = $nextId ? (new ProductRepository())->getById($nextId) : SocialPoster::next('facebook');
$caption   = $next ? SocialPoster::caption($next, 'facebook') : '';
$photos    = $next ? SocialPoster::images($next, 4) : [];
$recent    = db()->query("SELECT s.*, p.name FROM social_posts s LEFT JOIN products p ON p.id = s.product_id ORDER BY s.id DESC LIMIT 15")->fetchAll();
$reelReady = ProductReel::ready();
$stills    = [];
if ($next && $reelReady && !empty($_GET['preview'])) {
    [$price, $was] = SocialPoster::priceLabels($next);
    $stills = (new ProductReel())->preview($next + ['price_label' => $price, 'was_label' => $was], $photos);
}
?>

<div class="dash-head">
  <div>
    <h1>Social posting</h1>
    <p>Products go out to your Facebook Page and Instagram automatically — photo posts through the day and reels at set times.</p>
  </div>
  <a href="integrations.php#imagekit" class="btn-fc btn-fc--light"><i class="fa-solid fa-plug"></i> ImageKit &amp; AI keys</a>
</div>

<?php if ($flash): ?>
  <div class="alert-fc alert-fc--<?= e($flash[0]) ?>" style="word-break:break-word"><i class="fa-solid fa-circle-info"></i><span><?= e($flash[1]) ?></span></div>
<?php endif; ?>

<!-- ── Connection ───────────────────────────────────────────────────── -->
<section class="card-fc card-fc--pad mb-3">
  <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start">
    <div>
      <h2 style="font-size:1.1rem" class="mb-1"><i class="fa-brands fa-meta text-leaf me-1"></i>Meta connection</h2>
      <?php if ($fb): ?>
        <p class="mb-0" style="font-size:.9rem">
          <i class="fa-brands fa-facebook text-leaf"></i> <b><?= e($c['page_name']) ?></b>
          <?= $ig ? ' · <i class="fa-brands fa-instagram text-leaf"></i> <b>@' . e($c['ig_username']) . '</b>' : ' · <span class="text-muted-3">no Instagram linked to this Page</span>' ?>
        </p>
      <?php else: ?>
        <p class="text-muted-2 mb-0" style="font-size:.9rem">Not connected. Follow the one-time setup below (about 10 minutes).</p>
      <?php endif; ?>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="connect">
        <button class="btn-fc" <?= $c['app_id'] === '' ? 'disabled' : '' ?>><i class="fa-brands fa-facebook"></i> <?= $fb ? 'Reconnect' : 'Connect Facebook Page' ?></button></form>
      <?php if ($fb): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="diagnose"><button class="btn-fc btn-fc--ghost"><i class="fa-solid fa-stethoscope"></i> Check connection</button></form>
        <form method="post" onsubmit="return confirm('Disconnect Facebook and Instagram?')"><?= csrf_field() ?><input type="hidden" name="action" value="disconnect"><button class="btn-fc btn-fc--light">Disconnect</button></form>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($pages): ?>
    <form method="post" class="mt-3 pt-3 d-flex flex-wrap gap-2 align-items-end" style="border-top:1px solid var(--line)">
      <?= csrf_field() ?><input type="hidden" name="action" value="page">
      <div class="field mb-0"><label for="pg">Which Page should FairCart post to?</label>
        <select class="select-fc" id="pg" name="page_id">
          <?php foreach ($pages as $p): ?>
            <option value="<?= e((string) $p['id']) ?>"><?= e($p['name']) ?><?= isset($p['instagram_business_account']) ? ' · Instagram @' . e($p['instagram_business_account']['username']) : '' ?></option>
          <?php endforeach; ?>
        </select></div>
      <button class="btn-fc">Use this Page</button>
    </form>
  <?php endif; ?>

  <details class="mt-3 pt-3" style="border-top:1px solid var(--line)" <?= $fb ? '' : 'open' ?>>
    <summary style="cursor:pointer;font-weight:700">Step-by-step: set up Meta posting (one time)</summary>
    <div class="row g-4 mt-1">
      <div class="col-lg-7" style="font-size:.88rem;line-height:1.65">
        <ol class="ps-3 mb-0">
          <li><b>Instagram → Professional account.</b> In the Instagram app: Settings → Account type and tools → <em>Switch to professional account</em> (Business). Then on Facebook open the FairCart Page → Settings → <em>Linked accounts</em> → Instagram → Connect. Posting to Instagram only works through a Page it is linked to.</li>
          <li><b>Create the app.</b> Go to <a href="https://developers.facebook.com/apps" target="_blank" rel="noopener">developers.facebook.com/apps</a> (log in with the Facebook account that is admin of the FairCart Page) → <b>Create app</b> → name it <em>FairCart Posting</em>.</li>
          <li><b>Use case:</b> choose <b>Manage everything on your Page</b> (and also <em>Manage messaging &amp; content on Instagram</em> if it is offered) → pick or create the business portfolio <em>FairCart</em> → <b>Create app</b>.</li>
          <li><b>Redirect address.</b> In the app: <b>Facebook Login for Business → Settings → Valid OAuth Redirect URIs</b>, paste
            <code style="word-break:break-all"><?= e(SocialPoster::callbackUrl()) ?></code> → Save. (No such menu? Use <em>Use cases → Customize → Settings</em>.)</li>
          <li><b>App details.</b> <b>App settings → Basic</b>: App domains <code><?= e(SITE_DOMAIN) ?></code>, Privacy policy URL <code><?= e(absolute_url('privacy.php')) ?></code>, Terms URL <code><?= e(absolute_url('terms.php')) ?></code>, Category <em>Shopping</em> → Save changes. Copy the <b>App ID</b> and <b>App secret</b> (Show) into the form on the right.</li>
          <li><b>Only if Meta shows "Configurations"</b> under Facebook Login for Business: Create configuration → type <em>User access token</em> → tick the permissions <code>pages_show_list, pages_read_engagement, pages_manage_posts, business_management, instagram_basic, instagram_content_publish</code> → copy its <b>Configuration ID</b> into the form.</li>
          <li><b>Connect.</b> Save the form, press <b>Connect Facebook Page</b>, log in, tick the <b>FairCart Page</b> and its <b>Instagram</b> account, allow every permission. The Page key FairCart gets never expires.</li>
          <li><b>Turn it on.</b> Add the ImageKit key under <a href="integrations.php#imagekit">Integrations</a> (Instagram needs JPEGs; reels need MP4), make sure the <a href="integrations.php#cron">cron job</a> runs, then switch on automatic posting below.</li>
        </ol>
        <p class="text-muted-3 mt-2 mb-0" style="font-size:.8rem">The app can stay in Development mode: as its admin you may post to your own Page and Instagram without Meta App Review.</p>
      </div>
      <div class="col-lg-5">
        <form method="post" class="card-fc card-fc--pad" style="background:var(--paper)">
          <?= csrf_field() ?><input type="hidden" name="action" value="app">
          <div class="field"><label for="app_id">App ID</label><input class="input-fc" id="app_id" name="app_id" inputmode="numeric" value="<?= e($c['app_id']) ?>" placeholder="e.g. 1234567890123456"></div>
          <div class="field"><label for="app_secret">App secret</label><input class="input-fc" id="app_secret" name="app_secret" type="password" autocomplete="new-password" placeholder="<?= $c['app_secret'] !== '' ? 'Saved — type to replace' : 'Paste the App secret' ?>"><div class="field__hint">Stored encrypted.</div></div>
          <div class="field"><label for="config_id">Configuration ID <span class="text-muted-3 fw-normal">(only if step 6 applied)</span></label><input class="input-fc" id="config_id" name="config_id" inputmode="numeric" value="<?= e((string) get_setting('meta_config_id', '')) ?>"></div>
          <button class="btn-fc btn-fc--block">Save Meta app</button>
        </form>
      </div>
    </div>
  </details>
</section>

<!-- ── Stats ────────────────────────────────────────────────────────── -->
<div class="stat-grid mb-3">
  <?php foreach (SocialPoster::PLATFORMS as $k => $label): ?>
    <div class="stat-card card-fc card-fc--pad">
      <div style="font-size:.8rem;color:var(--text-3)"><?= e($label) ?></div>
      <div style="font-size:1.5rem;font-weight:800"><?= (int) ($stats[$k]['posted'] ?? 0) ?> <span style="font-size:.85rem;font-weight:500;color:var(--text-3)">posted</span></div>
      <div style="font-size:.78rem;color:var(--text-3)"><?= (int) ($stats[$k]['today'] ?? 0) ?> today<?= !empty($stats[$k]['failed']) ? ' · <span style="color:var(--danger)">' . (int) $stats[$k]['failed'] . ' failed (24h)</span>' : '' ?></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="row g-3">
  <!-- ── Next post ─────────────────────────────────────────────────── -->
  <div class="col-xl-7">
    <section class="card-fc card-fc--pad h-100">
      <div class="d-flex flex-wrap justify-content-between gap-2 align-items-center mb-2">
        <h2 style="font-size:1.05rem;margin:0"><?= $nextId ? 'Product preview' : 'Next product in the queue' ?></h2>
        <form method="get" class="d-flex gap-2">
          <select class="select-fc" name="product" onchange="this.form.submit()" style="max-width:260px">
            <option value="">Next in queue…</option>
            <?php foreach ($products as $p): ?><option value="<?= (int) $p['id'] ?>" <?= $nextId === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?>
          </select>
        </form>
      </div>
      <?php if ($next): ?>
        <div class="row g-3">
          <div class="col-sm-5">
            <div class="d-grid gap-1" style="grid-template-columns:1fr 1fr">
              <?php foreach ($photos as $ph): ?><img src="<?= e(SITE_URL . '/' . $ph) ?>" alt="" style="width:100%;aspect-ratio:1;object-fit:cover;border-radius:8px;background:var(--paper-2)"><?php endforeach; ?>
            </div>
          </div>
          <div class="col-sm-7"><pre style="white-space:pre-wrap;font-family:inherit;font-size:.8rem;background:var(--paper);padding:.75rem;border-radius:8px;margin:0"><?= e($caption) ?></pre></div>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-3 align-items-center">
          <form method="post" class="d-flex flex-wrap gap-2 align-items-center" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').innerHTML='Posting…'">
            <?= csrf_field() ?><input type="hidden" name="action" value="post"><input type="hidden" name="product_id" value="<?= (int) $next['id'] ?>">
            <label style="font-size:.84rem"><input type="checkbox" name="facebook" value="1" <?= $fb ? 'checked' : 'disabled' ?>> Facebook</label>
            <label style="font-size:.84rem"><input type="checkbox" name="instagram" value="1" <?= $ig ? 'checked' : 'disabled' ?>> Instagram</label>
            <button class="btn-fc btn-fc--sm" <?= $fb ? '' : 'disabled' ?>><i class="fa-solid fa-paper-plane"></i> Post photos now</button>
          </form>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="reel"><input type="hidden" name="product_id" value="<?= (int) $next['id'] ?>">
            <button class="btn-fc btn-fc--ghost btn-fc--sm" <?= $fb && $reelReady && ImageKit::configured() ? '' : 'disabled' ?>><i class="fa-solid fa-film"></i> Queue a reel</button></form>
          <a class="btn-fc btn-fc--light btn-fc--sm" href="?product=<?= (int) $next['id'] ?>&preview=1"><i class="fa-solid fa-eye"></i> Preview reel frames</a>
        </div>
        <?php if (!$reelReady): ?><p class="field__hint mt-2 mb-0">Reels need PHP's GD extension with FreeType on the server.</p><?php endif; ?>
        <?php if ($stills): ?>
          <div class="d-flex gap-2 mt-3"><?php foreach ($stills as $jpg): ?><img src="data:image/jpeg;base64,<?= base64_encode($jpg) ?>" alt="Reel frame" style="width:160px;border-radius:8px;border:1px solid var(--line)"><?php endforeach; ?></div>
        <?php endif; ?>
      <?php else: ?>
        <p class="text-muted-2 mb-0">Every in-stock product was posted recently. The queue refills after <?= (int) $c['repeat_days'] ?> days.</p>
      <?php endif; ?>
    </section>
  </div>

  <!-- ── Schedule ──────────────────────────────────────────────────── -->
  <div class="col-xl-5">
    <form method="post" class="card-fc card-fc--pad h-100">
      <?= csrf_field() ?><input type="hidden" name="action" value="settings">
      <h2 style="font-size:1.05rem">Automatic posting</h2>
      <label class="d-flex gap-2 align-items-center fw-bold mb-2" style="font-size:.9rem"><input type="checkbox" name="social_auto" value="1" <?= $c['auto'] ? 'checked' : '' ?> style="accent-color:var(--leaf)"> Post product photos automatically</label>
      <div class="d-flex gap-3 mb-2" style="font-size:.85rem">
        <label><input type="checkbox" name="social_use_facebook" value="1" <?= $c['facebook'] ? 'checked' : '' ?>> Facebook</label>
        <label><input type="checkbox" name="social_use_instagram" value="1" <?= $c['instagram'] ? 'checked' : '' ?>> Instagram</label>
      </div>
      <div class="row g-2">
        <div class="col-4 field"><label for="ppd">Posts a day</label><input class="input-fc" id="ppd" type="number" min="1" max="24" name="social_per_day" value="<?= (int) $c['per_day'] ?>"></div>
        <div class="col-4 field"><label for="sh">From (hour)</label><input class="input-fc" id="sh" type="number" min="0" max="23" name="social_start_hour" value="<?= (int) $c['start'] ?>"></div>
        <div class="col-4 field"><label for="eh">Until (hour)</label><input class="input-fc" id="eh" type="number" min="1" max="24" name="social_end_hour" value="<?= (int) $c['end'] ?>"></div>
      </div>
      <div class="field"><label for="rd">Don't repeat a product within (days)</label><input class="input-fc" id="rd" type="number" min="1" max="90" name="social_repeat_days" value="<?= (int) $c['repeat_days'] ?>"></div>

      <hr>
      <label class="d-flex gap-2 align-items-center fw-bold mb-2" style="font-size:.9rem"><input type="checkbox" name="reel_auto" value="1" <?= $c['reel_auto'] ? 'checked' : '' ?> style="accent-color:var(--leaf)"> Post product reels automatically</label>
      <div class="d-flex gap-3 mb-2" style="font-size:.85rem">
        <label><input type="checkbox" name="reel_use_instagram" value="1" <?= $c['ig_reel'] ? 'checked' : '' ?>> Instagram Reels</label>
        <label><input type="checkbox" name="reel_use_facebook" value="1" <?= $c['fb_reel'] ? 'checked' : '' ?>> Facebook video</label>
      </div>
      <div class="field"><label for="rt">Reel times</label><input class="input-fc" id="rt" name="reel_times" value="<?= e(implode(',', $c['reel_times'])) ?>"><div class="field__hint">24-hour times, comma separated, e.g. 11:00,17:30. One product reel per time.</div></div>
      <?php if (!ImageKit::configured()): ?><div class="alert-fc alert-fc--warn" style="font-size:.82rem"><i class="fa-solid fa-triangle-exclamation"></i><span>Add the ImageKit key under <a href="integrations.php#imagekit">Integrations</a> — Instagram and reels need it.</span></div><?php endif; ?>
      <button class="btn-fc"><i class="fa-solid fa-floppy-disk"></i> Save</button>
    </form>
  </div>
</div>

<!-- ── Recent ───────────────────────────────────────────────────────── -->
<section class="card-fc card-fc--pad mt-3">
  <h2 style="font-size:1.05rem">Recent posts</h2>
  <div class="table-wrap">
    <table class="table-fc">
      <thead><tr><th>When</th><th>Where</th><th>Product</th><th>Result</th></tr></thead>
      <tbody>
        <?php foreach ($recent as $r): ?>
          <tr>
            <td style="font-size:.8rem;white-space:nowrap"><?= e(date('j M H:i', strtotime($r['created_at']))) ?></td>
            <td style="font-size:.82rem"><?= e(SocialPoster::PLATFORMS[$r['platform']] ?? $r['platform']) ?></td>
            <td style="font-size:.82rem"><?= e((string) $r['name']) ?></td>
            <td style="font-size:.8rem"><?= $r['status'] === 'posted' ? '<span class="pill pill--ok">Posted</span>' : '<span class="pill pill--danger">Failed</span> ' . e((string) $r['error']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$recent): ?><tr><td colspan="4" class="text-muted-3">Nothing posted yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php require_once __DIR__ . '/../../includes/dash-foot.php'; ?>
