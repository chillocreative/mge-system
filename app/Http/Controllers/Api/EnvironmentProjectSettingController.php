<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EnvironmentProjectSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EnvironmentProjectSettingController extends Controller
{
    private const KINDS = ['policy', 'location'];

    public function show(int $project): JsonResponse
    {
        // Check project exists first - prevent FK 500 on unknown project
        if (! \App\Models\Project::where('id', $project)->exists()) {
            return $this->error('Project not found.', 422);
        }

        $setting = EnvironmentProjectSetting::with('project')->firstOrNew(['project_id' => $project]);

        return $this->success($this->present($setting, $project));
    }

    public function update(Request $request, int $project): JsonResponse
    {
        // Check project exists first - prevent FK 500 on unknown project
        if (! \App\Models\Project::where('id', $project)->exists()) {
            return $this->error('Project not found.', 422);
        }

        $data = $request->validate([
            'consultant_company' => ['nullable', 'string', 'max:255'],
            'consultant_name' => ['nullable', 'string', 'max:255'],
            'consultant_reg_no' => ['nullable', 'string', 'max:255'],
            'officer_name' => ['nullable', 'string', 'max:255'],
            'officer_reg_no' => ['nullable', 'string', 'max:255'],
        ]);

        // updateOrCreate, not firstOrCreate: firstOrCreate applies $data only when it creates the
        // row, so every PUT after the first would silently discard the operator's edits.
        $setting = EnvironmentProjectSetting::updateOrCreate(
            ['project_id' => $project],
            $data
        );

        $setting->load('project');

        return $this->success($this->present($setting, $project), 'Environment settings updated.');
    }

    public function uploadImage(Request $request, int $project): JsonResponse
    {
        // Check project exists first - prevent FK 500 on unknown project
        if (! \App\Models\Project::where('id', $project)->exists()) {
            return $this->error('Project not found.', 422);
        }

        $data = $request->validate([
            'kind' => ['required', 'in:'.implode(',', self::KINDS)],
            'file' => ['required', 'file', 'max:10240', 'extensions:jpg,jpeg,png,pdf'],
        ]);

        $setting = EnvironmentProjectSetting::firstOrNew(['project_id' => $project]);
        $column = "{$data['kind']}_image_path";

        // Store new file FIRST before deleting old one
        $newPath = $request->file('file')->store("projects/environment/{$project}", 'local');

        // Save old path BEFORE we overwrite
        $oldPath = $setting->{$column};

        $setting->project_id = $project;
        $setting->{$column} = $newPath;
        $setting->save();

        // Now safe to delete old file after successful save of new one
        if ($oldPath && $oldPath !== $newPath) {
            Storage::disk('local')->delete($oldPath);
        }

        return $this->success($this->present($setting, $project), 'Image uploaded.');
    }

    public function image(int $project, string $kind)
    {
        // Check project exists first
        if (! \App\Models\Project::where('id', $project)->exists()) {
            abort(404);
        }

        abort_unless(in_array($kind, self::KINDS, true), 404);

        $setting = EnvironmentProjectSetting::where('project_id', $project)->firstOrFail();
        $column = "{$kind}_image_path";
        $path = $setting->{$column};

        abort_if(! $path, 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf'];
        $contentType = $types[$extension] ?? 'application/octet-stream';

        return response($disk->get($path), 200, [
            'Content-Type' => $contentType,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
            // Uploaded files are user content: never let an embedded script run in our origin.
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'",
        ]);
    }

    public function deleteImage(int $project, string $kind): JsonResponse
    {
        // Check project exists first
        if (! \App\Models\Project::where('id', $project)->exists()) {
            return $this->error('Project not found.', 422);
        }

        abort_unless(in_array($kind, self::KINDS, true), 404);

        $setting = EnvironmentProjectSetting::where('project_id', $project)->firstOrFail();
        $column = "{$kind}_image_path";

        if ($setting->{$column}) {
            Storage::disk('local')->delete($setting->{$column});
            $setting->{$column} = null;
            $setting->save();
        }

        return $this->success($this->present($setting, $project), 'Image removed.');
    }

    private function present(EnvironmentProjectSetting $setting, int $project): EnvironmentProjectSetting
    {
        $setting->policy_image_url = $setting->policy_image_path
            ? url("/api/environment/settings/{$project}/image/policy")
            : null;

        $setting->location_map_url = $setting->location_map_path
            ? url("/api/environment/settings/{$project}/image/location")
            : null;

        return $setting;
    }
}
