<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Create thoughtful digital letters for confessions, apologies, birthdays, anniversaries, and the moments that deserve more than a text.">
    <title>DearYou - Put your feelings into words</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('dearyou-admin-mark.svg') }}">
    <link rel="preload" href="{{ asset('assets/dearyou/dearyou-floral-envelope-logo-web.webp') }}" as="image" type="image/webp">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/dearyou/app.css') }}?v={{ filemtime(public_path('assets/dearyou/app.css')) }}">
</head>
<body class="home-page">
@include('partials.user-navbar')

<main>
    @if(session('success'))
        <div class="home-notice auto-dismiss-alert" role="status" data-auto-dismiss-alert>
            <i class="bi bi-check-circle" aria-hidden="true"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <section class="home-hero" aria-labelledby="home-heading">
        <div class="home-hero-copy">
            <p class="home-kicker"><i class="bi bi-envelope-heart"></i> Private digital letters, made personal</p>
            <h1 id="home-heading">Some feelings deserve more than a text.</h1>
            <p class="home-lead">Write a private letter, personalize it with memories and music, then send one secure link. The recipient opens it without creating an account.</p>
            <div class="home-actions">
                @auth
                    <a class="home-button home-button-primary" href="{{ auth()->user()->isAdmin() ? route('admin.letters.create') : route('letters.create') }}"><i class="bi bi-pencil-square"></i> Write a letter</a>
                    <a class="home-button home-button-secondary" href="{{ auth()->user()->isAdmin() ? route('admin.letters.index') : route('letters.index') }}">View my letters</a>
                @else
                    <a class="home-button home-button-primary" href="{{ route('register') }}"><i class="bi bi-envelope-plus"></i> Create a free account</a>
                    <a class="home-button home-button-secondary" href="#how-it-works">How DearYou works</a>
                @endauth
            </div>
            <div class="home-trust-row">
                <span><i class="bi bi-person-x"></i> No recipient account</span>
                <span><i class="bi bi-link-45deg"></i> Time-limited private link</span>
                <span><i class="bi bi-chat-heart"></i> Optional private reply</span>
            </div>
        </div>
        <div class="home-hero-visual" aria-label="A DearYou letter waiting to be opened">
            <div class="home-visual-glow"></div>
            <div class="home-letter-card">
                <span class="home-letter-note">For someone special</span>
                <strong>Dear You,</strong>
                <p>I made this little corner of the internet just for you.</p>
            </div>
            <div class="home-envelope">
                <span class="home-envelope-flap"></span>
                <span class="home-envelope-left"></span>
                <span class="home-envelope-right"></span>
                <span class="home-envelope-bottom"></span>
                <span class="home-envelope-seal"><i class="bi bi-heart-fill"></i></span>
            </div>
            <span class="home-floater home-floater-one"><i class="bi bi-heart-fill"></i></span>
            <span class="home-floater home-floater-two"><i class="bi bi-stars"></i></span>
        </div>
    </section>

    <section class="home-section home-steps home-lazy-section" id="how-it-works">
        <div class="home-section-heading">
            <p class="home-kicker">HOW DEARYOU WORKS</p>
            <h2>Four simple steps from idea to inbox.</h2>
            <p>You create and manage the letter. The person receiving it only needs the private link you send.</p>
        </div>
        <ol class="steps-grid">
            <li><span>1</span><div><h3>Create your account</h3><p>Your account keeps your letters, private links, and responses together.</p></div></li>
            <li><span>2</span><div><h3>Write and personalize</h3><p>Choose an occasion, add your message, and optionally include photos, memories, or music.</p></div></li>
            <li><span>3</span><div><h3>Preview and publish</h3><p>Check what the recipient will see, then choose how long the private link stays active.</p></div></li>
            <li><span>4</span><div><h3>Share one link</h3><p>The recipient opens the letter directly and can reply if you allow it.</p></div></li>
        </ol>
    </section>

    <section class="home-section home-occasions home-lazy-section" id="occasions">
        <div class="home-section-heading">
            <p class="home-kicker">FOR EVERY MEANINGFUL MOMENT</p>
            <h2>Start with the reason you are writing.</h2>
            <p>Occasions provide a helpful starting style. You can still change the words, colors, envelope, and every personal detail.</p>
        </div>
        <div class="occasion-grid">
            <article><span><i class="bi bi-heart"></i></span><h3>Confession</h3><p>Put brave, honest feelings into words.</p></article>
            <article><span><i class="bi bi-flower1"></i></span><h3>Apology</h3><p>Say sorry with care and room for reflection.</p></article>
            <article><span><i class="bi bi-calendar-heart"></i></span><h3>Anniversary</h3><p>Bring your favorite memories into one place.</p></article>
            <article><span><i class="bi bi-balloon-heart"></i></span><h3>Birthday</h3><p>Make a wish feel more personal than a post.</p></article>
            <article><span><i class="bi bi-stars"></i></span><h3>Celebration</h3><p>Celebrate milestones, graduations, and wins.</p></article>
            <article><span><i class="bi bi-envelope-paper-heart"></i></span><h3>Just because</h3><p>Send a thoughtful note without needing a reason.</p></article>
        </div>
    </section>

    <section class="home-section home-features home-lazy-section" id="features">
        <div class="home-feature-intro">
            <p class="home-kicker">MAKE IT FEEL LIKE YOU</p>
            <h2>More than a message. A small experience.</h2>
            <p>Everything is optional. A letter can be simple text, or you can build a richer experience around it.</p>
        </div>
        <div class="feature-grid">
            <article><i class="bi bi-brush"></i><div><h3>Personal designs</h3><p>Choose themes, fonts, colors, decorations, envelope styles, and seals.</p></div></article>
            <article><i class="bi bi-images"></i><div><h3>Photos and memories</h3><p>Add pictures, GIFs, short videos, and a memory timeline for the moments behind your words.</p></div></article>
            <article><i class="bi bi-music-note-beamed"></i><div><h3>Music when it opens</h3><p>Add a song that begins when the recipient opens the envelope, with simple playback controls.</p></div></article>
            <article><i class="bi bi-reply"></i><div><h3>Private responses</h3><p>Invite a button response or personal message and receive it in your own inbox.</p></div></article>
            <article><i class="bi bi-eye"></i><div><h3>Link activity</h3><p>See how many times a published letter link has been opened.</p></div></article>
            <article><i class="bi bi-shield-lock"></i><div><h3>Sharing controls</h3><p>Publish when ready, disable or regenerate the link, and set an expiration date.</p></div></article>
        </div>
    </section>

    <section class="home-section home-privacy home-lazy-section" id="about">
        <div class="home-privacy-icon" aria-hidden="true"><i class="bi bi-shield-lock"></i></div>
        <div>
            <p class="home-kicker">THOUGHTFUL BY DESIGN</p>
            <h2>Your letter is not a public social post.</h2>
            <p>A letter is accessible to anyone who has its active link, so share it only with people you trust. You control when the link is published, when it expires, and whether it remains active. DearYou never shows your account email to the recipient.</p>
        </div>
    </section>

    <section class="home-section home-faq home-lazy-section">
        <div class="home-section-heading">
            <p class="home-kicker">GOOD TO KNOW</p>
            <h2>A few common questions.</h2>
        </div>
        <div class="faq-list">
            <details><summary>Does the recipient need an account?<i class="bi bi-plus-lg"></i></summary><p>No. They open the private link you share and can respond from the letter if you enable replies.</p></details>
            <details><summary>Can I edit a letter after creating it?<i class="bi bi-plus-lg"></i></summary><p>Yes. You can keep it as a draft, preview it, edit it, publish it, disable its link, or publish it again later.</p></details>
            <details><summary>What can I add to a letter?<i class="bi bi-plus-lg"></i></summary><p>Text, images, animated GIFs, short supported videos, background music, response choices, and memory chapters.</p></details>
            <details><summary>How private is the link?<i class="bi bi-plus-lg"></i></summary><p>The link uses a long random address and expires after the duration you choose. Anyone with the active link can open it, so do not post it publicly.</p></details>
            <details><summary>What happens when the link expires?<i class="bi bi-plus-lg"></i></summary><p>The recipient sees a gentle unavailable page instead of your letter. You can publish the letter again to create a fresh link.</p></details>
            <details><summary>Is there an upload limit?<i class="bi bi-plus-lg"></i></summary><p>Text letters are unlimited. Uploaded pictures, audio, videos, profile images, and memories share your account's media storage allowance.</p></details>
        </div>
    </section>

    <section class="home-final-cta">
        <img src="{{ asset('assets/dearyou/dearyou-floral-envelope-logo-web.webp') }}" alt="">
        <div><p class="home-kicker">READY WHEN YOU ARE</p><h2>Make someone feel remembered.</h2><p>Start with a few honest words. You can shape the rest as you go.</p></div>
        <a class="home-button home-button-primary" href="{{ auth()->check() ? (auth()->user()->isAdmin() ? route('admin.letters.create') : route('letters.create')) : route('register') }}">{{ auth()->check() ? 'Start writing' : 'Create a free account' }} <i class="bi bi-arrow-right"></i></a>
    </section>

    <section class="home-section home-feedback home-lazy-section" id="feedback">
        <div class="home-section-heading">
            <p class="home-kicker"><i class="bi bi-chat-heart"></i> HELP DEARYOU GROW</p>
            <h2>Have an idea or found something confusing?</h2>
            <p>Send a private note to the DearYou team. Only platform administrators can review it.</p>
        </div>
        <div class="feedback-card">
            @include('feedback._form', ['feedbackId' => 'home-feedback'])
        </div>
    </section>
