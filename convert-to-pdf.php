<?php
/**
 * Convert src/index.html to PDF using mPDF library
 */

$projectRoot = __DIR__;
$htmlFile = $projectRoot . '/src/index.html';
$pdfFile = $projectRoot . '/BankSploit_CTF_Guide.pdf';

if (!file_exists($htmlFile)) {
    die("[X] File not found: $htmlFile\n");
}

echo "[*] Converting: $htmlFile\n";
echo "[*] Output: $pdfFile\n";
echo "[*] This may take a minute...\n\n";

try {
    // Check if mPDF is installed
    $vendorAutoload = $projectRoot . '/vendor/autoload.php';
    if (!file_exists($vendorAutoload)) {
        echo "[*] Installing mPDF library...\n";
        shell_exec('cd ' . escapeshellarg($projectRoot) . ' && composer require mpdf/mpdf 2>&1');
    }

    require_once $vendorAutoload;

    // Read HTML content
    $html = file_get_contents($htmlFile);

    // Create mPDF instance
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 15,
        'margin_bottom' => 15,
        'orientation' => 'P',
    ]);

    // Write HTML to PDF
    $mpdf->WriteHTML($html);

    // Output PDF file
    $mpdf->Output($pdfFile, 'F');

    $fileSize = filesize($pdfFile) / (1024 * 1024);
    echo "\n[✓] PDF created successfully!\n";
    echo "[✓] Location: $pdfFile\n";
    printf("[✓] File size: %.2f MB\n", $fileSize);

} catch (Exception $e) {
    echo "[X] Conversion failed: " . $e->getMessage() . "\n";
    exit(1);
}
