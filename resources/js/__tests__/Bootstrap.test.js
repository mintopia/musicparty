import {describe, it, expect, beforeEach, vi} from 'vitest';

describe('bootstrap realtime', () => {
    beforeEach(() => {
        vi.resetModules();
        delete window.Echo;
    });

    it('does not throw and leaves a usable stub when the app key is missing', async () => {
        window.pusherConfig = {appKey: null, host: 'x', port: 443, scheme: 'https'};

        await expect(import('../bootstrap.js')).resolves.toBeDefined();

        expect(() => {
            window.Echo.channel('a').listen('E', () => {});
            window.Echo.private('b').listen('E', () => {});
            window.Echo.join('c').here(() => {}).joining(() => {});
            window.Echo.leave('a');
        }).not.toThrow();
    });

    it('does not throw when pusherConfig is absent', async () => {
        delete window.pusherConfig;
        await expect(import('../bootstrap.js')).resolves.toBeDefined();
        expect(window.Echo).toBeDefined();
    });

    it('mounts the app shell when the key is missing', async () => {
        window.pusherConfig = {appKey: ''};
        await import('../bootstrap.js');
        const {mount} = await import('@vue/test-utils');
        const wrapper = mount({template: '<div id="app">ok</div>'});
        expect(wrapper.text()).toBe('ok');
    });
});
