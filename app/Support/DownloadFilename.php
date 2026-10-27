<?php

namespace App\Support;

final class DownloadFilename
{
    /**
     * Make a value safe to put inside a download filename.
     *
     * Project codes and reference numbers often contain "/" (e.g. "JPS/IP/BPB/10/2025"), which the
     * Content-Disposition header refuses, so every run of unsafe characters becomes one hyphen.
     */
    public static function safe(string $value, string $fallback = 'file'): string
    {
        $safe = trim(preg_replace('/[^A-Za-z0-9._-]+/', '-', $value), '-.');

        return $safe !== '' ? $safe : $fallback;
    }

    private function __construct() {}
}
