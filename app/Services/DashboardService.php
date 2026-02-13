<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Get all dashboard data scoped by user permissions.
     */
    public function getData(User $user): array
    {
        $isAdmin = $user->hasRole('Admin & HR');
        $data = [];

        // ── KPI stat cards ──
        $data['stats'] = $this->getStatCards($user, $isAdmin);

        // ── Chart data ──
        $data['charts'] = [
            'project_status' => $this->getProjectStatusDistribution(),
            'budget_by_status' => $this->getBudgetByStatus(),
            'monthly_projects' => $this->getMonthlyProjectTrend(),
            'task_status' => $this->getTaskStatusDistribution($user, $isAdmin),
            'top_projects' => $this->getTopProjectsByBudget(),
        ];

        // ── My tasks (all roles see their own) ──
        $data['my_tasks'] = $this->getMyTasks($user);

        // ── Recent projects ──
        if ($user->can('projects.view')) {
            $data['recent_projects'] = $this->getRecentProjects();
        }

        return $data;
    }

    /**
     * Aggregated stat cards — uses single optimized queries.
     */
    private function getStatCards(User $user, bool $isAdmin): array
    {
        // Single query: project counts + financials
        $projectAgg = Project::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status NOT IN ('completed','cancelled') THEN 1 ELSE 0 END) as active")
            ->selectRaw("SUM(CASE WHEN end_date < CURDATE() AND status NOT IN ('completed','cancelled') THEN 1 ELSE 0 END) as delayed")
            ->selectRaw('COALESCE(SUM(budget), 0) as total_revenue')
            ->selectRaw("COALESCE(SUM(CASE WHEN status NOT IN ('completed','cancelled') THEN budget - spent ELSE 0 END), 0) as pending_invoices")
            ->first();

        $stats = [
            'total_projects' => (int) $projectAgg->total,
            'active_projects' => (int) $projectAgg->active,
            'delayed_projects' => (int) $projectAgg->delayed,
        ];

        // Finance stats — Admin & HR + Finances & HR
        if ($isAdmin || $user->can('dashboard.view-finance-stats')) {
            $stats['total_revenue'] = round((float) $projectAgg->total_revenue, 2);
            $stats['pending_invoices'] = round((float) $projectAgg->pending_invoices, 2);
        }

        // HR stats — Admin & HR + Finances & HR
        if ($isAdmin || $user->can('dashboard.view-hr-stats')) {
            $stats['staff_present_today'] = User::active()->count();
            $stats['total_staff'] = User::count();
        }

        // Safety incidents — derive from overdue critical tasks this month
        $stats['safety_incidents'] = Task::where('priority', 'critical')
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->count();

        // Task summaries
        $taskQuery = Task::query();
        if (!$isAdmin) {
            $taskQuery->where('assigned_to', $user->id);
        }

        $taskAgg = (clone $taskQuery)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed")
            ->selectRaw("SUM(CASE WHEN due_date < CURDATE() AND status NOT IN ('completed','cancelled') THEN 1 ELSE 0 END) as overdue")
            ->first();

        $stats['total_tasks'] = (int) $taskAgg->total;
        $stats['completed_tasks'] = (int) $taskAgg->completed;
        $stats['overdue_tasks'] = (int) $taskAgg->overdue;
        $stats['total_clients'] = Client::active()->count();

        return $stats;
    }

    /**
     * Project count grouped by status — for doughnut chart.
     */
    private function getProjectStatusDistribution(): array
    {
        return Project::query()
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'label' => ucfirst(str_replace('_', ' ', $row->status)),
                'value' => (int) $row->count,
            ])
            ->values()
            ->all();
    }

    /**
     * Budget vs Spent grouped by project status — for bar chart.
     */
    private function getBudgetByStatus(): array
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
            ->values()
            ->all();
    }

    /**
     * Projects created per month (last 12 months) — for line/area chart.
     */
    private function getMonthlyProjectTrend(): array
    {
        $since = Carbon::now()->subMonths(11)->startOfMonth();

        $rows = Project::query()
            ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"))
            ->selectRaw('COUNT(*) as count')
            ->where('created_at', '>=', $since)
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        // Fill missing months with 0
        $result = [];
        for ($i = 0; $i < 12; $i++) {
            $monthKey = Carbon::now()->subMonths(11 - $i)->format('Y-m');
            $label = Carbon::now()->subMonths(11 - $i)->format('M Y');
            $result[] = [
                'label' => $label,
                'value' => (int) ($rows[$monthKey]->count ?? 0),
            ];
        }

        return $result;
    }

    /**
     * Task count grouped by status — for doughnut chart.
     */
    private function getTaskStatusDistribution(User $user, bool $isAdmin): array
    {
        return Task::query()
            ->when(!$isAdmin, fn ($q) => $q->where('assigned_to', $user->id))
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'label' => ucfirst(str_replace('_', ' ', $row->status)),
                'value' => (int) $row->count,
            ])
            ->values()
            ->all();
    }

    /**
     * Top 5 projects by budget — for horizontal bar chart.
     */
    private function getTopProjectsByBudget(): array
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
            ->values()
            ->all();
    }

    /**
     * Current user's pending tasks.
     */
    private function getMyTasks(User $user): array
    {
        return Task::where('assigned_to', $user->id)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->with('project:id,name,code')
            ->orderByRaw("FIELD(priority, 'critical', 'high', 'medium', 'low')")
            ->limit(10)
            ->get()
            ->map(fn ($task) => [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status,
                'priority' => $task->priority,
                'due_date' => $task->due_date?->toDateString(),
                'project' => $task->project ? [
                    'id' => $task->project->id,
                    'name' => $task->project->name,
                ] : null,
            ])
            ->values()
            ->all();
    }

    /**
     * Recent projects list.
     */
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
            ->values()
            ->all();
    }
}
