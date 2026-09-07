<?php

namespace App\Http\Controllers\Concerns;

use App\Models\ProjectSite;

/**
 * Guards the project↔site relationship (Ciri 25).
 *
 * A `site_id` that merely `exists` is not enough — it must belong to the same
 * project as the record it is being attached to. Without this check a caller
 * could file a record under another project's site (a cross-project IDOR), since
 * the frontend only ever offers same-project sites but the API must not trust
 * that. A null site is always fine (optional). A site with no matching project
 * fails closed.
 */
trait AssertsSiteInProject
{
    protected function assertSiteInProject(?int $siteId, ?int $projectId): void
    {
        if ($siteId === null) {
            return;
        }

        $ok = $projectId !== null && ProjectSite::where('id', $siteId)
            ->where('project_id', $projectId)
            ->exists();

        abort_unless($ok, 422, 'The selected site does not belong to this project.');
    }
}
