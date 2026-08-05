<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function __construct(private EmployeeService $employeeService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 15), 100);
        $filters = $request->only(['search', 'department_id', 'category', 'status']);

        return $this->success($this->employeeService->list($filters, $perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_no' => ['required', 'string', 'max:50', 'unique:employees,employee_no'],
            'user_id' => ['nullable', 'exists:users,id', Rule::unique('employees', 'user_id')],
            'full_name' => ['required', 'string', 'max:200'],
            'ic_passport_no' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'gender' => ['nullable', 'in:male,female'],
            'dob' => ['nullable', 'date'],
            'address' => ['nullable', 'string'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'designation_id' => ['nullable', 'exists:designations,id'],
            'employment_type' => ['nullable', 'in:full_time,part_time,contract,site_worker'],
            'category' => ['nullable', 'in:office,site'],
            'hire_date' => ['nullable', 'date'],
            'resign_date' => ['nullable', 'date'],
            'reporting_manager_id' => ['nullable', 'exists:employees,id'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account_no' => ['nullable', 'string', 'max:50'],
            'epf_no' => ['nullable', 'string', 'max:50'],
            'socso_no' => ['nullable', 'string', 'max:50'],
            'tax_no' => ['nullable', 'string', 'max:50'],
            'base_salary' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'in:active,inactive,resigned'],
            'marital_status' => ['nullable', 'in:married,single,divorced'],
            'spouse_name' => ['nullable', 'string', 'max:255'],
            'spouse_ic_no' => ['nullable', 'string', 'max:50'],
            'number_of_children' => ['nullable', 'integer', 'min:0'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:100'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ]);

        $validated = array_merge($validated, \App\Models\Employee::splitName($validated['full_name']));
        unset($validated['photo'], $validated['full_name']);

        $employee = $this->employeeService->create($validated, $request->user()->id, $request->file('photo'));

        return $this->created($employee, 'Staff member created successfully.');
    }

    public function show(int $id): JsonResponse
    {
        return $this->success($this->employeeService->getOne($id));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'employee_no' => ['sometimes', 'string', 'max:50', 'unique:employees,employee_no,' . $id],
            'user_id' => ['nullable', 'exists:users,id', Rule::unique('employees', 'user_id')->ignore($id)],
            'full_name' => ['sometimes', 'required', 'string', 'max:200'],
            'ic_passport_no' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'gender' => ['nullable', 'in:male,female'],
            'dob' => ['nullable', 'date'],
            'address' => ['nullable', 'string'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'designation_id' => ['nullable', 'exists:designations,id'],
            'employment_type' => ['nullable', 'in:full_time,part_time,contract,site_worker'],
            'category' => ['nullable', 'in:office,site'],
            'hire_date' => ['nullable', 'date'],
            'resign_date' => ['nullable', 'date'],
            'reporting_manager_id' => ['nullable', 'exists:employees,id'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account_no' => ['nullable', 'string', 'max:50'],
            'epf_no' => ['nullable', 'string', 'max:50'],
            'socso_no' => ['nullable', 'string', 'max:50'],
            'tax_no' => ['nullable', 'string', 'max:50'],
            'base_salary' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'in:active,inactive,resigned'],
            'marital_status' => ['nullable', 'in:married,single,divorced'],
            'spouse_name' => ['nullable', 'string', 'max:255'],
            'spouse_ic_no' => ['nullable', 'string', 'max:50'],
            'number_of_children' => ['nullable', 'integer', 'min:0'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:100'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ]);

        if (isset($validated['full_name'])) {
            $validated = array_merge($validated, \App\Models\Employee::splitName($validated['full_name']));
            unset($validated['full_name']);
        }
        unset($validated['photo']);

        $employee = $this->employeeService->update($id, $validated, $request->file('photo'));

        return $this->success($employee, 'Staff member updated successfully.');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->employeeService->delete($id);

        return $this->success(null, 'Staff member deleted successfully.');
    }

    public function photo(int $id)
    {
        $employee = $this->employeeService->getOne($id);

        if (!$employee->photo_path || !Storage::disk('local')->exists($employee->photo_path)) {
            return $this->notFound('Photo not found.');
        }

        return Storage::disk('local')->response($employee->photo_path);
    }
}
