@props(['habit', 'check' => null, 'allowUpload' => true])
<div x-data="photoProof(@js($check?->checkState()))" class="photo-check" @online.window="poll()" @visibilitychange.document="poll()">
    @csrf
    <div x-cloak x-show="check?.status" class="check-feedback" :class="{ 'check-success': done, 'check-attention': ['failed', 'rejected', 'needs_review'].includes(check?.status) }" role="status" aria-live="polite" aria-atomic="true">
        <div class="check-feedback-heading"><span class="check-indicator" :class="{ 'check-pulse': pending }" aria-hidden="true" x-text="done ? '✓' : pending ? '◌' : '↻'"></span><strong x-text="title"></strong></div>
        <p x-show="pending">You can check in another habit or leave this page. Your result will be here when you return.</p>
        <p x-show="done">Your effort counts. Progress has been updated.</p>
        <p x-show="check?.reason && !done" x-text="check?.reason"></p>
        <p x-show="check?.worker_note" x-text="check?.worker_note"></p>
        <button x-show="check?.can_cancel" type="button" class="check-retry" @click="cancel()" :disabled="busy">Cancel queued check</button>
        <p class="check-date" x-text="check?.date ? 'Check-in for ' + check.date : ''"></p>
        <button x-show="check?.can_retry" type="button" class="check-retry" @click="retry()" :disabled="busy">Retry saved photo</button>
        <p x-show="pending && check?.can_retry">Taking longer than expected. You can retry without uploading again.</p>
    </div>
    <p x-cloak x-show="connectionNote" x-text="connectionNote" role="status" class="quiet-note mt-3"></p>
    @if ($check && in_array($check->verification_status, ['queued', 'checking', 'failed']))
        <noscript><p class="quiet-note">Your photo is {{ $check->verification_status }}. {{ $check->verification_reason }} Reload this page to see the latest result.</p>
        @if ($check->verification_status === 'queued')<form method="POST" action="{{ route('checks.cancel', $check) }}">@csrf<button class="action-button">Cancel queued check</button></form>@endif
        @if ($check->checkState()['can_retry'])<form method="POST" action="{{ route('checks.retry', $check) }}">@csrf<button class="action-button">Retry saved photo</button></form>@endif</noscript>
    @endif
    @if ($allowUpload)
    <form method="POST" action="{{ route('habits.complete', $habit) }}" enctype="multipart/form-data"
        @submit="submit($event)" x-show="!pending && !done" :aria-busy="busy" class="proof-form">
        @csrf
        <label for="photo-{{ $habit->id }}" class="text-sm font-semibold">Photo for {{ $habit->name }}</label>
        <p class="text-xs text-slate-500">Show the activity clearly. A simple, well-lit photo works best.</p>
        <input id="photo-{{ $habit->id }}" type="file" name="photo" accept="image/jpeg,image/png,image/webp" required :disabled="busy"
            @change="select($event)" aria-describedby="photo-help-{{ $habit->id }}" class="proof-input">
        <p id="photo-help-{{ $habit->id }}" class="text-xs text-slate-500">JPG, PNG or WebP · up to 5 MB · stored privately</p>
        <div x-cloak x-show="preview" class="photo-preview-frame">
            <img :src="preview" alt="Your selected check-in photo" class="proof-preview">
            <p><span x-text="filename"></span><span x-text="fileSize"></span></p>
        </div>
        <button type="submit" class="action-button" :disabled="busy || invalidFile || !preview">
            <span x-show="!busy">Save &amp; check photo <span aria-hidden="true">↗</span></span>
            <span x-cloak x-show="busy">Saving your photo…</span>
        </button>
        <p x-cloak x-show="busy" role="status" class="text-xs text-slate-600">Just the upload — the photo check runs in the background.</p>
    </form>
    @endif
    <p x-cloak x-show="error" x-text="error" role="alert" class="error-message !mt-3"></p>
</div>
