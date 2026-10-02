<?php
// Logika harmonogramu - port harmonogram.py. Konfiguracja jako tablica (JSON z przeglądarki).

const DNI = [1 => 'Poniedziałek', 2 => 'Wtorek', 3 => 'Środa', 4 => 'Czwartek', 5 => 'Piątek', 6 => 'Sobota', 7 => 'Niedziela'];
const SKROT = ['pon.', 'wt.', 'śr.', 'czw.', 'pt.', 'sob.', 'niedz.'];

class CfgError extends Exception {}

// --- daty jako numer dnia (od 1970-01-01) ---
function d2n(string $s): int {
    $s = trim($s);
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1]))
        throw new CfgError("Nieprawidłowa data: \"$s\" (format RRRR-MM-DD)");
    return intdiv(gmmktime(12, 0, 0, (int)$m[2], (int)$m[3], (int)$m[1]), 86400);
}
function n2d(int $n): string { return gmdate('Y-m-d', $n * 86400 + 43200); }
function iso(int $n): int { return (($n + 3) % 7 + 7) % 7 + 1; }   // 1=pon ... 7=niedz
function withDay(int $n): string { return n2d($n) . ' (' . SKROT[iso($n) - 1] . ')'; }

function str($v, int $max = 200): string {
    return mb_substr(trim((string)($v ?? '')), 0, $max);
}

/** Normalizuje i waliduje konfigurację z formularza. */
function readConfig(array $in): array {
    $dni = array_values(array_unique(array_map('intval', $in['dni'] ?? [1, 2, 3, 4, 5, 6])));
    sort($dni);
    foreach ($dni as $d) if ($d < 1 || $d > 7) throw new CfgError('Dni tygodnia muszą być w zakresie 1-7');
    if (!$dni) throw new CfgError('Wybierz co najmniej jeden dzień tygodnia');
    $n = (int)($in['n'] ?? 28);
    if ($n < 1 || $n > 100) throw new CfgError('Liczba spotkań musi być w zakresie 1-100');
    $cfg = [
        'rok' => str($in['rok'] ?? ''),
        'start' => d2n($in['start'] ?? ''),
        'n' => $n,
        'koniec' => !empty($in['koniec']) ? d2n($in['koniec']) : null,
        'plik' => preg_replace('/[^A-Za-z0-9_.-]/', '_', str($in['plik'] ?? 'harmonogram', 80)) ?: 'harmonogram',
        'weekend' => false,
        'dni' => $dni,
        'swieta' => [], 'ferie' => [], 'wyjatki' => [], 'dodatki' => [], 'ind' => [],
    ];
    $lim = function ($list) { if (count($list) > 500) throw new CfgError('Zbyt wiele wpisów'); return $list; };
    foreach (['stale', 'ruchome', 'ferie'] as $sec) {
        foreach ($lim($in[$sec] ?? []) as $r) {
            if (trim($r['od'] ?? '') === '') continue;
            $od = d2n($r['od']);
            $do = trim($r['do'] ?? '') === '' ? $od : d2n($r['do']);
            if ($do < $od) throw new CfgError("Zakres \"{$r['od']}\" - \"{$r['do']}\": koniec przed początkiem");
            if ($od === $do) $cfg['swieta'][$od] = str($r['opis'] ?? '');
            else $cfg['ferie'][] = [$od, $do, str($r['opis'] ?? '')];
        }
    }
    foreach (['wyjatki', 'dodatki'] as $sec)
        foreach ($lim($in[$sec] ?? []) as $r)
            if (trim($r['data'] ?? '') !== '') $cfg[$sec][d2n($r['data'])] = str($r['opis'] ?? '');
    foreach (['indWyj' => 'wyj', 'indDod' => 'dod'] as $sec => $key)
        foreach ($lim($in[$sec] ?? []) as $r) {
            if (trim($r['data'] ?? '') === '') continue;
            $osoba = str($r['osoba'] ?? '', 60);
            $cfg['ind'][$osoba] ??= ['wyj' => [], 'dod' => []];
            $cfg['ind'][$osoba][$key][d2n($r['data'])] = true;
        }
    // wszystko jako zakresy (od, do, opis); święto w niedzielę -> sobota-poniedziałek
    $z = $cfg['ferie'];
    foreach ($cfg['swieta'] as $d => $t)
        $z[] = ($cfg['weekend'] && iso($d) === 7) ? [$d - 1, $d + 1, $t] : [$d, $d, $t];
    foreach ($cfg['wyjatki'] as $d => $t) $z[] = [$d, $d, $t];
    usort($z, fn($a, $b) => [$a[0], $a[1], $a[2]] <=> [$b[0], $b[1], $b[2]]);
    $cfg['zakresy'] = $z;
    // przerwa zaczynająca się w poniedziałek obejmuje też poprzedni weekend
    $cfg['blokady'] = array_map(fn($r) => [$cfg['weekend'] && iso($r[0]) === 1 ? $r[0] - 2 : $r[0], $r[1]], $z);
    ksort($cfg['dodatki']);
    return $cfg;
}

