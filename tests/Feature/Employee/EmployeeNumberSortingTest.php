<?php

namespace Tests\Feature\Employee;

use App\Models\Employee;
use App\Services\EmployeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeNumberSortingTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_number_sorting_is_applied_before_pagination(): void
    {
        foreach (['EMP-003', 'EMP-001', 'EMP-002'] as $number) {
            Employee::create([
                'employee_no' => $number,
                'first_name' => $number,
                'last_name' => 'Staff',
                'status' => 'active',
            ]);
        }

        $service = app(EmployeeService::class);
        $ascending = $service->list(['sort' => 'employee_no', 'direction' => 'asc'], 2);
        $descending = $service->list(['sort' => 'employee_no', 'direction' => 'desc'], 2);

        $this->assertSame(['EMP-001', 'EMP-002'], $ascending->pluck('employee_no')->all());
        $this->assertSame(['EMP-003', 'EMP-002'], $descending->pluck('employee_no')->all());
        $this->assertSame(3, $ascending->total());
    }
}
