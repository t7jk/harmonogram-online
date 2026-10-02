<?php
// API: podgląd (JSON), import i eksport pliku cfg.
require __DIR__ . '/lib.php';
header('Content-Type: application/json; charset=utf-8');
function loadSettings(): string {
    $f = __DIR__ . '/data/ustawienia.php';
    return is_file($f) ? substr(file_get_contents($f), strpos(file_get_contents($f), "\n") + 1) : file_get_contents(__DIR__ . '/default.json');
}
mb_internal_encoding('UTF-8');
try {
    $act = $_GET['action'] ?? '';
    $body = file_get_contents('php://input');
    if (strlen($body) > 1000000) throw new CfgError('Zbyt duże dane');
    if ($act === 'preview') {
        $in = json_decode($body, true);
        if (!is_array($in)) throw new CfgError('Błędne dane');
        $cfg = readConfig($in);
        echo json_encode(['pionowo' => buildAll($cfg, false), 'poziomo' => buildAll($cfg, true)], JSON_UNESCAPED_UNICODE);
    } elseif ($act === 'save') {
        require __DIR__ . '/config.php';
        $w = json_decode($body, true);
        if (!is_array($w) || !is_array($w['cfg'] ?? null)) throw new CfgError('Błędne dane');
        if (!password_verify((string)($w['haslo'] ?? ''), SAVE_PASSWORD_HASH)) {
            usleep(500000);
            http_response_code(403);
            exit(json_encode(['error' => 'Nieprawidłowe hasło - ustawienia nie zostały zapisane.'], JSON_UNESCAPED_UNICODE));
        }
        $in = $w['cfg'];
        readConfig($in); // walidacja
        $dir = __DIR__ . '/data';
        if (!is_dir($dir) && !@mkdir($dir, 0775)) throw new CfgError('Nie można utworzyć katalogu data/');
        if (file_put_contents($dir . '/ustawienia.php', "<?php http_response_code(404); exit; ?>\n" . json_encode($in, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) === false)
            throw new CfgError('Brak uprawnień do zapisu w katalogu data/');
        echo json_encode(['ok' => true]);
    } elseif ($act === 'load') {
        echo loadSettings();
    } elseif ($act === 'import') {
        echo json_encode(cfgToArray($body), JSON_UNESCAPED_UNICODE);
    } elseif ($act === 'export') {
        $in = json_decode($body, true);
        if (!is_array($in)) throw new CfgError('Błędne dane');
        header('Content-Type: text/plain; charset=utf-8');
        echo arrayToCfg($in);
    } else throw new CfgError('Nieznana akcja');
} catch (CfgError $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
