<?php

namespace App\Services\Environment;

use App\Models\WaterQualityRecord;
use Barryvdh\DomPDF\Facade\Pdf;

class WaterQualityPdf
{
    public function build(WaterQualityRecord $record)
    {
        $record->loadMissing('project');

        return Pdf::loadView('pdf.water-quality-worksheet', [
            'record' => $record,
            'project' => $record->project,
            'points' => WaterQualityRecord::POINTS,
            'conditionOptions' => WaterQualityRecord::CONDITION_OPTIONS,
            'insituParams' => WaterQualityRecord::INSITU_PARAMS,
            'logo' => $this->logoData(),
        ])->setPaper('a4', 'landscape');
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
