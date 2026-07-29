<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('skrb:test', function () {
    $dir = 'C:\\Nova\\Rekayasa\\test';
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
    $pdf = (new \App\Support\SKRB_template())->generate();
    $outputPath = $dir . '\\preview_skrb.pdf';
    $pdf->Output($outputPath, 'F');
    $this->info("Preview PDF berhasil dibuat di: {$outputPath}");
})->purpose('Test generate SKRB_template and save to local test folder');
