@extends(auth()->user()->isAdmin() ? 'layouts.app' : 'layouts.creator')
@section('content')
@php($editing=$letter->exists)
<a class="btn btn-link admin-back-link" href="{{ route(\App\Support\CreatorRoute::name('letters.index')) }}"><i class="bi bi-arrow-left"></i> Back to letters</a>
<div class="admin-page-header">
    <div><p class="eyebrow">{{ $editing ? 'EDIT LETTER' : 'NEW LETTER' }}</p><h1>{{ $editing ? $letter->title : 'Create something meaningful' }}</h1></div>
    @if($editing)<a class="btn btn-outline-secondary" target="_blank" href="{{ route(\App\Support\CreatorRoute::name('letters.preview'),$letter) }}"><i class="bi bi-eye"></i> Preview</a>@endif
</div>
@unless($editing)
<section class="form-card onboarding-card mb-4" aria-labelledby="letter-onboarding-title">
    <div>
        <p class="eyebrow">QUICK START</p>
        <h2 id="letter-onboarding-title">Your first letter in 4 simple steps</h2>
        <p>Start with a preset if you want help, then shape the words, design, response, and private link.</p>
    </div>
    <ol class="onboarding-steps">
        <li><span>1</span><strong>Pick a preset</strong><small>It can fill starter words, design, and reply settings.</small></li>
        <li><span>2</span><strong>Make it personal</strong><small>Autosave keeps text safe on this device.</small></li>
        <li><span>3</span><strong>Add extras</strong><small>Photos, music, memories, and reactions are optional.</small></li>
        <li><span>4</span><strong>Preview and publish</strong><small>Share only the private link.</small></li>
    </ol>
