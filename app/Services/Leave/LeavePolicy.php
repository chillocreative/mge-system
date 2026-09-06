<?php

namespace App\Services\Leave;

use App\Models\LeavePolicySetting;
use Illuminate\Support\Collection;

/**
 * Reads leave policy settings (plan 7.3.1).
 *
 * Resolution order for a key is: an override recorded against the specific leave
 * type, then the global row (leave_type_id = null), then the hardcoded fallback
 * below. That last step matters — plan 7.3.3(a) requires the engine to behave
 * sensibly on a system where nobody has opened the settings screen yet. A missing
 * setting must never produce zero days or a crash.
 *
 * Settings are loaded once per instance. The engine resolves a whole request in
 * one pass, so there is no benefit to re-reading mid-calculation, and holding a
 * snapshot means a concurrent settings change cannot alter the answer halfway
 * through.
 */
class LeavePolicy
{
    /**
     * Fallbacks used when a setting has never been written. These mirror the
     * "Lalai cadangan" column of plan 7.3.1.
     */
    public const DEFAULTS = [
        'leave_year_type' => 'calendar',
        'service_base_date' => 'hire_date',
        'new_joiner_proration' => 'monthly',
        'proration_rounding' => 'nearest_half',
        'tier_crossing' => 'next_year',
        'carry_forward_enabled' => '0',
        'carry_forward_max_days' => '0',
        'carry_forward_expiry_month' => '3',
        'deduct_pending_from_balance' => '1',
        'allow_negative_balance' => '0',
        'exit_proration' => '1',
        'encashment_enabled' => '0',
        'backdate_limit_days' => '30',
    ];

    /** @var Collection<int, LeavePolicySetting> */
    private Collection $settings;

    public function __construct(?Collection $settings = null)
    {
        $this->settings = $settings ?? LeavePolicySetting::all();
    }

    /**
     * Resolve a setting, optionally scoped to a leave type.
     */
    public function get(string $key, ?int $leaveTypeId = null): ?string
    {
        if ($leaveTypeId !== null) {
            $override = $this->settings
                ->first(fn ($s) => $s->key === $key && (int) $s->leave_type_id === $leaveTypeId);

            if ($override !== null) {
                return $override->value;
            }
        }

        $global = $this->settings
            ->first(fn ($s) => $s->key === $key && $s->leave_type_id === null);

        return $global->value ?? self::DEFAULTS[$key] ?? null;
    }

    public function bool(string $key, ?int $leaveTypeId = null): bool
    {
        return filter_var($this->get($key, $leaveTypeId), FILTER_VALIDATE_BOOLEAN);
    }

    public function int(string $key, ?int $leaveTypeId = null): int
    {
        return (int) $this->get($key, $leaveTypeId);
    }

    public function float(string $key, ?int $leaveTypeId = null): float
    {
        return (float) $this->get($key, $leaveTypeId);
    }

    /**
     * The settings actually in force, for storing in leave_balances.rule_snapshot.
     *
     * Plan 7.3.3(b): a balance records the policy it was calculated under, so a
     * later settings change cannot retroactively rewrite a number that has
     * already been used to approve leave.
     *
     * @return array<string, string|null>
     */
    public function snapshot(?int $leaveTypeId = null): array
    {
        $keys = array_unique(array_merge(
            array_keys(self::DEFAULTS),
            $this->settings->pluck('key')->all(),
        ));

        return collect($keys)
            ->mapWithKeys(fn (string $key) => [$key => $this->get($key, $leaveTypeId)])
            ->all();
    }
}
