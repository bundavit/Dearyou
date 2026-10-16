<?php

namespace Tests\Feature;

use App\Models\Letter;
use App\Models\User;
use App\Support\LetterPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EditorNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    #[DataProvider('letterMutations')]
    public function test_letter_mutations_return_to_the_owners_editor_without_a_referer(
        string $role, string $previous, string $action,
    ): void {
        [$user, $letter, $prefix] = $this->letter($role);
        $oldToken = $letter->link->token;
        $this->actingAs($user);
        $this->setPreviousDestination($letter, $previous);

        $path = $prefix.'/letters/'.$letter->id;
        $response = $action === 'save'
            ? $this->put($path, array_replace($this->payload(), ['title' => 'Updated navigation letter']))
            : $this->post($path.'/'.$action);

        $response->assertRedirect($path.'/edit')->assertSessionHasNoErrors()->assertSessionHas('success');
        $letter->refresh()->load('link');
        match ($action) {
            'save' => $this->assertSame('Updated navigation letter', $letter->title),
            'publish' => $this->assertTrue($letter->isPubliclyAvailable()),
            'unpublish' => $this->assertSame('unpublished', $letter->status),
            'regenerate-link' => $this->assertNotSame($oldToken, $letter->link->token),
            'disable-link' => $this->assertFalse($letter->link->is_active),
        };
    }

    public static function letterMutations(): array
    {
        return self::mutationCases(['save', 'publish', 'unpublish', 'regenerate-link', 'disable-link']);
    }

    #[DataProvider('memoryMutations')]
    public function test_memory_mutations_return_to_the_owners_editor_without_a_referer(
        string $role, string $previous, string $action,
    ): void {
        [$user, $letter, $prefix] = $this->letter($role);
        $first = $letter->memories()->create(['title' => 'First memory', 'sort_order' => 0]);
        $memory = $letter->memories()->create(['title' => 'Second memory', 'sort_order' => 1]);
        $this->actingAs($user);
        $this->setPreviousDestination($letter, $previous);

        $response = match ($action) {
            'store' => $this->post($prefix.'/letters/'.$letter->id.'/memories', ['title' => 'New memory']),
            'update' => $this->put($prefix.'/memories/'.$memory->id, ['title' => 'Updated memory']),
            'move' => $this->patch($prefix.'/memories/'.$memory->id.'/move/up'),
            'destroy' => $this->delete($prefix.'/memories/'.$memory->id),
        };

        $response->assertRedirect($prefix.'/letters/'.$letter->id.'/edit')->assertSessionHasNoErrors();
        if ($action !== 'move') {
            $response->assertSessionHas('success');
        }
        match ($action) {
            'store' => $this->assertDatabaseHas('letter_memories', ['letter_id' => $letter->id, 'title' => 'New memory']),
            'update' => $this->assertSame('Updated memory', $memory->fresh()->title),
            'move' => $this->assertSame([$memory->id, $first->id], $letter->memories()->pluck('id')->all()),
            'destroy' => $this->assertDatabaseMissing('letter_memories', ['id' => $memory->id]),
        };
    }

    public static function memoryMutations(): array
    {
        return self::mutationCases(['store', 'update', 'move', 'destroy']);
    }

    #[DataProvider('roles')]
    public function test_owner_media_does_not_replace_editor_history_or_validation_destination(string $role): void
    {
        [$user, $letter, $prefix] = $this->letter($role);
        $editor = $prefix.'/letters/'.$letter->id.'/edit';
        $this->actingAs($user)->get($editor)->assertOk();
        $this->get($this->ownerMediaUrl($letter))->assertOk();
        $this->assertSame(url($editor), session()->previousUrl());

        $this->put($prefix.'/letters/'.$letter->id, array_replace($this->payload(), ['title' => '']))
            ->assertRedirect($editor)->assertSessionHasErrors('title');
        $this->assertSame('Navigation letter', $letter->fresh()->title);
    }

    #[DataProvider('roles')]
    public function test_memory_validation_returns_to_the_editor_after_media_loads(string $role): void
    {
        [$user, $letter, $prefix] = $this->letter($role);
        $editor = $prefix.'/letters/'.$letter->id.'/edit';
        $this->actingAs($user)->get($editor)->assertOk();
        $this->get($this->ownerMediaUrl($letter))->assertOk();

        $this->post($prefix.'/letters/'.$letter->id.'/memories', ['title' => ''])
            ->assertRedirect($editor)->assertSessionHasErrors('title');
        $this->assertDatabaseCount('letter_memories', 0);
    }

    #[DataProvider('roles')]
    public function test_avatar_does_not_replace_account_history_or_validation_destination(string $role): void
    {
        [$user, , $prefix] = $this->letter($role);
        $user->update(['avatar_path' => 'avatars/navigation.png']);
        Storage::disk('public')->put($user->avatar_path, $this->png());
        $account = $prefix.'/account';
        $this->actingAs($user)->get($account)->assertOk();
        $this->get('/media/avatar')->assertOk();
        $this->assertSame(url($account), session()->previousUrl());

        $this->put($account.'/profile', ['name' => '', 'email' => $user->email, 'current_password' => 'incorrect'])
            ->assertRedirect($account)->assertSessionHasErrorsIn('profile', 'name');
    }

    #[DataProvider('publicAttachments')]
    public function test_public_media_and_downloads_preserve_the_letter_page_for_non_javascript_replies(string $kind): void
    {
        [, $letter] = $this->letter(User::ROLE_USER);
        $public = '/l/'.$letter->link->token;
        $this->get($public)->assertOk();
        $attachment = match ($kind) {
            'image' => $public.'/media/'.hash('sha256', $letter->image_path),
            'text keepsake' => $public.'/download',
            'HTML keepsake' => $public.'/download?format=html',
        };
        $this->get($attachment)->assertOk();
        $this->assertSame(url($public), session()->previousUrl());

        $this->post($public.'/response', ['response_value' => 'positive'])
            ->assertRedirect($public)->assertSessionHas('response_sent', true);
        $this->assertDatabaseHas('responses', ['letter_id' => $letter->id, 'response_value' => 'positive']);
    }

    public function test_session_locking_and_persistence_still_work_with_the_web_session_middleware(): void
    {
        Route::middleware('web')->get('/_navigation-session-lock', function (Request $request) {
            $request->session()->put('navigation_lock_checked', true);

            return response('Session lock acquired.');
        })->block(1, 1);

        $this->get('/_navigation-session-lock')
            ->assertOk()->assertSee('Session lock acquired.')
            ->assertSessionHas('navigation_lock_checked', true)
            ->assertCookie(config('session.cookie'));
    }

    public static function roles(): array
    {
        return ['creator' => [User::ROLE_USER], 'administrator' => [User::ROLE_ADMIN]];
    }

    public static function publicAttachments(): array
    {
        return [['image'], ['text keepsake'], ['HTML keepsake']];
    }

    private static function mutationCases(array $actions): array
    {
        $cases = [];
        foreach (self::roles() as $label => [$role]) {
            foreach (['media', 'external'] as $previous) {
                foreach ($actions as $action) {
                    $cases[$label.' '.$action.' after '.$previous] = [$role, $previous, $action];
                }
            }
        }

        return $cases;
    }

    private function setPreviousDestination(Letter $letter, string $previous): void
    {
        if ($previous === 'media') {
            // A direct asset load has no editor page in history and no Referer.
            $this->get($this->ownerMediaUrl($letter))->assertOk();
        } else {
            // A stale/unrelated previous URL must never control a mutation's destination.
            $this->withSession(['_previous' => ['url' => 'https://unrelated.example.test/elsewhere']]);
        }
    }

    private function ownerMediaUrl(Letter $letter): string
    {
        return '/media/letters/'.$letter->id.'/'.hash('sha256', $letter->image_path);
    }

    private function letter(string $role): array
    {
        $user = User::factory()->create(['role' => $role]);
        $letter = $user->letters()->create($this->payload() + ['image_path' => 'letters/navigation.png']);
        Storage::disk('public')->put($letter->image_path, $this->png());
        app(LetterPublisher::class)->publish($letter);

        return [$user, $letter->refresh()->load('link'), $role === User::ROLE_ADMIN ? '/admin' : ''];
    }

    private function png(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII=');
    }

    private function payload(): array
    {
        return [
            'category' => 'confession', 'title' => 'Navigation letter', 'body' => 'Synthetic navigation check.',
            'theme' => 'warm', 'font_style' => 'classic', 'primary_color' => '#d85b78',
            'secondary_color' => '#fff1e8', 'decoration_type' => 'hearts',
            'allow_response' => false, 'response_mode' => 'buttons', 'expiry_minutes' => 60,
        ];
    }
}
