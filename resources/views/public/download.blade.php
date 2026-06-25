<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $letter->title }} | DearYou Keepsake</title>
    <style>
        :root {
            --accent: {{ $letter->primary_color ?: '#d85b78' }};
            --paper: {{ $letter->secondary_color ?: '#fff1e8' }};
            --ink: #3d2d35;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            background:
                radial-gradient(circle at 20% 15%, color-mix(in srgb, var(--accent) 18%, transparent), transparent 32rem),
                linear-gradient(135deg, #fff7f8, #fff0f4);
            color: var(--ink);
            font-family: Georgia, "Times New Roman", serif;
            line-height: 1.7;
        }

        main {
            width: min(860px, calc(100% - 32px));
            margin: 48px auto;
            padding: clamp(28px, 6vw, 64px);
            border: 1px solid rgba(216, 91, 120, .22);
            border-radius: 28px;
            background: var(--paper);
            box-shadow: 0 28px 80px rgba(104, 54, 74, .18);
        }

        .brand {
            color: var(--accent);
            font: 700 14px/1.1 Arial, sans-serif;
            letter-spacing: .18em;
            text-transform: uppercase;
        }

        h1 {
            margin: 22px 0 28px;
            font-size: clamp(2rem, 6vw, 4rem);
            line-height: 1.05;
        }

        .to, .signoff {
            font-style: italic;
            font-size: 1.1rem;
        }

        .body {
            white-space: pre-wrap;
            font-size: 1.05rem;
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

        @media print {
            body { background: #fff; }
            main { box-shadow: none; margin: 0 auto; }
        }
    </style>
</head>
<body>
<main>
    <p class="brand">DearYou</p>
    <p class="to">Dear {{ $letter->recipientLabel() }},</p>
    <h1>{{ $letter->title }}</h1>
    <div class="body">{{ $letter->body }}</div>

    @if($letter->image_path && ! \App\Models\Letter::isVideoMediaPath($letter->image_path))
        <figure>
            <img src="{{ Storage::url($letter->image_path) }}" alt="{{ $letter->image_alt ?: 'Letter image' }}">
            @if($letter->image_alt)<figcaption>{{ $letter->image_alt }}</figcaption>@endif
        </figure>
    @endif

    <p class="signoff">With care,<br><strong>{{ $letter->senderLabel() }}</strong></p>
</main>
</body>
</html>
