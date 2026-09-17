<?php

namespace Tests\Unit\MonthlyReport;

use App\Services\MonthlyReport\SectionMerger;
use PHPUnit\Framework\TestCase;

class SectionMergerTest extends TestCase
{
    public function test_scalars_and_nested_scalars_are_overridden(): void
    {
        $data = ['schema' => 1, 'project_title' => 'A', 'client' => ['name' => 'JPS', 'address' => 'X']];
        $merged = SectionMerger::merge($data, ['project_title' => 'B', 'client' => ['address' => 'Y']]);

        $this->assertSame('B', $merged['project_title']);
        $this->assertSame('JPS', $merged['client']['name']);
        $this->assertSame('Y', $merged['client']['address']);
    }

    public function test_rows_override_replaces_the_whole_array(): void
    {
        $data = ['schema' => 1, 'rows' => [['no' => 1, 'title' => 'a'], ['no' => 2, 'title' => 'b']]];
        $merged = SectionMerger::merge($data, ['_rows' => [['no' => 1, 'title' => 'z']]]);

        $this->assertCount(1, $merged['rows']);
        $this->assertSame('z', $merged['rows'][0]['title']);
    }

    public function test_unknown_keys_are_reported_and_ignored(): void
    {
        $data = ['schema' => 1, 'x' => 1];
        $this->assertSame(['y'], SectionMerger::validateOverrides($data, ['x' => 2, 'y' => 3]));
        $this->assertSame(['schema' => 1, 'x' => 2], SectionMerger::merge($data, ['x' => 2, 'y' => 3]));
    }

    public function test_null_overrides_return_data_unchanged(): void
    {
        $data = ['schema' => 1, 'x' => 1];
        $this->assertSame($data, SectionMerger::merge($data, null));
    }
}
