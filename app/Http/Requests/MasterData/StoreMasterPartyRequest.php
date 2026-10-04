<?php

namespace App\Http\Requests\MasterData;

use App\Services\MasterPartyService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMasterPartyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'initial' => strtoupper((string) $this->input('initial')),
            'normalized_name' => MasterPartyService::normalizeName((string) $this->input('name')),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'normalized_name' => ['required', Rule::unique('master_parties', 'normalized_name')],
            'initial' => ['required', 'string', 'size:3', 'regex:/^[A-Z0-9]{3}$/'],
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['integer', Rule::exists('party_categories', 'id')->where('is_active', true)],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'website' => ['nullable', 'url', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'contacts' => ['required', 'array'],
            'contacts.main' => ['required', 'array'],
            'contacts.additional' => ['required', 'array'],
            'contacts.main.name' => ['required', 'string', 'max:255'],
            'contacts.main.position' => ['required', 'string', 'max:255'],
            'contacts.main.phone' => ['required', 'string', 'max:50'],
            'contacts.main.email' => ['required', 'email', 'max:255'],
            'contacts.additional.name' => ['required', 'string', 'max:255'],
            'contacts.additional.position' => ['required', 'string', 'max:255'],
            'contacts.additional.phone' => ['required', 'string', 'max:50'],
            'contacts.additional.email' => ['required', 'email', 'max:255'],
        ];
    }
}