</section>
@endunless
<form method="post" enctype="multipart/form-data" action="{{ $editing ? route(\App\Support\CreatorRoute::name('letters.update'),$letter) : route(\App\Support\CreatorRoute::name('letters.store')) }}" data-letter-editor-form data-autosave-key="dearyou-letter-draft-{{ $editing ? $letter->id : 'new-'.auth()->id() }}">@csrf @if($editing)@method('PUT')@endif
<div class="form-card"><div class="row g-3">
<div class="col-md-6"><label class="form-label">Occasion</label><div class="input-group"><select name="category" id="category" class="form-select">@foreach($categories as $value => $label)<option value="{{ $value }}" @selected(old('category',$letter->category)===$value)>{{ $label }}</option>@endforeach</select><button class="btn btn-outline-secondary" type="button" id="apply-preset"><i class="bi bi-magic"></i> Apply preset</button></div><div class="form-text">Presets can fill starter words, design, and response fields when you click Apply.</div></div>
<div class="col-md-6"><label class="form-label">Title</label><input name="title" class="form-control" value="{{ old('title',$letter->title) }}" required></div>
<div class="col-md-6"><label class="form-label">Recipient name <span class="text-secondary">(optional)</span></label><input name="recipient_name" class="form-control" value="{{ old('recipient_name',$letter->recipient_name) }}" placeholder="Someone special" data-chapter-recipient-name></div>
<div class="col-md-6"><label class="form-label">Sender name <span class="text-secondary">(optional)</span></label><input name="sender_name" class="form-control" value="{{ old('sender_name',$letter->sender_name) }}" placeholder="Anonymous" data-chapter-sender-name></div>
<div class="col-12"><label class="form-label">Message</label><textarea name="body" class="form-control" rows="10" required>{{ old('body',$letter->body) }}</textarea></div>
<div class="col-md-8"><label class="form-label">Optional image or animation</label><input type="file" name="image" class="form-control @error('image') is-invalid @enderror" accept=".jpg,.jpeg,.png,.webp,.gif,.mp4,.webm,image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm" data-image-upload data-max-size-mb="{{ $creationSettings['letter_media_limit_mb'] }}"><div class="form-text">JPG, PNG, WebP, GIF, MP4, or WebM up to {{ $creationSettings['letter_media_limit_mb'] }} MB. Telegram GIFs usually save as MP4 or WebM and loop automatically.</div>@error('image')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
<div class="col-md-4"><label class="form-label">Image description</label><input name="image_alt" class="form-control" value="{{ old('image_alt',$letter->image_alt) }}" placeholder="A shared memory"></div>
@if($letter->image_path)<div class="col-12"><div class="image-preview-row" data-removable-image>@if(\App\Models\Letter::isVideoMediaPath($letter->image_path))<video src="{{ Storage::url($letter->image_path) }}" preload="metadata" muted loop playsinline data-autoplay-when-visible aria-label="{{ $letter->image_alt ?: 'Current letter video' }}"></video>@else<img src="{{ Storage::url($letter->image_path) }}" alt="{{ $letter->image_alt ?: 'Current letter image' }}" decoding="async">@endif<div class="image-preview-actions"><input type="checkbox" name="remove_image" value="1" hidden data-remove-image-input><button class="btn btn-sm btn-outline-danger" type="button" data-remove-image-button><i class="bi bi-trash"></i> <span>Delete picture</span></button><small class="text-secondary" data-remove-image-note>The picture will be deleted when you save the letter.</small></div></div></div>@endif
<div class="col-md-8"><label class="form-label">Optional background music</label><input type="file" name="audio" class="form-control @error('audio') is-invalid @enderror" accept=".mp3,.wav,.ogg,.m4a,.aac,audio/mpeg,audio/mp3,audio/wav,audio/ogg,audio/mp4,audio/aac" data-audio-upload data-max-size-mb="{{ $creationSettings['audio_limit_mb'] }}"><div class="form-text">MP3, WAV, OGG, M4A, or AAC up to {{ $creationSettings['audio_limit_mb'] }} MB. Music loops automatically when the visitor's browser allows it.</div>@error('audio')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
@if($letter->audio_path)<div class="col-md-4"><label class="form-label d-block">Current music</label><audio controls preload="metadata" src="{{ Storage::url($letter->audio_path) }}"></audio><label class="form-check mt-2"><input class="form-check-input" type="checkbox" name="remove_audio" value="1"> Remove music</label></div>@endif
@php($fontStyles = [
    'classic' => ['Classic', 'Georgia, serif'],
    'elegant' => ['Elegant', '"Lucida Calligraphy", "Monotype Corsiva", cursive'],
    'modern' => ['Modern', '"Segoe UI", Arial, sans-serif'],
    'friendly' => ['Friendly', '"Comic Sans MS", "Segoe Print", cursive'],
    'typewriter' => ['Typewriter', '"Courier New", Courier, monospace'],
    'handwritten' => ['Handwritten', '"Segoe Print", "Bradley Hand", cursive'],
    'formal' => ['Formal', '"Copperplate Gothic Light", Cambria, serif'],
])
<div class="col-12"><details class="editor-section" open><summary><span><i class="bi bi-palette"></i> Design</span><small>Envelope, theme, font, colors, and decorations</small></summary><div class="row g-3 pt-3">
<div class="col-12">
    <fieldset>
        <legend class="form-label">Envelope style</legend>
        <div class="envelope-style-picker">
            @foreach([
                'classic' => ['Classic', 'Traditional wax-seal envelope'],
                'rounded' => ['Soft rounded', 'Gentle curves and a soft seal'],
                'airmail' => ['Airmail', 'Playful red and blue border'],
                'vintage' => ['Vintage', 'Old-paper letter with a stamp'],
                'gift' => ['Gift ribbon', 'Wrapped like a small present'],
                'petal' => ['Petal fold', 'Soft layered envelope flaps'],
                'pocket' => ['Pocket note', 'A tucked note card style'],
                'ribbon' => ['Ribbon wrap', 'Elegant wrapped ribbon band'],
            ] as $value => [$label, $description])
                <label class="envelope-style-option">
                    <input type="radio" name="envelope_style" value="{{ $value }}" @checked(old('envelope_style',$letter->envelope_style ?: 'classic')===$value)>
                    <span class="envelope-style-swatch envelope-style-swatch-{{ $value }}" aria-hidden="true"><i></i></span>
                    <strong>{{ $label }}</strong>
                    <small>{{ $description }}</small>
                </label>
            @endforeach
        </div>
    </fieldset>
