import {mount} from '@vue/test-utils';
import {describe, expect, it} from 'vitest';
import TrackLink from '../Components/TrackLink.vue';

describe('TrackLink', () => {
    it('opens the provider page in a new tab', () => {
        const link = mount(TrackLink, {props: {href: 'https://open.spotify.com/track/abc'}, slots: {default: 'Song'}}).get('a');

        expect(link.attributes()).toMatchObject({href: 'https://open.spotify.com/track/abc', target: '_blank', rel: 'noopener'});
        expect(link.text()).toBe('Song');
    });

    it('renders plain text without a provider url', () => {
        const wrapper = mount(TrackLink, {props: {href: null}, slots: {default: 'Song'}});

        expect(wrapper.find('a').exists()).toBe(false);
        expect(wrapper.text()).toBe('Song');
    });
});