function generate(int $first, int $n, array $blokady, array $dodatki, array $zakazane): array {
    $out = [];
    $d = $first;
    for ($i = 0; count($out) < $n; $i++, $d += 7) {
        if ($i > 3000) throw new CfgError('Nie da się ułożyć harmonogramu - dni wolne pokrywają wszystkie terminy');
        if (isset($zakazane[$d])) continue;
        if (isset($dodatki[$d])) { $out[] = $d; continue; }
        foreach ($blokady as [$a, $b]) if ($a <= $d && $d <= $b) continue 2;
        $out[] = $d;
    }
    return $out;
}

function cell(array $col, int $r, bool $poziomy): string {
    $s = $poziomy ? gmdate("d\nm", $col[$r] * 86400 + 43200) : n2d($col[$r]);
    if ($r > 0) {
        $shift = $col[$r] - $col[$r - 1] - 7;
        if ($shift > 0) $s .= $poziomy ? "\n+$shift" : " (+$shift)";
    }
    return $s;
}

/** Zwraca [nagłówek, wiersze, ostatni dzień, bloki indywidualne]. */
function buildTable(array $cfg, bool $poziomy): array {
    $series = function (int $i, array $zak = [], array $dod = []) use ($cfg) {
        $first = $cfg['start'] + (($i - iso($cfg['start'])) % 7 + 7) % 7;
        return generate($first, $cfg['n'], $cfg['blokady'], $cfg['dodatki'] + $dod, $zak);
    };
    $meet = array_map(fn($i) => $series($i), $cfg['dni']);
    $names = array_map(fn($i) => DNI[$i], $cfg['dni']);
    $last = max(array_map(fn($c) => end($c), $meet));
    $weeks = array_map('strval', range(1, $cfg['n']));
    $extra = [];
    if ($poziomy) {
        $header = array_merge(['Dzień'], $weeks);
        $rows = [];
        foreach ($meet as $k => $col) {
            $row = [$names[$k]];
            for ($r = 0; $r < $cfg['n']; $r++) $row[] = cell($col, $r, true);
            $rows[] = $row;
        }
        foreach ($cfg['ind'] as $osoba => $ind) {
            $prows = [];
            foreach ($cfg['dni'] as $i) {
                $has = false;
                foreach (array_keys($ind['wyj'] + $ind['dod']) as $d) if (iso($d) === $i) $has = true;
                if (!$has) continue;
                $col = $series($i, $ind['wyj'], $ind['dod']);
                $last = max($last, end($col));
                $row = [DNI[$i]];
                for ($r = 0; $r < $cfg['n']; $r++) $row[] = cell($col, $r, true);
                $prows[] = $row;
            }
            if ($prows) $extra[] = ['osoba' => $osoba, 'header' => array_merge([$osoba], $weeks), 'rows' => $prows];
        }
    } else {
        $header = array_merge(['#'], $names);
        $rows = [];
        for ($r = 0; $r < $cfg['n']; $r++) {
            $row = [(string)($r + 1)];
            foreach ($meet as $col) $row[] = cell($col, $r, false);
            $rows[] = $row;
        }
    }
    return [$header, $rows, $last, $extra];
}

/** Całość danych do podglądu i PDF. */
function buildAll(array $cfg, bool $poziomy): array {
    [$header, $rows, $last, $extra] = buildTable($cfg, $poziomy);
    $wolne = array_map(fn($r) => [withDay($r[0]), withDay($r[1]), $r[2]], $cfg['zakresy']);
    $dod = [];
    foreach ($cfg['dodatki'] as $d => $t) $dod[] = [withDay($d), $t];
    $ind = [];
    foreach ($cfg['ind'] as $osoba => $v) {
        foreach (array_keys($v['wyj']) as $d) $ind[] = [$d, withDay($d), $osoba, 'brak zajęć trenera'];
        foreach (array_keys($v['dod']) as $d) $ind[] = [$d, withDay($d), $osoba, 'dzień dodatkowy trenera'];
    }
    usort($ind, fn($a, $b) => [$a[0], $a[2], $a[3]] <=> [$b[0], $b[2], $b[3]]);
    $ind = array_map(fn($r) => [$r[1], $r[2], $r[3]], $ind);
    $warn = [];
    foreach ($cfg['ind'] as $osoba => $v)
        foreach (array_keys($v['wyj'] + $v['dod']) as $d)
            if ($d < $cfg['start'] || !in_array(iso($d), $cfg['dni']))
                $warn[] = "Wpis trenera " . n2d($d) . " ($osoba) jest przed startem zajęć lub w dniu spoza harmonogramu - sprawdź datę";
    $info = $cfg['koniec'] !== null && $last > $cfg['koniec']
        ? "UWAGA: ostatnie zajęcia (" . n2d($last) . ") po końcu roku szkolnego (" . n2d($cfg['koniec']) . ")"
        : "Ostatnie zajęcia: " . n2d($last);
    if ($cfg['koniec'] !== null && $last > $cfg['koniec']) $warn[] = $info;
    return [
        'tytul' => "{$cfg['n']} TYGODNIOWY HARMONOGRAM ZAJĘĆ W {$cfg['rok']}",
        'header' => $header, 'rows' => $rows, 'extra' => $extra,
        'wolne' => $wolne, 'dodatki' => $dod, 'ind' => $poziomy ? $ind : [],
        'warnings' => $warn, 'info' => $info, 'plik' => $cfg['plik'] . '-' . ($poziomy ? 'poziomo' : 'pionowo'),
    ];
}

