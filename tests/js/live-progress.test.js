import test from 'node:test';
import assert from 'node:assert/strict';
import liveProgress from '../../resources/js/live-progress.js';

test('a new local date reloads server-rendered cards instead of retaining yesterday completion', async () => {
    const originalFetch = globalThis.fetch;
    const originalWindow = globalThis.window;
    let reloaded = false;
    const state = liveProgress({ date: '2026-10-06', timezone: 'Europe/Riga', ids: [1], dayEndsInMs: 1000 }, '/');
    globalThis.window = { location: { reload() { reloaded = true; } } };
    globalThis.fetch = async () => new Response(JSON.stringify({ date: '2026-10-07', timezone: 'Europe/Riga', ids: [], dayEndsInMs: 86400000 }));
    try {
        await state.refresh();
        assert.equal(reloaded, true);
    } finally { state.destroy(); globalThis.fetch = originalFetch; globalThis.window = originalWindow; }
});

test('same-day refresh updates progress and offline refresh preserves it for retry', async () => {
    const originalFetch = globalThis.fetch;
    const state = liveProgress({ date: '2026-10-06', timezone: 'Europe/Riga', ids: [], dayEndsInMs: 1000 }, '/');
    try {
        globalThis.fetch = async () => new Response(JSON.stringify({ date: '2026-10-06', timezone: 'Europe/Riga', ids: [1], dayEndsInMs: 2000 }));
        await state.refresh();
        assert.deepEqual(state.stats.ids, [1]);
        globalThis.fetch = async () => { throw new Error('offline'); };
        await state.refresh();
        assert.equal(state.refreshError, true);
        assert.deepEqual(state.stats.ids, [1]);
    } finally { state.destroy(); globalThis.fetch = originalFetch; }
});

test('midnight timer uses the server duration and is cleared on teardown', () => {
    const originalTimeout = globalThis.setTimeout;
    const originalClear = globalThis.clearTimeout;
    let delay;
    let cleared;
    globalThis.setTimeout = (callback, ms) => { delay = ms; return 42; };
    globalThis.clearTimeout = (timer) => { cleared = timer; };
    try {
        const state = liveProgress({ dayEndsInMs: 90000000 }, '/');
        state.init();
        assert.equal(delay, 90000500);
        state.destroy();
        assert.equal(cleared, 42);
    } finally { globalThis.setTimeout = originalTimeout; globalThis.clearTimeout = originalClear; }
});
