<?php

namespace App\Services\MonthlyReport\Export\Docx\Writers;

use App\Services\MonthlyReport\Export\Docx\DocxDocument;

/**
 * Renders 1.3 PROJECT LOCATION (a 2-column image grid — s1-3.blade.php) and 5.0 PROGRESS
 * PHOTOGRAPH (site access + key plan grids, then a Previous | Current pairs table —
 * s5-0.blade.php). Images arrive pre-embedded as data URIs in view data; this writer just
 * decodes them.
 */
final class ImagesWriter implements SectionWriter
{
    public function write(DocxDocument $doc, string $key, array $data, ?string $note, array $ctx): void
    {
        if ($key === '5.0') {
            $this->writeProgressPhotos($doc, $data);

            return;
        }

        $this->writeImageGrid($doc, $data['images'] ?? []);
    }

    private function writeProgressPhotos(DocxDocument $doc, array $data): void
    {
        $hasAny = ! empty($data['site_access']) || ! empty($data['key_plan']) || ! empty($data['pairs']);

        if (! $hasAny) {
            $doc->paragraph('No data.', ['italic' => true, 'color' => '555555']);

            return;
        }

        if (! empty($data['site_access'])) {
            $doc->heading('Site Access', 2);
            $this->writeImageGrid($doc, $data['site_access']);
        }

        if (! empty($data['key_plan'])) {
            $doc->heading('Key Plan', 2);
            $this->writeImageGrid($doc, $data['key_plan']);
        }

        if (! empty($data['pairs'])) {
            $pairs = array_map(fn ($pair) => [
                'label' => $pair['label'] ?? '',
                'previous' => $pair['previous'] ? $this->imageItem($pair['previous']) : null,
                'current' => $pair['current'] ? $this->imageItem($pair['current']) : null,
            ], $data['pairs']);

            $doc->imagePairs($pairs, ['widthMm' => 75]);
        }
    }

    private function writeImageGrid(DocxDocument $doc, array $images): void
    {
        if (empty($images)) {
            $doc->paragraph('No data.', ['italic' => true, 'color' => '555555']);

            return;
        }

        $items = array_map(fn ($img) => $this->imageItem($img), $images);

        $doc->imageGrid($items, ['columns' => 2, 'widthMm' => 80]);
    }

    /** @return array{image: ?string, caption: string} */
    private function imageItem(array $img): array
    {
        return [
            'image' => DocxDocument::binaryFromDataUri($img['data_uri'] ?? null),
            'caption' => (string) ($img['caption'] ?? ''),
        ];
    }
}
