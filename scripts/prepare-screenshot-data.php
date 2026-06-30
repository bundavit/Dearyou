<?php

declare(strict_types=1);

use App\Models\Feedback;
use App\Models\Response;
use App\Models\User;
use App\Support\LetterPublisher;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$demoPassword = 'screenshot-demo-password';

$demo = User::updateOrCreate(
    ['email' => 'demo@dearyou.test'],
    [
        'name' => 'Demo Creator',
        'password' => $demoPassword,
        'role' => User::ROLE_USER,
        'email_verified_at' => now(),
    ],
);

$unverified = User::updateOrCreate(
    ['email' => 'unverified@dearyou.test'],
    [
        'name' => 'Unverified User',
        'password' => $demoPassword,
        'role' => User::ROLE_USER,
        'email_verified_at' => null,
    ],
);

$admin = User::where('email', env('ADMIN_EMAIL'))->first();
if (! $admin) {
    fwrite(STDERR, "Admin user not found. Run: php artisan db:seed\n");
    exit(1);
}

$publisher = app(LetterPublisher::class);

$letters = [];
foreach (['confession', 'birthday', 'valentine'] as $index => $category) {
    $letter = $demo->letters()->updateOrCreate(
        ['title' => 'Portfolio Sample: '.ucfirst($category)],
        [
            'category' => $category,
            'recipient_name' => 'Alex',
            'sender_name' => 'Sam',
            'body' => "Dear Alex,\n\nThis is a sample {$category} letter created for portfolio screenshots.\n\nWith love,\nSam",
            'theme' => $category === 'birthday' ? 'celebration' : 'warm',
            'font_style' => 'classic',
            'envelope_style' => 'classic',
            'seal_style' => 'round',
            'primary_color' => '#d85b78',
            'secondary_color' => '#fff1e8',
            'decoration_type' => $category === 'birthday' ? 'balloons' : 'hearts',
            'question_text' => 'Would you like to reply?',
            'positive_button_text' => 'Yes',
            'negative_button_text' => 'Maybe later',
            'response_mode' => 'buttons_with_message',
            'allow_response' => true,
            'status' => 'draft',
            'expiry_minutes' => 1440,
        ],
    );

    if ($index === 0) {
        $publisher->publish($letter->fresh());
        $letter = $letter->fresh()->load('link');
    }

    $letters[] = $letter;
}

$published = $letters[0];
$response = Response::updateOrCreate(
    ['letter_id' => $published->id, 'response_value' => 'positive'],
    [
        'letter_link_id' => $published->link?->id,
        'message' => 'This made my day. Thank you for sharing this with me.',
        'submitted_at' => now()->subHour(),
    ],
);

$feedback = Feedback::updateOrCreate(
    ['email' => 'visitor@example.com', 'message' => 'Beautiful letter experience. The envelope animation is lovely.'],
    [
        'category' => 'suggestion',
        'rating' => 5,
        'status' => 'new',
        'source_page' => '/',
    ],
);

$adminLetter = $admin->letters()->first();
if ($adminLetter && $adminLetter->status === 'draft') {
    $publisher->publish($adminLetter->fresh());
    $adminLetter = $adminLetter->fresh();
}

$adminResponse = null;
if ($adminLetter?->link) {
    $adminResponse = Response::updateOrCreate(
        ['letter_id' => $adminLetter->id, 'response_value' => 'positive'],
        [
            'letter_link_id' => $adminLetter->link->id,
            'message' => 'Thank you for this beautiful letter.',
            'submitted_at' => now()->subMinutes(30),
        ],
    );
}

$config = [
    'baseUrl' => rtrim(env('APP_URL', 'http://127.0.0.1:8001'), '/'),
    'demo' => ['email' => $demo->email, 'password' => $demoPassword],
    'admin' => ['email' => $admin->email, 'password' => env('ADMIN_PASSWORD')],
    'unverified' => ['email' => $unverified->email, 'password' => $demoPassword],
    'publishedToken' => $published->link?->token,
    'letterIds' => [
        'demo' => collect($letters)->pluck('id')->values()->all(),
        'admin' => $adminLetter?->id,
    ],
    'responseId' => $response->id,
    'adminResponseId' => $adminResponse?->id,
    'feedbackId' => $feedback->id,
    'userId' => $demo->id,
];

file_put_contents(__DIR__.'/screenshot-config.json', json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
