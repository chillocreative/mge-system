<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('party_categories')) {
            Schema::create('party_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->boolean('is_system')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('master_parties')) {
            Schema::create('master_parties', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('normalized_name')->unique();
                $table->char('initial', 3);
                $table->text('address')->nullable();
                $table->string('city')->nullable();
                $table->string('state')->nullable();
                $table->string('country')->nullable();
                $table->string('postcode', 20)->nullable();
                $table->string('website')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['is_active', 'name']);
            });
        }

        if (! Schema::hasTable('master_party_category')) {
            Schema::create('master_party_category', function (Blueprint $table) {
                $table->foreignId('master_party_id')->constrained('master_parties')->cascadeOnDelete();
                $table->foreignId('party_category_id')->constrained('party_categories')->restrictOnDelete();
                $table->primary(['master_party_id', 'party_category_id']);
            });
        }

        if (! Schema::hasTable('master_party_contacts')) {
            Schema::create('master_party_contacts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('master_party_id')->constrained('master_parties')->cascadeOnDelete();
                $table->string('contact_type', 20); // main, additional
                $table->string('name');
                $table->string('position')->nullable();
                $table->string('phone', 50)->nullable();
                $table->string('email')->nullable();
                $table->timestamps();

                $table->unique(['master_party_id', 'contact_type']);
            });
        }

        if (! Schema::hasColumn('clients', 'master_party_id')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->foreignId('master_party_id')->nullable()->after('id')->constrained('master_parties')->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('project_parties', 'master_party_id')) {
            Schema::table('project_parties', function (Blueprint $table) {
                $table->foreignId('master_party_id')->nullable()->after('project_id')->constrained('master_parties')->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('project_contracts', 'master_party_id')) {
            Schema::table('project_contracts', function (Blueprint $table) {
                $table->foreignId('master_party_id')->nullable()->after('client_id')->constrained('master_parties')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('project_reference_settings')) {
            Schema::create('project_reference_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->unique()->constrained('projects')->cascadeOnDelete();
                $table->string('company_code', 20)->default('MGE');
                $table->string('client_code', 20)->default('JPS');
                $table->string('primary_project_code', 30)->default('TGOLAK');
                $table->string('alternate_project_code', 30)->default('OLAK');
                $table->string('volume_code', 20)->default('VOL1');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('project_reference_templates')) {
            Schema::create('project_reference_templates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
                $table->string('code', 50);
                $table->string('name');
                $table->string('type_token', 50);
                $table->string('pattern');
                $table->unsignedSmallInteger('padding')->default(3);
                $table->string('reset_period', 20)->default('annual');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['project_id', 'code']);
            });
        }

        if (! Schema::hasTable('project_reference_sequences')) {
            Schema::create('project_reference_sequences', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('project_reference_template_id');
                $table->foreign('project_reference_template_id', 'prs_template_fk')
                    ->references('id')->on('project_reference_templates')->cascadeOnDelete();
                $table->string('period_key', 20)->default('all');
                $table->unsignedInteger('next_number')->default(1);
                $table->timestamps();

                $table->unique(['project_reference_template_id', 'period_key'], 'project_reference_sequence_scope_unique');
            });
        }

        if (! Schema::hasTable('project_reference_allocations')) {
            Schema::create('project_reference_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
                $table->unsignedBigInteger('project_reference_template_id');
                $table->foreign('project_reference_template_id', 'pra_template_fk')
                    ->references('id')->on('project_reference_templates')->restrictOnDelete();
                $table->string('period_key', 20);
                $table->unsignedInteger('sequence_number');
                $table->string('reference_no');
                $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['project_id', 'reference_no'], 'project_reference_allocations_reference_unique');
                $table->unique(['project_reference_template_id', 'period_key', 'sequence_number'], 'project_reference_allocations_sequence_unique');
            });
        }

        $this->seedCategories();
        $this->backfillParties();
        $this->backfillProjectReferences();
    }

    private function seedCategories(): void
    {
        $now = now();
        foreach ([
            ['name' => 'Client', 'slug' => 'client', 'sort_order' => 10],
            ['name' => 'Consultant', 'slug' => 'consultant', 'sort_order' => 20],
            ['name' => 'Subcontractor', 'slug' => 'subcontractor', 'sort_order' => 30],
            ['name' => 'Vendor', 'slug' => 'vendor', 'sort_order' => 40],
        ] as $category) {
            DB::table('party_categories')->insertOrIgnore($category + [
                'is_system' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function backfillParties(): void
    {
        $categoryIds = DB::table('party_categories')->pluck('id', 'slug');

        DB::table('clients')->orderBy('id')->chunkById(200, function ($clients) use ($categoryIds) {
            foreach ($clients as $client) {
                $partyId = $this->findOrCreateParty([
                    'name' => $client->company_name,
                    'address' => $client->address,
                    'city' => $client->city,
                    'state' => $client->state,
                    'country' => $client->country,
                    'postcode' => $client->zip_code,
                    'website' => $client->website,
                    'is_active' => $client->status === 'active',
                ]);
                $this->attachCategory($partyId, (int) $categoryIds['client']);
                $this->upsertContact($partyId, 'main', [
                    'name' => $client->contact_person,
                    'position' => null,
                    'phone' => $client->phone,
                    'email' => $client->email,
                ]);
                DB::table('clients')->where('id', $client->id)->update(['master_party_id' => $partyId]);
            }
        });

        DB::table('project_parties')->orderBy('id')->chunkById(200, function ($parties) use ($categoryIds) {
            foreach ($parties as $projectParty) {
                $partyId = $this->findOrCreateParty([
                    'name' => $projectParty->name,
                    'address' => $projectParty->address ?? null,
                    'is_active' => (bool) $projectParty->is_active,
                ]);
                $slug = match ($projectParty->type) {
                    'client' => 'client',
                    'consultant' => 'consultant',
                    'subcontractor' => 'subcontractor',
                    'supplier' => 'vendor',
                    default => null,
                };
                if ($slug) {
                    $this->attachCategory($partyId, (int) $categoryIds[$slug]);
                }

                $contacts = DB::table('project_party_contacts')
                    ->where('project_party_id', $projectParty->id)
                    ->orderBy('sort_order')->orderBy('id')->limit(2)->get();
                if ($contacts->isEmpty() && $projectParty->contact_person) {
                    $contacts = collect([(object) [
                        'name' => $projectParty->contact_person,
                        'designation' => null,
                        'phone' => $projectParty->phone,
                        'email' => $projectParty->email,
                    ]]);
                }
                foreach ($contacts as $index => $contact) {
                    $this->upsertContact($partyId, $index === 0 ? 'main' : 'additional', [
                        'name' => $contact->name,
                        'position' => $contact->designation ?? null,
                        'phone' => $contact->phone,
                        'email' => $contact->email,
                    ]);
                }
                DB::table('project_parties')->where('id', $projectParty->id)->update(['master_party_id' => $partyId]);
            }
        });

        DB::table('project_contracts')
            ->whereNotNull('client_id')
            ->orderBy('id')
            ->chunkById(200, function ($contracts) {
                $partyIds = DB::table('clients')
                    ->whereIn('id', $contracts->pluck('client_id')->filter()->all())
                    ->pluck('master_party_id', 'id');
                foreach ($contracts as $contract) {
                    if ($partyId = $partyIds[$contract->client_id] ?? null) {
                        DB::table('project_contracts')->where('id', $contract->id)->update(['master_party_id' => $partyId]);
                    }
                }
            });
    }

    private function findOrCreateParty(array $data): int
    {
        $normalized = $this->normalizeName((string) $data['name']);
        $existing = DB::table('master_parties')->where('normalized_name', $normalized)->first();
        if ($existing) {
            return (int) $existing->id;
        }

        return (int) DB::table('master_parties')->insertGetId([
            'name' => trim((string) $data['name']),
            'normalized_name' => $normalized,
            'initial' => $this->initial((string) $data['name']),
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'country' => $data['country'] ?? null,
            'postcode' => $data['postcode'] ?? null,
            'website' => $data['website'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function attachCategory(int $partyId, int $categoryId): void
    {
        DB::table('master_party_category')->insertOrIgnore([
            'master_party_id' => $partyId,
            'party_category_id' => $categoryId,
        ]);
    }

    private function upsertContact(int $partyId, string $type, array $contact): void
    {
        if (! trim((string) ($contact['name'] ?? ''))) {
            return;
        }
        DB::table('master_party_contacts')->updateOrInsert(
            ['master_party_id' => $partyId, 'contact_type' => $type],
            $contact + ['updated_at' => now(), 'created_at' => now()],
        );
    }

    private function backfillProjectReferences(): void
    {
        DB::table('projects')->orderBy('id')->chunkById(200, function ($projects) {
            foreach ($projects as $project) {
                DB::table('project_reference_settings')->insertOrIgnore([
                    'project_id' => $project->id,
                    'company_code' => 'MGE',
                    'client_code' => 'JPS',
                    'primary_project_code' => 'TGOLAK',
                    'alternate_project_code' => 'OLAK',
                    'volume_code' => 'VOL1',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                foreach ($this->defaultTemplates() as $template) {
                    DB::table('project_reference_templates')->insertOrIgnore($template + [
                        'project_id' => $project->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }

    private function defaultTemplates(): array
    {
        $standard = '{company}/{client}-{project}/{type}/{yy}-{sequence}';
        $volumed = '{company}/{alternate_project}/{type}/{volume}/{mmyy}/{sequence}';
        $site = '{company}/{project}/{type}/{yy}-{sequence}';

        return collect([
            ['code' => 'MA', 'name' => 'Material Approval', 'type_token' => 'MA', 'pattern' => $standard],
            ['code' => 'MS', 'name' => 'Method Statement', 'type_token' => 'MS', 'pattern' => $standard],
            ['code' => 'DWG', 'name' => 'Drawing', 'type_token' => 'DWG', 'pattern' => $standard],
            ['code' => 'REPORT', 'name' => 'Report', 'type_token' => 'DATA', 'pattern' => $standard],
            ['code' => 'ADMIN', 'name' => 'Admin', 'type_token' => 'ADMIN', 'pattern' => $standard],
            ['code' => 'SUBCON', 'name' => 'Subcontractor', 'type_token' => 'SUBCON', 'pattern' => $standard],
            ['code' => 'RFI', 'name' => 'Request for Information', 'type_token' => 'RFI', 'pattern' => $volumed],
            ['code' => 'RFWI', 'name' => 'Request for Work Inspection', 'type_token' => 'RFWI', 'pattern' => $volumed],
            ['code' => 'SITE_MEMO', 'name' => 'Site Memo', 'type_token' => 'SM', 'pattern' => $site],
            ['code' => 'EI', 'name' => 'Engineer Instruction', 'type_token' => 'EI', 'pattern' => $site],
            ['code' => 'PTW', 'name' => 'Permit to Work', 'type_token' => 'PTW', 'pattern' => $site],
        ])->map(fn (array $template) => $template + [
            'padding' => 3,
            'reset_period' => 'annual',
            'is_active' => true,
        ])->all();
    }

    private function normalizeName(string $name): string
    {
        return Str::of($name)->trim()->lower()->replaceMatches('/\s+/', ' ')->toString();
    }

    private function initial(string $name): string
    {
        $words = preg_split('/[^A-Za-z0-9]+/', strtoupper($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $initial = count($words) >= 3
            ? implode('', array_map(fn ($word) => $word[0], array_slice($words, 0, 3)))
            : preg_replace('/[^A-Z0-9]/', '', strtoupper($name));

        return str_pad(substr((string) $initial, 0, 3), 3, 'X');
    }

    public function down(): void
    {
        Schema::dropIfExists('project_reference_allocations');
        Schema::dropIfExists('project_reference_sequences');
        Schema::dropIfExists('project_reference_templates');
        Schema::dropIfExists('project_reference_settings');

        Schema::table('project_contracts', fn (Blueprint $table) => $table->dropConstrainedForeignId('master_party_id'));
        Schema::table('project_parties', fn (Blueprint $table) => $table->dropConstrainedForeignId('master_party_id'));
        Schema::table('clients', fn (Blueprint $table) => $table->dropConstrainedForeignId('master_party_id'));

        Schema::dropIfExists('master_party_contacts');
        Schema::dropIfExists('master_party_category');
        Schema::dropIfExists('master_parties');
        Schema::dropIfExists('party_categories');
    }
};
