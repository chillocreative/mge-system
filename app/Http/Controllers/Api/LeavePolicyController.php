<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveEntitlementRule;
use App\Models\LeavePolicySetting;
use App\Models\PublicHoliday;
use App\Models\WorkPattern;
use App\Services\Leave\LeavePolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeavePolicyController extends Controller
{
    // ── Public holidays ──

    public function holidays(Request $request): JsonResponse
    {
        $query = PublicHoliday::query();

        if ($request->filled('year')) {
            $query->where('year', $request->integer('year'));
        }

        if ($request->filled('scope')) {
            $query->where('scope', $request->string('scope'));
        }

        return $this->success($query->orderBy('date', 'asc')->get());
    }

    public function storeHoliday(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'scope' => ['nullable', 'in:national,state'],
            'state' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $holiday = PublicHoliday::create(array_merge($validated, [
            'year' => (int) substr($validated['date'], 0, 4),
            'is_active' => true,
        ]));

        return $this->created($holiday, 'Public holiday created successfully.');
    }

    public function updateHoliday(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'date' => ['sometimes', 'required', 'date'],
            'scope' => ['sometimes', 'nullable', 'in:national,state'],
            'state' => ['sometimes', 'nullable', 'string', 'max:100'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $holiday = PublicHoliday::findOrFail($id);

        if (isset($validated['date'])) {
            $validated['year'] = (int) substr($validated['date'], 0, 4);
        }

        $holiday->update($validated);

        return $this->success($holiday, 'Public holiday updated successfully.');
    }

    public function destroyHoliday(int $id): JsonResponse
    {
        $holiday = PublicHoliday::findOrFail($id);
        $holiday->update(['is_active' => false]);

        return $this->success(null, 'Public holiday deactivated successfully.');
    }

    // ── Work patterns ──

    public function workPatterns(): JsonResponse
    {
        return $this->success(WorkPattern::orderBy('name')->get());
    }

    public function storeWorkPattern(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['required', 'integer', 'between:1,7'],
            'is_default_for' => ['nullable', 'in:office,site'],
        ]);

        $pattern = WorkPattern::create($validated);

        return $this->created($pattern, 'Work pattern created successfully.');
    }

    public function updateWorkPattern(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'working_days' => ['sometimes', 'required', 'array', 'min:1'],
            'working_days.*' => ['sometimes', 'required', 'integer', 'between:1,7'],
            'is_default_for' => ['sometimes', 'nullable', 'in:office,site'],
        ]);

        $pattern = WorkPattern::findOrFail($id);
        $pattern->update($validated);

        return $this->success($pattern, 'Work pattern updated successfully.');
    }

    // ── Entitlement rules ──

    public function entitlementRules(Request $request): JsonResponse
    {
        $query = LeaveEntitlementRule::with('leaveType')
            ->orderBy('leave_type_id', 'asc')
            ->orderBy('min_years', 'asc');

        if ($request->filled('leave_type_id')) {
            $query->where('leave_type_id', $request->integer('leave_type_id'));
        }

        if ($request->boolean('active_only')) {
            $query->active();
        }

        $rules = $query->get();

        return $this->success([
            'rules' => $rules,
            // Plan 7.3.10(a): while any tier is still a seeded Employment Act
            // minimum, the settings screen must warn that HR has not reviewed it.
            'has_unverified_defaults' => $rules->contains(fn ($r) => $r->is_seed_default && $r->is_active),
        ]);
    }

    public function storeEntitlementRule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'min_years' => ['required', 'numeric', 'min:0'],
            'max_years' => ['nullable', 'numeric', 'gt:min_years'],
            'days' => ['required', 'numeric', 'min:0'],
            'staff_category' => ['nullable', 'in:office,site'],
            'employment_type' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        $rule = LeaveEntitlementRule::create(array_merge($validated, [
            'created_by' => $request->user()->id,
            'is_seed_default' => false,
        ]));

        return $this->created($rule, 'Entitlement rule created successfully.');
    }

    public function updateEntitlementRule(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'leave_type_id' => ['sometimes', 'required', 'exists:leave_types,id'],
            'min_years' => ['sometimes', 'required', 'numeric', 'min:0'],
            'max_years' => ['sometimes', 'nullable', 'numeric'],
            'days' => ['sometimes', 'required', 'numeric', 'min:0'],
            'staff_category' => ['sometimes', 'nullable', 'in:office,site'],
            'employment_type' => ['sometimes', 'nullable', 'string', 'max:50'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $rule = LeaveEntitlementRule::findOrFail($id);

        // Validated against the merged result, since a partial update may send
        // max_years without min_years (or vice versa).
        $min = (float) ($validated['min_years'] ?? $rule->min_years);
        $max = array_key_exists('max_years', $validated) ? $validated['max_years'] : $rule->max_years;

        if ($max !== null && (float) $max <= $min) {
            return $this->error('The upper service bound must be greater than the lower bound.', 422);
        }

        // An admin editing a tier has, by definition, reviewed it.
        $rule->update(array_merge($validated, ['is_seed_default' => false]));

        return $this->success($rule, 'Entitlement rule updated successfully.');
    }

    public function destroyEntitlementRule(int $id): JsonResponse
    {
        $rule = LeaveEntitlementRule::findOrFail($id);
        $rule->update(['is_active' => false]);

        return $this->success(null, 'Entitlement rule deactivated successfully.');
    }

    /**
     * HR confirms the entitlement tiers are correct (plan 7.3.10b).
     *
     * The tiers ship seeded from Employment Act minimums. If MGE actually gives
     * more than the statutory minimum and nobody opens this screen, the system
     * quietly operates on the minimum and employees see fewer days than they are
     * owed — discovered months later, after leave has been approved on the wrong
     * numbers. Clearing is_seed_default is what turns an unexamined default into
     * a decision somebody made, with their name against it.
     */
    public function confirmEntitlements(Request $request): JsonResponse
    {
        $count = LeaveEntitlementRule::where('is_seed_default', true)
            ->where('is_active', true)
            ->update([
                'is_seed_default' => false,
                'created_by' => $request->user()->id,
            ]);

        return $this->success(
            ['confirmed' => $count],
            "{$count} entitlement rule(s) confirmed as reviewed.",
        );
    }

    // ── Policy settings ──

    public function settings(): JsonResponse
    {
        return $this->success([
            'settings' => LeavePolicySetting::with('leaveType:id,name,code')->get(),
            // The engine falls back to these when a setting has never been
            // written, so the UI can show what is actually in force.
            'defaults' => LeavePolicy::DEFAULTS,
            'engine_enabled' => (bool) config('leave.engine_enabled'),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.key' => ['required', 'string'],
            'settings.*.value' => ['nullable', 'string'],
            'settings.*.leave_type_id' => ['nullable', 'exists:leave_types,id'],
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['settings'] as $setting) {
                LeavePolicySetting::updateOrCreate(
                    [
                        'leave_type_id' => $setting['leave_type_id'] ?? null,
                        'key' => $setting['key'],
                        'effective_from' => null,
                    ],
                    ['value' => $setting['value'] ?? null]
                );
            }
        });

        return $this->success(LeavePolicySetting::all(), 'Policy settings updated successfully.');
    }
}
