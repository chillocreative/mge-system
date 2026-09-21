<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\SiteForm;
use App\Models\SiteFormAttachment;
use App\Services\SiteFormService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SiteFormController extends Controller
{
    public function __construct(private SiteFormService $service) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['form_type', 'project_id', 'search', 'per_page']);

        return $this->success($this->service->list($filters));
    }

    public function nextRef(Request $request): JsonResponse
    {
        $data = $request->validate([
            'form_type' => ['required', 'in:'.implode(',', SiteForm::types())],
            'project_id' => ['nullable', 'exists:projects,id'],
        ]);

        $project = ! empty($data['project_id']) ? Project::find($data['project_id']) : null;

        return $this->success(['ref_no' => $this->service->nextRefNo($data['form_type'], $project)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'form_type' => ['required', 'in:'.implode(',', SiteForm::types())],
            'project_id' => ['nullable', 'exists:projects,id'],
            'ref_no' => ['required', 'string', 'max:255'],
            'form_date' => ['nullable', 'date'],
            'title' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:20'],
            'data' => ['nullable', 'array'],
        ]);

        $form = $this->service->create($data, $request->user()->id);

        return $this->created($form, 'Site form created.');
    }

    public function show(int $id): JsonResponse
    {
        $form = SiteForm::with(['project', 'creator', 'attachments'])->findOrFail($id);

        return $this->success($form);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $form = SiteForm::findOrFail($id);

        $data = $request->validate([
            'form_type' => ['sometimes', 'required', 'in:'.implode(',', SiteForm::types())],
            'project_id' => ['nullable', 'exists:projects,id'],
            'ref_no' => ['sometimes', 'required', 'string', 'max:255'],
            'form_date' => ['nullable', 'date'],
            'title' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:20'],
            'data' => ['nullable', 'array'],
        ]);

        $form = $this->service->update($form, $data, $request->user()->id);

        return $this->success($form, 'Site form updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        $form = SiteForm::findOrFail($id);
        $this->service->delete($form);

        return $this->success(null, 'Site form deleted.');
    }

    public function uploadAttachment(Request $request, int $id): JsonResponse
    {
        $form = SiteForm::findOrFail($id);

        $data = $request->validate([
            'slot' => ['nullable', 'string', 'max:40'],
            'file' => ['required', 'file', 'max:10240', 'extensions:jpg,jpeg,png,pdf,doc,docx,xls,xlsx'],
        ]);

        $attachment = $this->service->addAttachment($form, $data['slot'] ?? null, $request->file('file'));

        return $this->created($attachment, 'Attachment uploaded.');
    }

    public function deleteAttachment(int $attachment): JsonResponse
    {
        $model = SiteFormAttachment::findOrFail($attachment);
        $this->service->removeAttachment($model);

        return $this->success(null, 'Attachment deleted.');
    }

    public function downloadAttachment(int $attachment)
    {
        $model = SiteFormAttachment::findOrFail($attachment);
        $extension = strtolower(pathinfo($model->file_name, PATHINFO_EXTENSION));

        if (in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
            return Storage::disk('local')->response($model->file_path, $model->file_name);
        }

        return Storage::disk('local')->download($model->file_path, $model->file_name);
    }
}
