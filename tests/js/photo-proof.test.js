import test from 'node:test';
import assert from 'node:assert/strict';
import photoProof from '../../resources/js/photo-proof.js';

const selection = (file) => ({ target: { files: file ? [file] : [], validityMessage: '', setCustomValidity(message) { this.validityMessage = message; } } });

test('cancellation stops polling and a start-race leaves the check intact', async () => {
    const originalFetch = globalThis.fetch;
    const originalDocument = globalThis.document;
    const state = photoProof({ status: 'queued', can_cancel: true, cancel_url: '/cancel' });
    let dispatched = '';
    state.$dispatch = (name) => { dispatched = name; };
    globalThis.document = { querySelector: () => ({ content: 'csrf' }) };
    try {
        globalThis.fetch = async () => new Response(JSON.stringify({ errors: { photo: ['Checking has started.'] } }), { status: 422 });
        await state.cancel();
        assert.equal(state.pending, true);
        assert.equal(state.error, 'Checking has started.');
        globalThis.fetch = async () => new Response(JSON.stringify({ status: 'cancelled', can_cancel: false }));
        await state.cancel();
        assert.equal(state.pending, false);
        assert.equal(state.busy, false);
        assert.equal(dispatched, 'photo-checked');
        assert.equal(state.title, 'Queued check cancelled.');
    } finally { state.destroy(); globalThis.fetch = originalFetch; globalThis.document = originalDocument; }
});

test('valid photos get a preview and clearing selection removes it', () => {
    const state = photoProof();
    state.select(selection(new Blob(['photo'], { type: 'image/jpeg' })));
    assert.match(state.preview, /^blob:/);
    assert.equal(state.error, '');
    state.select(selection());
    assert.equal(state.preview, '');
    assert.equal(state.error, '');
});

test('unsupported and oversized files block submission; a valid replacement recovers', () => {
    const state = photoProof();
    for (const file of [new Blob(['pdf'], { type: 'application/pdf' }), new Blob([new Uint8Array(5242881)], { type: 'image/png' })]) {
        const event = selection(file);
        state.select(event);
        assert.notEqual(event.target.validityMessage, '');
        let blocked = false;
        state.submit({ preventDefault() { blocked = true; } });
        assert.equal(blocked, true);
        assert.equal(state.busy, false);
    }
    state.select(selection(new Blob(['valid'], { type: 'image/webp' })));
    assert.equal(state.error, '');
    assert.match(state.preview, /^blob:/);
    state.destroy();
});

test('upload saves without waiting for verification and polling updates the result', async () => {
    const originalFetch = globalThis.fetch;
    const originalDocument = globalThis.document;
    const state = photoProof();
    state.$el = { querySelector: () => ({ reset() {} }) };
    let dispatched = '';
    state.$dispatch = (name) => { dispatched = name; };
    globalThis.document = { hidden: false };
    try {
        globalThis.fetch = async () => new Response(JSON.stringify({ status: 'queued', status_url: '/checks/1' }), { status: 202 });
        await state.send('/upload', new FormData());
        assert.equal(state.busy, false);
        assert.equal(state.pending, true);
        let prevented = false;
        await state.submit({ preventDefault() { prevented = true; } });
        assert.equal(prevented, true);
        globalThis.fetch = async () => new Response(JSON.stringify({ status: 'approved' }));
        await state.poll();
        assert.equal(state.done, true);
        assert.equal(state.pending, false);
        assert.equal(dispatched, 'photo-checked');
    } finally { state.destroy(); globalThis.fetch = originalFetch; globalThis.document = originalDocument; }
});

test('upload errors recover controls and preserve the selected preview', async () => {
    const originalFetch = globalThis.fetch;
    const state = photoProof();
    state.select(selection(new Blob(['photo'], { type: 'image/jpeg' })));
    try {
        globalThis.fetch = async () => new Response(JSON.stringify({ errors: { photo: ['Please choose another photo.'] } }), { status: 422 });
        await state.send('/upload', new FormData());
        assert.equal(state.busy, false);
        assert.equal(state.error, 'Please choose another photo.');
        assert.match(state.preview, /^blob:/);
        globalThis.fetch = async () => { throw new Error('offline'); };
        await state.send('/upload', new FormData());
        assert.match(state.error, /may have saved/);
        assert.equal(state.busy, false);
    } finally { state.destroy(); globalThis.fetch = originalFetch; }
});

test('polling backs off on network errors and stops on expired sessions', async () => {
    const originalFetch = globalThis.fetch;
    const originalDocument = globalThis.document;
    const state = photoProof({ status: 'checking', status_url: '/checks/1' });
    globalThis.document = { hidden: false };
    try {
        globalThis.fetch = async () => { throw new Error('offline'); };
        await state.poll();
        assert.equal(state.pending, true);
        assert.match(state.connectionNote, /Reconnecting/);
        assert.equal(state.failures, 1);
        globalThis.fetch = async () => new Response('{}', { status: 401 });
        await state.poll();
        assert.equal(state.stopped, true);
        assert.match(state.connectionNote, /Sign in/);
    } finally { state.destroy(); globalThis.fetch = originalFetch; globalThis.document = originalDocument; }
});
