<?php

namespace Tests\Feature\Assets;

use App\Models\User;
use App\Models\Vehicle;
use App\Support\MachineryTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class VehicleMachineryTypeTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['assets.view', 'assets.manage'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $this->actor = User::create([
            'first_name' => 'Asset',
            'last_name' => 'Manager',
            'email' => 'asset-manager-'.uniqid().'@mge-eng.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $this->actor->givePermissionTo(['assets.view', 'assets.manage']);
    }

    public function test_all_site_log_machinery_types_are_accepted_for_assets(): void
    {
        foreach (MachineryTypes::VALUES as $index => $type) {
            $this->actingAs($this->actor)
                ->postJson('/api/vehicles', [
                    'registration_no' => "TYPE-{$index}",
                    'make' => 'Test Make',
                    'type' => 'machinery',
                    'category' => $index % 2 === 0 ? 'vehicle' : 'machine',
                    'custom_type' => $type,
                ])
                ->assertCreated()
                ->assertJsonPath('data.custom_type', $type);
        }

        $this->assertSame(MachineryTypes::VALUES, Vehicle::orderBy('id')->pluck('custom_type')->all());
    }

    public function test_unknown_machinery_type_is_rejected_and_filter_uses_the_aligned_type(): void
    {
        $this->actingAs($this->actor)
            ->postJson('/api/vehicles', [
                'registration_no' => 'INVALID-TYPE',
                'make' => 'Test Make',
                'type' => 'machinery',
                'custom_type' => 'Forklift',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('custom_type');

        Vehicle::create([
            'registration_no' => 'EX-1',
            'make' => 'Volvo',
            'type' => 'machinery',
            'custom_type' => 'Excavator',
            'category' => 'machine',
        ]);
        Vehicle::create([
            'registration_no' => 'CR-1',
            'make' => 'Tadano',
            'type' => 'machinery',
            'custom_type' => 'Crane',
            'category' => 'machine',
        ]);

        $response = $this->actingAs($this->actor)
            ->getJson('/api/vehicles?custom_type=Excavator')
            ->assertOk();

        $this->assertSame(['EX-1'], collect($response->json('data.data'))->pluck('registration_no')->all());
    }

    public function test_legacy_type_values_remain_readable_and_can_be_updated_without_reclassification(): void
    {
        $legacy = Vehicle::create([
            'registration_no' => 'LEGACY-1',
            'make' => 'Honda',
            'type' => 'other',
            'custom_type' => 'Motorcycle',
            'category' => 'vehicle',
        ]);

        $this->actingAs($this->actor)
            ->putJson("/api/vehicles/{$legacy->id}", ['notes' => 'Legacy record retained'])
            ->assertOk();

        $legacy->refresh();
        $this->assertSame('other', $legacy->type);
        $this->assertSame('Motorcycle', $legacy->custom_type);
        $this->assertSame('Legacy record retained', $legacy->notes);
    }
}
