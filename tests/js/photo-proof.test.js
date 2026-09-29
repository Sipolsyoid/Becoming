import test from 'node:test';
import assert from 'node:assert/strict';
import photoProof from '../../resources/js/photo-proof.js';

const selection = (file) => ({ target: { files: file ? [file] : [], validityMessage: '', setCustomValidity(message) { this.validityMessage = message; } } });

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

test('submission enters waiting state and prevents duplicate submissions', () => {
    const state = photoProof();
    let blocked = 0;
    const event = { preventDefault() { blocked++; } };
    state.submit(event);
    assert.equal(state.busy, true);
    assert.equal(blocked, 0);
    state.submit(event);
    assert.equal(blocked, 1);
});
