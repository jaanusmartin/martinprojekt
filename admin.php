<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
app_start_session();
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store, private');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(app_csrf_token(), $token)) {
        $error = 'Turvakontroll ebaõnnestus. Värskenda lehte ja proovi uuesti.';
    } else {
        try {
            app_rate_limit('admin_login', 8, 900);
            $password = $_POST['password'] ?? '';
            if (!is_string($password) || !password_verify($password, ADMIN_PASSWORD_HASH)) {
                $error = 'Parool ei ole õige.';
            } else {
                session_regenerate_id(true);
                $_SESSION['is_admin'] = true;
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                header('Location: admin.php');
                exit;
            }
        } catch (DomainException $exception) {
            $error = $exception->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (is_string($token) && hash_equals(app_csrf_token(), $token)) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], '', (bool) $params['secure'], (bool) $params['httponly']);
        }
        session_destroy();
        header('Location: admin.php');
        exit;
    }
    $error = 'Väljalogimise turvakontroll ebaõnnestus.';
}

$csrf = app_csrf_token();
?>
<!doctype html>
<html lang="et">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= app_html($csrf) ?>">
  <title><?= app_html(APP_NAME) ?> — tugispetsialisti töölaud</title>
  <link rel="stylesheet" href="style.css">
  <?php if (app_is_admin()): ?><script src="admin.js" defer></script><?php endif; ?>
</head>
<body class="admin-page">
<?php if (!app_is_admin()): ?>
  <main class="login-shell">
    <a class="brand" href="index.php"><span class="brand-mark" aria-hidden="true">✦</span><span>MeeleKompass</span></a>
    <section class="content-card login-card">
      <p class="eyebrow">Tugispetsialistile</p><h1>Tere tulemast tagasi</h1><p>Logi sisse, et näha anonüümset koondpilti ja vastata pöördumistele.</p>
      <?php if ($error !== ''): ?><p class="form-message error-message" role="alert"><?= app_html($error) ?></p><?php endif; ?>
      <form method="post" action="admin.php">
        <input type="hidden" name="csrf_token" value="<?= app_html($csrf) ?>">
        <label class="field-label" for="password">Parool</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required maxlength="200">
        <button class="button button-primary" type="submit" name="login" value="1">Logi sisse</button>
      </form>
      <p class="privacy-hint">Õpilaste pöördumised on anonüümsed. Ära küsi vastuses õpilase isikuandmeid.</p>
    </section>
    <a class="back-link" href="index.php">← Tagasi õpilase vaatesse</a>
  </main>
<?php else: ?>
  <header class="site-header admin-header">
    <a class="brand" href="admin.php"><span class="brand-mark" aria-hidden="true">✦</span><span>MeeleKompass <small>tugispetsialisti vaade</small></span></a>
    <form method="post" action="admin.php"><input type="hidden" name="csrf_token" value="<?= app_html($csrf) ?>"><button class="button button-secondary" type="submit" name="logout" value="1">Logi välja</button></form>
  </header>
  <main class="admin-main">
    <div class="page-heading"><p class="eyebrow">Kooli heaolu</p><h1>Ülevaade</h1><p>Anonüümne üldpilt viimase 30 päeva check-in'idest ja õpilaste pöördumistest.</p></div>
    <section class="stats-grid">
      <article class="content-card stats-card"><div class="card-heading"><div><p class="eyebrow">Meeleolu trend</p><h2>Keskmine skoor</h2></div><span class="stat-unit">1–5</span></div><div class="chart-wrap"><svg id="admin-mood-chart" viewBox="0 0 640 240" role="img" aria-label="Viimase 30 päeva keskmise meeleoluskoori graafik"></svg></div><p class="chart-caption" id="mood-chart-caption"></p></article>
      <article class="content-card stats-card"><div class="card-heading"><div><p class="eyebrow">Stressiallikad</p><h2>Kategooriate jaotus</h2></div></div><div id="category-chart" class="category-chart"></div></article>
    </section>
    <section class="content-card tickets-card"><div class="card-heading"><div><p class="eyebrow">Anonüümsed pöördumised</p><h2>Õpilaste sõnumid</h2></div><button class="button button-secondary" id="admin-refresh" type="button">Värskenda</button></div><p id="admin-message" class="form-message" role="status"></p><div id="ticket-list" class="admin-ticket-list"></div></section>
    <p class="privacy-hint admin-privacy">Statistika põhineb vabatahtlikel anonüümsetel check-in'idel. Väikese vastajate arvu korral väldi järeldusi üksikute õpilaste kohta.</p>
  </main>
  <div class="modal-backdrop" id="ticket-modal" hidden>
    <section class="ticket-modal content-card" role="dialog" aria-modal="true" aria-labelledby="modal-ticket-title">
      <div class="card-heading"><div><p class="eyebrow">Anonüümne pöördumine</p><h2 id="modal-ticket-title"></h2></div><button class="icon-button" id="modal-close" type="button" aria-label="Sulge">×</button></div>
      <div id="admin-conversation" class="conversation-messages admin-conversation"></div>
      <form id="admin-reply-form"><label class="field-label" for="admin-reply">Sinu toetav vastus</label><textarea id="admin-reply" rows="4" maxlength="2000" required></textarea><button class="button button-primary" type="submit">Saada vastus</button></form>
      <label class="field-label status-label" for="ticket-status">Pöördumise staatus</label><select id="ticket-status"><option value="new">Uus</option><option value="answered">Vastatud</option><option value="closed">Suletud</option></select>
    </section>
  </div>
<?php endif; ?>
</body>
</html>
