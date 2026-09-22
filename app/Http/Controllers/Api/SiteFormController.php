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
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

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
        $disk = Storage::disk('local');

        // Decide from the stored path, not the client-supplied name. Photos are served inline so the
        // daily site diary can render them; everything else is forced to download. Content-Type is
        // set explicitly because production PHP lacks the fileinfo extension that MIME guessing needs.
        $extension = strtolower(pathinfo($model->file_path, PATHINFO_EXTENSION));
        $inline = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];

        if (isset($inline[$extension])) {
            return response($disk->get($model->file_path), 200, [
                'Content-Type' => $inline[$extension],
                'Content-Disposition' => 'inline; filename="'.addslashes($model->file_name).'"',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return $disk->download($model->file_path, $model->file_name, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Build a DOCX from client-rendered sheet screenshots (one page image per
     * `.site-form-paper` sheet, captured with html2canvas in the viewer) and
     * return it as a download. Images are the only input — no server-side
     * PDF/HTML rendering is involved.
     */
    public function exportDocx(Request $request, int $id)
    {
        $form = SiteForm::findOrFail($id);

        $data = $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:10'],
            'images.*' => ['required', 'string', 'regex:/^data:image\/(jpeg|png);base64,/'],
        ]);

        $tempFiles = [];

        try {
            foreach ($data['images'] as $image) {
                [$header, $base64] = explode(',', $image, 2);
                $bytes = base64_decode($base64, true);

                if ($bytes === false || strlen($bytes) > 6 * 1024 * 1024) {
                    return response()->json(['message' => 'Each image must be a valid, non-empty base64 image no larger than 6 MB.'], 422);
                }

                $extension = str_contains($header, 'image/png') ? 'png' : 'jpg';
                $tmp = tempnam(sys_get_temp_dir(), 'sfd_').'.'.$extension;
                file_put_contents($tmp, $bytes);
                $tempFiles[] = $tmp;
            }

            $phpWord = new PhpWord;

            foreach ($tempFiles as $tmp) {
                $section = $phpWord->addSection([
                    'pageSizeW' => 11906,
                    'pageSizeH' => 16838,
                    'marginTop' => 567,
                    'marginBottom' => 567,
                    'marginLeft' => 567,
                    'marginRight' => 567,
                ]);
                $section->addImage($tmp, ['width' => 520, 'alignment' => 'center']);
            }

            $base = preg_replace('/[^A-Za-z0-9._-]+/', '-', $form->ref_no ?: "site-form-{$form->id}");
            $outputPath = tempnam(sys_get_temp_dir(), 'sfd_out_').'.docx';
            IOFactory::createWriter($phpWord, 'Word2007')->save($outputPath);

            return response()->download($outputPath, "{$base}.docx")->deleteFileAfterSend(true);
        } finally {
            foreach ($tempFiles as $tmp) {
                if (is_file($tmp)) {
                    @unlink($tmp);
                }
            }
        }
    }
}
