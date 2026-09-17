<?php

namespace App\Http\Controllers\Api\ReportData;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectParty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReportPartyController extends Controller
{
    private const LOGO_TYPES = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp'];

    private const PARTY_TYPES = 'client,consultant,main_contractor,subcontractor,supplier,authority,other';

    public function index(int $projectId): JsonResponse
    {
        return $this->success(ProjectParty::where('project_id', $projectId)->with('contacts')->orderBy('sort_order')->orderBy('id')->get());
    }

    public function store(int $projectId, Request $request): JsonResponse
    {
        Project::findOrFail($projectId);
        $validated = $this->validatePayload($request, true);
        $contacts = $validated['contacts'] ?? [];
        unset($validated['contacts']);
        $validated['project_id'] = $projectId;

        $party = DB::transaction(function () use ($validated, $contacts) {
            $party = ProjectParty::create($validated);
            $this->syncContacts($party, $contacts);

            return $party;
        });

        return $this->created($party->load('contacts'), 'Party added.');
    }

    public function update(int $projectId, int $partyId, Request $request): JsonResponse
    {
        $party = ProjectParty::where('project_id', $projectId)->findOrFail($partyId);
        $validated = $this->validatePayload($request, false);
        $contacts = array_key_exists('contacts', $validated) ? $validated['contacts'] : null;
        unset($validated['contacts']);

        DB::transaction(function () use ($party, $validated, $contacts) {
            $party->update($validated);
            if ($contacts !== null) {
                $this->syncContacts($party, $contacts);
            }
        });

        return $this->success($party->fresh()->load('contacts'), 'Party updated.');
    }

    public function destroy(int $projectId, int $partyId): JsonResponse
    {
        $party = ProjectParty::where('project_id', $projectId)->findOrFail($partyId);
        if ($party->logo_path) {
            Storage::disk('local')->delete($party->logo_path);
        }
        $party->delete();

        return $this->success(null, 'Party removed.');
    }

    public function storeLogo(int $projectId, int $partyId, Request $request): JsonResponse
    {
        $party = ProjectParty::where('project_id', $projectId)->findOrFail($partyId);
        $request->validate(['logo' => ['required', 'file', 'max:2048', 'extensions:png,jpg,jpeg,webp']]);

        if ($party->logo_path) {
            Storage::disk('local')->delete($party->logo_path);
        }
        $party->update(['logo_path' => $request->file('logo')->store("projects/{$projectId}/party-logos", 'local')]);

        return $this->success($party->fresh()->load('contacts'), 'Logo uploaded.');
    }

    public function showLogo(int $projectId, int $partyId)
    {
        $party = ProjectParty::where('project_id', $projectId)->findOrFail($partyId);
        abort_unless($party->logo_path && Storage::disk('local')->exists($party->logo_path), 404);

        $ext = strtolower(pathinfo($party->logo_path, PATHINFO_EXTENSION));

        return response()->file(Storage::disk('local')->path($party->logo_path), [
            'Content-Type' => self::LOGO_TYPES[$ext] ?? 'application/octet-stream',
            'Content-Disposition' => 'inline',
        ]);
    }

    private function validatePayload(Request $request, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'name' => [$required, 'string', 'max:255'],
            'type' => ['nullable', 'in:'.self::PARTY_TYPES],
            'report_role' => ['nullable', 'in:'.implode(',', ProjectParty::REPORT_ROLES)],
            'role_label' => ['nullable', 'string', 'max:100', 'required_if:report_role,other'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'contacts' => ['sometimes', 'array', 'max:20'],
            'contacts.*.name' => ['required', 'string', 'max:255'],
            'contacts.*.designation' => ['nullable', 'string', 'max:255'],
            'contacts.*.phone' => ['nullable', 'string', 'max:50'],
            'contacts.*.email' => ['nullable', 'email', 'max:255'],
        ]);
    }

    private function syncContacts(ProjectParty $party, array $contacts): void
    {
        $party->contacts()->delete();
        foreach (array_values($contacts) as $i => $c) {
            $party->contacts()->create($c + ['sort_order' => $i]);
        }
    }
}
