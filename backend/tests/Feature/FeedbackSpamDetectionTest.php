<?php

namespace Tests\Feature;

use App\Models\Feedback;
use App\Notifications\FeedbackReceived;
use App\Support\FeedbackSpamDetector;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FeedbackSpamDetectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        app(PlatformSettings::class)->update(['feedback_notify_email' => 'feedback@example.test']);
    }

    public function test_guest_bug_report_with_one_link_is_retained_and_notified(): void
    {
        $message = 'The letter editor freezes after I open https://dearyou.test/letters/1/edit on my phone.';
        $this->post('/feedback', ['category' => 'bug', 'message' => $message])
            ->assertRedirect('/#feedback')->assertSessionHas('success');

        $feedback = Feedback::firstOrFail();
        $this->assertSame('new', $feedback->status);
        $this->assertSame(0, $feedback->spam_score);
        $this->assertSame([], $feedback->spam_reasons);
        $this->assertNull($feedback->user_id);
        $this->assertSame($message, $feedback->message);
        Notification::assertSentOnDemand(FeedbackReceived::class);
    }

    public function test_multiple_promotional_links_are_kept_for_review_without_email(): void
    {
        $message = 'Buy now! Cheap backlinks are available at https://offers.example.test/one and https://offers.example.test/two.';
        $this->post('/feedback', ['category' => 'other', 'message' => $message])
            ->assertRedirect('/#feedback')->assertSessionHas('success');

        $this->assertDatabaseCount('feedback', 1);
        $feedback = Feedback::firstOrFail();
        $this->assertSame('spam', $feedback->status);
        $this->assertGreaterThanOrEqual(80, $feedback->spam_score);
        $this->assertSame($message, $feedback->message);
        $this->assertContains('Multiple links combined with repeated promotional solicitations.', $feedback->spam_reasons);
        Notification::assertNothingSent();
    }

    public function test_single_link_large_monthly_earnings_promotion_is_retained_without_email(): void
    {
        $message = 'READ THE NEWS AND EARN $50,000,000 PER MONTH NOTABLY https://telegra.ph/qa-promotion-example';
        $this->post('/feedback', ['category' => 'other', 'message' => $message])
            ->assertRedirect('/#feedback')->assertSessionHas('success');

        $feedback = Feedback::firstOrFail();
        $this->assertSame('spam', $feedback->status);
        $this->assertGreaterThanOrEqual(80, $feedback->spam_score);
        $this->assertSame($message, $feedback->message);
        $this->assertContains('A link promotes unusually large recurring earnings or guaranteed income.', $feedback->spam_reasons);
        $this->assertDatabaseCount('feedback', 1);
        Notification::assertNothingSent();
    }

    public function test_money_amounts_monthly_prices_and_ordinary_earnings_reports_remain_allowed(): void
    {
        config(['dearyou.feedback_email_hourly_limit' => 10]);
        $messages = [
            'The price on https://dearyou.test/plans displays $50,000,000 per month by mistake. Please fix the price bug.',
            'Is the amount of $10,000 per month shown on https://dearyou.test/plans correct? I would like to ask about pricing.',
            'I earn $500 per month and cannot load the free plan at https://dearyou.test/plans. Please help.',
            'This error shows $50,000,000 on https://dearyou.test/letters/1/edit when I save a letter.',
        ];
        foreach ($messages as $message) {
            $this->post('/feedback', ['category' => 'bug', 'message' => $message])->assertRedirect('/#feedback');
        }

        $this->assertSame(4, Feedback::where('status', 'new')->count());
        $this->assertSame(0, Feedback::where('status', 'spam')->count());
        Notification::assertSentOnDemandTimes(FeedbackReceived::class, 4);
    }

    public function test_income_amount_needs_a_link_and_recurring_frequency_before_it_is_flagged(): void
    {
        $detector = app(FeedbackSpamDetector::class);
        $this->assertFalse($detector->inspect('Earn $50,000,000 per month by writing feedback about the site.')['flagged']);
        $this->assertFalse($detector->inspect('Earn $50,000,000 with this opportunity https://offers.example.test/start')['flagged']);
        $this->assertTrue($detector->inspect('Guaranteed income of USD 10,000 every week at https://offers.example.test/start')['flagged']);
        $this->assertFalse($detector->inspect('Earn $9,999 per month at https://offers.example.test/start')['flagged']);
    }

    public function test_three_normalized_duplicate_messages_are_detected_across_different_ips(): void
    {
        $message = 'This duplicated submission is arriving over and over again.';
        foreach (['192.0.2.1', '192.0.2.2'] as $ip) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])->post('/feedback', [
                'category' => 'other', 'message' => $message,
            ])->assertRedirect('/#feedback');
        }

        Notification::fake();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.3'])->post('/feedback', [
            'category' => 'other',
            'message' => "  THIS   duplicated submission is arriving over and over again. \n",
        ])->assertRedirect('/#feedback');

        $this->assertDatabaseCount('feedback', 3);
        $this->assertSame(2, Feedback::where('status', 'new')->count());
        $feedback = Feedback::latest('id')->firstOrFail();
        $this->assertSame('spam', $feedback->status);
        $this->assertContains('The same normalized message was already submitted at least twice within 24 hours.', $feedback->spam_reasons);
        $this->assertSame(3, Feedback::distinct()->count('ip_hash'));
        Notification::assertNothingSent();
    }

    public function test_old_duplicates_do_not_flag_a_current_message(): void
    {
        $message = 'Please make the letter preview a little easier to read on my phone.';
        foreach (range(1, 2) as $attempt) {
            Feedback::create(['category' => 'design', 'message' => $message,
                'created_at' => now()->subHours(25)]);
        }

        $this->post('/feedback', ['category' => 'design', 'message' => $message])->assertRedirect('/#feedback');

        $this->assertSame('new', Feedback::latest('id')->value('status'));
        Notification::assertSentOnDemand(FeedbackReceived::class);
    }

    public function test_unicode_feedback_and_several_bug_report_links_remain_allowed(): void
    {
        config(['dearyou.feedback_email_hourly_limit' => 10]);
        $messages = [
            'សូមជួយពិនិត្យមើលបញ្ហានៅពេលខ្ញុំបើកសំបុត្រនៅលើទូរស័ព្ទ។ អរគុណច្រើន។',
            'จดหมายเปิดช้าบนโทรศัพท์ของฉัน กรุณาช่วยตรวจสอบ ขอบคุณค่ะ',
            'The preview fails on https://dearyou.test/one but works on https://dearyou.test/two. Please check the first page.',
            'मेरे फ़ोन पर पत्र नहीं खुल रहा है। कृपया इस समस्या को ठीक करने में मेरी मदद करें। धन्यवाद।',
            'আমার ফোনে চিঠিটি খুলছে না। অনুগ্রহ করে এই সমস্যাটি ঠিক করতে সাহায্য করুন। ধন্যবাদ।',
        ];
        foreach ($messages as $message) {
            $this->post('/feedback', ['category' => 'bug', 'message' => $message])->assertRedirect('/#feedback');
        }

        $this->assertSame(5, Feedback::where('status', 'new')->count());
        $this->assertSame(0, Feedback::where('status', 'spam')->count());
        Notification::assertSentOnDemandTimes(FeedbackReceived::class, 5);
    }

    public function test_duplicate_detection_normalizes_unicode_feedback_without_flagging_its_language(): void
    {
        $message = 'আমি ফোনে চিঠি খুলতে পারছি না, অনুগ্রহ করে এই সমস্যাটি দেখুন।';
        foreach (['192.0.2.11', '192.0.2.12'] as $ip) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])->post('/feedback', [
                'category' => 'bug', 'message' => $message,
            ])->assertRedirect('/#feedback');
        }
        $this->assertSame(2, Feedback::where('status', 'new')->count());
        Notification::fake();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.13'])->post('/feedback', [
            'category' => 'bug', 'message' => "  আমি  ফোনে\nচিঠি খুলতে পারছি না, অনুগ্রহ করে এই সমস্যাটি দেখুন।  ",
        ])->assertRedirect('/#feedback');

        $feedback = Feedback::latest('id')->firstOrFail();
        $this->assertSame('spam', $feedback->status);
        $this->assertSame(['The same normalized message was already submitted at least twice within 24 hours.'], $feedback->spam_reasons);
        $this->assertDatabaseCount('feedback', 3);
        Notification::assertNothingSent();
    }

    public function test_repeated_noise_is_retained_without_notification(): void
    {
        $message = str_repeat('asdf ', 20);
        $this->post('/feedback', ['category' => 'other', 'message' => $message])->assertRedirect('/#feedback');

        $this->assertDatabaseCount('feedback', 1);
        $this->assertSame('spam', Feedback::firstOrFail()->status);
        $this->assertContains('The message consists almost entirely of a repeated short pattern.', Feedback::firstOrFail()->spam_reasons);
        Notification::assertNothingSent();
    }

    public function test_worker_inspection_can_exclude_the_current_record(): void
    {
        $message = 'This ordinary feedback was submitted once again after a connection problem.';
        Feedback::create(['category' => 'bug', 'message' => $message]);
        $current = Feedback::create(['category' => 'bug', 'message' => $message]);

        $result = app(FeedbackSpamDetector::class)->inspect($message, $current->id);

        $this->assertFalse($result['flagged']);
        $this->assertSame([], $result['reasons']);
    }

    public function test_honeypot_still_rejects_submission_before_it_is_recorded(): void
    {
        $this->post('/feedback', ['category' => 'other', 'message' => 'This feedback filled in the hidden honeypot.',
            'website' => 'https://advertiser.example.test'])->assertSessionHasErrors('website');

        $this->assertDatabaseCount('feedback', 0);
        Notification::assertNothingSent();
    }
}
