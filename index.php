<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
app_start_session();
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
?>
<!doctype html>
<html lang="et">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= app_html(app_csrf_token()) ?>">
  <meta name="description" content="Rahulik ja anonüümne tugi koolipäeva keskel.">
  <title>MeeleKompass — sinu rahulik hetk</title>
  <link rel="stylesheet" href="style.css">
  <script src="app.js" defer></script>
</head>
<body>
  <header class="site-header">
    <a class="brand" href="index.php" aria-label="MeeleKompass avaleht">
      <span class="brand-mark" aria-hidden="true">✦</span><span>MeeleKompass</span>
    </a>
    <nav class="main-nav" aria-label="Põhinavigatsioon">
      <button class="nav-link active" data-panel="home">Avaleht</button>
      <button class="nav-link" data-panel="checkin">Enesetunne</button>
      <button class="nav-link" data-panel="tools">SOS-tööriistad</button>
      <button class="nav-link" data-panel="support">Anonüümne tugi</button>
    </nav>
    <button class="theme-toggle" id="theme-toggle" type="button" aria-label="Lülita tumedat režiimi">◐</button>
  </header>

  <main>
    <section class="panel active" id="panel-home" aria-labelledby="welcome-title">
      <div class="hero">
        <div class="hero-copy">
          <p class="eyebrow">Sinu jaoks. Just praegu.</p>
          <h1 id="welcome-title">Võta hetk.<br><span>Sa ei pea kõike korraga lahendama.</span></h1>
          <p class="hero-text">Siin saad korraks peatuda, oma enesetunnet märgata ja leida väikese järgmise sammu. See on sinu ruum — anonüümne ja hinnanguvaba.</p>
          <button class="button button-primary" data-go="tools" type="button">Vajan praegu tuge <span aria-hidden="true">→</span></button>
        </div>
        <div class="hero-art" aria-hidden="true">
          <div class="sun"></div><div class="hill hill-back"></div><div class="hill hill-front"></div>
          <span class="art-spark spark-one">✦</span><span class="art-spark spark-two">✧</span>
        </div>
      </div>

      <div class="section-heading">
        <div><p class="eyebrow">Sinu järgmine samm</p><h2>Mis aitaks sind täna?</h2></div>
      </div>
      <div class="feature-grid">
        <button class="feature-card" data-go="checkin" type="button">
          <span class="feature-icon icon-peach" aria-hidden="true">☼</span><span><strong>Peatu ja märka</strong><small>Kiire enesetunde check-in</small></span><span class="arrow">↗</span>
        </button>
        <button class="feature-card" data-go="tools" type="button">
          <span class="feature-icon icon-sage" aria-hidden="true">〰</span><span><strong>Rahusta oma meelt</strong><small>Hingamine ja maandamine</small></span><span class="arrow">↗</span>
        </button>
        <button class="feature-card" data-go="support" type="button">
          <span class="feature-icon icon-blue" aria-hidden="true">♡</span><span><strong>Räägi kellegagi</strong><small>Anonüümne sõnum tugispetsialistile</small></span><span class="arrow">↗</span>
        </button>
      </div>
      <aside class="gentle-note"><span aria-hidden="true">🌱</span><p><strong>Väike meeldetuletus</strong><br>Abi küsimine on julgus, mitte nõrkus. Kui tunned, et oled otseses ohus, pöördu kohe usaldusväärse täiskasvanu või hädaabinumbril 112 poole.</p></aside>
    </section>

    <section class="panel" id="panel-checkin" aria-labelledby="checkin-title">
      <div class="page-heading"><p class="eyebrow">Enesetunne</p><h1 id="checkin-title">Kuidas sul praegu läheb?</h1><p>Siin ei ole õigeid ega valesid vastuseid. Vali see, mis tundub praegu kõige lähem.</p></div>
      <div class="content-card">
        <form id="checkin-form">
          <fieldset class="mood-field"><legend>Vali oma meeleolu</legend><div class="mood-options">
            <label class="mood-option"><input type="radio" name="mood" value="1" required><span class="mood-face">😣</span><span>Väga raske</span></label>
            <label class="mood-option"><input type="radio" name="mood" value="2"><span class="mood-face">🙁</span><span>Pigem raske</span></label>
            <label class="mood-option"><input type="radio" name="mood" value="3"><span class="mood-face">😐</span><span>Enam-vähem</span></label>
            <label class="mood-option"><input type="radio" name="mood" value="4"><span class="mood-face">🙂</span><span>Pigem hästi</span></label>
            <label class="mood-option"><input type="radio" name="mood" value="5"><span class="mood-face">😊</span><span>Hästi</span></label>
          </div></fieldset>
          <label class="field-label" for="stress-category">Mis mõjutab su enesetunnet kõige rohkem?</label>
          <select id="stress-category" required>
            <option value="">Vali üks</option>
            <option>Kontrolltööd/õppimine</option><option>Suhted/üksindus</option><option>Väsimus/unepuudus</option><option>Ärevus/pinge</option><option>Muu</option>
          </select>
          <button class="button button-primary" type="submit">Salvesta enesetunne</button>
          <p class="privacy-hint">Sinu isiklik ajalugu jääb ainult sellesse brauserisse. Koolile saadetakse ainult anonüümne kuupäeva, skoori ja kategooria koondkirje.</p>
          <p class="form-message" id="checkin-message" role="status"></p>
        </form>
      </div>
      <div class="content-card history-card"><div class="card-heading"><div><p class="eyebrow">Sinu ajalugu</p><h2>Viimased 14 päeva</h2></div><span class="history-legend">Meeleolu 1–5</span></div>
        <div class="chart-wrap"><svg id="mood-chart" viewBox="0 0 640 220" role="img" aria-label="Sinu viimase 14 päeva meeleolugraafik"></svg></div>
        <div id="history-list" class="history-list"></div>
      </div>
    </section>

    <section class="panel" id="panel-tools" aria-labelledby="tools-title">
      <div class="page-heading"><p class="eyebrow">SOS-tööriistad</p><h1 id="tools-title">Teeme ruumi rahulikumale hetkele.</h1><p>Vali üks harjutus. Sa võid igal hetkel pausi teha või lõpetada.</p></div>
      <div class="tool-tabs" role="tablist" aria-label="Vali harjutus">
        <button class="tool-tab active" role="tab" aria-selected="true" data-tool="breathing">Hingamine</button>
        <button class="tool-tab" role="tab" aria-selected="false" data-tool="grounding">5–4–3–2–1 maandamine</button>
        <button class="tool-tab" role="tab" aria-selected="false" data-tool="worry">Murepurustaja</button>
      </div>
      <div class="tool-panel active" id="tool-breathing">
        <div class="content-card breath-card">
          <div class="card-heading"><div><p class="eyebrow">Hingamisharjutus</p><h2>Hinga omas tempos</h2><p>Kui hinge kinni hoidmine ei tundu hea, hinga lihtsalt aeglaselt ja mugavalt.</p></div></div>
          <div class="breathing-controls"><label for="breath-pattern">Harjutus</label><select id="breath-pattern"><option value="478">4–7–8 hingamine</option><option value="box">Ruuthingamine</option></select></div>
          <div class="breath-stage"><div class="breath-orbit"><div class="breath-circle" id="breath-circle"><span id="breath-cue">Valmis?</span></div></div><p id="breath-timer" aria-live="polite">Vali harjutus ja alusta</p></div>
          <div class="button-row"><button class="button button-primary" id="breath-start" type="button">Alusta</button><button class="button button-secondary" id="breath-stop" type="button" disabled>Lõpeta</button></div>
        </div>
      </div>
      <div class="tool-panel" id="tool-grounding" hidden>
        <div class="content-card"><p class="eyebrow">5–4–3–2–1 tehnika</p><h2>Tule tagasi siia ja praegu</h2><p>Vaata rahulikult enda ümber ja pane tähele seda, mida meeled sulle ütlevad.</p>
          <div class="grounding-progress"><span id="grounding-count">Samm 1 / 5</span><div class="progress-track"><span id="grounding-progress"></span></div></div>
          <div id="grounding-step" class="grounding-step"><label for="grounding-answer">Nimeta 5 asja, mida sa näed.</label><textarea id="grounding-answer" rows="3" placeholder="Kirjuta või ütle need mõttes..." maxlength="500"></textarea></div>
          <div class="button-row"><button class="button button-primary" id="grounding-next" type="button">Järgmine samm</button><button class="button button-secondary" id="grounding-reset" type="button">Alusta uuesti</button></div>
          <p class="form-message" id="grounding-message" role="status"></p>
        </div>
      </div>
      <div class="tool-panel" id="tool-worry" hidden>
        <div class="content-card worry-card"><p class="eyebrow">Murepurustaja</p><h2>Pane mõte korraks endast välja.</h2><p>Kirjuta, mis su peas keerleb. See tekst ei lahku sinu seadmest ega salvestu kuhugi.</p>
          <label class="sr-only" for="worry-text">Sinu mure või ärev mõte</label><textarea id="worry-text" rows="5" maxlength="1000" placeholder="Siia võid kirjutada kõike, mis sul mõttes on..."></textarea>
          <div class="button-row"><button class="button button-primary" id="crush-worry" type="button">Purusta see mõte</button><button class="button button-secondary" id="clear-worry" type="button">Tühjenda</button></div>
          <p class="crush-result" id="crush-result" aria-live="polite"></p>
        </div>
      </div>
    </section>

    <section class="panel" id="panel-support" aria-labelledby="support-title">
      <div class="page-heading"><p class="eyebrow">Anonüümne tugi</p><h1 id="support-title">Sa ei pea sellega üksi olema.</h1><p>Kirjuta kooli tugispetsialistile ilma nime ja e-posti aadressita. Sinu jälgimiskoodiga saad vastust lugeda ja vestlust jätkata.</p></div>
      <div class="support-grid">
        <div class="content-card"><p class="eyebrow">Uus pöördumine</p><h2>Kirjuta oma murest</h2>
          <form id="ticket-create-form"><label class="field-label" for="ticket-message">Sinu sõnum tugispetsialistile</label><textarea id="ticket-message" rows="6" maxlength="2000" required placeholder="Kirjuta nii palju või vähe, kui soovid..."></textarea><p class="privacy-hint">Ära lisa sõnumisse oma nime ega muid andmeid, mille järgi sind ära tuntaks.</p><button class="button button-primary" type="submit">Saada anonüümselt</button><p class="form-message" id="ticket-create-message" role="status"></p></form>
          <div id="ticket-code-card" class="ticket-code-card" hidden><p>Sinu jälgimiskood</p><strong id="ticket-code"></strong><p>Kirjuta see kood üles — ainult selle abil saad vestluse hiljem avada. Koodi ei salvestata sinu seadmesse.</p></div>
        </div>
        <div class="content-card"><p class="eyebrow">Jätka vestlust</p><h2>Kas sul on jälgimiskood?</h2>
          <form id="ticket-load-form"><label class="field-label" for="ticket-code-input">Jälgimiskood</label><input id="ticket-code-input" type="text" inputmode="text" autocomplete="off" placeholder="MK-ABCD-EFGH" maxlength="12" required><button class="button button-secondary" type="submit">Ava vestlus</button><p class="form-message" id="ticket-load-message" role="status"></p></form>
          <div id="conversation" class="conversation" hidden><div class="conversation-heading"><strong id="conversation-status"></strong><button class="text-button" id="refresh-ticket" type="button">Värskenda</button></div><div id="conversation-messages" class="conversation-messages" aria-live="polite"></div><form id="ticket-reply-form"><label class="sr-only" for="ticket-reply">Lisa sõnum vestlusse</label><textarea id="ticket-reply" rows="3" maxlength="2000" placeholder="Kirjuta vastus..." required></textarea><button class="button button-primary" type="submit">Saada sõnum</button><p class="form-message" id="ticket-reply-message" role="status"></p></form></div>
        </div>
      </div>
      <aside class="gentle-note"><span aria-hidden="true">💛</span><p>Kui sul on praegu oht endale või kellelegi teisele viga teha, ära jää üksi — räägi kohe täiskasvanuga või helista 112. Veebirakendus ei ole kriisiabi ega asenda professionaalset abi.</p></aside>
    </section>
  </main>
  <footer><span>© <?= date('Y') ?> MeeleKompass</span><span>Hoolivus algab märkamistest.</span><a href="admin.php">Tugispetsialisti sisselogimine</a></footer>
</body>
</html>
