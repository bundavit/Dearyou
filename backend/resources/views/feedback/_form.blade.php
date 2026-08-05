<form method="post" action="{{ route('feedback.store') }}" class="feedback-form">
    @csrf
    <input type="hidden" name="source_page" value="{{ old('source_page', url()->previous()) }}">
    <div class="feedback-honeypot" aria-hidden="true">
        <label for="website">Website</label>
        <input id="website" name="website" tabindex="-1" autocomplete="off">
    </div>
    <div class="feedback-form-grid">
        <div class="feedback-select-field">
            <label for="{{ $feedbackId }}-category">What is this about?</label>
            <select id="{{ $feedbackId }}-category" name="category" required>
                @foreach(\App\Models\Feedback::CATEGORIES as $value => $label)
                    <option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="feedback-rating-field">
            <span class="feedback-rating-label">Your rating <small>(optional)</small></span>
            <div class="feedback-stars" aria-label="Your rating">
                @foreach(range(5, 1) as $rating)
                    <input id="{{ $feedbackId }}-rating-{{ $rating }}" type="radio" name="rating" value="{{ $rating }}" @checked((string) old('rating') === (string) $rating)>
                    <label for="{{ $feedbackId }}-rating-{{ $rating }}" title="{{ $rating }} star{{ $rating === 1 ? '' : 's' }}"><i class="bi bi-star-fill"></i><span class="visually-hidden">{{ $rating }} star{{ $rating === 1 ? '' : 's' }}</span></label>
                @endforeach
            </div>
        </div>
    </div>
    <label for="{{ $feedbackId }}-message">Your feedback</label>
    <textarea id="{{ $feedbackId }}-message" name="message" rows="5" minlength="10" maxlength="3000" required placeholder="Tell us what worked well or what could be improved.">{{ old('message') }}</textarea>
    @guest
        <label for="{{ $feedbackId }}-email">Email <span>(optional, only if you want a reply)</span></label>
        <input id="{{ $feedbackId }}-email" type="email" name="email" value="{{ old('email') }}" autocomplete="email">
    @endguest
    @if($errors->any())
        <div class="feedback-error" role="alert">{{ $errors->first() }}</div>
    @endif
    <button class="home-button home-button-primary" type="submit"><i class="bi bi-send"></i> Send feedback</button>
</form>