</div>
<div class="col-12">
    <fieldset>
        <legend class="form-label">Seal shape</legend>
        <div class="seal-style-picker">
            @foreach([
                'round' => ['Classic round', 'bi-heart-fill'],
                'heart' => ['Heart', 'bi-heart-fill'],
                'star' => ['Star', 'bi-star-fill'],
                'flower' => ['Flower', 'bi-flower1'],
                'diamond' => ['Diamond', 'bi-gem'],
            ] as $value => [$label, $icon])
                <label class="seal-style-option">
                    <input type="radio" name="seal_style" value="{{ $value }}" @checked(old('seal_style',$letter->seal_style ?: 'round')===$value)>
                    <span class="seal-style-preview seal-style-preview-{{ $value }}" aria-hidden="true"><i class="bi {{ $icon }}"></i></span>
                    <strong>{{ $label }}</strong>
                </label>
            @endforeach
        </div>
    </fieldset>
</div>
<div class="col-md-6 col-xl"><label class="form-label">Theme</label><select name="theme" class="form-select">@foreach(['warm','romantic','celebration','peaceful','friendship','midnight'] as $t)<option @selected(old('theme',$letter->theme)===$t)>{{ ucfirst($t) }}</option>@endforeach</select></div>
<div class="col-md-6 col-xl"><label class="form-label" for="font-style">Letter font</label><select name="font_style" id="font-style" class="form-select font-style-select" data-font-select>@foreach($fontStyles as $value => [$label, $stack])<option value="{{ $value }}" style="font-family:{{ $stack }}" data-font-stack="{{ $stack }}" @selected(old('font_style',$letter->font_style ?: 'classic')===$value)>{{ $label }} - Dear You</option>@endforeach</select></div>
<div class="col-md-6 col-xl"><label class="form-label">Decorations</label><select name="decoration_type" class="form-select">@foreach(['hearts','stars','balloons','confetti','flowers','sparkles','none'] as $d)<option value="{{ $d }}" @selected(old('decoration_type',$letter->decoration_type ?: 'hearts')===$d)>{{ ucfirst($d) }}</option>@endforeach</select></div>
<div class="col-md-6 col-xl"><label class="form-label">Primary color</label><input type="color" name="primary_color" class="form-control form-control-color w-100" value="{{ old('primary_color',$letter->primary_color ?: '#d85b78') }}" data-chapter-color></div>
<div class="col-md-6 col-xl"><label class="form-label">Paper color</label><input type="color" name="secondary_color" class="form-control form-control-color w-100" value="{{ old('secondary_color',$letter->secondary_color ?: '#fff1e8') }}"></div>
</div></details></div>
<div class="col-12"><details class="editor-section" open><summary><span><i class="bi bi-chat-heart"></i> Response</span><small>Question, choices, and expiration</small></summary><div class="row g-3 pt-3">
<div class="col-md-4"><label class="form-label">Mode</label><select name="response_mode" class="form-select">@foreach(['none','message','buttons','buttons_with_message','reactions'] as $m)<option value="{{ $m }}" @selected(old('response_mode',$letter->response_mode)===$m)>{{ str_replace('_',' ',ucfirst($m)) }}</option>@endforeach</select><div class="form-text">Reactions let recipients choose a feeling such as happy or thankful.</div></div>
<div class="col-md-8"><label class="form-label">Question</label><input name="question_text" class="form-control" value="{{ old('question_text',$letter->question_text) }}" placeholder="Do you want to give us a chance?"></div>
<div class="col-md-4"><label class="form-label">Positive button</label><input name="positive_button_text" class="form-control" value="{{ old('positive_button_text',$letter->positive_button_text ?: 'Yes') }}"></div>
<div class="col-md-4"><label class="form-label">Negative button</label><input name="negative_button_text" class="form-control" value="{{ old('negative_button_text',$letter->negative_button_text ?: 'No') }}"></div>
<div class="col-md-4">
    <label class="form-label">Private link duration</label>
    <select name="expiry_minutes" class="form-select">
        @foreach($expiryOptions as $minutes => $label)
            <option value="{{ $minutes }}" @selected((int) old('expiry_minutes', $letter->expiry_minutes) === $minutes)>{{ $label }}</option>
        @endforeach
    </select>
    <div class="form-text">The countdown starts each time you publish or regenerate the link.</div>
