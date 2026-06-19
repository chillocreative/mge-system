<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'project_id' => ['required', 'exists:projects,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['integer', 'exists:users,id'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:25600', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg,zip'],
            'parent_id' => ['nullable', 'exists:tasks,id'],
            'status' => ['nullable', 'in:pending,in_progress,in_review,completed,cancelled'],
            'priority' => ['nullable', 'in:low,medium,high,critical'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'estimated_hours' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