// --- import / eksport pliku .cfg (zgodny z harmonogram.py) ---
function cfgToArray(string $text): array {
    $sec = ''; $S = [];
    foreach (preg_split('/\r?\n/', $text) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (preg_match('/^\[(.+)\]$/', $line, $m)) { $sec = trim($m[1]); $S[$sec] ??= []; continue; }
        $S[$sec][] = $line;
    }
    $kv = function (string $l): array {
        $p = strpos($l, '=');
        return $p === false ? [trim($l), ''] : [trim(substr($l, 0, $p)), trim(substr($l, $p + 1))];
    };
    $u = [];
    foreach ($S['ustawienia'] ?? [] as $l) { [$k, $v] = $kv($l); $u[$k] = $v; }
    $out = [
        'rok' => $u['rok_szkolny'] ?? '', 'start' => $u['start'] ?? '', 'n' => (int)($u['liczba_spotkan'] ?? 28),
        'koniec' => $u['koniec_roku'] ?? '', 'plik' => $u['plik_wyjsciowy'] ?? 'harmonogram',
        'weekend' => in_array(strtolower($u['weekend_przed_przerwa'] ?? 'tak'), ['tak', 'yes', '1', 'true']),
        'dni' => array_map('intval', explode(',', $u['dni'] ?? '1,2,3,4,5,6')),
        'stale' => [], 'ruchome' => [], 'ferie' => [], 'wyjatki' => [], 'dodatki' => [], 'indWyj' => [], 'indDod' => [],
    ];
    foreach (['stale' => 'swieta_stale', 'ruchome' => 'swieta_ruchome', 'ferie' => 'ferie'] as $k => $name)
        foreach ($S[$name] ?? [] as $l) {
            [$a, $opis] = $kv($l);
            $p = explode(',', $a);
            $out[$k][] = ['od' => trim($p[0]), 'do' => trim($p[1] ?? ''), 'opis' => $opis];
        }
    $days = function (string $name) use ($S, $kv) {
        $r = [];
        foreach ($S[$name] ?? [] as $l) {
            [$a, $v] = $kv($l);
            $p = preg_split('/\s+/', $a, 2);
            $r[] = ['d' => $p[0], 'o' => $v !== '' ? $v : ($p[1] ?? '')];
        }
        return $r;
    };
    foreach ($days('wyjatki') as $r) $out['wyjatki'][] = ['data' => $r['d'], 'opis' => $r['o']];
    foreach ($days('dodatki') as $r) $out['dodatki'][] = ['data' => $r['d'], 'opis' => $r['o']];
    foreach ($days('indywidualne wyjatki') as $r) $out['indWyj'][] = ['data' => $r['d'], 'osoba' => $r['o']];
    foreach ($days('indywidualne dodatki') as $r) $out['indDod'][] = ['data' => $r['d'], 'osoba' => $r['o']];
    return $out;
}

function arrayToCfg(array $c): string {
    $o = ["# Konfiguracja generatora harmonogramu zajęć MindCloud", "# Format dat: RRRR-MM-DD. Zakres dni: RRRR-MM-DD,RRRR-MM-DD = opis", "",
        "[ustawienia]", "rok_szkolny = " . str($c['rok'] ?? ''), "start = " . str($c['start'] ?? ''),
        "liczba_spotkan = " . (int)($c['n'] ?? 28), "koniec_roku = " . str($c['koniec'] ?? ''), "output = PDF",
        "plik_wyjsciowy = " . str($c['plik'] ?? 'harmonogram'), "dni = " . implode(',', array_map('intval', $c['dni'] ?? [])),
        "weekend_przed_przerwa = " . 'nie', ""];
    foreach (['stale' => 'swieta_stale', 'ruchome' => 'swieta_ruchome', 'ferie' => 'ferie'] as $k => $name) {
        $o[] = "[$name]";
        foreach ($c[$k] ?? [] as $r) {
            if (trim($r['od'] ?? '') === '') continue;
            $key = trim($r['od']) . (trim($r['do'] ?? '') !== '' ? ',' . trim($r['do']) : '');
            $o[] = "$key = " . str($r['opis'] ?? '');
        }
        $o[] = '';
    }
    foreach (['wyjatki' => ['wyjatki', 'opis'], 'dodatki' => ['dodatki', 'opis'],
              'indWyj' => ['indywidualne wyjatki', 'osoba'], 'indDod' => ['indywidualne dodatki', 'osoba']] as $k => [$name, $f]) {
        $o[] = "[$name]";
        foreach ($c[$k] ?? [] as $r) if (trim($r['data'] ?? '') !== '') $o[] = trim($r['data']) . ' ' . str($r[$f] ?? '');
        $o[] = '';
    }
    return implode("\n", $o);
}
