@extends('layouts.app')
@section('content')
@php($editing=$letter->exists)
<a class="btn btn-link admin-back-link" href="{{ route('admin.letters.index') }}"><i class="bi bi-arrow-left"></i> Back to letters</a>
<div class="admin-page-header">
    <div><p class="eyebrow">{{ $editing ? 'EDIT LETTER' : 'NEW LETTER' }}</p><h1>{{ $editing ? $letter->title : 'Create something meaningful' }}</h1></div>
    @if($editing)<a class="btn btn-outline-secondary" target="_blank" href="{{ route('admin.letters.preview',$letter) }}"><i class="bi bi-eye"></i> Preview</a>@endif
</div>
<form method="post" enctype="multipart/form-data" action="{{ $editing ? route('admin.letters.update',$letter) : route('admin.letters.store') }}">@csrf @if($editing)@method('PUT')@endif
<div class="form-card"><div class="row g-3">
@php($categories=['confession','apology','birthday','anniversary','valentine','congratulations','thank-you','friendship','graduation','celebration','custom'])
<div class="col-md-6"><label class="form-label">Occasion</label><div class="input-group"><select name="category" id="category" class="form-select">@foreach($categories as $c)<option value="{{ $c }}" @selected(old('category',$letter->category)===$c)>{{ ucfirst($c) }}</option>@endforeach</select><button class="btn btn-outline-secondary" type="button" id="apply-preset"><i class="bi bi-magic"></i> Apply preset</button></div><div class="form-text">Presets update theme and response fields only when you click Apply.</div></div>
<div class="col-md-6"><label class="form-label">Title</label><input name="title" class="form-control" value="{{ old('title',$letter->title) }}" required></div>
<div class="col-md-6"><label class="form-label">Recipient name</label><input name="recipient_name" class="form-control" value="{{ old('recipient_name',$letter->recipient_name) }}" required></div>
<div class="col-md-6"><label class="form-label">Sender name</label><input name="sender_name" class="form-control" value="{{ old('sender_name',$letter->sender_name) }}" required></div>
<div class="col-12"><label class="form-label">Message</label><textarea name="body" class="form-control" rows="10" required>{{ old('body',$letter->body) }}</textarea></div>
<div class="col-md-8"><label class="form-label">Optional image</label><input type="file" name="image" class="form-control @error('image') is-invalid @enderror" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" data-image-upload><div class="form-text">JPG, PNG, or WebP up to 5 MB.</div>@error('image')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
<div class="col-md-4"><label class="form-label">Image description</label><input name="image_alt" class="form-control" value="{{ old('image_alt',$letter->image_alt) }}" placeholder="A shared memory"></div>
@if($letter->image_path)<div class="col-12"><div class="image-preview-row"><img src="{{ Storage::url($letter->image_path) }}" alt="{{ $letter->image_alt ?: 'Current letter image' }}"><label class="form-check"><input class="form-check-input" type="checkbox" name="remove_image" value="1"> Remove current image</label></div></div>@endif
<div class="col-md-3"><label class="form-label">Theme</label><select name="theme" class="form-select">@foreach(['warm','romantic','celebration','peaceful','friendship','midnight'] as $t)<option @selected(old('theme',$letter->theme)===$t)>{{ ucfirst($t) }}</option>@endforeach</select></div>
<div class="col-md-3"><label class="form-label">Decorations</label><select name="decoration_type" class="form-select">@foreach(['hearts','stars','balloons','confetti','flowers','sparkles','none'] as $d)<option value="{{ $d }}" @selected(old('decoration_type',$letter->decoration_type ?: 'hearts')===$d)>{{ ucfirst($d) }}</option>@endforeach</select></div>
<div class="col-md-3"><label class="form-label">Primary color</label><input type="color" name="primary_color" class="form-control form-control-color w-100" value="{{ old('primary_color',$letter->primary_color ?: '#d85b78') }}"></div>
<div class="col-md-3"><label class="form-label">Paper color</label><input type="color" name="secondary_color" class="form-control form-control-color w-100" value="{{ old('secondary_color',$letter->secondary_color ?: '#fff1e8') }}"></div>
<div class="col-12"><hr><h2 class="h5">Response</h2></div>
<div class="col-md-4"><label class="form-label">Mode</label><select name="response_mode" class="form-select">@foreach(['none','message','buttons','buttons_with_message'] as $m)<option value="{{ $m }}" @selected(old('response_mode',$letter->response_mode)===$m)>{{ str_replace('_',' ',ucfirst($m)) }}</option>@endforeach</select></div>
<div class="col-md-8"><label class="form-label">Question</label><input name="question_text" class="form-control" value="{{ old('question_text',$letter->question_text) }}" placeholder="Do you want to give us a chance?"></div>
<div class="col-md-4"><label class="form-label">Positive button</label><input name="positive_button_text" class="form-control" value="{{ old('positive_button_text',$letter->positive_button_text ?: 'Yes') }}"></div>
<div class="col-md-4"><label class="form-label">Negative button</label><input name="negative_button_text" class="form-control" value="{{ old('negative_button_text',$letter->negative_button_text ?: 'No') }}"></div>
<div class="col-md-4"><label class="form-label">Expires</label><input type="datetime-local" name="expires_at" class="form-control" value="{{ old('expires_at',$letter->expires_at?->format('Y-m-d\TH:i')) }}"></div>
<div class="col-12 form-check ms-2"><input type="hidden" name="allow_response" value="0"><input class="form-check-input" type="checkbox" name="allow_response" value="1" id="allow" @checked(old('allow_response',$letter->allow_response ?? true))><label class="form-check-label" for="allow">Allow a private response</label></div>
<div class="col-12 confession-options" data-confession-options>
<hr><h2 class="h5">Accepted confession extras</h2><p class="text-secondary">Shown only after the recipient sends a positive response to a confession.</p>
<div class="row g-3">
<div class="col-md-4"><label class="form-label">Started from date</label><input type="date" name="relationship_started_at" class="form-control" value="{{ old('relationship_started_at',$letter->relationship_started_at?->format('Y-m-d')) }}"></div>
<div class="col-md-4"><label class="form-label">Your profile image</label><input type="file" name="sender_profile" class="form-control @error('sender_profile') is-invalid @enderror" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" data-image-upload>@error('sender_profile')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
<div class="col-md-4"><label class="form-label">Recipient profile image</label><input type="file" name="recipient_profile" class="form-control @error('recipient_profile') is-invalid @enderror" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" data-image-upload>@error('recipient_profile')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
@if($letter->sender_profile_path)<div class="col-md-6"><div class="profile-preview"><img src="{{ Storage::url($letter->sender_profile_path) }}" alt="Current sender profile"><label class="form-check"><input class="form-check-input" type="checkbox" name="remove_sender_profile" value="1"> Remove your profile image</label></div></div>@endif
@if($letter->recipient_profile_path)<div class="col-md-6"><div class="profile-preview"><img src="{{ Storage::url($letter->recipient_profile_path) }}" alt="Current recipient profile"><label class="form-check"><input class="form-check-input" type="checkbox" name="remove_recipient_profile" value="1"> Remove recipient profile image</label></div></div>@endif
</div></div>
</div>@if($errors->any())<div class="alert alert-danger mt-3">{{ $errors->first() }}</div>@endif
<button class="btn btn-dearyou btn-wide mt-4"><i class="bi bi-check2-circle"></i> Save letter</button></div></form>
@if($editing)<section class="form-card mt-4 anniversary-options" data-anniversary-options>
<div class="d-flex justify-content-between align-items-center"><div><h2 class="h5 mb-1">Memory timeline</h2><p class="text-secondary mb-0">Add dated moments for anniversary letters.</p></div><span class="badge text-bg-light">{{ $letter->memories->count() }} memories</span></div>
<form method="post" enctype="multipart/form-data" action="{{ route('admin.memories.store',$letter) }}" class="row g-3 mt-2">@csrf
<div class="col-md-4"><label class="form-label">Memory title</label><input class="form-control" name="title" required maxlength="120"></div>
<div class="col-md-3"><label class="form-label">Date</label><input class="form-control" type="date" name="memory_date"></div>
<div class="col-md-5"><label class="form-label">Optional image</label><input class="form-control @error('memory_image') is-invalid @enderror" type="file" name="memory_image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" data-image-upload>@error('memory_image')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
<div class="col-12"><label class="form-label">Caption</label><textarea class="form-control" name="caption" rows="2" maxlength="1000"></textarea></div>
<div class="col-12"><button class="btn btn-dearyou"><i class="bi bi-plus-circle"></i> Add memory</button></div></form>
@foreach($letter->memories as $memory)
<div class="memory-editor mt-3">
<form method="post" enctype="multipart/form-data" action="{{ route('admin.memories.update',$memory) }}" class="row g-2 flex-grow-1">@csrf @method('PUT')
@if($memory->image_path)<div class="col-auto"><img class="memory-thumb" src="{{ Storage::url($memory->image_path) }}" alt=""></div>@endif
<div class="col-md"><input class="form-control" name="title" value="{{ $memory->title }}" required><textarea class="form-control mt-2" name="caption" rows="2">{{ $memory->caption }}</textarea></div>
<div class="col-md-3"><input class="form-control" type="date" name="memory_date" value="{{ $memory->memory_date?->format('Y-m-d') }}"><input class="form-control mt-2 @error('memory_image') is-invalid @enderror" type="file" name="memory_image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" data-image-upload>@error('memory_image')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @if($memory->image_path)<label class="form-check mt-2"><input class="form-check-input" type="checkbox" name="remove_memory_image" value="1"> Remove image</label>@endif</div>
<div class="col-auto"><button class="btn btn-sm btn-outline-primary"><i class="bi bi-check2"></i> Save</button></div></form>
<div class="memory-actions">
<form method="post" action="{{ route('admin.memories.move',[$memory,'up']) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-icon btn-outline-secondary" title="Move up" aria-label="Move memory up"><i class="bi bi-arrow-up"></i></button></form>
<form method="post" action="{{ route('admin.memories.move',[$memory,'down']) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-icon btn-outline-secondary" title="Move down" aria-label="Move memory down"><i class="bi bi-arrow-down"></i></button></form>
<form method="post" action="{{ route('admin.memories.destroy',$memory) }}" onsubmit="return confirm('Delete this memory?')">@csrf @method('DELETE')<button class="btn btn-sm btn-icon btn-outline-danger" title="Delete memory" aria-label="Delete memory"><i class="bi bi-trash"></i></button></form>
</div></div>
@endforeach
</section>@endif
@if($editing)<div class="form-card mt-4"><h2 class="h5">Publishing</h2>@if($letter->link)<div class="input-group my-3"><input id="share-link" readonly class="form-control" value="{{ route('letters.public',$letter->link->token) }}"><button class="btn btn-outline-secondary" type="button" data-copy="#share-link"><i class="bi bi-copy"></i> Copy</button></div>@endif<div class="d-flex flex-wrap gap-2">
<form method="post" action="{{ route('admin.letters.publish',$letter) }}">@csrf<button class="btn btn-success"><i class="bi bi-send-check"></i> Publish</button></form>
<form method="post" action="{{ route('admin.letters.unpublish',$letter) }}">@csrf<button class="btn btn-outline-secondary"><i class="bi bi-eye-slash"></i> Unpublish</button></form>
<form method="post" action="{{ route('admin.letters.regenerate',$letter) }}">@csrf<button class="btn btn-outline-primary"><i class="bi bi-arrow-repeat"></i> Regenerate link</button></form>
<form method="post" action="{{ route('admin.letters.disable',$letter) }}">@csrf<button class="btn btn-outline-warning"><i class="bi bi-link-45deg"></i> Disable link</button></form>
<form method="post" action="{{ route('admin.letters.destroy',$letter) }}" onsubmit="return confirm('Delete this letter?')">@csrf @method('DELETE')<button class="btn btn-outline-danger"><i class="bi bi-trash"></i> Delete</button></form></div></div>@endif
@endsection
