<?php

namespace App\Http\Requests\MasterData;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProjectReferenceSettingsRequest extends FormRequest
{
    private const TOKENS = [
        '{company}', '{client}', '{project}', '{alternate_project}', '{volume}',
        '{type}', '{yy}', '{yyyy}', '{mm}', '{mmyy}', '{sequence}',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'settings' => ['required', 'array'],
            'settings.company_code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/'],
            'settings.client_code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/'],
            'settings.primary_project_code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]+$/'],
            'settings.alternate_project_code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]+$/'],
            'settings.volume_code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/'],
            'templates' => ['required', 'array', 'min:1'],
            'templates.*.code' => [
                'required', 'string', 'max:50',
                Rule::exists('project_reference_templates', 'code')
                    ->where('project_id', (int) $this->route('project')),
            ],
            'templates.*.name' => ['required', 'string', 'max:255'],
            'templates.*.type_token' => ['required', 'string', 'max:50'],
            'templates.*.pattern' => ['required', 'string', 'max:255'],
            'templates.*.padding' => ['required', 'integer', 'min:1', 'max:10'],
            'templates.*.reset_period' => ['required', Rule::in(['annual', 'monthly', 'never'])],
            'templates.*.is_active' => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            foreach ($this->input('templates', []) as $index => $template) {
                $pattern = (string) ($template['pattern'] ?? '');
                if (! str_contains($pattern, '{sequence}')) {
                    $validator->errors()->add("templates.{$index}.pattern", 'The pattern must contain {sequence}.');
                }
                preg_match_all('/\{[^}]+\}/', $pattern, $matches);
                $unknown = array_diff($matches[0] ?? [], self::TOKENS);
                if ($unknown) {
                    $validator->errors()->add("templates.{$index}.pattern", 'Unsupported token: '.implode(', ', $unknown));
                }
            }
        }];
    }
}
