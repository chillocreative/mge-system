<?php

namespace Tests\Feature\Employee;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * The New Staff form posts every field, blanks included, and the
 * ConvertEmptyStringsToNull middleware turns those blanks into null. A few
 * employee columns are NOT NULL with a DB default (number_of_children,
 * base_salary, …); writing null to them used to throw a 500 ("Server Error").
 * The controller now drops null values for those columns so the DB default
 * applies instead.
 */
class CreateStaffTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        Permission::findOrCreate('staff.create', 'web');
        $u = User::create([
            'first_name' => 'Ad', 'last_name' => 'Min',
            'email' => 'a-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active',
        ]);
        $u->givePermissionTo('staff.create');

        return $u;
    }

    public function test_creating_staff_with_blank_number_of_children_and_salary_succeeds(): void
    {
        // Mirrors the form payload: every field present, optional ones blank →
        // null after ConvertEmptyStringsToNull.
        $payload = [
            'employee_no' => 'MGE-TEST-1', 'user_id' => null, 'full_name' => 'Siti Nasabudin',
            'ic_passport_no' => null, 'email' => null, 'phone' => null, 'gender' => null,
            'dob' => null, 'address' => null, 'department_id' => null, 'designation_id' => null,
            'employment_type' => 'full_time', 'category' => 'office',
            'hire_date' => '2015-01-01', 'resign_date' => null, 'reporting_manager_id' => null,
            'bank_name' => null, 'bank_account_no' => null, 'epf_no' => null, 'socso_no' => null,
            'tax_no' => null, 'base_salary' => null, 'status' => 'active',
            'marital_status' => null, 'spouse_name' => null, 'spouse_ic_no' => null,
            'number_of_children' => null, 'emergency_contact_name' => null,
            'emergency_contact_phone' => null, 'emergency_contact_relationship' => null,
        ];

        $this->actingAs($this->actor())
            ->postJson('/api/employees', $payload)
            ->assertCreated();

        $employee = Employee::where('employee_no', 'MGE-TEST-1')->firstOrFail();
        $this->assertSame(0, (int) $employee->number_of_children); // DB default applied
        $this->assertSame('0.00', (string) $employee->base_salary);
    }

    public function test_creating_staff_with_a_salary_and_children_still_persists_them(): void
    {
        $this->actingAs($this->actor())
            ->postJson('/api/employees', [
                'employee_no' => 'MGE-TEST-2', 'full_name' => 'Ali Bin Abu',
                'employment_type' => 'full_time', 'category' => 'office', 'status' => 'active',
                'base_salary' => 4000, 'number_of_children' => 3,
            ])
            ->assertCreated();

        $employee = Employee::where('employee_no', 'MGE-TEST-2')->firstOrFail();
        $this->assertSame(3, (int) $employee->number_of_children);
        $this->assertSame('4000.00', (string) $employee->base_salary);
    }
}
