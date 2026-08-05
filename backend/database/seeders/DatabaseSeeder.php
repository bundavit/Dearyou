<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\PlatformSettings;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        app(PlatformSettings::class)->update([
            'allowed_expiry_minutes' => PlatformSettings::DEFAULT_EXPIRY_OPTIONS,
            'default_expiry_minutes' => 60,
            'storage_limit_mb' => (int) config('dearyou.storage_limit_mb', 250),
            'cleanup_grace_days' => (int) config('dearyou.storage_cleanup_grace_days', 7),
            'cleanup_enabled' => true,
            'cleanup_policy' => 'oldest_expired',
            'enabled_categories' => array_keys(PlatformSettings::CATEGORY_OPTIONS),
            'letter_media_limit_mb' => 10,
            'audio_limit_mb' => 25,
            'profile_image_limit_mb' => 10,
            'memory_files_per_upload' => 10,
        ]);

        $admin = User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@dearyou.test')],
            [
                'name' => 'DearYou Admin',
                'password' => env('ADMIN_PASSWORD', 'change-me-local-admin-password'),
                'role' => User::ROLE_ADMIN,
                'email_verified_at' => now(),
            ],
        );
        if (! $admin->isAdmin()) {
            $admin->update(['role' => User::ROLE_ADMIN]);
        }
        if (! $admin->hasVerifiedEmail()) {
            $admin->markEmailAsVerified();
        }

        $samples = [
            ['confession', 'Something I Need to Tell You', 'Do you want to give us a chance?', 'Yes, I do', 'Not right now'],
            ['apology', 'I Owe You an Apology', 'Can you forgive me?', 'I forgive you', 'Not yet'],
            ['birthday', 'A Birthday Wish Just for You', 'Want to leave a little reply?', 'Thank you', 'Send a note'],
            ['valentine', 'To My Favorite Person', 'Will you be my Valentine?', 'Yes', "Let's talk"],
            ['congratulations', 'You Did It!', 'How are you feeling?', 'Amazing', 'Leave a note'],
            ['custom', 'A Note for You', 'Would you like to reply?', 'Reply', 'Maybe later'],
        ];
        foreach ($samples as [$category, $title, $question, $positive, $negative]) {
            $admin->letters()->firstOrCreate([
                'category' => $category,
                'title' => $title,
            ], [
                'recipient_name' => 'Someone Special', 'sender_name' => 'Me',
                'body' => "I made this little corner of the internet just for you.\n\nReplace this sample with the words you really want to say.",
                'theme' => in_array($category, ['birthday', 'congratulations']) ? 'celebration' : ($category === 'valentine' ? 'romantic' : 'warm'),
                'font_style' => 'classic',
                'envelope_style' => 'classic',
                'seal_style' => 'round',
                'primary_color' => '#d85b78', 'secondary_color' => '#fff1e8',
                'decoration_type' => match ($category) {
                    'birthday' => 'balloons',
                    'congratulations' => 'confetti',
                    'custom' => 'sparkles',
                    default => 'hearts',
                },
                'question_text' => $question, 'positive_button_text' => $positive, 'negative_button_text' => $negative,
                'response_mode' => 'buttons_with_message', 'allow_response' => true, 'status' => 'draft',
            ]);
        }
    }
}
