<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SessionSecurity;
use Illuminate\Cache\ArrayStore;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SessionSecurityTest extends TestCase
{
    use RefreshDatabase;

    private ?string $sessionDirectory = null;

    private array $cacheSessionValues = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['session.lottery' => [0, 100]]);

        // Make genuinely old sessions, with neither a password hash nor version.
        // These routes exist only in this test application's in-memory router.
        Route::middleware('web')->post('/_session-test/legacy/{user}', function (Request $request, User $user) {
            Auth::guard('web')->login($user);
            $request->session()->forget(SessionSecurity::SESSION_KEY);

            return response()->json(['security' => $request->session()->get(SessionSecurity::SESSION_KEY)]);
        });
        Route::middleware(['web', 'auth'])->get('/_session-test/me', function (Request $request) {
            return response()->json([
                'id' => $request->user()->id,
                'security' => $request->session()->get(SessionSecurity::SESSION_KEY),
                'session_id' => $request->session()->getId(),
                'csrf' => $request->session()->token(),
            ]);
        });
        Route::middleware('web')->post('/_session-test/reset/{user}', function (Request $request, User $user) {
            $request->session()->put('password_reset_authorized', [
                'user_id' => $user->id,
                'email' => $user->email,
                'password_fingerprint' => hash('sha256', $user->getAuthPassword()),
                'session_version' => (int) $user->session_version,
                'expires_at' => now()->addMinutes(10)->timestamp,
            ]);

            return response()->noContent();
        });
    }

    protected function tearDown(): void
    {
        if ($this->sessionDirectory !== null) {
            foreach (glob($this->sessionDirectory.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($this->sessionDirectory);
        }

        parent::tearDown();
    }

    public static function sessionDrivers(): array
    {
        return [
            'database' => ['database'],
            'file' => ['file'],
            'cookie (including replay of the encrypted payload)' => ['cookie'],
            'redis (isolated cache transport)' => ['redis'],
        ];
    }

    #[DataProvider('sessionDrivers')]
    public function test_account_password_change_revokes_other_sessions_and_remember_cookies_and_preserves_current_browser(string $driver): void
    {
        $this->useSessionDriver($driver);
        $user = User::factory()->create(['password' => 'StrongPass1']);
        $user->createToken('before-password-change', ['letters:read']);
        $legacy = $other = $current = [];

        $this->browse($legacy, 'POST', '/_session-test/legacy/'.$user->id)
            ->assertOk()->assertJsonPath('security', null);
        $this->login($other, $user, true);
        $this->login($current, $user, true);
        $oldRemember = $this->rememberOnly($other);
        $before = $this->browse($current, 'GET', '/_session-test/me')->assertOk()->json();
        $oldRememberToken = $user->fresh()->remember_token;

        $this->browse($current, 'PUT', '/account/password', [
            'current_password' => 'StrongPass1',
            'password' => 'ChangedPass2',
            'password_confirmation' => 'ChangedPass2',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $updated = $user->fresh();
        $this->assertSame(1, $updated->session_version);
        $this->assertTrue(Hash::check('ChangedPass2', $updated->password));
        $this->assertNotSame($oldRememberToken, $updated->remember_token);
        $this->assertSame(0, $updated->tokens()->count());
        $after = $this->browse($current, 'GET', '/_session-test/me')
            ->assertOk()->assertJsonPath('id', $user->id)->assertJsonPath('security.version', 1)->json();
        $this->assertNotSame($before['session_id'], $after['session_id']);
        $this->assertNotSame($before['csrf'], $after['csrf']);

        $this->browse($legacy, 'GET', '/_session-test/me')->assertRedirect('/login');
        $this->browse($other, 'GET', '/_session-test/me')->assertRedirect('/login');
        $this->browse($oldRemember, 'GET', '/_session-test/me')->assertRedirect('/login');
        $this->assertSame($updated->remember_token, $user->fresh()->remember_token);

        // A rejected old browser must not rotate the new shared remember token.
        $newRemember = $this->rememberOnly($current);
        $this->browse($newRemember, 'GET', '/_session-test/me')
            ->assertOk()->assertJsonPath('security.version', 1);
        $freshLogin = [];
        $this->login($freshLogin, $updated, false, 'ChangedPass2');
        $this->browse($freshLogin, 'GET', '/_session-test/me')->assertOk()->assertJsonPath('security.version', 1);
    }

    #[DataProvider('sessionDrivers')]
    public function test_password_reset_revokes_legacy_sessions_existing_sessions_and_old_remember_cookies(string $driver): void
    {
        $this->useSessionDriver($driver);
        $user = User::factory()->create(['password' => 'StrongPass1']);
        $user->createToken('before-reset', ['letters:read']);
        $legacy = $other = $reset = [];
        $this->browse($legacy, 'POST', '/_session-test/legacy/'.$user->id)->assertOk();
        $this->login($other, $user, true);
        $remember = $this->rememberOnly($other);
        $oldToken = $user->fresh()->remember_token;
        $this->browse($reset, 'POST', '/_session-test/reset/'.$user->id)->assertNoContent();

        $this->browse($reset, 'POST', '/reset-password', [
            'password' => 'ChangedPass2',
            'password_confirmation' => 'ChangedPass2',
        ])->assertRedirect('/login')->assertSessionHasNoErrors();

        $this->assertSame(1, $user->fresh()->session_version);
        $this->assertNotSame($oldToken, $user->fresh()->remember_token);
        $this->assertSame(0, $user->tokens()->count());
        $this->browse($legacy, 'GET', '/_session-test/me')->assertRedirect('/login');
        $this->browse($other, 'GET', '/_session-test/me')->assertRedirect('/login');
        $this->browse($remember, 'GET', '/_session-test/me')->assertRedirect('/login');
        $this->browse($reset, 'GET', '/_session-test/me')->assertRedirect('/login');

        $newBrowser = [];
        $this->login($newBrowser, $user->fresh(), true, 'ChangedPass2');
        $this->browse($newBrowser, 'GET', '/_session-test/me')->assertOk()->assertJsonPath('security.version', 1);
        $newRemember = $this->rememberOnly($newBrowser);
        $this->browse($newRemember, 'GET', '/_session-test/me')->assertOk()->assertJsonPath('security.version', 1);
    }

    public function test_a_versionless_session_cannot_be_enrolled_after_password_rotation(): void
    {
        $this->useSessionDriver('database');
        $user = User::factory()->create(['password' => 'StrongPass1']);
        app(SessionSecurity::class)->changePassword($user, 'ChangedPass2');
        $browser = [];
        $this->browse($browser, 'POST', '/_session-test/legacy/'.$user->id)->assertOk();
        $this->browse($browser, 'GET', '/_session-test/me')->assertRedirect('/login');
    }

    public function test_legacy_database_payload_is_rejected_without_depending_on_row_deletion(): void
    {
        $this->useSessionDriver('database');
        $user = User::factory()->create();
        $browser = [];
        $this->browse($browser, 'POST', '/_session-test/legacy/'.$user->id)->assertOk();
        $row = DB::table('sessions')->where('user_id', $user->id)->first();
        $this->assertNotNull($row);
        $payload = json_decode(base64_decode($row->payload), true);
        $this->assertArrayNotHasKey(SessionSecurity::SESSION_KEY, $payload);
        $this->assertArrayNotHasKey('password_hash_web', $payload);

        app(SessionSecurity::class)->changePassword($user, 'ChangedPass2');
        $this->assertDatabaseHas('sessions', ['id' => $row->id]);
        $this->browse($browser, 'GET', '/_session-test/me', [], true)->assertUnauthorized();
    }

    public function test_token_revocation_failure_rolls_back_password_remember_token_and_session_version(): void
    {
        $user = User::factory()->create(['password' => 'StrongPass1']);
        $token = $user->createToken('must-survive-rollback', ['letters:read']);
        $before = $user->fresh()->getAttributes();
        DB::unprepared("CREATE TRIGGER reject_security_token_delete BEFORE DELETE ON personal_access_tokens BEGIN SELECT RAISE(ABORT, 'synthetic token delete failure'); END");

        try {
            app(SessionSecurity::class)->changePassword($user, 'ChangedPass2');
            $this->fail('The synthetic token deletion failure must abort password change.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('synthetic token delete failure', $exception->getMessage());
        } finally {
            DB::unprepared('DROP TRIGGER reject_security_token_delete');
        }

        $this->assertSame($before['password'], $user->fresh()->password);
        $this->assertSame($before['remember_token'], $user->fresh()->remember_token);
        $this->assertSame(0, $user->fresh()->session_version);
        $this->assertSame($before['password'], $user->password);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_stale_credential_snapshot_cannot_overwrite_a_newer_password(): void
    {
        $user = User::factory()->create(['password' => 'StrongPass1']);
        $stale = $user->fresh();
        app(SessionSecurity::class)->changePassword($user, 'ChangedPass2');

        try {
            app(SessionSecurity::class)->changePassword($stale, 'AttackerPass3');
            $this->fail('Credentials validated before another password change must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('password', $exception->errors());
        }

        $this->assertSame(1, $user->fresh()->session_version);
        $this->assertTrue(Hash::check('ChangedPass2', $user->fresh()->password));
    }

    private function useSessionDriver(string $driver): void
    {
        config(['session.driver' => $driver]);

        if ($driver === 'file') {
            $this->sessionDirectory = sys_get_temp_dir().'/dearyou-session-test-'.Str::random(20);
            mkdir($this->sessionDirectory);
            config(['session.files' => $this->sessionDirectory]);
        }

        if ($driver === 'redis') {
            // Exercise Laravel's Redis session driver without contacting any
            // configured Redis server. Only the cache transport is substituted.
            $store = new class($this->cacheSessionValues) extends ArrayStore
            {
                public function __construct(array &$values)
                {
                    parent::__construct();
                    // Laravel clones Redis repositories per session. The
                    // offline transport must share data across those clones.
                    $this->storage = &$values;
                }

                public function setConnection(?string $connection): static
                {
                    return $this;
                }
            };
            $this->app['cache']->extend('session-security-test', fn () => $this->app['cache']->repository($store));
            config([
                'cache.stores.session-security-test' => ['driver' => 'session-security-test'],
                'session.store' => 'session-security-test',
            ]);
        }
    }

    private function login(array &$browser, User $user, bool $remember, string $password = 'StrongPass1'): void
    {
        $response = $this->browse($browser, 'POST', '/login', [
            'email' => $user->email,
            'password' => $password,
            'remember' => $remember,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertContains(parse_url($response->headers->get('Location'), PHP_URL_PATH), ['/letters', '/_session-test/me']);
    }

    private function rememberOnly(array $browser): array
    {
        $name = Auth::guard('web')->getRecallerName();
        $this->assertArrayHasKey($name, $browser);

        return [$name => $browser[$name]];
    }

    /** Use distinct encrypted cookie jars and fresh guards/stores, as separate browsers would. */
    private function browse(array &$browser, string $method, string $uri, array $data = [], bool $json = false): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
        $this->app['cookie']->flushQueuedCookies();
        $server = $json ? ['HTTP_ACCEPT' => 'application/json'] : [];
        $response = $this->call($method, $uri, $data, $browser, [], $server);

        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getExpiresTime() !== 0 && $cookie->getExpiresTime() <= time()) {
                unset($browser[$cookie->getName()]);
            } else {
                $browser[$cookie->getName()] = $cookie->getValue();
            }
        }

        return $response;
    }
}
