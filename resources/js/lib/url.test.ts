// Run: node --test resources/js/lib/url.test.ts (Node 22.18+ runs TypeScript directly).
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { withBase, withoutBase } from './url.ts';

test('withBase leaves paths alone at a domain root', () => {
    assert.equal(withBase('/feed', ''), '/feed');
    assert.equal(withBase('/', ''), '/');
});

test('withBase puts the subfolder in front of app paths', () => {
    assert.equal(withBase('/feed', '/campus-hub'), '/campus-hub/feed');
    assert.equal(withBase('/admin/meetings/export.xlsx?club=2', '/campus-hub'), '/campus-hub/admin/meetings/export.xlsx?club=2');
    assert.equal(withBase('/', '/campus-hub/'), '/campus-hub/');
});

test('withBase never doubles the subfolder', () => {
    assert.equal(withBase('/campus-hub/feed', '/campus-hub'), '/campus-hub/feed');
    assert.equal(withBase('/campus-hub', '/campus-hub'), '/campus-hub');
    assert.equal(withBase('/campus-hub?x=1', '/campus-hub'), '/campus-hub?x=1');
    // Storage::url() already returns based paths on a subfolder install.
    assert.equal(withBase('/campus-hub/storage/club-logos/a.svg', '/campus-hub'), '/campus-hub/storage/club-logos/a.svg');
    assert.equal(withBase(withBase('/clubs/gps', '/campus-hub'), '/campus-hub'), '/campus-hub/clubs/gps');
});

test('withBase does not mistake a look-alike path for the subfolder', () => {
    assert.equal(withBase('/campus-hubs', '/campus-hub'), '/campus-hub/campus-hubs');
});

test('withBase passes full URLs, protocol-relative URLs and relative paths through', () => {
    assert.equal(withBase('https://accounts.google.com/x', '/campus-hub'), 'https://accounts.google.com/x');
    assert.equal(withBase('//cdn.example.com/a.js', '/campus-hub'), '//cdn.example.com/a.js');
    assert.equal(withBase('#top', '/campus-hub'), '#top');
    assert.equal(withBase('mailto:sld@qiu.edu.my', '/campus-hub'), 'mailto:sld@qiu.edu.my');
    assert.equal(withBase('', '/campus-hub'), '');
});

test('withoutBase reads a mounted path as an app path', () => {
    assert.equal(withoutBase('/campus-hub/clubs/chess', '/campus-hub'), '/clubs/chess');
    assert.equal(withoutBase('/campus-hub', '/campus-hub'), '/');
    assert.equal(withoutBase('/campus-hub/', '/campus-hub'), '/');
    assert.equal(withoutBase('/campus-hub?tab=1', '/campus-hub'), '/?tab=1');
    assert.equal(withoutBase('/feed', ''), '/feed');
    assert.equal(withoutBase('/campus-hubs/x', '/campus-hub'), '/campus-hubs/x');
});