</main>

<footer class="home-footer">
    <div class="home-footer-main">
        <div class="home-footer-brand">
            <a class="home-brand" href="{{ route('home') }}"><img src="{{ asset('assets/dearyou/dearyou-floral-envelope-logo-web.webp') }}" alt=""><span>DearYou</span></a>
            <p>A gentle place for the words that matter.</p>
        </div>
        <div><h2>Explore</h2><a href="#occasions">Occasions</a><a href="#features">Features</a><a href="#how-it-works">How it works</a><a href="#feedback">Feedback</a></div>
        <div><h2>Account</h2>@auth @if(auth()->user()->isAdmin())<a href="{{ route('admin.platform') }}">Admin dashboard</a>@endif<a href="{{ auth()->user()->isAdmin() ? route('admin.letters.index') : route('letters.index') }}">My letters</a>@else<a href="{{ route('login') }}">Sign in</a><a href="{{ route('register') }}">Create account</a>@endauth</div>
    </div>
    <div class="home-footer-bottom">
        <span>&copy; {{ date('Y') }} DearYou. Made for meaningful moments.</span>
        <a href="mailto:hello@dearyous.app">Contact</a>
    </div>
</footer>
<script src="{{ asset('assets/dearyou/app.js') }}?v={{ filemtime(public_path('assets/dearyou/app.js')) }}" defer></script>
</body>
</html>
