<?php

namespace App\Services\Safety;

use App\Models\WorkPermit;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The one place the PTW state machine lives (Ciri 25).
 *
 * Every transition is guarded here rather than in the controller so the rules
 * — you can only approve something pending, you can only close something
 * approved — cannot be sidestepped by hitting a different endpoint. Expiry is
 * not a transition: it is derived on read (WorkPermit::effective_status), so
 * there is no "expire" method and nothing to schedule.
 */
class WorkPermitService
{
    public function create(array $data, int $requestedBy, bool $submit = false): WorkPermit
    {
        return DB::transaction(function () use ($data, $requestedBy, $submit) {
            $data['requested_by'] = $requestedBy;
            $data['status'] = $submit ? 'pending' : 'draft';
            $data['permit_no'] ??= $this->nextPermitNo();

            return WorkPermit::create($data);
        });
    }

    /**
     * Edit permit fields. Only a draft or pending permit is editable — once a
     * decision has been made the record is frozen except for closing.
     */
    public function update(WorkPermit $permit, array $data): WorkPermit
    {
        if (! in_array($permit->status, ['draft', 'pending'], true)) {
            throw new RuntimeException('Only a draft or pending permit can be edited.');
        }

        // Never let an edit rewrite decision/close bookkeeping.
        unset($data['status'], $data['approved_by'], $data['approved_at'], $data['closed_by'], $data['closed_at']);

        $permit->update($data);

        return $permit;
    }

    public function submit(WorkPermit $permit): WorkPermit
    {
        if ($permit->status !== 'draft') {
            throw new RuntimeException('Only a draft permit can be submitted for approval.');
        }

        $permit->update(['status' => 'pending']);

        return $permit;
    }

    public function approve(WorkPermit $permit, int $approverId, ?string $notes = null): WorkPermit
    {
        if ($permit->status !== 'pending') {
            throw new RuntimeException('Only a pending permit can be approved.');
        }

        $permit->update([
            'status' => 'approved',
            'approved_by' => $approverId,
            'approved_at' => now(),
            'decision_notes' => $notes,
        ]);

        return $permit;
    }

    public function reject(WorkPermit $permit, int $approverId, ?string $notes = null): WorkPermit
    {
        if ($permit->status !== 'pending') {
            throw new RuntimeException('Only a pending permit can be rejected.');
        }

        $permit->update([
            'status' => 'rejected',
            'approved_by' => $approverId,
            'approved_at' => now(),
            'decision_notes' => $notes,
        ]);

        return $permit;
    }

    public function close(WorkPermit $permit, int $userId): WorkPermit
    {
        if ($permit->status !== 'approved') {
            throw new RuntimeException('Only an approved permit can be closed.');
        }

        $permit->update([
            'status' => 'closed',
            'closed_by' => $userId,
            'closed_at' => now(),
        ]);

        return $permit;
    }

    /**
     * Sequential per-year permit number, PTW-YYYY-0001. Derived from the highest
     * existing number in the current year so gaps from deletes don't recycle.
     */
    private function nextPermitNo(): string
    {
        $year = now()->year;
        $prefix = "PTW-{$year}-";

        $last = WorkPermit::where('permit_no', 'like', $prefix.'%')
            ->orderByDesc('permit_no')
            ->value('permit_no');

        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
