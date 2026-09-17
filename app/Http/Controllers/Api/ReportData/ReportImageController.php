<?php

namespace App\Http\Controllers\Api\ReportData;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ReportImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReportImageController extends Controller
{
    private const TYPES = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp'];

    public function index(int $projectId, Request $request): JsonResponse
    {
        $q = ReportImage::where('project_id', $projectId)->orderBy('section')->orderBy('sort_order')->orderBy('id');
        if ($request->section) {
            $q->where('section', $request->section);
        }
        if ($request->filled('period_id')) {
            $q->where('period_id', $request->integer('period_id'));
        }

        return $this->success($q->get());
    }

    public function store(int $projectId, Request $request): JsonResponse
    {
        Project::findOrFail($projectId);
        $validated = $request->validate([
            'image' => ['required', 'file', 'max:20480', 'extensions:png,jpg,jpeg,webp'],
            'section' => ['required', 'in:'.implode(',', ReportImage::SECTIONS)],
            'label' => ['nullable', 'string', 'max:100'],
            'period_id' => ['nullable', 'integer', 'exists:project_progress_periods,id,project_id,'.$projectId],
            'caption' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'taken_on' => ['nullable', 'date'],
        ]);
        $file = $request->file('image');
        unset($validated['image']);

        $path = $file->store("projects/{$projectId}/report-images", 'local');
        $this->downscale(Storage::disk('local')->path($path));

        $image = ReportImage::create($validated + [
            'project_id' => $projectId,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'created_by' => $request->user()->id,
        ]);

        return $this->created($image, 'Image uploaded.');
    }

    public function update(int $projectId, int $imageId, Request $request): JsonResponse
    {
        $image = ReportImage::where('project_id', $projectId)->findOrFail($imageId);
        $image->update($request->validate([
            'section' => ['sometimes', 'in:'.implode(',', ReportImage::SECTIONS)],
            'label' => ['nullable', 'string', 'max:100'],
            'period_id' => ['nullable', 'integer', 'exists:project_progress_periods,id,project_id,'.$projectId],
            'caption' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'taken_on' => ['nullable', 'date'],
        ]));

        return $this->success($image->fresh(), 'Image updated.');
    }

    public function destroy(int $projectId, int $imageId): JsonResponse
    {
        $image = ReportImage::where('project_id', $projectId)->findOrFail($imageId);
        Storage::disk('local')->delete($image->file_path);
        $image->delete();

        return $this->success(null, 'Image removed.');
    }

    public function view(int $projectId, int $imageId)
    {
        $image = ReportImage::where('project_id', $projectId)->findOrFail($imageId);
        abort_unless(Storage::disk('local')->exists($image->file_path), 404);
        $ext = strtolower(pathinfo($image->file_path, PATHINFO_EXTENSION));

        return response()->file(Storage::disk('local')->path($image->file_path), [
            'Content-Type' => self::TYPES[$ext] ?? 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.addslashes($image->file_name).'"',
        ]);
    }

    /** Best-effort: shrink large photos so a 40-page PDF stays within memory. Requires GD; no-op otherwise. */
    private function downscale(string $absolutePath, int $maxWidth = 1600): void
    {
        if (! function_exists('imagecreatefromstring')) {
            return;
        }
        $info = @getimagesize($absolutePath);
        if (! $info || $info[0] <= $maxWidth) {
            return;
        }
        $src = @imagecreatefromstring((string) file_get_contents($absolutePath));
        if (! $src) {
            return;
        }
        $ratio = $maxWidth / $info[0];
        $dst = imagecreatetruecolor($maxWidth, (int) round($info[1] * $ratio));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $maxWidth, (int) round($info[1] * $ratio), $info[0], $info[1]);
        match ($info[2]) {
            IMAGETYPE_PNG => imagepng($dst, $absolutePath, 6),
            IMAGETYPE_WEBP => imagewebp($dst, $absolutePath, 82),
            default => imagejpeg($dst, $absolutePath, 82),
        };
        imagedestroy($src);
        imagedestroy($dst);
    }
}
