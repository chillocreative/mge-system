<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\HazardReport;
use App\Models\Invoice;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\Project;
use App\Models\SafetyIncident;
use App\Models\Task;
use App\Models\TrainingRequest;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * All dashboard data, scoped by what the user is allowed to see.
     */
    public function getData(User $user): array
    {
        $isAdmin = $user->hasRole('Admin & HR');
        $can = fn (string $perm) => $isAdmin || $user->can($perm);

        return [
            'stats' => $this->getStats($user, $can),
            'charts' => $this->getCharts($user, $isAdmin, $can),
            'lists' => $this->getLists($user, $isAdmin, $can),
            'my' => $this->getMyData($user),
        ];
    }

    // ── Stats ───────────────────────────────────────────────────────────────

    private function getStats(User $user, callable $can): array
    {
        $stats = [];
        $today = Carbon::today()->toDateString();

        if ($can('projects.view')) {
            $p = Project::query()
                ->selectRaw('COUNT(*) as total')
                ->selectRaw("SUM(CASE WHEN status NOT IN ('completed','cancelled') THEN 1 ELSE 0 END) as active")
                ->selectRaw("SUM(CASE WHEN end_date < CURDATE() AND status NOT IN ('completed','cancelled') THEN 1 ELSE 0 END) as delayed_count")
                ->first();
            $stats['total_projects'] = (int) $p->total;
            $stats['active_projects'] = (int) $p->active;
            $stats['delayed_projects'] = (int) $p->delayed_count;
        }

        // Tasks — always show the user's own; overdue is org-wide for task viewers.
        $stats['my_open_tasks'] = Task::where('assigned_to', $user->id)
            ->whereNotIn('status', ['completed', 'cancelled'])->count();
        $overdue = Task::query()->where('due_date', '<', $today)
            ->whereNotIn('status', ['completed', 'cancelled']);
        if (!$can('tasks.view')) {
            $overdue->where('assigned_to', $user->id);
        }
        $stats['overdue_tasks'] = $overdue->count();

        if ($can('clients.view')) {
            $stats['total_clients'] = Client::where('status', 'active')->count();
        }

        if ($can('finance.view') || $can('dashboard.view-finance-stats')) {
            $stats['total_revenue'] = round((float) Project::sum('budget'), 2);
            $stats['receivables'] = round((float) Invoice::whereNotIn('status', ['paid', 'cancelled'])->sum('balance_due'), 2);
            $stats['overdue_invoices'] = Invoice::whereNotIn('status', ['paid', 'cancelled'])
                ->whereDate('due_date', '<', $today)->count();
            $stats['pending_expenses'] = round((float) Expense::where('status', 'pending')->sum('amount'), 2);
        }

        if ($can('staff.view') || $can('dashboard.view-hr-stats')) {
            $stats['total_staff'] = Employee::where('status', 'active')->count();
        }

        if ($this->canApproveLeave($user, $can)) {
            $stats['pending_approvals'] = $this->pendingLeaveQuery($user, $can)->count();
        }

        if ($can('training.view')) {
            $stats['training_pending'] = TrainingRequest::where('status', 'pending')->count();
        }

        if ($can('safety.view')) {
            $stats['open_incidents'] = SafetyIncident::whereIn('status', ['open', 'investigating'])->count();
            $stats['open_hazards'] = HazardReport::whereIn('status', ['open', 'mitigated'])->count();
        }

        return $stats;
    }

    // ── Charts ──────────────────────────────────────────────────────────────

    private function getCharts(User $user, bool $isAdmin, callable $can): array
    {
        $charts = [];

        if ($can('projects.view')) {
            $charts['project_status'] = $this->groupCount(Project::query(), 'status');
            $charts['budget_by_status'] = $this->budgetByStatus();
            $charts['monthly_projects'] = $this->monthlyProjectTrend();
            $charts['top_projects'] = $this->topProjectsByBudget();
        }

        // Task breakdown — admins see all, others their own.
        $charts['task_status'] = $this->groupCount(
            Task::query()->when(!$isAdmin, fn ($q) => $q->where('assigned_to', $user->id)),
            'status'
        );

        if ($can('leave.view')) {
            $charts['leave_status'] = $this->groupCount(
                LeaveRequest::whereYear('created_at', Carbon::now()->year),
                'status'
            );
        }

        if ($can('finance.view')) {
            $charts['expense_category'] = Expense::query()
                ->select('category', DB::raw('COALESCE(SUM(amount),0) as total'))
                ->groupBy('category')
                ->orderByDesc('total')
                ->get()
                ->map(fn ($r) => ['label' => ucfirst(str_replace('_', ' ', $r->category)), 'value' => round((float) $r->total, 2)])
                ->all();
        }

        return $charts;
    }

    private function groupCount($query, string $column): array
    {
        return $query->select($column, DB::raw('COUNT(*) as count'))
            ->groupBy($column)
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'label' => ucfirst(str_replace('_', ' ', (string) $row->{$column})),
                'value' => (int) $row->count,
            ])
            ->values()
            ->all();
    }

    private function budgetByStatus(): array
    {
        return Project::query()
            ->select('status')
            ->selectRaw('COALESCE(SUM(budget), 0) as budget')
            ->selectRaw('COALESCE(SUM(spent), 0) as spent')
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(fn ($row) => [
                'label' => ucfirst(str_replace('_', ' ', $row->status)),
                'budget' => round((float) $row->budget, 2),
                'spent' => round((float) $row->spent, 2),
            ])
            ->all();
    }

    private function monthlyProjectTrend(): array
    {
        $since = Carbon::now()->subMonths(11)->startOfMonth();
        $rows = Project::query()
            ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"), DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', $since)
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        $result = [];
        for ($i = 0; $i < 12; $i++) {
            $m = Carbon::now()->subMonths(11 - $i);
            $result[] = ['label' => $m->format('M Y'), 'value' => (int) ($rows[$m->format('Y-m')]->count ?? 0)];
        }

        return $result;
    }

    private function topProjectsByBudget(): array
    {
        return Project::query()
            ->select('name', 'budget', 'spent', 'progress')
            ->where('budget', '>', 0)
            ->orderByDesc('budget')
            ->limit(5)
            ->get()
            ->map(fn ($p) => [
                'name' => $p->name,
                'budget' => round((float) $p->budget, 2),
                'spent' => round((float) $p->spent, 2),
                'progress' => (int) $p->progress,
            ])
            ->all();
    }

    // ── Lists ───────────────────────────────────────────────────────────────

    private function getLists(User $user, bool $isAdmin, callable $can): array
    {
        $lists = [
            'my_tasks' => $this->getMyTasks($user),
        ];

        if ($can('projects.view')) {
            $lists['recent_projects'] = $this->getRecentProjects();
        }

        if ($this->canApproveLeave($user, $can)) {
            $lists['pending_approvals'] = $this->pendingLeaveQuery($user, $can)
                ->with('employee:id,first_name,last_name', 'leaveType:id,name')
                ->orderByDesc('created_at')
                ->limit(6)
                ->get()
                ->map(fn ($l) => [
                    'id' => $l->id,
                    'employee' => $l->employee?->full_name,
                    'type' => $l->leaveType?->name,
                    'start_date' => $l->start_date?->toDateString(),
                    'end_date' => $l->end_date?->toDateString(),
                    'stage' => $l->current_approval_level,
                ])
                ->all();
        }

        if ($can('activity-logs.view')) {
            $lists['recent_activity'] = ActivityLog::with('user:id,first_name,last_name')
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'user' => $a->user?->full_name,
                    'action' => $a->action,
                    'subject' => class_basename($a->subject_type ?? ''),
                    'at' => $a->created_at?->diffForHumans(),
                ])
                ->all();
        }

        if ($can('calendar.view')) {
            $lists['upcoming'] = CalendarEvent::query()
                ->where('start_datetime', '>=', Carbon::now())
                ->where('start_datetime', '<=', Carbon::now()->addDays(14))
                ->where('status', '!=', 'cancelled')
                ->with('project:id,name')
                ->orderBy('start_datetime')
                ->limit(6)
                ->get()
                ->map(fn ($e) => [
                    'id' => $e->id,
                    'title' => $e->title,
                    'type' => $e->type,
                    'when' => $e->start_datetime?->format('d M, H:i'),
                    'project' => $e->project?->name,
                ])
                ->all();
        }

        return $lists;
    }

    private function getMyTasks(User $user): array
    {
        return Task::where('assigned_to', $user->id)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->with('project:id,name,code')
            ->orderByRaw("FIELD(priority, 'critical', 'high', 'medium', 'low')")
            ->limit(8)
            ->get()
            ->map(fn ($task) => [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status,
                'priority' => $task->priority,
                'due_date' => $task->due_date?->toDateString(),
                'project' => $task->project ? ['id' => $task->project->id, 'name' => $task->project->name] : null,
            ])
            ->all();
    }

    private function getRecentProjects(): array
    {
        return Project::with('client:id,company_name')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($project) => [
                'id' => $project->id,
                'name' => $project->name,
                'code' => $project->code,
                'status' => $project->status,
                'progress' => $project->progress,
                'client' => $project->client?->company_name,
            ])
            ->all();
    }

    // ── Self-service ─────────────────────────────────────────────────────────

    private function getMyData(User $user): array
    {
        $employee = Employee::where('user_id', $user->id)->first();
        if (!$employee) {
            return [];
        }

        $balances = LeaveBalance::where('employee_id', $employee->id)
            ->where('year', Carbon::now()->year)
            ->with('leaveType:id,name')
            ->get()
            ->map(fn ($b) => [
                'label' => $b->leaveType?->name ?? 'Leave',
                'remaining' => (float) $b->remaining_days,
                'entitled' => (float) $b->entitled_days,
            ])
            ->all();

        return ['leave_balance' => $balances];
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function canApproveLeave(User $user, callable $can): bool
    {
        return $can('leave.manage') || $can('leave.approve') || $user->is_manager || $user->is_director;
    }

    private function pendingLeaveQuery(User $user, callable $can)
    {
        $query = LeaveRequest::where('status', 'pending');

        if (!$can('leave.manage')) {
            $query->where(function ($w) use ($user) {
                $w->awaitingApprovalBy($user->id);
                if ($user->is_manager) {
                    $w->orWhere('current_approval_level', 'manager');
                }
                if ($user->is_director) {
                    $w->orWhere('current_approval_level', 'director');
                }
            });
        }

        return $query;
    }
}