</div>
<div class="col-12 form-check ms-2"><input type="hidden" name="allow_response" value="0"><input class="form-check-input" type="checkbox" name="allow_response" value="1" id="allow" @checked(old('allow_response',$letter->allow_response ?? true))><label class="form-check-label" for="allow">Allow a private response</label></div>
</div></details></div>
<div class="col-12 confession-options" data-confession-options>
<details class="editor-section" open><summary><span><i class="bi bi-heart"></i> Accepted confession</span><small>Customize the positive response moment</small></summary>
<div class="row g-3">
<div class="col-12"><label class="form-label">Chapter heading</label><input name="chapter_heading" class="form-control" maxlength="150" value="{{ old('chapter_heading',$letter->chapter_heading ?: 'A beautiful new chapter begins.') }}" data-chapter-heading></div>
<div class="col-md-4"><label class="form-label">Started from date</label><input type="date" name="relationship_started_at" class="form-control" value="{{ old('relationship_started_at',$letter->relationship_started_at?->format('Y-m-d')) }}" data-chapter-date></div>
<div class="col-md-4"><label class="form-label">Your profile image</label><input type="file" name="sender_profile" class="form-control @error('sender_profile') is-invalid @enderror" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" data-image-upload data-max-size-mb="{{ $creationSettings['profile_image_limit_mb'] }}" data-chapter-sender-image><div class="form-text">Up to {{ $creationSettings['profile_image_limit_mb'] }} MB.</div>@error('sender_profile')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
<div class="col-md-4"><label class="form-label">Recipient profile image</label><input type="file" name="recipient_profile" class="form-control @error('recipient_profile') is-invalid @enderror" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" data-image-upload data-max-size-mb="{{ $creationSettings['profile_image_limit_mb'] }}" data-chapter-recipient-image><div class="form-text">Up to {{ $creationSettings['profile_image_limit_mb'] }} MB.</div>@error('recipient_profile')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
@if($letter->sender_profile_path)<div class="col-md-6"><div class="profile-preview"><img src="{{ Storage::url($letter->sender_profile_path) }}" alt="Current sender profile"><label class="form-check"><input class="form-check-input" type="checkbox" name="remove_sender_profile" value="1"> Remove your profile image</label></div></div>@endif
@if($letter->recipient_profile_path)<div class="col-md-6"><div class="profile-preview"><img src="{{ Storage::url($letter->recipient_profile_path) }}" alt="Current recipient profile"><label class="form-check"><input class="form-check-input" type="checkbox" name="remove_recipient_profile" value="1"> Remove recipient profile image</label></div></div>@endif
<div class="col-12">
<section class="chapter-preview" data-chapter-preview style="--chapter-accent:{{ old('primary_color',$letter->primary_color ?: '#d85b78') }}">
    <p class="chapter-preview-kicker">Positive response preview</p>
    <div class="chapter-preview-heart" aria-hidden="true">&#9829;</div>
    <h3 data-chapter-heading-preview>{{ old('chapter_heading',$letter->chapter_heading ?: 'A beautiful new chapter begins.') }}</h3>
    <div class="chapter-preview-profiles">
        <div>
            <span class="chapter-preview-avatar" data-chapter-sender-avatar>
                @if($letter->sender_profile_path)<img src="{{ Storage::url($letter->sender_profile_path) }}" alt="">@else<span data-chapter-sender-initial>{{ strtoupper(substr(old('sender_name',$letter->sender_name ?: 'Anonymous'),0,1)) }}</span>@endif
            </span>
            <strong data-chapter-sender-label>{{ old('sender_name',$letter->sender_name ?: 'Anonymous') }}</strong>
        </div>
        <span class="chapter-preview-connector" aria-hidden="true">&#9829;</span>
        <div>
            <span class="chapter-preview-avatar" data-chapter-recipient-avatar>
                @if($letter->recipient_profile_path)<img src="{{ Storage::url($letter->recipient_profile_path) }}" alt="">@else<span data-chapter-recipient-initial>{{ strtoupper(substr(old('recipient_name',$letter->recipient_name ?: 'Someone special'),0,1)) }}</span>@endif
            </span>
            <strong data-chapter-recipient-label>{{ old('recipient_name',$letter->recipient_name ?: 'Someone special') }}</strong>
        </div>
    </div>
    <p class="chapter-preview-date" data-chapter-date-label>
        @if(old('relationship_started_at',$letter->relationship_started_at?->format('Y-m-d')))
            Started from {{ \Carbon\Carbon::parse(old('relationship_started_at',$letter->relationship_started_at?->format('Y-m-d')))->format('F j, Y') }}
        @else
            Add a start date to show it here
        @endif
    </p>
