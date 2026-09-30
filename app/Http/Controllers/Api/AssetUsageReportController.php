<?php

namespace App\Http\Controllers\Api;

use App\Exports\AssetUsageReportExport;
use App\Http\Controllers\Controller;
use App\Services\AssetUsageReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

class AssetUsageReportController extends Controller
{
    public function __construct(private AssetUsageReportService $service) {}

    public function index(Request $request)
    {
        $filters = $this->filters($request);

        return $this->success($this->service->generate($filters['month'], $filters['category'] ?? null));
    }

    public function export(Request $request)
    {
        $filters = $this->filters($request, true);
        $report = $this->service->generate($filters['month'], $filters['category'] ?? null);
        $filename = 'asset-usage-'.$filters['month'];

        if ($filters['format'] === 'pdf') {
            return Pdf::loadView('pdf.asset-usage-report', ['report' => $report])
                ->setPaper('a4', 'landscape')
                ->download($filename.'.pdf');
        }

        if ($filters['format'] === 'docx') {
            return $this->docx($report, $filename);
        }

        return Excel::download(new AssetUsageReportExport($report['rows']), $filename.'.xlsx');
    }

    private function filters(Request $request, bool $withFormat = false): array
    {
        $rules = [
            'month' => ['nullable', 'date_format:Y-m'],
            'category' => ['nullable', 'in:vehicle,machine'],
        ];
        if ($withFormat) {
            $rules['format'] = ['required', 'in:pdf,docx,xlsx'];
        }

        $validated = $request->validate($rules);
        $validated['month'] ??= now()->format('Y-m');

        return $validated;
    }

    private function docx(array $report, string $filename)
    {
        $phpWord = new PhpWord;
        $section = $phpWord->addSection(['orientation' => 'landscape']);
        $section->addTitle('Vehicle & Machine Usage Report', 1);
        $section->addText('Reporting month: '.$report['month_label']);
        $section->addTextBreak();
        $table = $section->addTable(['borderSize' => 6, 'borderColor' => 'B7C3D0', 'cellMargin' => 60]);
        $headers = ['Asset', 'Category', 'Projects', 'Month Days', 'Total Days', 'Maintenance', 'Maint. Due', 'Road Tax', 'Days Left'];
        $table->addRow();
        foreach ($headers as $header) {
            $table->addCell()->addText($header, ['bold' => true]);
        }
        foreach ($report['rows'] as $row) {
            $table->addRow();
            foreach ([
                $row['asset_no'].' '.$row['asset'], ucfirst($row['category']), $row['projects'] ?: '-',
                (string) $row['monthly_usage_days'], (string) $row['total_usage_days'],
                ucfirst(str_replace('_', ' ', $row['maintenance_status'])), $row['maintenance_due_date'] ?: '-',
                $row['road_tax_expiry_date'] ?: '-', (string) ($row['road_tax_days_remaining'] ?? '-'),
            ] as $value) {
                $table->addCell()->addText($value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'asset_report_').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return response()->download($path, $filename.'.docx')->deleteFileAfterSend(true);
    }
}
