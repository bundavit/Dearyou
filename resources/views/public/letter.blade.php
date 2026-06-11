<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $letter->title }} | DearYou</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('dearyou-admin-mark.svg') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/dearyou/app.css') }}?v={{ filemtime(public_path('assets/dearyou/app.css')) }}">
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
    $fontStacks = [
        'classic' => 'Georgia, serif',
        'elegant' => '"Lucida Calligraphy", "Monotype Corsiva", cursive',
        'modern' => '"Segoe UI", Arial, sans-serif',
        'friendly' => '"Comic Sans MS", "Segoe Print", cursive',
        'typewriter' => '"Courier New", Courier, monospace',
        'handwritten' => '"Segoe Print", "Bradley Hand", cursive',
        'formal' => '"Copperplate Gothic Light", Cambria, serif',
    ];
@endphp
<body class="recipient-page theme-{{ $letter->theme }} category-{{ $letter->category }} font-{{ $letter->font_style ?: 'classic' }}" style="--accent:{{ $letter->primary_color }};--paper:{{ $letter->secondary_color }};--letter-font:{{ $fontStacks[$letter->font_style] ?? $fontStacks['classic'] }}">
<div class="floaters decoration-{{ $letter->decoration_type }}" aria-hidden="true">
    @foreach($decorations[$letter->decoration_type] ?? $decorations['sparkles'] as $symbol)<span>{!! $symbol !!}</span>@endforeach
</div>
<main class="recipient-main">
    @if(!empty($preview))<div class="preview-ribbon">Preview</div>@endif
    @if($letter->audio_path)
        <div class="letter-audio-player">
            <button type="button" data-audio-toggle aria-label="Play background music"><i class="bi bi-play-fill"></i><span>Play music</span></button>
            <audio data-letter-audio preload="metadata" src="{{ Storage::url($letter->audio_path) }}"></audio>
        </div>
    @endif
    <section id="envelope-stage" class="envelope-stage" @if(session('response_sent')) hidden @endif>
        <header class="recipient-app-header">
            <span class="recipient-brand-mark">D</span>
            <span>DearYou</span>
        </header>

        <div class="envelope-welcome text-center">
            <span class="occasion-pill">{{ ucfirst($letter->category) }}</span>
            <p class="eyebrow">{{ $copy['label'] }}</p>
            <h1>{{ $letter->recipientLabel() }}</h1>
            <p class="envelope-intro">{{ $copy['hint'] }}</p>
        </div>

        <button id="open-letter" class="envelope-button" aria-controls="letter-content" aria-expanded="false">
            <span class="envelope-letter">
                <span>For {{ $letter->recipientLabel() }}</span>
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
    </section>
    <section id="letter-content" class="opened-letter-scene @if(session('response_sent')) revealed @endif" @unless(session('response_sent')) hidden @endunless>
    <button class="letter-close-button" id="close-letter" type="button" aria-label="Close letter">
        <i class="bi bi-x-lg" aria-hidden="true"></i>
    </button>
    <div class="opened-envelope" aria-hidden="true">
        <span class="opened-envelope-back"></span>
        <span class="opened-envelope-flap"></span>
        <span class="opened-envelope-front"></span>
        <span class="opened-envelope-seal"><i class="bi bi-heart-fill"></i></span>
    </div>
    <article class="paper">
        <p class="letter-to">Dear {{ $letter->recipientLabel() }},</p>
        <h1>{{ $letter->title }}</h1>
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
                            @if($memory->images->isNotEmpty())
                                <div class="memory-gallery memory-gallery-{{ min($memory->images->count(), 4) }}">
                                    @foreach($memory->images as $image)<button type="button" class="memory-gallery-button" data-lightbox-image="{{ Storage::url($image->image_path) }}" data-lightbox-alt="{{ $memory->title }} picture {{ $loop->iteration }}"><img src="{{ Storage::url($image->image_path) }}" alt="{{ $memory->title }} picture {{ $loop->iteration }}"></button>@endforeach
                                </div>
                            @endif
                            @if($memory->caption)<p>{{ $memory->caption }}</p>@endif
                        </div>
                    </article>
                @endforeach
            </section>
        @endif
        <p class="letter-signoff">With care,<br><strong>{{ $letter->senderLabel() }}</strong></p>
        @if($letter->image_path)
            <figure class="letter-image letter-image-after-message"><img src="{{ Storage::url($letter->image_path) }}" alt="{{ $letter->image_alt ?: '' }}">@if($letter->image_alt)<figcaption>{{ $letter->image_alt }}</figcaption>@endif</figure>
        @endif

        @if(session('response_sent'))
            @include('public.partials.response-result', ['responseValue' => session('response_value')])
        @elseif($letter->allow_response && empty($preview) && $letter->response_mode !== 'none')
            <form class="response-form" method="post" action="{{ route('letters.respond',$link->token) }}" data-response-form data-async-response data-mode="{{ $letter->response_mode }}">
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
    </article>
    </section>
</main>
<dialog class="memory-lightbox" data-memory-lightbox aria-label="Memory picture viewer">
    <button class="lightbox-close" type="button" data-lightbox-close aria-label="Close picture viewer"><i class="bi bi-x-lg"></i></button>
    <button class="lightbox-nav lightbox-prev" type="button" data-lightbox-prev aria-label="Previous picture"><i class="bi bi-chevron-left"></i></button>
    <figure><img data-lightbox-main alt=""><figcaption data-lightbox-caption></figcaption></figure>
    <button class="lightbox-nav lightbox-next" type="button" data-lightbox-next aria-label="Next picture"><i class="bi bi-chevron-right"></i></button>
</dialog>
<script src="{{ asset('assets/dearyou/app.js') }}?v={{ filemtime(public_path('assets/dearyou/app.js')) }}"></script>
</body>
</html>
