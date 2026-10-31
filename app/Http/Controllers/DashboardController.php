<?php

namespace App\Http\Controllers;

use App\Enums\BacklogStatus;
use App\Enums\SprintStatus;
use App\Models\BacklogItem;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\SprintReview;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the project overview of the signed-in account.
     *
     * The page reads source records: one query for the visible projects and
     * one grouped aggregate each for the item counts, the running sprints and
     * the upcoming reviews, whatever number of projects there are.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        abort_if($user === null, 403);

        $projects = Project::query()
            ->visibleTo($user)
            ->orderBy('name')
            ->get(['id', 'name', 'status', 'archived_at']);

        $ids = $projects->modelKeys();
        $counts = $this->itemCounts($ids);
        $activeSprints = $this->activeSprints($ids);
        $nextReviews = $this->nextReviews($ids);

        return Inertia::render('dashboard', [
            'projects' => $projects
                ->map(fn (Project $project): array => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'status' => $project->status->value,
                    'status_label' => $project->status->label(),
                    'archived_at' => $project->archived_at?->toIso8601String(),
                    'active_sprint' => $activeSprints[$project->id] ?? null,
                    'next_review_at' => $nextReviews[$project->id] ?? null,
                    'counts' => [
                        BacklogStatus::Todo->value => 0,
                        BacklogStatus::InProgress->value => 0,
                        BacklogStatus::Done->value => 0,
                        ...($counts[$project->id] ?? []),
                    ],
                ])
                ->all(),
            'summary' => [
                'projects' => $projects->count(),
            ],
        ]);
    }

    /**
     * Count the live backlog items of every project by status.
     *
     * @param  array<int, int>  $projectIds
     * @return array<int, array<string, int>>
     */
    private function itemCounts(array $projectIds): array
    {
        if ($projectIds === []) {
            return [];
        }

        $rows = BacklogItem::query()
            ->toBase()
            ->whereIn('project_id', $projectIds)
            ->whereNull('archived_at')
            ->selectRaw('project_id, status, count(*) as total')
            ->groupBy('project_id', 'status')
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $counts[$row->project_id][$row->status] = (int) $row->total;
        }

        return $counts;
    }

    /**
     * Get the running sprint name of every project.
     *
     * At most one sprint per project is active, so the name is all that is
     * needed and the list stays flat.
     *
     * @param  array<int, int>  $projectIds
     * @return array<int, string>
     */
    private function activeSprints(array $projectIds): array
    {
        if ($projectIds === []) {
            return [];
        }

        return Sprint::query()
            ->whereIn('project_id', $projectIds)
            ->where('status', SprintStatus::Active->value)
            ->orderBy('id')
            ->get(['project_id', 'name'])
            ->mapWithKeys(fn (Sprint $sprint): array => [$sprint->project_id => $sprint->name])
            ->all();
    }

    /**
     * Get the earliest upcoming review date of every project.
     *
     * Past reviews are left out: the dashboard looks forward.
     *
     * @param  array<int, int>  $projectIds
     * @return array<int, string>
     */
    private function nextReviews(array $projectIds): array
    {
        if ($projectIds === []) {
            return [];
        }

        /** @var Collection<int|string, mixed> $rows */
        $rows = SprintReview::query()
            ->toBase()
            ->whereIn('project_id', $projectIds)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>=', now())
            ->selectRaw('project_id, min(scheduled_at) as next_at')
            ->groupBy('project_id')
            ->pluck('next_at', 'project_id');

        return $rows
            ->mapWithKeys(fn (mixed $nextAt, int|string $projectId): array => [
                (int) $projectId => Carbon::parse((string) $nextAt)->toIso8601String(),
            ])
            ->all();
    }
}
