<?php

namespace App\Services;

use App\Models\CorrespondenceEvent;
use App\Models\ProjectCorrespondence;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The workflow layer over a correspondence (Batch 7): who has it now, its
 * history, and the guarded transitions. Every method that changes the
 * correspondence also writes one append-only event, so the timeline can never
 * drift from the record it describes — both happen in one transaction.
 */
class CorrespondenceWorkflowService
{
    /** Record the opening event when a correspondence is first raised. */
    public function recordRaised(ProjectCorrespondence $c, ?int $userId): void
    {
        $this->log($c, 'raised', ['to_status' => $c->status, 'to_party_id' => $c->current_party_id, 'created_by' => $userId]);
    }

    /** Hand the correspondence to another party and record the handover. */
    public function handOver(ProjectCorrespondence $c, int $toPartyId, ?string $note, ?int $userId): ProjectCorrespondence
    {
        return DB::transaction(function () use ($c, $toPartyId, $note, $userId) {
            $from = $c->current_party_id;
            $c->update(['current_party_id' => $toPartyId]);
            $this->log($c, 'handed_over', [
                'from_party_id' => $from,
                'to_party_id' => $toPartyId,
                'note' => $note,
                'created_by' => $userId,
            ]);

            return $c->fresh(['currentParty', 'events']);
        });
    }

    public function addNote(ProjectCorrespondence $c, string $note, ?int $userId): ProjectCorrespondence
    {
        $this->log($c, 'noted', ['note' => $note, 'created_by' => $userId]);

        return $c->fresh('events');
    }

    public function changeStatus(ProjectCorrespondence $c, string $status, ?string $note, ?int $userId): ProjectCorrespondence
    {
        return DB::transaction(function () use ($c, $status, $note, $userId) {
            $from = $c->status;
            $c->update(['status' => $status]);
            $this->log($c, 'status_changed', ['from_status' => $from, 'to_status' => $status, 'note' => $note, 'created_by' => $userId]);

            return $c->fresh('events');
        });
    }

    /**
     * Close a correspondence. A close is only valid with both a closing
     * reference and at least one attached document — the evidence that it was
     * actually resolved, not just marked done. The actual close date is stamped
     * server-side to now, distinct from any expected_close_date set earlier.
     */
    public function close(ProjectCorrespondence $c, ?string $closingReference, ?string $note, ?int $userId): ProjectCorrespondence
    {
        $closingReference = $closingReference !== null ? trim($closingReference) : '';

        if ($closingReference === '') {
            throw new RuntimeException('A closing reference is required to close this correspondence.');
        }
        if ($c->files()->count() === 0) {
            throw new RuntimeException('At least one supporting document must be attached before closing.');
        }

        return DB::transaction(function () use ($c, $closingReference, $note, $userId) {
            $from = $c->status;
            $c->update([
                'status' => 'closed',
                'closing_reference' => $closingReference,
                'actual_close_date' => now()->toDateString(),
                'closed_by' => $userId,
            ]);
            $this->log($c, 'closed', ['from_status' => $from, 'to_status' => 'closed', 'note' => $note, 'created_by' => $userId]);

            return $c->fresh(['events', 'closer']);
        });
    }

    public function reopen(ProjectCorrespondence $c, ?string $note, ?int $userId): ProjectCorrespondence
    {
        return DB::transaction(function () use ($c, $note, $userId) {
            $from = $c->status;
            $c->update(['status' => 'open', 'actual_close_date' => null, 'closed_by' => null]);
            $this->log($c, 'reopened', ['from_status' => $from, 'to_status' => 'open', 'note' => $note, 'created_by' => $userId]);

            return $c->fresh('events');
        });
    }

    private function log(ProjectCorrespondence $c, string $type, array $attrs): void
    {
        CorrespondenceEvent::create(array_merge([
            'project_correspondence_id' => $c->id,
            'event_type' => $type,
        ], $attrs));
    }
}
