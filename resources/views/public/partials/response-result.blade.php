<div class="response-thanks response-thanks-entering" data-response-result>
    @if($responseValue === 'positive' && $letter->category === 'confession')
        <p class="accepted-mark" aria-hidden="true">&#9829;</p>
        <h2>{{ $letter->chapter_heading ?: 'A beautiful new chapter begins.' }}</h2>
        <div class="confession-profiles">
            <div>@if($letter->sender_profile_path)<img src="{{ Storage::url($letter->sender_profile_path) }}" alt="{{ $letter->sender_name }}">@else<span class="profile-placeholder">{{ strtoupper(substr($letter->sender_name,0,1)) }}</span>@endif<strong>{{ $letter->sender_name }}</strong></div>
            <span class="profile-heart" aria-hidden="true">&#9829;</span>
            <div>@if($letter->recipient_profile_path)<img src="{{ Storage::url($letter->recipient_profile_path) }}" alt="{{ $letter->recipient_name }}">@else<span class="profile-placeholder">{{ strtoupper(substr($letter->recipient_name,0,1)) }}</span>@endif<strong>{{ $letter->recipient_name }}</strong></div>
        </div>
        @if($letter->relationship_started_at)<p class="started-date">Started from {{ $letter->relationship_started_at->format('F j, Y') }}</p>@endif
    @else
        <h2>{{ $responseValue === 'positive' ? 'Thank you for this warm answer.' : 'Thank you for answering honestly.' }}</h2>
        <p>Your response was sent privately and respectfully.</p>
    @endif
</div>
