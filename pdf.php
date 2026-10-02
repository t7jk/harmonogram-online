<?php
// Generuje PDF (TCPDF) - odpowiednik write_pdf z harmonogram.py. POST: cfg (JSON), layout (poziomo|pionowo).
require __DIR__ . '/lib.php';
require __DIR__ . '/tcpdf/tcpdf.php';
mb_internal_encoding('UTF-8');

try {
    $in = json_decode($_POST['cfg'] ?? '', true);
    if (!is_array($in)) throw new CfgError('Błędne dane');
    $poziomy = ($_POST['layout'] ?? '') === 'poziomo';
    $cfg = readConfig($in);
    $D = buildAll($cfg, $poziomy);
} catch (CfgError $e) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    exit($e->getMessage());
}

$pdf = new TCPDF('L', 'pt', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Harmonogram online');
$pdf->SetTitle('Harmonogram zajęć ' . $cfg['rok']);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(28, 24, 28);
$pdf->SetAutoPageBreak(true, 24);
$pdf->setCellPaddings(1, 2, 1, 2);
$pdf->SetLineWidth(0.5);
$pdf->AddPage();
$pageW = $pdf->getPageWidth();
$pageH = $pdf->getPageHeight();
$availW = $pageW - 56;

function drawTable(TCPDF $pdf, array $data, array $widths, $heights, float $fs, array $headRows = [0]): void {
    foreach ($data as $i => $row) {
        $h = is_array($heights) ? $heights[$i] : $heights;
        if ($pdf->GetY() + $h > $pdf->getPageHeight() - 24) $pdf->AddPage();
        $head = in_array($i, $headRows, true);
        $pdf->SetFont('dejavusans', $head ? 'B' : '', $fs);
        $pdf->SetFillColor(224, 224, 224);
        $y = $pdf->GetY();
        $x = 28;
        foreach ($row as $k => $txt) {
            $pdf->SetXY($x, $y);
            $pdf->MultiCell($widths[$k], $h, (string)$txt, 1, 'C', $head, 0, '', '', true, 0, false, true, $h, 'M');
            $x += $widths[$k];
        }
        $pdf->SetY($y + $h);
    }
}
function heading(TCPDF $pdf, string $t): void {
    if ($pdf->GetY() + 40 > $pdf->getPageHeight() - 24) $pdf->AddPage();
    $pdf->Ln(10);
    $pdf->SetFont('dejavusans', 'B', 10);
    $pdf->Cell(0, 14, $t, 0, 1, 'C');
    $pdf->Ln(2);
}

$pdf->SetFont('dejavusans', 'B', 16);
$pdf->Cell(0, 20, $D['tytul'], 0, 1, 'C');
$pdf->Ln(6);

// tabela główna wypełnia całą stronę: wysokość wiersza dobrana do liczby wierszy
$data = array_merge([$D['header']], $D['rows']);
$headRows = [0];
foreach ($D['extra'] as $e) {
    $headRows[] = count($data);
    $data[] = $e['header'];
    foreach ($e['rows'] as $r) $data[] = $r;
}
$availH = $pageH - 48 - $pdf->GetY() + 24 - 16;
if ($poziomy) {
    $headH = 24;
    $bodyH = ($availH - $headH * count($headRows)) / (count($data) - count($headRows));
    $rowH = [];
    foreach (array_keys($data) as $i) $rowH[] = in_array($i, $headRows, true) ? $headH : $bodyH;
} else {
    $rowH = $availH / count($data);
}
$w0 = $poziomy ? 78 : 40;
$ncols = count($D['header']);
$widths = array_merge([$w0], array_fill(0, $ncols - 1, ($availW - $w0) / ($ncols - 1)));
drawTable($pdf, $data, $widths, $rowH, $poziomy ? 10 : 9, $headRows);

$pdf->AddPage();
heading($pdf, 'DNI WOLNE');
drawTable($pdf, array_merge([['Od', 'Do', 'Opis dnia']], $D['wolne']), [130, 130, 340], 16, 8);
if ($D['dodatki']) {
    heading($pdf, 'DODATKOWE DNI NAUKI (CAŁA SZKOŁA)');
    drawTable($pdf, array_merge([['Data', 'Opis dnia']], $D['dodatki']), [260, 340], 16, 8);
}
if ($D['ind']) {
    heading($pdf, 'DNI TRENERA: BRAK ZAJĘĆ I DNI DODATKOWE');
    drawTable($pdf, array_merge([['Data', 'Trener', 'Rodzaj']], $D['ind']), [200, 150, 250], 16, 8);
}
$pdf->Output($D['plik'] . '.pdf', 'D');
