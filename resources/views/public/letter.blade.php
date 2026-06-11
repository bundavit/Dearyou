<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $letter->title }} | DearYou</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('dearyou-admin-mark.svg') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/dearyou/app.css') }}">
</head>
@php
    $occasionCopy = [
        'confession' => ['label' => 'A FEELING PUT INTO WORDS', 'hint' => 'Something honest is waiting inside.'],
        'apology' => ['label' => 'A SINCERE LETTER FOR', 'hint' => 'Some words deserve to be said properly.'],
        'birthday' => ['label' => 'A BIRTHDAY SURPRISE FOR', 'hint' => 'A little celebration is waiting inside.'],
        'anniversary' => ['label' => 'A MEMORY-FILLED LETTER FOR', 'hint' => 'A small reminder of how far you have come.'],
        'valentine' => ['label' => 'A VALENTINE FOR', 'hint' => 'Made with a little extra heart.'],
        'congratulations' => ['label' => 'A CELEBRATION FOR', 'hint' => 'You did something worth celebrating.'],
        'thank-you' => ['label' => 'A NOTE OF THANKS FOR', 'hint' => 'A grateful message is waiting inside.'],
        'friendship' => ['label' => 'A NOTE FOR A WONDERFUL FRIEND', 'hint' => 'Good friends deserve good words.'],
        'graduation' => ['label' => 'A PROUD LETTER FOR', 'hint' => 'A new chapter deserves a celebration.'],
        'celebration' => ['label' => 'SOMETHING TO CELEBRATE WITH', 'hint' => 'Open this when you are ready to smile.'],
        'custom' => ['label' => 'A PRIVATE LETTER FOR', 'hint' => 'From someone who wanted to say this properly.'],
    ];
    $copy = $occasionCopy[$letter->category] ?? $occasionCopy['custom'];
    $decorations = [
        'hearts' => ['&#9829;', '&#9825;', '&#9829;', '&#9825;'],
        'stars' => ['&#9733;', '&#10022;', '&#9734;', '&#10022;'],
        'balloons' => ['&#9679;', '&#9675;', '&#9679;', '&#9675;'],
        'confetti' => ['&#10022;', '&#9632;', '&#9679;', '&#9650;'],
        'flowers' => ['&#10047;', '&#10048;', '&#10047;', '&#10048;'],
        'sparkles' => ['&#10022;', '&#10023;', '&#10022;', '&#10023;'],
        'none' => [],
    ];
@endphp
<body class="recipient-page theme-{{ $letter->theme }} category-{{ $letter->category }}" style="--accent:{{ $letter->primary_color }};--paper:{{ $letter->secondary_color }}">
<div class="floaters decoration-{{ $letter->decoration_type }}" aria-hidden="true">
    @foreach($decorations[$letter->decoration_type] ?? $decorations['sparkles'] as $symbol)<span>{!! $symbol !!}</span>@endforeach
