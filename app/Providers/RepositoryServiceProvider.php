<?php

namespace App\Providers;

use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Repositories\Contracts\CorrespondenceRfaSubtypeRepositoryInterface;
use App\Repositories\Contracts\MasterPartyRepositoryInterface;
use App\Repositories\Contracts\MaterialRepositoryInterface;
use App\Repositories\Contracts\PartyCategoryRepositoryInterface;
use App\Repositories\Contracts\ProjectReferenceRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\ClientRepository;
use App\Repositories\Eloquent\CorrespondenceRfaSubtypeRepository;
use App\Repositories\Eloquent\MasterPartyRepository;
use App\Repositories\Eloquent\MaterialRepository;
use App\Repositories\Eloquent\PartyCategoryRepository;
use App\Repositories\Eloquent\ProjectReferenceRepository;
use App\Repositories\Eloquent\ProjectRepository;
use App\Repositories\Eloquent\TaskRepository;
use App\Repositories\Eloquent\UserRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(ProjectRepositoryInterface::class, ProjectRepository::class);
        $this->app->bind(TaskRepositoryInterface::class, TaskRepository::class);
        $this->app->bind(ClientRepositoryInterface::class, ClientRepository::class);
        $this->app->bind(MasterPartyRepositoryInterface::class, MasterPartyRepository::class);
        $this->app->bind(PartyCategoryRepositoryInterface::class, PartyCategoryRepository::class);
        $this->app->bind(ProjectReferenceRepositoryInterface::class, ProjectReferenceRepository::class);
        $this->app->bind(CorrespondenceRfaSubtypeRepositoryInterface::class, CorrespondenceRfaSubtypeRepository::class);
        $this->app->bind(MaterialRepositoryInterface::class, MaterialRepository::class);
    }
}
