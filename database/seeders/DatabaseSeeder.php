<?php

namespace Database\Seeders;

use App\Models\User;
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
        $admin = User::factory()->create([
            'name' => 'DearYou Admin',
            'email' => env('ADMIN_EMAIL', 'admin@dearyou.test'),
            'password' => env('ADMIN_PASSWORD', 'ChangeMe123!'),
        ]);

        $samples = [
            ['confession', 'Something I Need to Tell You', 'Do you want to give us a chance?', 'Yes, I do', 'Not right now'],
            ['apology', 'I Owe You an Apology', 'Can you forgive me?', 'I forgive you', 'Not yet'],
            ['birthday', 'A Birthday Wish Just for You', 'Want to leave a little reply?', 'Thank you', 'Send a note'],
            ['valentine', 'To My Favorite Person', 'Will you be my Valentine?', 'Yes', "Let's talk"],
            ['congratulations', 'You Did It!', 'How are you feeling?', 'Amazing', 'Leave a note'],
            ['custom', 'A Note for You', 'Would you like to reply?', 'Reply', 'Maybe later'],
        ];
        foreach ($samples as [$category,$title,$question,$positive,$negative]) {
            $admin->letters()->create([
                'category' => $category, 'title' => $title, 'recipient_name' => 'Someone Special', 'sender_name' => 'Me',
                'body' => "I made this little corner of the internet just for you.\n\nReplace this sample with the words you really want to say.",
                'theme' => in_array($category, ['birthday', 'congratulations']) ? 'celebration' : ($category === 'valentine' ? 'romantic' : 'warm'),
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