</div>
<main class="recipient-main">
    @if(!empty($preview))<div class="preview-ribbon">Preview</div>@endif
    <section id="envelope-stage" class="envelope-stage">
        <header class="recipient-app-header">
            <span class="recipient-brand-mark">D</span>
            <span>DearYou</span>
            <span class="private-note"><i class="bi bi-lock-fill"></i><span>Private</span></span>
        </header>

        <div class="envelope-welcome text-center">
            <span class="occasion-pill">{{ ucfirst($letter->category) }}</span>
            <p class="eyebrow">{{ $copy['label'] }}</p>
            <h1>{{ $letter->recipient_name }}</h1>
            <p class="envelope-intro">{{ $copy['hint'] }}</p>
        </div>

        <button id="open-letter" class="envelope-button" aria-controls="letter-content" aria-expanded="false">
            <span class="envelope-letter">
                <span>For {{ $letter->recipient_name }}</span>
                <strong>{{ $letter->title }}</strong>
            </span>
            <span class="envelope-back"></span>
            <span class="envelope-fold envelope-fold-left"></span>
            <span class="envelope-fold envelope-fold-right"></span>
            <span class="envelope-fold envelope-fold-bottom"></span>
            <span class="envelope-flap"></span>
            <span class="envelope-seal"><i class="bi bi-heart-fill"></i></span>
            <span class="visually-hidden">Open letter</span>
        </button>

        <button class="btn btn-dearyou btn-wide recipient-open-button" id="open-letter-text">
            <i class="bi bi-envelope-open-heart"></i> Open Letter
        </button>
        <p class="recipient-privacy"><i class="bi bi-shield-check"></i> Made especially for you</p>
    </section>
    <article id="letter-content" class="paper" hidden>
        <p class="letter-to">Dear {{ $letter->recipient_name }},</p>
        <h1>{{ $letter->title }}</h1>
        @if($letter->image_path)
            <figure class="letter-image"><img src="{{ Storage::url($letter->image_path) }}" alt="{{ $letter->image_alt ?: '' }}">@if($letter->image_alt)<figcaption>{{ $letter->image_alt }}</figcaption>@endif</figure>
        @endif
        <div class="letter-body">{!! nl2br(e($letter->body)) !!}</div>
        @if($letter->category === 'anniversary' && $letter->memories->isNotEmpty())
            <section class="memory-timeline" aria-labelledby="memory-heading">
                <p class="eyebrow">OUR STORY</p><h2 id="memory-heading">Moments worth remembering</h2>
                @foreach($letter->memories as $memory)
                    <article class="memory-moment">
                        <span class="memory-dot" aria-hidden="true"></span>
                        <div class="memory-card">
                            @if($memory->memory_date)<time datetime="{{ $memory->memory_date->format('Y-m-d') }}">{{ $memory->memory_date->format('F j, Y') }}</time>@endif
                            <h3>{{ $memory->title }}</h3>
                            @if($memory->image_path)<img src="{{ Storage::url($memory->image_path) }}" alt="{{ $memory->title }}">@endif
                            @if($memory->caption)<p>{{ $memory->caption }}</p>@endif
                        </div>
                    </article>
                @endforeach
            </section>
        @endif
        <p class="letter-signoff">With care,<br><strong>{{ $letter->sender_name }}</strong></p>

        @if(session('response_sent'))
            <div class="response-thanks">
                @if(session('response_value') === 'positive' && $letter->category === 'confession')
                    <p class="accepted-mark" aria-hidden="true">&#9829;</p>
                    <h2>A beautiful new chapter begins.</h2>
                    <p>Your answer was sent privately. Thank you for meeting these words with honesty.</p>
                    @if($letter->sender_profile_path || $letter->recipient_profile_path)
                        <div class="confession-profiles">
                            <div>@if($letter->sender_profile_path)<img src="{{ Storage::url($letter->sender_profile_path) }}" alt="{{ $letter->sender_name }}">@else<span class="profile-placeholder">{{ strtoupper(substr($letter->sender_name,0,1)) }}</span>@endif<strong>{{ $letter->sender_name }}</strong></div>
                            <span class="profile-heart" aria-hidden="true">&#9829;</span>
                            <div>@if($letter->recipient_profile_path)<img src="{{ Storage::url($letter->recipient_profile_path) }}" alt="{{ $letter->recipient_name }}">@else<span class="profile-placeholder">{{ strtoupper(substr($letter->recipient_name,0,1)) }}</span>@endif<strong>{{ $letter->recipient_name }}</strong></div>
                        </div>
                    @endif
                    @if($letter->relationship_started_at)<p class="started-date">Started from {{ $letter->relationship_started_at->format('F j, Y') }}</p>@endif
                @else
                    <h2>{{ session('response_value') === 'positive' ? 'Thank you for this warm answer.' : 'Thank you for answering honestly.' }}</h2>
                    <p>Your response was sent privately and respectfully.</p>
                @endif
            </div>
        @elseif($letter->allow_response && empty($preview) && $letter->response_mode !== 'none')
            <form class="response-form" method="post" action="{{ route('letters.respond',$link->token) }}" data-response-form data-mode="{{ $letter->response_mode }}">
                @csrf
                <h2>{{ $letter->question_text ?: 'Would you like to reply?' }}</h2>
                @if($letter->response_mode === 'message')
                    <input type="hidden" name="response_value" value="message">
                    <textarea class="form-control mt-3" name="message" rows="4" placeholder="Write a private response" required></textarea>
                    <button class="btn btn-dearyou mt-3"><i class="bi bi-send"></i> Send private response</button>
                @else
                    <div class="d-flex flex-wrap gap-2 justify-content-center">
                        <button type="{{ $letter->response_mode === 'buttons' ? 'submit' : 'button' }}" name="response_value" value="positive" data-response-choice="positive" class="btn btn-dearyou">{{ $letter->positive_button_text ?: 'Yes' }}</button>
                        <button type="{{ $letter->response_mode === 'buttons' ? 'submit' : 'button' }}" name="response_value" value="negative" data-response-choice="negative" class="btn btn-outline-secondary">{{ $letter->negative_button_text ?: 'No' }}</button>
                    </div>
                    @if($letter->response_mode === 'buttons_with_message')
                        <div class="response-compose mt-3" hidden>
                            <input type="hidden" name="response_value" data-response-value>
                            <p class="response-guidance mb-2"></p>
                            <textarea class="form-control" name="message" rows="4" placeholder="Add a private message (optional)"></textarea>
                            <button class="btn btn-dearyou mt-3"><i class="bi bi-send"></i> Send response</button>
                        </div>
                    @endif
                @endif
            </form>
        @endif
        <button class="btn btn-link d-flex mx-auto mt-4" id="reread"><i class="bi bi-arrow-counterclockwise"></i> Close and reread</button>
    </article>
</main>
<script src="{{ asset('assets/dearyou/app.js') }}"></script>
</body>
</html>
