<?php

namespace App\Services;

use App\Models\Project;
use App\Models\SiteForm;
use App\Models\SiteFormAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;

class SiteFormService
{
    public function list(array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 20);

        $query = SiteForm::with(['project:id,name,code', 'creator:id,first_name,last_name'])
            ->orderByDesc('form_date')
            ->orderByDesc('id');

        if (! empty($filters['form_type'])) {
            $query->ofType($filters['form_type']);
        }
        if (! empty($filters['project_id'])) {
            $query->forProject($filters['project_id']);
        }
        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        return $query->paginate($perPage);
    }

    public function create(array $data, int $userId): SiteForm
    {
        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;

        return SiteForm::create($data)->load(['project:id,name,code', 'creator:id,first_name,last_name']);
    }

    public function update(SiteForm $form, array $data, int $userId): SiteForm
    {
        $data['updated_by'] = $userId;
        $form->update($data);

        return $form->load(['project:id,name,code', 'creator:id,first_name,last_name']);
    }

    public function delete(SiteForm $form): void
    {
        foreach ($form->attachments as $attachment) {
            Storage::disk('local')->delete($attachment->file_path);
            $attachment->delete();
        }

        $form->delete();
    }

    public function nextRefNo(string $type, ?Project $project): string
    {
        $prefix = SiteForm::TYPES[$type]['ref_prefix'] ?? strtoupper($type);
        $code = $project?->code ? strtoupper($project->code) : 'MGE';
        $year = now()->format('y');

        $count = SiteForm::ofType($type)
            ->when($project, fn ($q) => $q->forProject($project->id), fn ($q) => $q->whereNull('project_id'))
            ->whereYear('created_at', now()->year)
            ->count();

        $seq = str_pad((string) ($count + 1), 2, '0', STR_PAD_LEFT);

        return "MGE/{$code}/{$prefix}/{$year}-{$seq}";
    }

    public function addAttachment(SiteForm $form, ?string $slot, UploadedFile $file): SiteFormAttachment
    {
        if ($slot) {
            $existing = $form->attachments()->where('slot', $slot)->first();
            if ($existing) {
                Storage::disk('local')->delete($existing->file_path);
                $existing->delete();
            }
        }

        $path = $file->store("projects/site-forms/{$form->id}", 'local');

        return $form->attachments()->create([
            'slot' => $slot,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_type' => $file->getClientOriginalExtension(),
            'file_size' => $file->getSize(),
        ]);
    }

    public function removeAttachment(SiteFormAttachment $attachment): void
    {
        Storage::disk('local')->delete($attachment->file_path);
        $attachment->delete();
    }
}
