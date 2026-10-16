<?php

namespace Tests\Feature;

use App\Models\Letter;
use App\Models\ModerationAudit;
use App\Models\User;
use App\Support\LetterPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ModerationExpiryTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('expiryInputs')]
    public function test_expiry_override_stores_the_same_database_timestamp_for_letter_and_link(
        string $input, string $expected,
    ): void {
        $this->travelTo(Carbon::parse('2030-01-01 00:00:00', 'UTC'));
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $owner = User::factory()->create();
        $letter = $this->letter($owner);
        $token = $letter->link->token;
        $page = '/admin/moderation/letters/'.$letter->id;

        $this->actingAs($admin)->from($page)->patch($page.'/expiry', [
            'expires_at' => $input,
            'reason' => 'Synthetic expiry format regression.',
        ])->assertRedirect($page)->assertSessionHasNoErrors()->assertSessionHas('success');

        // Assert uncast values: SQLite accepts ISO strings that MySQL timestamps
        // can reject, so checking only the Eloquent datetime casts misses this.
        $this->assertSame($expected, DB::table('letters')->where('id', $letter->id)->value('expires_at'));
        $this->assertSame($expected, DB::table('letter_links')->where('letter_id', $letter->id)->value('expires_at'));
        $letter->refresh()->load('link');
        $this->assertSame($token, $letter->link->token);
        $this->assertTrue($letter->link->is_active);
        $this->assertTrue($letter->expires_at->equalTo($letter->link->expires_at));

        $audit = ModerationAudit::where('letter_id', $letter->id)
            ->where('action', 'letter_expiry_overridden')->sole();
        $this->assertSame($admin->id, $audit->admin_user_id);
        $this->assertSame($owner->id, $audit->target_user_id);
        $this->assertSame('Synthetic expiry format regression.', $audit->reason);
        $this->assertSame(Carbon::parse($expected, 'UTC')->toIso8601String(), $audit->metadata['expires_at']);

        $public = '/l/'.$token;
        $this->travelTo(Carbon::parse($expected, 'UTC')->subSecond());
        $this->get($public)->assertOk();
        $this->travelTo(Carbon::parse($expected, 'UTC')->addSecond());
        $this->get($public)->assertNotFound();
    }

    public static function expiryInputs(): array
    {
        return [
            'JavaScript UTC with milliseconds' => ['2030-01-01T02:03:04.567Z', '2030-01-01 02:03:04'],
            'explicit timezone offset' => ['2030-01-01T09:03:04+07:00', '2030-01-01 02:03:04'],
            'browser datetime-local' => ['2030-01-01T02:03', '2030-01-01 02:03:00'],
        ];
    }

    private function letter(User $owner): Letter
    {
        $letter = $owner->letters()->create([
            'category' => 'custom', 'title' => 'Expiry format regression',
            'body' => 'Synthetic expiry coverage.', 'theme' => 'warm',
            'primary_color' => '#d85b78', 'secondary_color' => '#fff1e8',
            'decoration_type' => 'hearts', 'font_style' => 'classic',
            'response_mode' => 'none', 'allow_response' => false, 'expiry_minutes' => 60,
        ]);
        app(LetterPublisher::class)->publish($letter);

        return $letter->refresh()->load('link');
    }
}
