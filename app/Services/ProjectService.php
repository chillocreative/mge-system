<?php

namespace App\Services;

use App\Models\Project;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ProjectService
{
    public function __construct(
        private ProjectRepositoryInterface $projectRepository,
        private NotificationService $notifications,
    ) {}

    public function listProjects(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->projectRepository->getWithRelations($perPage, $filters);
    }

    public function getProject(int $id): Project
    {
        $project = $this->projectRepository->findOrFail($id);
        $project->load([
            'client',
            'manager',
            'members',
            'tasks.assignee:id,first_name,last_name',
            'milestones.creator:id,first_name,last_name',
            'siteLogs.logger:id,first_name,last_name',
            'siteLogs.machinery',
            'documents.uploader:id,first_name,last_name',
            'calendarEvents.creator:id,first_name,last_name',
        ]);
        $project->loadCount(['tasks', 'milestones', 'siteLogs', 'documents', 'calendarEvents']);

        return $project;
    }

    public function createProject(array $data): Project
    {
        $project = $this->projectRepository->create($data);

        if (!empty($data['member_ids'])) {
            $members = collect($data['member_ids'])->mapWithKeys(fn ($userId) => [
                $userId => ['role' => 'member', 'joined_at' => now()],
            ]);
            $project->members()->attach($members);
            $this->notifyMembersAdded($project, $data['member_ids']);
        }

        return $project->load(['client', 'manager']);
    }

    public function updateProject(int $id, array $data): Project
    {
        $project = $this->projectRepository->update($id, $data);

        if (isset($data['member_ids'])) {
            $existing = $project->members()->pluck('users.id')->all();
            $members = collect($data['member_ids'])->mapWithKeys(fn ($userId) => [
                $userId => ['role' => 'member', 'joined_at' => now()],
            ]);
            $project->members()->sync($members);
            $newlyAdded = array_diff($data['member_ids'], $existing);
            $this->notifyMembersAdded($project, $newlyAdded);
        }

        return $project->load(['client', 'manager']);
    }

    public function deleteProject(int $id): bool
    {
        return $this->projectRepository->delete($id);
    }

    /**
     * Notify users who were just added to a project's team.
     */
    private function notifyMembersAdded(Project $project, array $userIds): void
    {
        $this->notifications->notifyUserIds(
            array_values($userIds),
            'Added to a project',
            "You were added to the project \"{$project->name}\".",
            'project',
            "/projects/{$project->id}",
            ['project_id' => $project->id],
        );
    }

    public function getActiveProjects(): Collection
    {
        return $this->projectRepository->getActiveProjects();
    }
}