</section>
</div>
</div></details></div>
</div>@if($errors->any())<div class="alert alert-danger mt-3">@if($errors->has('media'))<strong>Storage limit reached.</strong> @endif{{ $errors->first() }}</div>@endif
<div class="letter-save-row mt-4"><button class="btn btn-dearyou btn-wide"><i class="bi bi-check2-circle"></i> Save letter</button><span class="draft-autosave-status" data-draft-autosave-status>Draft autosave is on for this device.</span></div></div></form>
@if($editing)<section class="form-card mt-4 anniversary-options" data-anniversary-options>
<div class="d-flex justify-content-between align-items-center"><div><h2 class="h5 mb-1">Memory timeline</h2><p class="text-secondary mb-0">Add dated moments for anniversary letters.</p></div><span class="badge text-bg-light">{{ $letter->memories->count() }} memories</span></div>
<form method="post" enctype="multipart/form-data" action="{{ route(\App\Support\CreatorRoute::name('memories.store'),$letter) }}" class="row g-3 mt-2">@csrf
<div class="col-md-4"><label class="form-label">Memory title</label><input class="form-control" name="title" required maxlength="120"></div>
<div class="col-md-3"><label class="form-label">Date</label><input class="form-control" type="date" name="memory_date"></div>
<div class="col-md-5"><label class="form-label">Memory pictures or animations</label><input class="form-control @error('memory_images') is-invalid @enderror" type="file" name="memory_images[]" multiple accept=".jpg,.jpeg,.png,.webp,.gif,.mp4,.webm,image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm" data-image-upload data-max-size-mb="{{ $creationSettings['letter_media_limit_mb'] }}" data-max-files="{{ $creationSettings['memory_files_per_upload'] }}"><div class="form-text">Choose up to {{ $creationSettings['memory_files_per_upload'] }} JPG, PNG, WebP, GIF, MP4, or WebM files, {{ $creationSettings['letter_media_limit_mb'] }} MB each.</div>@error('memory_images')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @error('memory_images.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
<div class="col-12"><label class="form-label">Caption</label><textarea class="form-control" name="caption" rows="2" maxlength="1000"></textarea></div>
<div class="col-12"><button class="btn btn-dearyou"><i class="bi bi-plus-circle"></i> Add memory</button></div></form>
<div data-sortable-memories data-reorder-url="{{ route(\App\Support\CreatorRoute::name('memories.reorder'),$letter) }}">
@foreach($letter->memories as $memory)
<div class="memory-editor mt-3" draggable="true" data-memory-id="{{ $memory->id }}">
<button class="drag-handle" type="button" title="Drag to reorder" aria-label="Drag memory to reorder"><i class="bi bi-grip-vertical"></i></button>
<form method="post" enctype="multipart/form-data" action="{{ route(\App\Support\CreatorRoute::name('memories.update'),$memory) }}" class="row g-2 flex-grow-1">@csrf @method('PUT')
@if($memory->images->isNotEmpty())<div class="col-12"><div class="memory-image-editor" data-sortable-images data-reorder-url="{{ route(\App\Support\CreatorRoute::name('memory-images.reorder'),$memory) }}">@foreach($memory->images as $image)<label draggable="true" data-image-id="{{ $image->id }}">@if(\App\Models\Letter::isVideoMediaPath($image->image_path))<video class="memory-thumb" src="{{ Storage::url($image->image_path) }}" preload="metadata" muted loop playsinline data-autoplay-when-visible aria-label="Memory video"></video>@else<img class="memory-thumb" src="{{ Storage::url($image->image_path) }}" alt="" loading="lazy" decoding="async">@endif<span><i class="bi bi-grip-horizontal" aria-hidden="true"></i><input class="form-check-input" type="checkbox" name="remove_memory_images[]" value="{{ $image->id }}"> Remove</span></label>@endforeach</div></div>@endif
<div class="col-md"><input class="form-control" name="title" value="{{ $memory->title }}" required><textarea class="form-control mt-2" name="caption" rows="2">{{ $memory->caption }}</textarea></div>
<div class="col-md-4"><input class="form-control" type="date" name="memory_date" value="{{ $memory->memory_date?->format('Y-m-d') }}"><input class="form-control mt-2 @error('memory_images') is-invalid @enderror" type="file" name="memory_images[]" multiple accept=".jpg,.jpeg,.png,.webp,.gif,.mp4,.webm,image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm" data-image-upload data-max-size-mb="{{ $creationSettings['letter_media_limit_mb'] }}" data-max-files="{{ $creationSettings['memory_files_per_upload'] }}"><div class="form-text">Add up to {{ $creationSettings['memory_files_per_upload'] }} more files, {{ $creationSettings['letter_media_limit_mb'] }} MB each.</div>@error('memory_images')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @error('memory_images.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
<div class="col-auto"><button class="btn btn-sm btn-outline-primary"><i class="bi bi-check2"></i> Save</button></div></form>
<div class="memory-actions">
<form method="post" action="{{ route(\App\Support\CreatorRoute::name('memories.move'),[$memory,'up']) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-icon btn-outline-secondary" title="Move up" aria-label="Move memory up"><i class="bi bi-arrow-up"></i></button></form>
<form method="post" action="{{ route(\App\Support\CreatorRoute::name('memories.move'),[$memory,'down']) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-icon btn-outline-secondary" title="Move down" aria-label="Move memory down"><i class="bi bi-arrow-down"></i></button></form>
<form method="post" action="{{ route(\App\Support\CreatorRoute::name('memories.destroy'),$memory) }}" onsubmit="return confirm('Delete this memory?')">@csrf @method('DELETE')<button class="btn btn-sm btn-icon btn-outline-danger" title="Delete memory" aria-label="Delete memory"><i class="bi bi-trash"></i></button></form>
</div></div>
@endforeach
</div>
</section>@endif
@if($editing)<div class="form-card mt-4 publishing-card">
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div>
        <p class="eyebrow mb-1">PRIVATE LINK</p>
        <h2 class="h5 mb-1">Publishing</h2>
        <p class="text-secondary mb-0">Each publish creates a fresh private link valid for {{ $letter->expiryDurationLabel() }}.</p>
    </div>
    @php($linkState = $letter->linkState())
    <span class="link-state link-state-{{ $linkState }}"><i class="bi {{ $linkState === 'active' ? 'bi-broadcast-pin' : ($linkState === 'expired' ? 'bi-hourglass-bottom' : 'bi-link-45deg') }}"></i> {{ ucfirst($linkState) }}</span>
</div>
@if($letter->link)<div class="input-group my-3"><input id="share-link" readonly class="form-control" value="{{ route('letters.public',$letter->link->token) }}"><button class="btn btn-outline-secondary" type="button" data-copy="#share-link"><i class="bi bi-copy"></i> Copy</button></div>@endif
@if($letter->linkState() === 'active' && $letter->expires_at)
    <p class="link-countdown mb-3"><i class="bi bi-clock"></i> Link closes in <strong data-link-countdown="{{ $letter->expires_at->toIso8601String() }}">{{ $letter->expires_at->diffForHumans() }}</strong></p>
@endif
<div class="d-flex flex-wrap gap-2">
<form method="post" action="{{ route(\App\Support\CreatorRoute::name('letters.publish'),$letter) }}">@csrf<button class="btn btn-success"><i class="bi bi-send-check"></i> Publish</button></form>
<form method="post" action="{{ route(\App\Support\CreatorRoute::name('letters.unpublish'),$letter) }}">@csrf<button class="btn btn-outline-secondary"><i class="bi bi-eye-slash"></i> Unpublish</button></form>
<form method="post" action="{{ route(\App\Support\CreatorRoute::name('letters.regenerate'),$letter) }}">@csrf<button class="btn btn-outline-primary"><i class="bi bi-arrow-repeat"></i> Regenerate link</button></form>
<form method="post" action="{{ route(\App\Support\CreatorRoute::name('letters.disable'),$letter) }}">@csrf<button class="btn btn-outline-warning"><i class="bi bi-link-45deg"></i> Disable link</button></form>
<form method="post" action="{{ route(\App\Support\CreatorRoute::name('letters.destroy'),$letter) }}" onsubmit="return confirm('Delete this letter?')">@csrf @method('DELETE')<button class="btn btn-outline-danger"><i class="bi bi-trash"></i> Delete</button></form></div></div>@endif
@endsection
