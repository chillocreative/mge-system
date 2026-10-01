<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\CorrespondenceWorkbookImportService;
use Illuminate\Console\Command;

class ImportCorrespondenceWorkbook extends Command
{
    protected $signature = 'correspondence:import-workbook
        {file : Absolute or project-relative XLSX/XLS path}
        {--project= : Optional project ID override for every operational row}
        {--commit : Write valid rows; omitted means preview only}
        {--user= : Importing user ID, required with --commit}';

    protected $description = 'Preview or safely import the legacy Project Correspondence workbook';

    public function handle(CorrespondenceWorkbookImportService $service): int
    {
        $file = (string) $this->argument('file');
        $path = str_starts_with($file, DIRECTORY_SEPARATOR) ? $file : base_path($file);
        $projectId = $this->option('project') !== null ? (int) $this->option('project') : null;

        if (! $this->option('commit')) {
            $result = $service->preview($path, $projectId, basename($path));
            $this->render($result);
            $this->warn('Preview only. Re-run with --commit --user=<id> after reviewing errors and duplicates.');

            return self::SUCCESS;
        }

        $userId = (int) $this->option('user');
        if (! $userId || ! User::whereKey($userId)->exists()) {
            $this->error('A valid --user=<id> is required with --commit.');

            return self::FAILURE;
        }

        $result = $service->import($path, $userId, $projectId, basename($path));
        $this->render($result);
        $this->table(['Imported', 'Duplicate', 'Error'], [[
            $result['result']['imported'],
            $result['result']['duplicate'],
            $result['result']['error'],
        ]]);

        return $result['result']['error'] > 0 ? self::INVALID : self::SUCCESS;
    }

    private function render(array $result): void
    {
        $this->info("{$result['mode']}: {$result['source_file']}");
        $this->table(['Rows', 'Ready', 'Duplicate', 'Error'], [[
            $result['totals']['rows'],
            $result['totals']['ready'],
            $result['totals']['duplicate'],
            $result['totals']['error'],
        ]]);
        foreach ($result['errors'] as $error) {
            $this->error("{$error['sheet']} row {$error['row']}: {$error['message']}");
        }
    }
}
