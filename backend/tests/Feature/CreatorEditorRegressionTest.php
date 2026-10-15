<?php

namespace Tests\Feature;

use App\Models\Letter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CreatorEditorRegressionTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('invalidRelationshipDates')]
    public function test_invalid_relationship_date_returns_a_usable_editor(mixed $date, bool $editing): void
    {
        $user = User::factory()->create();
        $letter = $editing ? $user->letters()->create($this->letterPayload()) : null;
        $editor = $letter ? "/letters/{$letter->id}/edit" : '/letters/create';
        $payload = $this->letterPayload() + ['relationship_started_at' => $date];

        $response = $this->actingAs($user)->from($editor);
        $response = $letter
            ? $response->put("/letters/{$letter->id}", $payload)
            : $response->post('/letters', $payload);
        $response->assertRedirect($editor)->assertSessionHasErrors('relationship_started_at');

        $this->withCookie(config('session.cookie'), session()->getId())->get($editor)
            ->assertOk()
            ->assertSee('The relationship started at field must be a valid date.')
            ->assertSee('Save letter');
        $this->assertSame($editing ? 1 : 0, Letter::count());
        if ($letter) {
            $this->assertNull($letter->fresh()->relationship_started_at);
        }
    }

    public static function invalidRelationshipDates(): array
    {
        return [
            'create malformed string' => ['not-a-date', false],
            'edit malformed string' => ['not-a-date', true],
            'edit unexpected array' => [['not-a-date'], true],
        ];
    }

    public function test_valid_saved_relationship_date_still_appears_in_preview(): void
    {
        $user = User::factory()->create();
        $letter = $user->letters()->create($this->letterPayload() + [
            'relationship_started_at' => '2026-06-10',
        ]);

        $this->actingAs($user)->get("/letters/{$letter->id}/edit")
            ->assertOk()
            ->assertSee('Started from June 10, 2026')
            ->assertSee('value="2026-06-10"', false);
    }

    #[DataProvider('accountValidationCases')]
    public function test_account_validation_errors_appear_only_in_the_submitted_form(string $form, bool $correctPassword): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => 'OriginalPassword1']);
        $currentPassword = $correctPassword ? 'OriginalPassword1' : 'incorrect-password';
        [$method, $path, $bag, $payload] = match ($form) {
            'profile' => ['put', '/account/profile', 'profile', [
                'name' => 'Unapplied name', 'email' => $user->email, 'current_password' => $currentPassword,
            ]],
            'password' => ['put', '/account/password', 'password', [
                'current_password' => $currentPassword,
                'password' => 'ReplacementPassword2', 'password_confirmation' => 'ReplacementPassword2',
            ]],
            'deletion' => ['delete', '/account', 'deletion', [
                'current_password' => $currentPassword, 'confirmation' => $correctPassword ? 'keep' : 'delete',
            ]],
        };
        $field = $correctPassword ? 'confirmation' : 'current_password';

        $this->actingAs($user)->from('/account')->{$method}($path, $payload)
            ->assertRedirect('/account')
            ->assertSessionHasErrorsIn($bag, $field);
        $errors = session('errors');
        $this->assertFalse($errors->getBag('default')->any());
        $message = $errors->getBag($bag)->first($field);
        $page = $this->withCookie(config('session.cookie'), session()->getId())->get('/account')->assertOk();
        $this->assertErrorAppearsOnlyInForm($page->getContent(), $path, $message);

        $this->assertFalse($user->fresh()->trashed());
        $this->assertNotSame('Unapplied name', $user->fresh()->name);
        $this->assertTrue(Hash::check('OriginalPassword1', $user->fresh()->password));
        Notification::assertNothingSent();
    }

    public static function accountValidationCases(): array
    {
        return [
            'profile incorrect password' => ['profile', false],
            'password incorrect password' => ['password', false],
            'deletion incorrect password' => ['deletion', false],
            'deletion incorrect confirmation' => ['deletion', true],
        ];
    }

    public function test_profile_storage_error_remains_visible_in_the_profile_form(): void
    {
        Storage::fake('public');
        config(['dearyou.storage_limit_mb' => 1]);
        $user = User::factory()->create(['password' => 'OriginalPassword1']);
        $user->letters()->create($this->letterPayload() + ['image_path' => 'letters/existing.png']);
        Storage::disk('public')->put('letters/existing.png', str_repeat('x', 1024 * 1024));

        $this->actingAs($user)->from('/account')->put('/account/profile', [
            'name' => $user->name, 'email' => $user->email, 'current_password' => 'OriginalPassword1',
            'avatar' => UploadedFile::fake()->image('avatar.png'),
        ])->assertRedirect('/account')->assertSessionHasErrorsIn('profile', 'media');

        $message = session('errors')->getBag('profile')->first('media');
        $page = $this->withCookie(config('session.cookie'), session()->getId())->get('/account')->assertOk();
        $this->assertErrorAppearsOnlyInForm($page->getContent(), '/account/profile', $message);
        $this->assertNull($user->fresh()->avatar_path);
        Storage::disk('public')->assertExists('letters/existing.png');
    }

    private function assertErrorAppearsOnlyInForm(string $html, string $path, string $message): void
    {
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML($html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new \DOMXPath($document);
        $errors = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " alert-danger ")]');
        $form = $xpath->query('//form[@action="'.url($path).'"]')->item(0);
        $this->assertNotNull($form);
        $this->assertStringContainsString($message, $html);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString($message, $form->textContent);
    }

    private function letterPayload(): array
    {
        return [
            'category' => 'confession', 'title' => 'QA regression draft', 'body' => 'Synthetic QA content.',
            'theme' => 'warm', 'font_style' => 'classic', 'primary_color' => '#d85b78',
            'secondary_color' => '#fff1e8', 'decoration_type' => 'hearts',
            'allow_response' => 1, 'response_mode' => 'buttons_with_message', 'expiry_minutes' => 60,
        ];
    }
}
