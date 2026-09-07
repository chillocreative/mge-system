<?php

namespace Chillocreative\QwenAgent\Console\Commands;

use Chillocreative\QwenAgent\QwenService;
use Illuminate\Console\Command;

/**
 * Invoke the Qwen coding sub-agent for a single delegated task.
 *
 * This command NEVER writes to the host project itself — it only calls Qwen
 * and prints (or saves) the raw response text. The Lead (Claude) reads that
 * output, decides whether to apply it, and makes any file edits itself via
 * its own tools. This keeps a review checkpoint between Qwen's suggestion
 * and any actual file mutation.
 *
 * Usage:
 *   php artisan qwen:agent storage/app/qwen-tasks/some-task.txt
 *   php artisan qwen:agent storage/app/qwen-tasks/some-task.txt --context=app/Models/Foo.php,app/Http/Controllers/FooController.php
 *   php artisan qwen:agent storage/app/qwen-tasks/some-task.txt --out=storage/app/qwen-tasks/some-task.out.txt
 */
class QwenAgent extends Command
{
    protected $signature = 'qwen:agent
                            {prompt : Path to a text file containing the full task prompt}
                            {--context= : Comma-separated repo-relative file paths to append as read-only context}
                            {--out= : Optional path to also write the raw response text to}
                            {--max-tokens=4096 : Max output tokens}
                            {--timeout=120 : HTTP timeout in seconds}';

    protected $description = 'Send a delegated coding task to the Qwen sub-agent and print its response (read-only, no file writes to the host project)';

    private const SYSTEM_PROMPT = <<<'PROMPT'
You are a coding sub-agent working under a Lead Software Engineer. You do not
have direct file access — respond with the exact file content or unified
diff needed, inside fenced code blocks labelled with the target file path,
so the Lead Engineer can apply it after review. Follow existing project
conventions shown in any provided context. Do not invent APIs, models, or
files that were not shown to you or explicitly described in the task.
PROMPT;

    public function handle(QwenService $qwen): int
    {
        $promptPath = $this->argument('prompt');

        if (! is_file($promptPath)) {
            $this->error("Prompt file not found: {$promptPath}");

            return self::FAILURE;
        }

        $prompt = file_get_contents($promptPath);

        if ($context = $this->option('context')) {
            $prompt .= "\n\n---\nCONTEXT FILES (read-only, for reference):\n";

            foreach (array_filter(array_map('trim', explode(',', $context))) as $relativePath) {
                $fullPath = base_path($relativePath);

                if (! is_file($fullPath)) {
                    $this->warn("Context file not found, skipping: {$relativePath}");

                    continue;
                }

                $prompt .= "\n### {$relativePath}\n```\n".file_get_contents($fullPath)."\n```\n";
            }
        }

        $this->info('Sending task to Qwen ('.config('qwen.model').')...');

        $result = $qwen->chat(
            systemPrompt: self::SYSTEM_PROMPT,
            userPrompt: $prompt,
            maxTokens: (int) $this->option('max-tokens'),
            timeout: (int) $this->option('timeout'),
        );

        if (! $result['ok']) {
            $this->error('Qwen call failed: '.($result['error'] ?? 'unknown_error'));

            return self::FAILURE;
        }

        if ($outPath = $this->option('out')) {
            file_put_contents($outPath, $result['content']);
            $this->info("Response written to {$outPath}");
        }

        $this->line($result['content']);

        return self::SUCCESS;
    }
}
