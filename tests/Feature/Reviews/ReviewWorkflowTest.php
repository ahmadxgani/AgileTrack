<?php

namespace Tests\Feature\Reviews;

use App\Enums\BacklogPriority;
use App\Enums\BacklogStatus;
use App\Enums\ProjectRole;
use App\Enums\ReviewDecision;
use App\Enums\UserRole;
use App\Models\BacklogItem;
use App\Models\Project;
use App\Models\ProjectMembership;
use App\Models\Sprint;
use App\Models\SprintReview;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReviewWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_creates_and_updates_a_review(): void
    {
        $project = Project::factory()->create();
        $manager = $this->member($project, ProjectRole::Manager);
        $sprint = Sprint::factory()->for($project)->create();

        $this->actingAs($manager)->post(route('projects.reviews.store', $project), [
            'sprint_id' => $sprint->id,
            'scheduled_at' => '2026-11-15T10:30',
            'notes' => 'Show the sprint board.',
            'feedback' => 'Keep the demo short.',
        ])->assertRedirect();

        $review = SprintReview::query()->sole();

        $this->assertSame($project->id, $review->project_id);
        $this->assertSame($sprint->id, $review->sprint_id);
        $this->assertSame('2026-11-15 10:30', $review->scheduled_at->format('Y-m-d H:i'));
        $this->assertSame('Show the sprint board.', $review->notes);
        $this->assertSame('Keep the demo short.', $review->feedback);
        $this->assertSame(ReviewDecision::Pending, $review->decision);
        $this->assertSame($manager->id, $review->created_by);
        $this->assertNull($review->decided_by);
        $this->assertNull($review->decided_at);

        $this->actingAs($manager)->patch(route('projects.reviews.update', [$project, $review]), [
            'notes' => 'Show the dashboard too.',
            'scheduled_at' => '2026-11-16T09:00',
        ])->assertRedirect(route('projects.reviews.show', [$project, $review]));

        $review->refresh();

        $this->assertSame('Show the dashboard too.', $review->notes);
        $this->assertSame('2026-11-16 09:00', $review->scheduled_at->format('Y-m-d H:i'));
        $this->assertSame('Keep the demo short.', $review->feedback);
        $this->assertSame($sprint->id, $review->sprint_id);
    }

    public function test_decision_stamps_the_actor_and_time(): void
    {
        $this->freezeTime();
        $project = Project::factory()->create();
        $manager = $this->member($project, ProjectRole::Manager);
        $review = SprintReview::factory()->for($project)->create();

        $this->actingAs($manager)->patch(route('projects.reviews.decision', [$project, $review]), [
            'decision' => ReviewDecision::Approved->value,
        ])->assertRedirect(route('projects.reviews.show', [$project, $review]));

        $review->refresh();

        $this->assertSame(ReviewDecision::Approved, $review->decision);
        $this->assertSame($manager->id, $review->decided_by);
        $this->assertTrue($review->decided_at->equalTo(now()));
    }

    public function test_needs_changes_requires_follow_up_work(): void
    {
        $project = Project::factory()->create();
        $manager = $this->member($project, ProjectRole::Manager);
        $review = SprintReview::factory()->for($project)->create();

        $this->actingAs($manager)->patch(route('projects.reviews.decision', [$project, $review]), [
            'decision' => ReviewDecision::NeedsChanges->value,
        ])->assertSessionHasErrors('follow_up_title');

        $review->refresh();

        $this->assertSame(ReviewDecision::Pending, $review->decision);
        $this->assertNull($review->decided_by);
        $this->assertNull($review->decided_at);
        $this->assertNull($review->follow_up_backlog_item_id);
        $this->assertDatabaseCount('backlog_items', 0);
    }

    public function test_needs_changes_files_one_todo_item_in_the_same_project_and_reuses_it(): void
    {
        $project = Project::factory()->create();
        $manager = $this->member($project, ProjectRole::Manager);
        $review = SprintReview::factory()->for($project)->create();

        $this->actingAs($manager)->patch(route('projects.reviews.decision', [$project, $review]), [
            'decision' => ReviewDecision::NeedsChanges->value,
            'follow_up_title' => 'Explain the status changes.',
        ])->assertRedirect();

        $review->refresh();
        $item = BacklogItem::query()->sole();

        $this->assertSame($item->id, $review->follow_up_backlog_item_id);
        $this->assertSame($project->id, $item->project_id);
        $this->assertSame('Explain the status changes.', $item->title);
        $this->assertSame(BacklogStatus::Todo, $item->status);
        $this->assertSame(BacklogPriority::Medium, $item->priority);
        $this->assertSame($manager->id, $item->created_by);
        $this->assertNull($item->sprint_id);

        // A second request reuses the link rather than filing the same work
        // twice, even if the caller supplies another title.
        $this->actingAs($manager)->patch(route('projects.reviews.decision', [$project, $review]), [
            'decision' => ReviewDecision::NeedsChanges->value,
            'follow_up_title' => 'Do not create a second item.',
        ])->assertRedirect();

        $this->assertDatabaseCount('backlog_items', 1);
        $this->assertSame($item->id, $review->fresh()->follow_up_backlog_item_id);
    }

    public function test_reverting_to_pending_clears_decision_stamps_but_keeps_the_work(): void
    {
        $project = Project::factory()->create();
        $manager = $this->member($project, ProjectRole::Manager);
        $review = SprintReview::factory()->for($project)->create();

        $this->actingAs($manager)->patch(route('projects.reviews.decision', [$project, $review]), [
            'decision' => ReviewDecision::NeedsChanges->value,
            'follow_up_title' => 'Polish the dashboard.',
        ])->assertRedirect();
        $itemId = $review->fresh()->follow_up_backlog_item_id;

        $this->actingAs($manager)->patch(route('projects.reviews.decision', [$project, $review]), [
            'decision' => ReviewDecision::Pending->value,
        ])->assertRedirect();

        $review->refresh();

        $this->assertSame(ReviewDecision::Pending, $review->decision);
        $this->assertNull($review->decided_by);
        $this->assertNull($review->decided_at);
        $this->assertSame($itemId, $review->follow_up_backlog_item_id);
        $this->assertDatabaseHas('backlog_items', ['id' => $itemId]);

        // The linked item satisfies a later needs-changes decision too.
        $this->actingAs($manager)->patch(route('projects.reviews.decision', [$project, $review]), [
            'decision' => ReviewDecision::NeedsChanges->value,
        ])->assertRedirect();
        $this->assertDatabaseCount('backlog_items', 1);

        $this->actingAs($manager)->patch(route('projects.reviews.decision', [$project, $review]), [
            'decision' => ReviewDecision::Approved->value,
        ])->assertRedirect();
        $this->assertSame($itemId, $review->fresh()->follow_up_backlog_item_id);
    }

    public function test_member_reads_and_records_only_feedback_but_cannot_decide(): void
    {
        $project = Project::factory()->create();
        $member = $this->member($project, ProjectRole::Member);
        $review = SprintReview::factory()->for($project)->create(['notes' => 'Original notes.']);

        $this->actingAs($member)->get(route('projects.reviews.index', $project))->assertOk();
        $this->actingAs($member)->get(route('projects.reviews.show', [$project, $review]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('reviews/show')
            ->where('can.update', false)
            ->where('can.record_feedback', true)
            ->where('can.decide', false));

        $this->actingAs($member)->patch(route('projects.reviews.update', [$project, $review]), [
            'feedback' => 'The workflow is easy to follow.',
            'notes' => 'Not the member field.',
            'decision' => ReviewDecision::Approved->value,
            'decided_by' => $member->id,
        ])->assertRedirect();

        $review->refresh();

        $this->assertSame('The workflow is easy to follow.', $review->feedback);
        $this->assertSame('Original notes.', $review->notes);
        $this->assertSame(ReviewDecision::Pending, $review->decision);
        $this->assertNull($review->decided_by);

        $this->actingAs($member)->get(route('projects.reviews.create', $project))->assertForbidden();
        $this->actingAs($member)->post(route('projects.reviews.store', $project), [])->assertForbidden();
        $this->actingAs($member)->get(route('projects.reviews.edit', [$project, $review]))->assertForbidden();
        $this->actingAs($member)->patch(route('projects.reviews.decision', [$project, $review]), [
            'decision' => ReviewDecision::Approved->value,
        ])->assertForbidden();
    }

    public function test_viewer_is_read_only(): void
    {
        $project = Project::factory()->create();
        $viewer = $this->member($project, ProjectRole::Viewer);
        $review = SprintReview::factory()->for($project)->create();

        $this->actingAs($viewer)->get(route('projects.reviews.index', $project))->assertOk();
        $this->actingAs($viewer)->get(route('projects.reviews.show', [$project, $review]))->assertOk();
        $this->actingAs($viewer)->get(route('projects.reviews.create', $project))->assertForbidden();
        $this->actingAs($viewer)->get(route('projects.reviews.edit', [$project, $review]))->assertForbidden();
        $this->actingAs($viewer)->post(route('projects.reviews.store', $project), [])->assertForbidden();
        $this->actingAs($viewer)->patch(route('projects.reviews.update', [$project, $review]), [
            'feedback' => 'Not writable.',
        ])->assertForbidden();
        $this->actingAs($viewer)->patch(route('projects.reviews.decision', [$project, $review]), [
            'decision' => ReviewDecision::Approved->value,
        ])->assertForbidden();

        $this->assertNull($review->fresh()->feedback);
    }

    public function test_customer_role_cannot_decide_even_with_a_manager_membership(): void
    {
        $project = Project::factory()->create();
        $customer = $this->member($project, ProjectRole::Manager);
        $customer->roles()->create(['role' => UserRole::Customer]);
        $review = SprintReview::factory()->for($project)->create();

        $this->actingAs($customer)->patch(route('projects.reviews.decision', [$project, $review]), [
            'decision' => ReviewDecision::Approved->value,
        ])->assertForbidden();

        $this->assertSame(ReviewDecision::Pending, $review->fresh()->decision);
    }

    public function test_administrator_manages_reviews_without_a_membership(): void
    {
        $project = Project::factory()->create();
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->post(route('projects.reviews.store', $project), [
            'notes' => 'Review all projects.',
        ])->assertRedirect();
        $review = SprintReview::query()->sole();

        $this->actingAs($admin)->patch(route('projects.reviews.update', [$project, $review]), [
            'notes' => 'Updated by admin.',
        ])->assertRedirect();
        $this->actingAs($admin)->patch(route('projects.reviews.decision', [$project, $review]), [
            'decision' => ReviewDecision::Approved->value,
        ])->assertRedirect();

        $this->assertSame('Updated by admin.', $review->fresh()->notes);
        $this->assertSame($admin->id, $review->fresh()->decided_by);
    }

    public function test_cross_project_review_binding_is_not_found(): void
    {
        $project = Project::factory()->create();
        $manager = $this->member($project, ProjectRole::Manager);
        $foreign = SprintReview::factory()->create();

        $this->actingAs($manager)->get(route('projects.reviews.show', [$project, $foreign]))->assertNotFound();
        $this->actingAs($manager)->get(route('projects.reviews.edit', [$project, $foreign]))->assertNotFound();
        $this->actingAs($manager)->patch(route('projects.reviews.update', [$project, $foreign]), [
            'notes' => 'Not mine.',
        ])->assertNotFound();
        $this->actingAs($manager)->patch(route('projects.reviews.decision', [$project, $foreign]), [
            'decision' => ReviewDecision::Approved->value,
        ])->assertNotFound();
    }

    public function test_a_sprint_of_another_project_cannot_be_reviewed_here(): void
    {
        $project = Project::factory()->create();
        $manager = $this->member($project, ProjectRole::Manager);
        $foreign = Sprint::factory()->create();
        $review = SprintReview::factory()->for($project)->create();

        $this->actingAs($manager)->post(route('projects.reviews.store', $project), [
            'sprint_id' => $foreign->id,
        ])->assertSessionHasErrors('sprint_id');
        $this->actingAs($manager)->patch(route('projects.reviews.update', [$project, $review]), [
            'sprint_id' => $foreign->id,
        ])->assertSessionHasErrors('sprint_id');

        $this->assertDatabaseCount('sprint_reviews', 1);
        $this->assertNull($review->fresh()->sprint_id);
    }

    public function test_archived_projects_are_read_only_even_for_an_administrator(): void
    {
        $project = Project::factory()->archived()->create();
        $manager = $this->member($project, ProjectRole::Manager);
        $admin = User::factory()->administrator()->create();
        $member = $this->member($project, ProjectRole::Member);
        $review = SprintReview::factory()->for($project)->create();

        foreach ([$manager, $admin, $member] as $reader) {
            $this->actingAs($reader)->get(route('projects.reviews.show', [$project, $review]))->assertOk();
            $this->actingAs($reader)->get(route('projects.reviews.create', $project))->assertForbidden();
            $this->actingAs($reader)->post(route('projects.reviews.store', $project), [])->assertForbidden();
            $this->actingAs($reader)->patch(route('projects.reviews.update', [$project, $review]), [
                'feedback' => 'Too late.',
            ])->assertForbidden();
            $this->actingAs($reader)->patch(route('projects.reviews.decision', [$project, $review]), [
                'decision' => ReviewDecision::NeedsChanges->value,
                'follow_up_title' => 'Too late.',
            ])->assertForbidden();
        }

        $this->assertDatabaseCount('sprint_reviews', 1);
        $this->assertDatabaseCount('backlog_items', 0);
    }

    public function test_guests_and_outsiders_cannot_read_a_project_review(): void
    {
        $project = Project::factory()->create();
        $review = SprintReview::factory()->for($project)->create();
        $outsider = User::factory()->create();

        $this->get(route('projects.reviews.index', $project))->assertRedirect(route('login'));
        $this->get(route('projects.reviews.show', [$project, $review]))->assertRedirect(route('login'));
        $this->actingAs($outsider)->get(route('projects.reviews.index', $project))->assertForbidden();
        $this->actingAs($outsider)->get(route('projects.reviews.show', [$project, $review]))->assertForbidden();
    }

    public function test_shortcut_opens_the_first_visible_project_or_the_project_list(): void
    {
        Project::factory()->create(['name' => 'A secret']);
        $project = Project::factory()->create(['name' => 'B visible']);
        $member = $this->member($project, ProjectRole::Member);

        $this->actingAs($member)->get(route('projects.reviews.shortcut'))
            ->assertRedirect(route('projects.reviews.index', $project));
        $this->actingAs(User::factory()->create())->get(route('projects.reviews.shortcut'))
            ->assertRedirect(route('projects.index'));
    }

    public function test_a_decision_is_reauthorized_in_the_transaction_and_rolls_back(): void
    {
        $project = Project::factory()->create();
        $member = $this->member($project, ProjectRole::Member);
        $review = SprintReview::factory()->for($project)->create();

        // A plain member fails the decide policy. The gate runs inside the
        // transaction, after the locked reads, so the whole decision must
        // roll back: no follow-up item, no decided_by or decided_at.
        try {
            $review->recordDecision(ReviewDecision::NeedsChanges, $member, 'A follow-up item');
            $this->fail('A plain project member must not decide a review.');
        } catch (AuthorizationException) {
            // Expected: the in-transaction gate is the authoritative check.
        }

        $fresh = SprintReview::query()->findOrFail($review->getKey());

        $this->assertSame(ReviewDecision::Pending, $fresh->decision);
        $this->assertNull($fresh->decided_by);
        $this->assertNull($fresh->decided_at);
        $this->assertNull($fresh->follow_up_backlog_item_id);
        $this->assertSame(0, $project->backlogItems()->count());
    }

    private function member(Project $project, ProjectRole $role): User
    {
        $user = User::factory()->create();
        ProjectMembership::factory()->for($project)->for($user)->create([
            'project_role' => $role,
        ]);

        return $user;
    }
}
