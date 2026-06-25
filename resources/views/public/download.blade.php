@php
    $accent = $letter->primary_color ?: '#d85b78';
    $paper = $letter->secondary_color ?: '#fff1e8';
    $envelopeStyle = $letter->envelope_style ?: 'classic';
    $sealStyle = $letter->seal_style ?: 'round';
    $sealSymbols = [
        'round' => '&hearts;',
        'heart' => '&hearts;',
        'star' => '&#9733;',
        'flower' => '&#10048;',
        'diamond' => '&#9670;',
        'square' => '&#9632;',
        'scallop' => '&#10048;',
        'moon' => '&#9790;',
        'sparkle' => '&#10022;',
        'sun' => '&#9728;',
    ];
    $sealSymbol = $sealSymbols[$sealStyle] ?? '&hearts;';
    $toDataUri = static function (?string $path): ?string {
        if (! $path || \App\Models\Letter::isVideoMediaPath($path)) {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return null;
        }

        $mime = $disk->mimeType($path) ?: 'image/jpeg';

        return 'data:'.$mime.';base64,'.base64_encode($disk->get($path));
    };
    $imageDataUri = $toDataUri($letter->image_path);
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $letter->title }} | DearYou Keepsake</title>
    <style>
        :root {
            --accent: {{ $accent }};
            --paper: {{ $paper }};
            --ink: #3d2d35;
            --accent-dark: color-mix(in srgb, var(--accent), #5b2435 28%);
            --accent-light: color-mix(in srgb, var(--accent), #ffffff 34%);
            --accent-soft: color-mix(in srgb, var(--accent), #ffffff 72%);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            background:
                radial-gradient(circle at 20% 16%, color-mix(in srgb, var(--accent) 18%, transparent), transparent 26rem),
                radial-gradient(circle at 88% 78%, color-mix(in srgb, var(--accent) 16%, transparent), transparent 25rem),
                linear-gradient(135deg, #fff7f8, #fff0f4);
            color: var(--ink);
            font-family: Georgia, "Times New Roman", serif;
            line-height: 1.7;
        }

        .keepsake-scene {
            width: min(1040px, calc(100% - 32px));
            margin: 46px auto;
            padding: clamp(22px, 4vw, 56px) 0 72px;
        }

        .opened-envelope {
            position: relative;
            width: min(940px, 100%);
            min-height: 620px;
            margin: 0 auto;
            padding-bottom: 170px;
            filter: drop-shadow(0 30px 52px rgba(104, 54, 74, .2));
        }

        .opened-envelope-back,
        .opened-envelope-front,
        .opened-envelope-flap {
            position: absolute;
            left: 0;
            right: 0;
            pointer-events: none;
        }

        .opened-envelope-back {
            z-index: 1;
            bottom: 0;
            height: 360px;
            border-radius: 28px;
            background: var(--accent);
        }

        .opened-envelope-flap {
            z-index: 2;
            top: 185px;
            height: 280px;
            border-radius: 28px 28px 0 0;
            background: var(--accent-light);
            clip-path: polygon(0 0, 100% 0, 50% 82%);
        }

        .opened-envelope-front {
            z-index: 3;
            bottom: 0;
            height: 300px;
            border-radius: 0 0 28px 28px;
            background:
                linear-gradient(146deg, transparent 0 49.6%, var(--accent-dark) 50% 100%),
                linear-gradient(214deg, transparent 0 49.6%, color-mix(in srgb, var(--accent), #ffffff 10%) 50% 100%),
                var(--accent);
            clip-path: polygon(0 0, 50% 45%, 100% 0, 100% 100%, 0 100%);
        }

        .opened-envelope-paper {
            position: relative;
            z-index: 6;
            width: min(760px, calc(100% - 80px));
            margin: 0 auto;
            padding: clamp(34px, 6vw, 70px);
            border: 1px solid color-mix(in srgb, var(--accent), #ffffff 58%);
            border-radius: 24px;
            background: var(--paper);
            box-shadow: 0 20px 45px rgba(61, 45, 53, .15);
        }

        .opened-envelope-seal {
            position: absolute;
            z-index: 5;
            left: 50%;
            bottom: 118px;
            width: 62px;
            height: 62px;
            display: grid;
            place-items: center;
            border: 5px solid rgba(255, 255, 255, .72);
            border-radius: 50%;
            transform: translateX(-50%);
            background: #fff;
            color: var(--accent);
            font: 700 1.3rem/1 Georgia, serif;
            box-shadow: 0 11px 25px rgba(70, 34, 47, .22);
        }

        .opened-envelope-seal span {
            display: inline-block;
        }

        .brand {
            color: var(--accent);
            font: 700 14px/1.1 Arial, sans-serif;
            letter-spacing: .18em;
            text-transform: uppercase;
        }

        h1 {
            margin: 22px 0 28px;
            font-size: clamp(2.2rem, 6vw, 4.5rem);
            line-height: 1.05;
            letter-spacing: -.03em;
        }

        .to, .signoff {
            font-style: italic;
            font-size: 1.12rem;
        }

        .body {
            white-space: pre-wrap;
            font-size: 1.08rem;
        }

        figure {
            margin: 2rem 0;
        }

        img {
            display: block;
            width: 100%;
            max-height: 420px;
            object-fit: cover;
            border-radius: 20px;
            box-shadow: 0 18px 40px rgba(61, 45, 53, .14);
        }

        figcaption {
            margin-top: .75rem;
            color: #7c6671;
            text-align: center;
        }

        .envelope-style-rounded .opened-envelope-back,
        .envelope-style-rounded .opened-envelope-front,
        .envelope-style-rounded .opened-envelope-paper { border-radius: 40px; }

        .envelope-style-airmail .opened-envelope {
            padding: 10px;
            border-radius: 30px;
            background: repeating-linear-gradient(135deg, #dc4f68 0 15px, #fff 15px 28px, #497cb5 28px 43px, #fff 43px 56px);
        }

        .envelope-style-airmail .opened-envelope-front { background: #f2e8da; }
        .envelope-style-airmail .opened-envelope-back { background: #f8f2e8; }
        .envelope-style-airmail .opened-envelope-seal { color: #497cb5; }

        .envelope-style-vintage .opened-envelope { filter: drop-shadow(0 28px 42px rgba(92, 58, 30, .25)) sepia(.15); }
        .envelope-style-vintage .opened-envelope-back { background: #c7a477; }
        .envelope-style-vintage .opened-envelope-front { background: #a77a48; }
        .envelope-style-vintage .opened-envelope-paper { background: #f4e5c7; color: #62462f; }
        .envelope-style-vintage .opened-envelope-seal { border-radius: 8px; transform: translateX(-50%) rotate(8deg); background: #8d4238; color: #f6dfbd; }

        .envelope-style-gift .opened-envelope::before,
        .envelope-style-gift .opened-envelope::after,
        .envelope-style-ribbon .opened-envelope::before,
        .envelope-style-ribbon .opened-envelope::after {
            content: "";
            position: absolute;
            z-index: 4;
            background: #f4d27f;
            pointer-events: none;
        }

        .envelope-style-gift .opened-envelope::before { top: 185px; bottom: 0; left: calc(50% - 24px); width: 48px; }
        .envelope-style-gift .opened-envelope::after { left: 0; right: 0; bottom: 185px; height: 38px; }
        .envelope-style-ribbon .opened-envelope::before { top: 185px; bottom: 0; left: calc(50% - 18px); width: 36px; background: #fff0c9; }
        .envelope-style-ribbon .opened-envelope::after { left: 0; right: 0; bottom: 195px; height: 28px; background: #fff0c9; }

        .envelope-style-petal .opened-envelope-back,
        .envelope-style-petal .opened-envelope-front { border-radius: 38px; }
        .envelope-style-petal .opened-envelope-flap {
            border-radius: 50% 50% 0 0;
            clip-path: ellipse(56% 52% at 50% 0);
        }

        .envelope-style-pocket .opened-envelope-flap { display: none; }
        .envelope-style-pocket .opened-envelope-back,
        .envelope-style-pocket .opened-envelope-front { border-radius: 12px 12px 42px 42px; }

        .envelope-style-lace .opened-envelope-back,
        .envelope-style-lace .opened-envelope-front {
            border: 8px dotted rgba(255, 255, 255, .82);
            background-color: var(--accent);
        }

        .envelope-style-lace .opened-envelope-paper {
            border-style: dashed;
            box-shadow: 0 20px 45px rgba(61, 45, 53, .12), inset 0 0 0 10px rgba(255, 255, 255, .32);
        }

        .envelope-style-postcard .opened-envelope-back,
        .envelope-style-postcard .opened-envelope-front {
            border-radius: 10px;
            background: linear-gradient(135deg, #f7e7d1, var(--accent-soft));
        }

        .envelope-style-postcard .opened-envelope-front {
            clip-path: none;
            border-top: 4px dashed color-mix(in srgb, var(--accent), #6b4b3b 28%);
        }

        .seal-style-heart .opened-envelope-seal { border-radius: 45% 45% 50% 50%; clip-path: polygon(50% 91%, 8% 49%, 8% 27%, 22% 10%, 42% 11%, 50% 22%, 58% 11%, 78% 10%, 92% 27%, 92% 49%); border: 0; }
        .seal-style-star .opened-envelope-seal { border-radius: 0; clip-path: polygon(50% 0, 61% 34%, 98% 34%, 68% 55%, 79% 91%, 50% 69%, 21% 91%, 32% 55%, 2% 34%, 39% 34%); border: 0; }
        .seal-style-flower .opened-envelope-seal { border-radius: 42% 58% 46% 54% / 55% 42% 58% 45%; }
        .seal-style-diamond .opened-envelope-seal { border-radius: 10px; transform: translateX(-50%) rotate(45deg); }
        .seal-style-diamond .opened-envelope-seal span { transform: rotate(-45deg); }
        .seal-style-square .opened-envelope-seal { border-radius: 14px; }
        .seal-style-scallop .opened-envelope-seal { border-radius: 36% 64% 42% 58% / 58% 35% 65% 42%; }
        .seal-style-moon .opened-envelope-seal { box-shadow: inset -13px 0 0 color-mix(in srgb, var(--accent), white 82%), 0 11px 25px rgba(70, 34, 47, .22); }
        .seal-style-sparkle .opened-envelope-seal { border-radius: 16px 999px 16px 999px; }
        .seal-style-sun .opened-envelope-seal { border-radius: 50%; box-shadow: 0 0 0 8px color-mix(in srgb, var(--accent), transparent 76%), 0 11px 25px rgba(70, 34, 47, .22); }

        @media (max-width: 720px) {
            .opened-envelope-paper {
                width: calc(100% - 34px);
                padding: 28px;
            }

            .opened-envelope {
                min-height: 560px;
            }
        }

        @media print {
            body {
                background: #fff;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .keepsake-scene {
                margin: 0 auto;
                padding: 20px 0;
            }

            .opened-envelope {
                filter: none;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body class="envelope-style-{{ $envelopeStyle }} seal-style-{{ $sealStyle }}">
<main class="keepsake-scene">
    <section class="opened-envelope" aria-label="DearYou keepsake">
        <div class="opened-envelope-back"></div>
        <div class="opened-envelope-flap"></div>
        <article class="opened-envelope-paper">
            <p class="brand">DearYou</p>
            <p class="to">Dear {{ $letter->recipientLabel() }},</p>
            <h1>{{ $letter->title }}</h1>
            <div class="body">{{ $letter->body }}</div>

            @if($imageDataUri)
                <figure>
                    <img src="{{ $imageDataUri }}" alt="{{ $letter->image_alt ?: 'Letter image' }}">
                    @if($letter->image_alt)<figcaption>{{ $letter->image_alt }}</figcaption>@endif
                </figure>
            @endif

            <p class="signoff">With care,<br><strong>{{ $letter->senderLabel() }}</strong></p>
        </article>
        <div class="opened-envelope-front"></div>
        <div class="opened-envelope-seal"><span>{!! $sealSymbol !!}</span></div>
    </section>
</main>
</body>
</html>
