<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDirectReportingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reports_to_id' => [
                'present',
                'nullable',
                'integer',
                Rule::notIn([(int) $this->route('user')?->id]),
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('status', 'active')
                    ->whereNull('deleted_at')),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'reports_to_id.not_in' => 'A user cannot report directly to themselves.',
            'reports_to_id.exists' => 'The selected direct-reporting person must be an active user.',
        ];
    }
}
