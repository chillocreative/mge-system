<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CalendarService;
use App\Services\GoogleCalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function __construct(
        private CalendarService $calendarService,
        private GoogleCalendarService $googleService,
    ) {}

    // ── Events ──
    // Permission gates (calendar.view / calendar.manage) are enforced via
    // route middleware in routes/api.php, matching the project convention.

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['start', 'end', 'type']);

        return $this->success($this->calendarService->list($filters));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateEvent($request);

        $event = $this->calendarService->create($validated, $request->user()->id);

        return $this->created($event, 'Event created successfully.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $this->validateEvent($request, true);

        return $this->success($this->calendarService->update($id, $validated), 'Event updated successfully.');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->calendarService->delete($id);

        return $this->success(null, 'Event deleted successfully.');
    }

    // ── Google Calendar ──

    public function googleStatus(Request $request): JsonResponse
    {
        return $this->success($this->googleService->status($request->user()->id));
    }

    public function googleConnect(Request $request)
    {
        try {
            return redirect()->away($this->googleService->authUrl());
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function googleCallback(Request $request): JsonResponse
    {
        try {
            $code = $request->query('code');
            if (!$code) {
                return $this->error('Missing authorization code.', 422);
            }
            $this->googleService->handleCallback($code, $request->user()->id);

            return $this->success(null, 'Google Calendar connected successfully.');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function googleSync(Request $request): JsonResponse
    {
        try {
            $count = $this->googleService->syncPull($request->user()->id);

            return $this->success(['synced' => $count], "Synced {$count} event(s) from Google Calendar.");
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    // ── Validation ──

    private function validateEvent(Request $request, bool $partial = false): array
    {
        $rule = fn (array $r) => $partial ? array_merge(['sometimes'], $r) : $r;

        return $request->validate([
            'title' => $rule(['required', 'string', 'max:255']),
            'description' => ['nullable', 'string'],
            'type' => $rule(['required', 'in:meeting,holiday,leave,deadline,training,other']),
            'start_datetime' => $rule(['required', 'date']),
            'end_datetime' => ['nullable', 'date', 'after_or_equal:start_datetime'],
            'all_day' => ['nullable', 'boolean'],
            'location' => ['nullable', 'string', 'max:255'],
            'employee_id' => ['nullable', 'exists:employees,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
        ]);
    }
}
