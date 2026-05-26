<?php

namespace App\Support;

class OsCommand
{
    public static function isWindows(): bool
    {
        return strtoupper(substr(PHP_OS_FAMILY, 0, 3)) === 'WIN';
    }

    public static function ghostscriptBinary(): string
    {
        return self::isWindows() ? 'gswin64c' : 'gs';
    }

    public static function buildGhostscriptCommand(string $inputPath, string $outputPath): string
    {
        return sprintf(
            '%s -dSAFER -dBATCH -dNOPAUSE -sDEVICE=png16m -r300 -dTextAlphaBits=4 -dGraphicsAlphaBits=4 -sOutputFile=%s %s 2>&1',
            escapeshellcmd(self::ghostscriptBinary()),
            escapeshellarg($outputPath),
            escapeshellarg($inputPath)
        );
    }
}
