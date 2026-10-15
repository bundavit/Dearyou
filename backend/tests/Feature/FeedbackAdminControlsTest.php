<?php

namespace Tests\Feature;

use App\Models\Feedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackAdminControlsTest extends TestCase
{
    use RefreshDatabase;

    private function feedback(array $attributes = []): Feedback
    {
        return Feedback::create(array_merge([
            'category' => 'bug',
            'message' => 'Synthetic feedback for admin review.',
            'status' => 'new',
        ], $attributes));
    }

    public function test_only_admins_can_mark_all_feedback_as_read_or_classify_spam(): void
    {
        $feedback = $this->feedback();
        $spam = $this->feedback([
            'status' => 'spam', 'spam_score' => 100, 'spam_reasons' => ['Repeated promotion.'],
        ]);
        $this->post('/admin/feedback/mark-all-read')->assertRedirect('/login');
        $this->patch("/admin/feedback/{$spam->id}", ['status' => 'new'])->assertRedirect('/login');
        $this->actingAs(User::factory()->create())
            ->post('/admin/feedback/mark-all-read')->assertForbidden();
        $this->patch("/admin/feedback/{$feedback->id}", ['status' => 'spam'])->assertForbidden();
        $this->patch("/admin/feedback/{$spam->id}", ['status' => 'new'])->assertForbidden();
        $this->assertSame('new', $feedback->fresh()->status);
        $this->assertNull($feedback->fresh()->reviewed_at);
        $this->assertSame('spam', $spam->fresh()->status);
        $this->assertSame(100, $spam->fresh()->spam_score);
        $this->assertSame(['Repeated promotion.'], $spam->fresh()->spam_reasons);
    }

    public function test_mark_all_read_updates_all_new_items_and_preserves_other_statuses(): void
    {
        $this->freezeTime();
        $new = collect(range(1, 30))->map(fn ($number) => $this->feedback([
            'category' => $number % 2 === 0 ? 'bug' : 'suggestion',
            'message' => "Unread synthetic feedback {$number}.",
        ]));
        $previousReview = now()->subDay();
        $reviewed = $this->feedback(['status' => 'reviewed', 'reviewed_at' => $previousReview]);
        $resolved = $this->feedback(['status' => 'resolved', 'reviewed_at' => $previousReview]);
        $spam = $this->feedback([
            'status' => 'spam', 'spam_score' => 100, 'spam_reasons' => ['Repeated promotion.'],
        ]);
        $preserved = [$reviewed->fresh()->toArray(), $resolved->fresh()->toArray(), $spam->fresh()->toArray()];

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post('/admin/feedback/mark-all-read', ['status' => 'new', 'category' => 'bug'])
            ->assertRedirect(route('admin.feedback.index', ['status' => 'new', 'category' => 'bug']))
            ->assertSessionHas('success', '30 feedback items marked as read.');

        foreach ($new as $item) {
            $this->assertSame('reviewed', $item->fresh()->status);
            $this->assertSame(now()->toDateTimeString(), $item->fresh()->reviewed_at->toDateTimeString());
        }
        $this->assertSame($preserved, [$reviewed->fresh()->toArray(), $resolved->fresh()->toArray(), $spam->fresh()->toArray()]);
        $this->assertDatabaseCount('feedback', 33);
        $this->assertDatabaseMissing('feedback', ['status' => 'new']);
        $this->get('/admin/feedback')->assertOk()->assertSee('0 unread')->assertSee('1 potential spam');
    }

    public function test_mark_all_read_is_safe_when_no_new_feedback_remains(): void
    {
        $spam = $this->feedback(['status' => 'spam']);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post('/admin/feedback/mark-all-read')
            ->assertRedirect('/admin/feedback')
            ->assertSessionHas('success', '0 feedback items marked as read.');
        $this->assertSame('spam', $spam->fresh()->status);
        $this->assertNull($spam->fresh()->reviewed_at);
        $this->assertDatabaseCount('feedback', 1);
    }

    public function test_admin_can_review_potential_spam_and_restore_it_without_deleting_feedback(): void
    {
        $spam = $this->feedback([
            'status' => 'spam', 'spam_score' => 100, 'spam_reasons' => ['Repeated promotion <script>example</script>.'],
        ]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get("/admin/feedback/{$spam->id}")
            ->assertOk()->assertSee('Restore to New')
            ->assertSee('Repeated promotion &lt;script&gt;example&lt;/script&gt;.', false)
            ->assertDontSee('Repeated promotion <script>example</script>.', false);
        $this->assertSame('spam', $spam->fresh()->status);
        $this->assertNull($spam->fresh()->reviewed_at);
        $restored = $this->from("/admin/feedback/{$spam->id}")
            ->patch("/admin/feedback/{$spam->id}", ['status' => 'new'])
            ->assertRedirect(route('admin.feedback.index', ['status' => 'new']));
        $this->followRedirects($restored)->assertOk()
            ->assertViewIs('admin.feedback.index')->assertSee('1 unread')
            ->assertViewHas('feedback', fn ($page) => $page->contains('id', $spam->id));
        $this->assertSame('new', $spam->fresh()->status);
        $this->assertNull($spam->fresh()->reviewed_at);
        $this->assertSame([], $spam->fresh()->spam_reasons);
        $this->assertSame(0, $spam->fresh()->spam_score);
        $this->assertSame('Synthetic feedback for admin review.', $spam->fresh()->message);
        $this->patch("/admin/feedback/{$spam->id}", ['status' => 'spam'])->assertRedirect();
        $this->assertSame('spam', $spam->fresh()->status);
        $this->assertNotNull($spam->fresh()->reviewed_at);
        $this->assertDatabaseCount('feedback', 1);
    }

    public function test_changing_reviewed_feedback_to_new_keeps_it_unread_after_the_redirect(): void
    {
        $feedback = $this->feedback(['status' => 'reviewed', 'reviewed_at' => now()]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $updated = $this->from("/admin/feedback/{$feedback->id}")
            ->patch("/admin/feedback/{$feedback->id}", ['status' => 'new'])
            ->assertRedirect(route('admin.feedback.index', ['status' => 'new']));

        $this->followRedirects($updated)->assertOk()
            ->assertViewIs('admin.feedback.index')->assertSee('1 unread');
        $this->assertSame('new', $feedback->fresh()->status);
        $this->assertNull($feedback->fresh()->reviewed_at);

        $this->get("/admin/feedback/{$feedback->id}")->assertOk();
        $this->assertSame('reviewed', $feedback->fresh()->status);
        $this->assertNotNull($feedback->fresh()->reviewed_at);
    }

    public function test_spam_filter_keeps_category_and_pagination_and_normal_feedback_separate(): void
    {
        foreach (range(1, 27) as $number) {
            $this->feedback(['status' => 'spam', 'message' => "Potential spam item {$number}."]);
        }
        $this->feedback(['status' => 'spam', 'category' => 'design', 'message' => 'Different spam category.']);
        $this->feedback(['message' => 'Legitimate unread feedback.']);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/admin/feedback?status=spam&category=bug')
            ->assertOk()->assertViewHas('feedback', fn ($page) => $page->total() === 27 && $page->count() === 25)
            ->assertSee('Potential spam')->assertSee('1 unread')->assertSee('28 potential spam')
            ->assertDontSee('Different spam category.')->assertDontSee('Legitimate unread feedback.')
            ->assertSee('status=spam&amp;category=bug', false);
        $this->get('/admin/feedback?status=new')->assertOk()
            ->assertSee('Legitimate unread feedback.')->assertDontSee('Potential spam item 1.');
    }
}
