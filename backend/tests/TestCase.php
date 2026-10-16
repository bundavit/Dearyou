<?php

namespace Tests;

use App\Models\User;
use App\Support\SessionSecurity;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Email;

abstract class TestCase extends BaseTestCase
{
    protected function acceptedMailResponse(): SentMessage
    {
        $message = (new Email)->from('sender@example.test')->to('recipient@example.test')->text('Synthetic accepted message');

        return new SentMessage(new \Symfony\Component\Mailer\SentMessage($message, Envelope::create($message)));
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('legacy_media');
    }

    public function actingAs(Authenticatable $user, $guard = null)
    {
        parent::actingAs($user, $guard);

        if ($user instanceof User && ($guard === null || $guard === 'web')) {
            // Each explicit test login represents a newly authenticated browser,
            // including when a feature test switches between creator and admin.
            $this->withSession([SessionSecurity::SESSION_KEY => [
                'user_id' => (string) $user->getAuthIdentifier(),
                'version' => (int) $user->session_version,
                'password_fingerprint' => hash('sha256', $user->getAuthPassword()),
            ]]);
        }

        return $this;
    }
}
