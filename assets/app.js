(function () {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const LS = 'ho_cfg_v2';
  const COLS = { od: ['Od', 'date'], do: ['Do', 'date'], data: ['Data', 'date'], opis: ['Opis', 'text'], osoba: ['Trener', 'text'] };
  const LISTS = ['stale', 'ruchome', 'ferie', 'wyjatki', 'dodatki', 'indWyj', 'indDod'];
  let cfg, timer, last = null;


  // ---------- wiersze tabel ----------
  function renderTable(box) {
    const key = box.dataset.key, cols = box.dataset.cols.split(',');
    const rows = cfg[key] = cfg[key] || [];
    box.innerHTML = '';
    const t = document.createElement('table');
    t.innerHTML = '<thead><tr>' + cols.map(c => `<th>${COLS[c][0]}</th>`).join('') + '<th></th></tr></thead>';
    const tb = document.createElement('tbody');
    rows.forEach((row, i) => {
      const tr = document.createElement('tr');
      let prevOd = row.od;
      cols.forEach(c => {
        const td = document.createElement('td'), inp = document.createElement('input');
        inp.type = COLS[c][1]; inp.value = row[c] || '';
        inp.addEventListener('input', () => { row[c] = inp.value;
          if (c === 'od' && cols.includes('do') && (!row.do || row.do === prevOd)) { row.do = inp.value; $$('input', tr)[cols.indexOf('do')].value = inp.value; }
          prevOd = row.od; if (c !== 'opis' && c !== 'osoba') delete row.auto; changed(); });
        td.appendChild(inp); tr.appendChild(td);
      });
      const td = document.createElement('td');
      td.style.whiteSpace = 'nowrap';
      if (row.auto) td.innerHTML = '<span class="badge">auto</span>';
      td.insertAdjacentHTML('beforeend',
        '<button class="x ins" title="Wstaw wiersz poniżej" style="color:var(--acc)">＋</button><button class="x del" title="Usuń wiersz">✕</button>');
      $('.ins', td).onclick = () => { rows.splice(i + 1, 0, {}); renderTable(box); changed(); };
      $('.del', td).onclick = () => { rows.splice(i, 1); renderTable(box); changed(); };
      tr.appendChild(td); tb.appendChild(tr);
    });
    t.appendChild(tb); box.appendChild(t);
    const add = document.createElement('button');
    add.className = 'btn add'; add.textContent = '＋ Dodaj wiersz';
    add.onclick = () => { rows.push({}); renderTable(box); const ins = $$('tbody tr:last-child input', box)[0]; if (ins) ins.focus(); changed(); };
    box.appendChild(add);
  }

  // ---------- pola ogólne ----------
  const F = { rok: '#f_rok', start: '#f_start', n: '#f_n', koniec: '#f_koniec', plik: '#f_plik' };
  function fillForm() {
    for (const k in F) $(F[k]).value = cfg[k] ?? '';
    $$('.f_dzien').forEach(c => c.checked = (cfg.dni || []).includes(+c.value));
    $$('.tbl').forEach(renderTable);
  }
  function readForm() {
    for (const k in F) cfg[k] = k === 'n' ? (+$(F[k]).value || 0) : $(F[k]).value;
    cfg.weekend = true;
    cfg.dni = $$('.f_dzien').filter(c => c.checked).map(c => +c.value);
  }

  // ---------- kalendarz chrześcijański ----------
  function easter(y) { // algorytm Meeusa/Jonesa/Butchera (kalendarz gregoriański)
    const a = y % 19, b = Math.floor(y / 100), c = y % 100, d = Math.floor(b / 4), e = b % 4,
      f = Math.floor((b + 8) / 25), g = Math.floor((b - f + 1) / 3), h = (19 * a + b - d - g + 15) % 30,
      i = Math.floor(c / 4), k = c % 4, l = (32 + 2 * e + 2 * i - h - k) % 7, m = Math.floor((a + 11 * h + 22 * l) / 451),
      mo = Math.floor((h + l - 7 * m + 114) / 31), da = ((h + l - 7 * m + 114) % 31) + 1;
    return new Date(Date.UTC(y, mo - 1, da));
  }
  const fmt = d => d.toISOString().slice(0, 10);
  const add = (d, n) => new Date(d.getTime() + n * 864e5);
  function startYear() {
    const m = /(\d{4})/.exec($('#f_rok').value) || /(\d{4})/.exec($('#f_start').value);
    return m ? +m[1] : new Date().getFullYear();
  }
  function autoHolidays() {
    const y = startYear(), e = easter(y + 1), bc = add(e, 60);
    const S = [[`${y}-11-01`, 'Wszystkich Świętych'], [`${y}-11-11`, 'Narodowe Święto Niepodległości'],
      [`${y}-12-24`, 'Wigilia Bożego Narodzenia'], [`${y}-12-25`, 'Boże Narodzenie (pierwszy dzień)'],
      [`${y}-12-26`, 'Boże Narodzenie (drugi dzień)'], [`${y + 1}-01-01`, 'Nowy Rok'], [`${y + 1}-01-06`, 'Trzech Króli'],
      [`${y + 1}-05-01`, 'Święto Pracy'], [`${y + 1}-05-03`, 'Święto Konstytucji 3 Maja']];
    cfg.stale = S.map(([od, opis]) => ({ od, do: od, opis, auto: true }));
    const keep = (cfg.ruchome || []).filter(r => !/Wielkanoc|Zielone|Boże Ciało/i.test(r.opis || ''));
    const R = [
      { od: fmt(e), do: fmt(e), opis: 'Wielkanoc' },
      { od: fmt(add(e, 1)), do: fmt(add(e, 1)), opis: 'Poniedziałek Wielkanocny' },
      { od: fmt(add(e, 49)), do: fmt(add(e, 49)), opis: 'Zielone Świątki' },
      { od: fmt(bc), do: fmt(bc), opis: 'Boże Ciało' }];
    R.forEach(r => r.auto = true);
    cfg.ruchome = keep.concat(R).sort((a, b) => (a.od || '').localeCompare(b.od || ''));
    fillForm(); changed();
  }

  // ---------- podgląd ----------
  function esc(s) { return String(s).replace(/[&<>]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c])); }
  function tbl(rows, head, firstCol, wide) {
    let h = '<table class="s"><thead><tr>' + head.map((c, i) => `<th${firstCol && i === 0 ? ' class="first"' : ''}>${esc(c)}</th>`).join('') + '</tr></thead><tbody>';
    rows.forEach(r => h += '<tr>' + r.map((c, i) => `<td${firstCol && i === 0 ? ' class="first"' : ''}>${esc(c)}</td>`).join('') + '</tr>');
    return h + '</tbody></table>';
  }
  function render(layout, D) {
    const poz = layout === 'poziomo';
    $('#tyt-' + layout).textContent = D.tytul;
    $('#warn-' + layout).innerHTML = D.warnings.map(w => `<div>⚠ ${esc(w)}</div>`).join('');
    $('#info-' + layout).textContent = D.info;
    let h = tbl(D.rows, D.header, poz);
    D.extra.forEach(e => h += tbl(e.rows, e.header, true));
    h += '<h3>DNI WOLNE</h3>' + tbl(D.wolne, ['Od', 'Do', 'Opis dnia']);
    if (D.dodatki.length) h += '<h3>DODATKOWE DNI NAUKI (CAŁA SZKOŁA)</h3>' + tbl(D.dodatki, ['Data', 'Opis dnia']);
    if (D.ind.length) h += '<h3>DNI TRENERA: BRAK ZAJĘĆ I DNI DODATKOWE</h3>' + tbl(D.ind, ['Data', 'Trener', 'Rodzaj']);
    $('#out-' + layout).innerHTML = h;
    if (!poz) $$('#out-pionowo table.s').forEach(t => t.style.minWidth = '420px');
  }
  function showErr(m) { const e = $('#msg'); e.removeAttribute('style'); e.hidden = !m; e.textContent = m || ''; }
  async function preview() {
    try {
      const r = await fetch('api.php?action=preview', { method: 'POST', body: JSON.stringify(cfg) });
      const j = await r.json();
      if (!r.ok) { showErr(j.error); return; }
      showErr(''); last = j;
      render('poziomo', j.poziomo); render('pionowo', j.pionowo);
      $('#rokLabel').textContent = cfg.rok || '';
    } catch (e) { showErr('Błąd połączenia z serwerem'); }
  }
  function changed() { readFormSafe(); clearTimeout(timer); timer = setTimeout(preview, 350); }
  function readFormSafe() { if (!$('#tab-ustawienia').hidden) readForm(); }

  // ---------- PDF ----------
  function download(layout) {
    readForm();
    const f = document.createElement('form');
    f.method = 'POST'; f.action = 'pdf.php'; f.style.display = 'none';
    [['cfg', JSON.stringify(cfg)], ['layout', layout]].forEach(([n, v]) => {
      const i = document.createElement('input'); i.type = 'hidden'; i.name = n; i.value = v; f.appendChild(i);
    });
    document.body.appendChild(f); f.submit(); f.remove();
  }

  function normalize() {
    LISTS.forEach(k => cfg[k] = cfg[k] || []);
    ['stale', 'ruchome', 'ferie'].forEach(k => cfg[k].forEach(r => { if (r.od && !r.do) r.do = r.od; }));
  }

  // ---------- init ----------
  cfg = window.DEFAULT_CFG; normalize();
  fillForm();
  $$('.tab').forEach(b => b.onclick = () => {
    $$('.tab').forEach(x => x.classList.toggle('active', x === b));
    $$('.panel').forEach(p => p.hidden = p.id !== 'tab-' + b.dataset.tab);
    window.scrollTo(0, 0);
  });
  $$('#tab-ustawienia .grid input, .f_dzien').forEach(i => i.addEventListener('input', () => changed()));
  $$('.dl').forEach(b => b.onclick = () => download(b.dataset.layout));
  $('#btnAuto').onclick = autoHolidays;
  function askPassword() {
    return new Promise(resolve => {
      const ov = document.createElement('div');
      ov.className = 'modal';
      ov.innerHTML = '<form class="box"><h3>Zapis ustawień</h3><label>Hasło<input type="password" autocomplete="current-password"></label>' +
        '<div class="btns"><button type="button" class="btn" data-x>Anuluj</button><button class="btn primary">Zapisz</button></div></form>';
      const inp = $('input', ov), done = v => { ov.remove(); resolve(v); };
      $('form', ov).onsubmit = e => { e.preventDefault(); done(inp.value); };
      $('[data-x]', ov).onclick = () => done(null);
      ov.addEventListener('keydown', e => { if (e.key === 'Escape') done(null); });
      document.body.appendChild(ov); inp.focus();
    });
  }
  function status(t, err) { const e = $('#msg'); e.hidden = false; e.textContent = t; e.style.background = err ? '' : '#e3f6e8'; e.style.color = err ? '' : '#1b5e20'; e.style.borderColor = err ? '' : '#a5d6b0'; if (!err) setTimeout(() => { e.hidden = true; e.removeAttribute('style'); }, 2500); }
  $('#btnSave').onclick = async () => {
    readForm();
    const haslo = await askPassword();
    if (haslo === null) return;
    const r = await fetch('api.php?action=save', { method: 'POST', body: JSON.stringify({ haslo, cfg }) });
    const j = await r.json();
    status(r.ok ? 'Ustawienia zapisane na serwerze.' : j.error, !r.ok);
  };
  $('#btnLoad').onclick = async () => {
    const r = await fetch('api.php?action=load');
    const j = await r.json();
    if (!r.ok) return status(j.error, true);
    cfg = j; normalize(); fillForm(); changed(); status('Wczytano ustawienia z serwera.');
  };
  preview();
})();
