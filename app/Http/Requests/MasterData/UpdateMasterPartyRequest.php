<?php

namespace App\Http\Requests\MasterData;

use Illuminate\Validation\Rule;

class UpdateMasterPartyRequest extends StoreMasterPartyRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
    }

    public function rules(): array
    {
        $rules = parent::rules();
        $rules['normalized_name'] = [
            'required',
            Rule::unique('master_parties', 'normalized_name')->ignore($this->route('party')),
        ];

        return $rules;
    }
}
