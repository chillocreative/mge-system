<?php

namespace App\Http\Controllers\Api;

use App\Exports\CorrespondenceRegisterExport;
use App\Http\Controllers\Controller;
use App\Services\CorrespondenceRegisterService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class CorrespondenceRegisterController extends Controller
{
    public function __construct(private CorrespondenceRegisterService $registerService) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'type' => ['required', 'exists:correspondence_types,code'],
        ]);

        return $this->success($this->registerService->context($validated['project_id'], $validated['type']));
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'type' => ['required', 'exists:correspondence_types,code'],
            'format' => ['required', 'in:xlsx,pdf'],
        ]);

        $context = $this->registerService->context($validated['project_id'], $validated['type']);
        // Project codes may contain "/" (e.g. contract-style codes), which is not allowed in a download filename.
        $code = trim(preg_replace('/[^A-Za-z0-9]+/', '-', $context['project']->code ?? 'project'), '-') ?: 'project';
        $filename = $code.'-'.strtoupper($validated['type']).'-register-'.now()->format('Ymd');

        if ($validated['format'] === 'xlsx') {
            return Excel::download(new CorrespondenceRegisterExport($context), "{$filename}.xlsx");
        }

        $context['logo'] = $this->logoData();
        $pdf = Pdf::loadView('pdf.correspondence-register', $context)->setPaper('a4', 'landscape');

        return $pdf->download("{$filename}.pdf");
    }

    private function logoData(): ?string
    {
        $path = public_path('logo.png');
        if (! is_file($path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode(file_get_contents($path));
    }
}
