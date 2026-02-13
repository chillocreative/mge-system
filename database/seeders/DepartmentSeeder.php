<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Designation;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            [
                'name' => 'Administration',
                'code' => 'ADMIN',
                'designations' => ['CEO', 'COO', 'Office Manager', 'Admin Assistant'],
            ],
            [
                'name' => 'Engineering',
                'code' => 'ENG',
                'designations' => ['Chief Engineer', 'Senior Engineer', 'Site Engineer', 'Junior Engineer'],
            ],
            [
                'name' => 'Project Management',
                'code' => 'PM',
                'designations' => ['Director of Projects', 'Senior Project Manager', 'Project Manager', 'Assistant PM'],
            ],
            [
                'name' => 'Construction',
                'code' => 'CON',
                'designations' => ['Site Supervisor', 'Foreman', 'Safety Officer', 'Quality Inspector'],
            ],
            [
                'name' => 'Finance',
                'code' => 'FIN',
                'designations' => ['CFO', 'Accountant', 'Finance Officer', 'Procurement Officer'],
            ],
            [
                'name' => 'Human Resources',
                'code' => 'HR',
                'designations' => ['HR Manager', 'HR Officer', 'Recruitment Specialist'],
            ],
            [
                'name' => 'Design',
                'code' => 'DES',
                'designations' => ['Lead Architect', 'Senior Designer', 'CAD Technician', 'Interior Designer'],
            ],
        ];

        foreach ($departments as $dept) {
            $department = Department::firstOrCreate(
                ['code' => $dept['code']],
                ['name' => $dept['name']]
            );

            foreach ($dept['designations'] as $title) {
                Designation::firstOrCreate([
                    'name' => $title,
                    'department_id' => $department->id,
                ]);
            }
        }
    }
}
