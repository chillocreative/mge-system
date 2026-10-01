<?php

namespace App\Services;

use App\Models\CorrespondencePartyReview;
use App\Models\ProjectCorrespondence;
use App\Models\ProjectCorrespondenceFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CorrespondenceService
{
    public function __construct(private CorrespondenceNumberService $numberService) {}

    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = ProjectCorrespondence::with([
            'project:id,name,code', 'site:id,name', 'creator:id,first_name,last_name', 'files',
            'fromParty:id,name,type', 'toParty:id,name,type',
            'partyReviews.party:id,name,type',
        ])
            ->orderByDesc('raised_date')
            ->orderByDesc('id');

        if (! empty($filters['project_id'])) {
            $query->forProject($filters['project_id']);
        }
        if (! empty($filters['type'])) {
            $query->byType($filters['type']);
        }
        if (! empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }
        if (! empty($filters['search'])) {
            $query->where(fn ($q) => $q->where('title', 'like', "%{$filters['search']}%")
                ->orWhere('reference_no', 'like', "%{$filters['search']}%")
                ->orWhere('description', 'like', "%{$filters['search']}%"));
        }

        return $query->paginate($perPage);
    }

    public function create(array $data, int $userId, array $files = []): ProjectCorrespondence
    {
        return DB::transaction(function () use ($data, $userId, $files) {
            $tracking = $this->extractTrackingPayload($data);
            $data['created_by'] = $userId;
            if (trim((string) ($data['reference_no'] ?? '')) === '') {
                $data['reference_no'] = $this->numberService->generate(
                    (int) $data['project_id'],
                    $data['type'],
                    $data['document_subtype'] ?? null,
                    isset($data['raised_date']) ? (int) substr((string) $data['raised_date'], 0, 4) : null,
                );
                $data['reference_no_is_manual'] = false;
            } else {
                $data['reference_no'] = trim($data['reference_no']);
                $data['reference_no_is_manual'] = true;
            }
            if (array_key_exists('status', $data) && $data['status'] !== 'others') {
                $data['other_status_text'] = null;
            }
            $correspondence = ProjectCorrespondence::create($data);
            $this->syncTracking($correspondence, $tracking, $userId);
            $this->storeFiles($correspondence, $files);

            return $this->loadTrackingRelations($correspondence);
        });
    }

    public function update(int $id, array $data, array $files = [], ?int $userId = null): ProjectCorrespondence
    {
        return DB::transaction(function () use ($id, $data, $files, $userId) {
            $correspondence = ProjectCorrespondence::findOrFail($id);
            $tracking = $this->extractTrackingPayload($data);
            if (array_key_exists('reference_no', $data)) {
                if (trim((string) $data['reference_no']) === '') {
                    $data['reference_no'] = $this->numberService->generate(
                        (int) ($data['project_id'] ?? $correspondence->project_id),
                        $data['type'] ?? $correspondence->type,
                        $data['document_subtype'] ?? $correspondence->document_subtype,
                        isset($data['raised_date'])
                            ? (int) substr((string) $data['raised_date'], 0, 4)
                            : (int) $correspondence->raised_date->format('Y'),
                    );
                    $data['reference_no_is_manual'] = false;
                } else {
                    $data['reference_no'] = trim($data['reference_no']);
                    $data['reference_no_is_manual'] = $data['reference_no'] !== $correspondence->reference_no
                        ? true
                        : $correspondence->reference_no_is_manual;
                }
            }
            if (array_key_exists('status', $data) && $data['status'] !== 'others') {
                $data['other_status_text'] = null;
            }
            $correspondence->update($data);
            $this->syncTracking($correspondence, $tracking, $userId);
            $this->storeFiles($correspondence, $files);

            return $this->loadTrackingRelations($correspondence);
        });
    }

    public function delete(int $id): void
    {
        $correspondence = ProjectCorrespondence::with('files')->findOrFail($id);
        foreach ($correspondence->files as $file) {
            Storage::disk('local')->delete($file->file_path);
        }
        $correspondence->files()->delete();
        $correspondence->delete();
    }

    public function getOne(int $id): ProjectCorrespondence
    {
        return ProjectCorrespondence::with([
            'project:id,name,code', 'site:id,name', 'creator:id,first_name,last_name', 'files',
            'currentParty:id,name,type', 'fromParty:id,name,type', 'toParty:id,name,type', 'closer:id,first_name,last_name',
            'detail.subcontractorParty:id,name,type', 'partyReviews.party:id,name,type',
            'outgoingLinks.target:id,project_id,type,reference_no,title',
            'incomingLinks.source:id,project_id,type,reference_no,title',
            'events' => fn ($q) => $q->with(['fromParty:id,name', 'toParty:id,name', 'creator:id,first_name,last_name']),
        ])->findOrFail($id);
    }

    public function updateCloseDates(int $id, array $dates): ProjectCorrespondence
    {
        return DB::transaction(function () use ($id, $dates) {
            $correspondence = ProjectCorrespondence::findOrFail($id);
            $correspondence->update($dates);

            foreach (['consultant_closed_date' => 'jpriz', 'client_closed_date' => 'client'] as $column => $role) {
                if (! array_key_exists($column, $dates)) {
                    continue;
                }

                $review = $correspondence->partyReviews()->where('party_role', $role)->first();
                if ($review) {
                    $review->update(['closed_date' => $dates[$column]]);
                }
            }

            return $this->loadTrackingRelations($correspondence);
        });
    }

    public function downloadFile(int $fileId)
    {
        $file = ProjectCorrespondenceFile::findOrFail($fileId);

        return Storage::disk('local')->download($file->file_path, $file->file_name);
    }

    public function deleteFile(int $fileId): void
    {
        $file = ProjectCorrespondenceFile::findOrFail($fileId);
        Storage::disk('local')->delete($file->file_path);
        $file->delete();
    }

    private function storeFiles(ProjectCorrespondence $correspondence, array $files): void
    {
        foreach ($files as $file) {
            $path = $file->store('projects/correspondence', 'local');
            $correspondence->files()->create([
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
            ]);
        }
    }

    private function extractTrackingPayload(array &$data): array
    {
        $tracking = [];
        foreach (['detail', 'party_reviews', 'links'] as $key) {
            if (array_key_exists($key, $data)) {
                $tracking[$key] = $data[$key];
                unset($data[$key]);
            }
        }

        if (array_key_exists('links_sync', $data)) {
            if ($data['links_sync'] && ! array_key_exists('links', $tracking)) {
                $tracking['links'] = [];
            }
            unset($data['links_sync']);
        }

        return $tracking;
    }

    private function syncTracking(ProjectCorrespondence $correspondence, array $tracking, ?int $userId): void
    {
        if (array_key_exists('detail', $tracking)) {
            $detail = $this->nullablePayload($tracking['detail']);
            if ($this->hasMeaningfulValue($detail)) {
                $correspondence->detail()->updateOrCreate([], $detail);
            } else {
                $correspondence->detail()->delete();
            }
        }

        if (array_key_exists('party_reviews', $tracking)) {
            $correspondence->partyReviews()->delete();
            foreach ($tracking['party_reviews'] as $index => $review) {
                $review = $this->nullablePayload($review);
                $role = $review['party_role'] ?? null;
                $content = array_diff_key($review, array_flip(['party_role', 'sequence']));
                if (! $role || ! $this->hasMeaningfulValue($content)) {
                    continue;
                }

                if (empty($review['status_normalized']) && ! empty($review['status_raw'])) {
                    $review['status_normalized'] = $this->normalizeReviewStatus($review['status_raw']);
                }
                $review['sequence'] = $index;
                $correspondence->partyReviews()->create($review);
            }

            $this->syncLegacyPartyColumns($correspondence);
        }

        if (array_key_exists('links', $tracking)) {
            $correspondence->outgoingLinks()->delete();
            foreach ($tracking['links'] as $link) {
                $link = $this->nullablePayload($link);
                $correspondence->outgoingLinks()->create([
                    'target_correspondence_id' => $link['target_correspondence_id'],
                    'relation_type' => $link['relation_type'],
                    'note' => $link['note'] ?? null,
                    'created_by' => $userId,
                ]);
            }
        }
    }

    private function syncLegacyPartyColumns(ProjectCorrespondence $correspondence): void
    {
        $reviews = $correspondence->partyReviews()->get()->keyBy('party_role');
        $jpriz = $reviews->get('jpriz');
        $client = $reviews->get('client');

        $correspondence->forceFill([
            'consultant_status' => $jpriz?->status_raw,
            'consultant_closed_date' => $jpriz?->closed_date,
            'client_status' => $client?->status_raw,
            'client_closed_date' => $client?->closed_date,
        ])->save();
    }

    private function normalizeReviewStatus(string $status): string
    {
        $normalized = strtolower(trim($status));
        $aliases = [
            'decline' => 'declined',
            'done' => 'closed',
            'forwarded' => 'pending',
            'forward to jpriz' => 'pending',
            'tunggu vo' => 'pending',
        ];
        $normalized = $aliases[$normalized] ?? $normalized;

        return in_array($normalized, CorrespondencePartyReview::NORMALIZED_STATUSES, true)
            ? $normalized
            : 'other';
    }

    private function nullablePayload(array $payload): array
    {
        return array_map(
            fn ($value) => is_string($value) && trim($value) === '' ? null : $value,
            $payload,
        );
    }

    private function hasMeaningfulValue(array $payload): bool
    {
        return collect($payload)->contains(fn ($value) => $value !== null && $value !== '' && $value !== []);
    }

    private function loadTrackingRelations(ProjectCorrespondence $correspondence): ProjectCorrespondence
    {
        return $correspondence->load([
            'project:id,name,code', 'creator:id,first_name,last_name', 'files',
            'fromParty:id,name,type', 'toParty:id,name,type',
            'detail.subcontractorParty:id,name,type', 'partyReviews.party:id,name,type',
            'outgoingLinks.target:id,project_id,type,reference_no,title',
        ]);
    }
}
