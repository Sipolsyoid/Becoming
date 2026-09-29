@props(['habit'])
<form method="POST" action="{{ route('habits.complete', $habit) }}" enctype="multipart/form-data"
    x-data="photoProof" @submit="submit($event)" @pageshow.window="busy = false" :aria-busy="busy" class="proof-form">
    @csrf
    <label for="photo-{{ $habit->id }}" class="text-sm font-semibold">Photo for {{ $habit->name }}</label>
    <input id="photo-{{ $habit->id }}" type="file" name="photo" accept="image/jpeg,image/png,image/webp" required
        @change="select($event)" aria-describedby="photo-help-{{ $habit->id }}" class="proof-input">
    <p id="photo-help-{{ $habit->id }}" class="text-xs text-slate-500">JPG, PNG or WebP · up to 5 MB</p>
    <img x-cloak x-show="preview" :src="preview" alt="Selected photo preview" class="proof-preview">
    <p x-cloak x-show="error" x-text="error" role="alert" class="text-sm text-red-700"></p>
    <button type="submit" class="action-button" :disabled="busy || !!error">
        <span x-show="!busy">Check photo <span aria-hidden="true">↗</span></span>
        <span x-cloak x-show="busy">Checking your photo…</span>
    </button>
    <p x-cloak x-show="busy" role="status" class="text-xs text-slate-600">This may take up to 3 minutes. Keep this page open.</p>
</form>
