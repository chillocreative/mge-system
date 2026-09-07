# chillocreative/qwen-agent

Reusable Laravel package providing a Qwen coding sub-agent, for the
"Claude as Lead Engineer, Qwen as low-cost implementation sub-agent" pattern.

Ships:
- `Chillocreative\QwenAgent\QwenService` — HTTP client for the Qwen
  (DashScope/QwenCloud) OpenAI-compatible chat completions API. Never
  throws; always returns `['ok'=>bool,'content'=>string|null,'raw'=>array|null,'error'=>string|null]`.
- `php artisan qwen:agent <prompt-file>` — sends a task to Qwen and prints
  the response. Read-only: it never writes to the host project. The Lead
  (Claude, or you) reviews the output and applies changes with its own tools.

## Install in a Laravel project

Add this repo as a path repository in the project's `composer.json`:

```json
"repositories": [
    { "type": "path", "url": "../qwen-agent" }
]
```

(Adjust the relative path so it points at wherever this package sits
relative to the project, e.g. `/Volumes/SSD/Projects/qwen-agent`.)

Then:

```bash
composer require chillocreative/qwen-agent:*
```

Laravel package auto-discovery registers `QwenAgentServiceProvider`
automatically — no manual provider registration needed.

## Configure

Add to the project's `.env`:

```env
QWEN_API_KEY=
QWEN_BASE_URL=https://dashscope-intl.aliyuncs.com/compatible-mode/v1
QWEN_MODEL=qwen3.7-flash
```

Optionally publish the config file to override defaults per-project:

```bash
php artisan vendor:publish --tag=qwen-agent-config
```

## Usage

```bash
php artisan qwen:agent storage/app/qwen-tasks/some-task.txt \
    --context=app/Models/Foo.php,app/Http/Controllers/FooController.php
```

Add `/storage/app/qwen-tasks/` to the host project's `.gitignore` — it's
scratch space for task prompts and raw responses, not meant to be committed.
