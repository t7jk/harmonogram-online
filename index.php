<?php $f = __DIR__ . '/data/ustawienia.php'; $def = is_file($f) ? substr(file_get_contents($f), strpos(file_get_contents($f), "\n") + 1) : file_get_contents(__DIR__ . '/default.json'); $v = filemtime(__DIR__ . '/assets/app.js'); ?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Harmonogram online</title>
<link rel="stylesheet" href="assets/style.css?v=<?= $v ?>">
</head>
<body>
<header class="top">
  <h1>Harmonogram zajęć <span id="rokLabel"></span></h1>
  <nav class="tabs" role="tablist">
    <button class="tab active" data-tab="poziomo">Harmonogram poziomy</button>
    <button class="tab" data-tab="pionowo">Harmonogram pionowy</button>
    <button class="tab" data-tab="ustawienia">Ustawienia</button>
  </nav>
</header>
<main>
  <div id="msg" class="msg" hidden></div>

  <?php foreach (['poziomo' => 'poziomy', 'pionowo' => 'pionowy'] as $k => $nazwa): ?>
  <section class="panel" id="tab-<?= $k ?>" <?= $k === 'poziomo' ? '' : 'hidden' ?>>
    <div class="bar"><h2 id="tyt-<?= $k ?>"></h2>
      <button class="btn primary dl" data-layout="<?= $k ?>">⬇ Pobierz PDF</button></div>
    <div class="warns" id="warn-<?= $k ?>"></div>
    <div class="scroll"><div id="out-<?= $k ?>"></div></div>
    <div class="bar bottom"><span class="info" id="info-<?= $k ?>"></span>
      <button class="btn primary dl" data-layout="<?= $k ?>">⬇ Pobierz PDF</button></div>
  </section>
  <?php endforeach; ?>

  <section class="panel" id="tab-ustawienia" hidden>
    <div class="bar"><h2>Ustawienia</h2>
      <div class="btns">
        <button class="btn" id="btnLoad">Wczytaj ustawienia</button>
        <button class="btn primary" id="btnSave">Zapisz ustawienia</button>
      </div></div>

    <div class="card"><h3>Ustawienia ogólne</h3>
      <div class="grid">
        <label>Rok szkolny<input id="f_rok" placeholder="2026/2027"></label>
        <label>Pierwszy dzień zajęć<input id="f_start" type="date"></label>
        <label>Liczba spotkań<input id="f_n" type="number" min="1" max="100"></label>
        <label>Koniec zajęć w szkołach<input id="f_koniec" type="date"></label>
        <label>Nazwa pliku PDF<input id="f_plik" placeholder="harmonogram"></label>
      </div>
      <div class="days"><span>Dni tygodnia:</span>
        <?php foreach (['Pon', 'Wt', 'Śr', 'Czw', 'Pt', 'Sob', 'Niedz'] as $i => $d): ?>
        <label class="chip"><input type="checkbox" class="f_dzien" value="<?= $i + 1 ?>"><?= $d ?></label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card"><h3>Święta stałe <small>(co roku w tym samym dniu; jeden dzień = ta sama data w „Od” i „Do”)</small></h3>
      <div class="tbl" data-key="stale" data-cols="od,do,opis"></div></div>

    <div class="card"><h3>Święta ruchome <small>(zależne od daty Wielkanocy, np. przerwa wielkanocna, Boże Ciało)</small></h3>
      <div class="auto">
        <button class="btn" id="btnAuto">⟳ Przelicz święta automatycznie dla roku szkolnego</button>
      </div>
      <div class="tbl" data-key="ruchome" data-cols="od,do,opis"></div></div>

    <div class="card"><h3>Ferie zimowe <small>(zakres od–do wg kalendarza MEN dla Twojego województwa)</small></h3>
      <div class="tbl" data-key="ferie" data-cols="od,do,opis"></div></div>

    <div class="card"><h3>Dodatkowe dni wolne (cała szkoła) <small>(dni, w które zajęcia się nie odbywają)</small></h3>
      <div class="tbl" data-key="wyjatki" data-cols="data,opis"></div></div>

    <div class="card"><h3>Dodatkowe dni nauki (cała szkoła) <small>(zajęcia mimo dnia wolnego)</small></h3>
      <div class="tbl" data-key="dodatki" data-cols="data,opis"></div></div>

    <div class="card"><h3>Dni braku zajęć Trenera <small>(dotyczą jednego trenera)</small></h3>
      <div class="tbl" data-key="indWyj" data-cols="data,osoba"></div></div>

    <div class="card"><h3>Dni dodatkowe Trenera <small>(zajęcia trenera mimo dnia wolnego)</small></h3>
      <div class="tbl" data-key="indDod" data-cols="data,osoba"></div></div>

    <p class="hint">Zmiany działają od razu w podglądzie i PDF. Aby zachować je na serwerze, kliknij „Zapisz ustawienia”.</p>
  </section>
</main>
<script>window.DEFAULT_CFG = <?= $def ?>;</script>
<script src="assets/app.js?v=<?= $v ?>"></script>
</body>
</html>
