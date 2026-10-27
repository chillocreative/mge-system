<?php

namespace Tests\Unit;

use App\Support\DownloadFilename;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DownloadFilenameTest extends TestCase
{
    #[DataProvider('cases')]
    public function test_unsafe_characters_become_single_hyphens(string $input, string $expected): void
    {
        $this->assertSame($expected, DownloadFilename::safe($input, 'fallback'));
    }

    public static function cases(): array
    {
        return [
            'real project code' => ['JPS/IP/BPB/10/2025', 'JPS-IP-BPB-10-2025'],
            'backslashes' => ['A\\B\\C', 'A-B-C'],
            'reference number' => ['MGE/MANUAL/RFWI-001', 'MGE-MANUAL-RFWI-001'],
            'plain code is untouched' => ['PR1234', 'PR1234'],
            'dots, underscores and hyphens are kept' => ['a_b.c-d', 'a_b.c-d'],
            'runs collapse to one hyphen' => ['A // B  &&  C', 'A-B-C'],
            'edges are trimmed' => ['/A/', 'A'],
            'no leading dot (hidden or relative names)' => ['../etc/passwd', 'etc-passwd'],
            'only unsafe characters use the fallback' => ['///', 'fallback'],
            'empty uses the fallback' => ['', 'fallback'],
        ];
    }
}
