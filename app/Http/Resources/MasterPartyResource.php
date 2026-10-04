<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MasterPartyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'initial' => $this->initial,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'postcode' => $this->postcode,
            'website' => $this->website,
            'is_active' => $this->is_active,
            'categories' => PartyCategoryResource::collection($this->whenLoaded('categories')),
            'contacts' => $this->whenLoaded('contacts', fn () => $this->contacts->mapWithKeys(fn ($contact) => [
                $contact->contact_type => [
                    'name' => $contact->name,
                    'position' => $contact->position,
                    'phone' => $contact->phone,
                    'email' => $contact->email,
                ],
            ])),
            'references_count' => $this->when(
                isset($this->legacy_clients_count),
                fn () => $this->legacy_clients_count + $this->project_parties_count + $this->project_contracts_count,
            ),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
