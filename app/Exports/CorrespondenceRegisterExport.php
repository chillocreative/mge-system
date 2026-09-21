<?php

namespace App\Exports;

use App\Models\CorrespondenceType;
use App\Models\Project;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Mirrors the client's Excel correspondence register layout exactly:
 * logo + project header (rows 1-5), type title (row 7), two-row column
 * header (rows 9-10), then data rows from row 11.
 */
class CorrespondenceRegisterExport implements WithColumnWidths, WithDrawings, WithEvents, WithStyles, WithTitle
{
    private Project $project;

    private CorrespondenceType $type;

    private Collection $rows;

    public function __construct(array $context)
    {
        $this->project = $context['project'];
        $this->type = $context['type'];
        $this->rows = $context['rows'];
    }

    public function title(): string
    {
        return strtoupper($this->type->code);
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,
            'B' => 22,
            'C' => 40,
            'D' => 14,
            'E' => 14,
            'F' => 14,
            'G' => 14,
            'H' => 30,
            'I' => 14,
        ];
    }

    public function drawings()
    {
        $path = public_path('logo.png');
        if (! is_file($path)) {
            return [];
        }

        $drawing = new Drawing;
        $drawing->setName('Logo');
        $drawing->setPath($path);
        $drawing->setHeight(60);
        $drawing->setCoordinates('A1');

        return $drawing;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastDataRow = 10 + max($this->rows->count(), 1);

                $this->writeHeader($sheet);
                $this->writeColumnHeader($sheet);
                $this->writeData($sheet);
                $this->applyBorders($sheet, $lastDataRow);
            },
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [];
    }

    private function writeHeader(Worksheet $sheet): void
    {
        $sheet->mergeCells('A1:B5');
        $sheet->mergeCells('C1:I5');
        $sheet->setCellValue('C1', 'PROJECT : '.strtoupper($this->project->name));
        $sheet->getStyle('C1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('C1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        $title = $this->type->full_name.' ('.strtoupper($this->type->code).')';
        $sheet->mergeCells('A7:I7');
        $sheet->setCellValue('A7', $title);
        $sheet->getStyle('A7')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    private function writeColumnHeader(Worksheet $sheet): void
    {
        $sheet->mergeCells('A9:A10');
        $sheet->mergeCells('B9:B10');
        $sheet->mergeCells('C9:C10');
        $sheet->mergeCells('D9:D10');
        $sheet->mergeCells('G9:G10');
        $sheet->mergeCells('H9:H10');
        $sheet->mergeCells('I9:I10');

        $sheet->setCellValue('A9', 'Bil');
        $sheet->setCellValue('B9', 'Reference Number');
        $sheet->setCellValue('C9', 'Title');
        $sheet->setCellValue('D9', 'Date Issued');
        $sheet->setCellValue('E9', 'Date');
        $sheet->setCellValue('E10', 'Inspection');
        $sheet->setCellValue('F9', 'Date');
        $sheet->setCellValue('F10', 'Closed');
        $sheet->setCellValue('G9', 'Status');
        $sheet->setCellValue('H9', 'REMARKS');
        $sheet->setCellValue('I9', 'ATTACHMENT');

        $headerRange = $sheet->getStyle('A9:I10');
        $headerRange->getFont()->setBold(true);
        $headerRange->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
    }

    private function writeData(Worksheet $sheet): void
    {
        $row = 11;
        foreach ($this->rows as $item) {
            $sheet->setCellValueExplicit('A'.$row, number_format($item['bil'], 1, '.', ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue('B'.$row, $item['reference_no']);
            $sheet->setCellValue('C'.$row, $item['title']);
            $sheet->setCellValue('D'.$row, $this->formatDate($item['date_issued']));
            $sheet->setCellValue('E'.$row, $this->formatDate($item['date_inspection']));
            $sheet->setCellValue('F'.$row, $this->formatDate($item['date_closed']));
            $sheet->setCellValue('G'.$row, strtoupper((string) $item['status']));
            $sheet->setCellValue('H'.$row, $item['remarks']);
            $sheet->setCellValue('I'.$row, $item['attachments']);

            $sheet->getStyle("A{$row}:I{$row}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
            $sheet->getStyle("C{$row}")->getAlignment()->setWrapText(true);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D{$row}:G{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("I{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $row++;
        }
    }

    private function formatDate(?string $date): string
    {
        if (! $date) {
            return '';
        }

        return \Carbon\Carbon::parse($date)->format('d/m/Y');
    }

    private function applyBorders(Worksheet $sheet, int $lastDataRow): void
    {
        $range = "A9:I{$lastDataRow}";
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }
}
