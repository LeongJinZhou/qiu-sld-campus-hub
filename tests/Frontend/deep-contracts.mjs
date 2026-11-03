import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import test from 'node:test';
import ts from 'typescript';

// Executes the application's actual scripts with small boundary stubs.
// This does not replace browser rendering, keyboard or screen-reader testing.
const calendarSource = fs.readFileSync('resources/js/Pages/Calendar/Index.vue', 'utf8')
    .match(/<script setup lang="ts">([\s\S]*?)<\/script>/)[1]
    .replace(/^import .*;\s*$/gm, '');
const calendarJs = ts.transpileModule(calendarSource, {
    compilerOptions: { target: ts.ScriptTarget.ES2022, module: ts.ModuleKind.None },
}).outputText;
const themeScript = fs.readFileSync('resources/views/app.blade.php', 'utf8')
    .match(/<script[^>]*>([\s\S]*?)<\/script>/)[1];
const layoutSource = fs.readFileSync('resources/js/Layouts/AppLayout.vue', 'utf8')
    .match(/<script setup lang="ts">([\s\S]*?)<\/script>/)[1]
    .replace(/^import .*;\s*$/gm, '');
const layoutJs = ts.transpileModule(layoutSource, {
    compilerOptions: { target: ts.ScriptTarget.ES2022, module: ts.ModuleKind.None },
}).outputText;

function calendar(events, timeZone) {
    const previous = process.env.TZ;
    process.env.TZ = timeZone;
    try {
        const context = vm.createContext({
            defineProps: () => ({ month: '2026-11', today: '2026-11-02', events, years: [2026], feedUrl: 'https://example.invalid/calendar.ics' }),
            computed: (getter) => ({ get value() { return getter(); } }),
            ref: (value) => ({ value }),
            watch: () => {}, useSwipe: () => ({ direction: { value: null } }),
        });
        vm.runInContext(`${calendarJs}\nglobalThis.inspectDays = () => days.value; globalThis.setFilter = (value) => { filter.value = value; };`, context);
        const inZone = (action) => {
            const active = process.env.TZ;
            process.env.TZ = timeZone;
            try { return action(); }
            finally {
                if (active === undefined) delete process.env.TZ;
                else process.env.TZ = active;
            }
        };
        return {
            inspectDays: () => inZone(() => context.inspectDays()),
            setFilter: (value) => context.setFilter(value),
        };
    } finally {
        if (previous === undefined) delete process.env.TZ;
        else process.env.TZ = previous;
    }
}

for (const theme of ['light', 'dark', 'system']) {
    for (const device of ['light', 'dark', null, 'unavailable']) {
        test(`account theme ${theme} survives device preference ${device}`, () => {
            const element = { dataset: { theme } };
            vm.runInNewContext(themeScript, {
                document: { documentElement: element },
                localStorage: { getItem() { if (device === 'unavailable') throw new Error('Storage disabled'); return device; } },
            });
            assert.equal(element.dataset.theme, theme);
        });
    }
}

for (const zone of ['Asia/Kuala_Lumpur', 'UTC', 'America/New_York', 'Europe/London', 'Pacific/Auckland']) {
    test(`midnight event ending is exclusive in calendar viewed from ${zone}`, () => {
        const c = calendar([{ slug: 'midnight', starts_at: '2026-11-02T10:00:00+08:00', ends_at: '2026-11-03T00:00:00+08:00' }], zone);
        const days = c.inspectDays();
        assert.equal(days.find((d) => d.key === '2026-11-02').events.length, 1);
        assert.equal(days.find((d) => d.key === '2026-11-03').events.length, 0);
    });
    test(`calendar filters remain independent in ${zone}`, () => {
        const c = calendar([
            { slug: 'mine', registered: true, followed: false, starts_at: '2026-11-02T10:00:00+08:00', ends_at: '2026-11-02T12:00:00+08:00' },
            { slug: 'followed', registered: false, followed: true, starts_at: '2026-11-02T10:00:00+08:00', ends_at: '2026-11-02T12:00:00+08:00' },
        ], zone);
        c.setFilter('mine');
        assert.equal(c.inspectDays().find((d) => d.key === '2026-11-02').events[0].slug, 'mine');
        c.setFilter('followed');
        assert.equal(c.inspectDays().find((d) => d.key === '2026-11-02').events[0].slug, 'followed');
        c.setFilter('all');
        assert.equal(c.inspectDays().find((d) => d.key === '2026-11-02').events.length, 2);
    });
}

for (const path of ['/meetings/1', '/meetings/100?tab=minutes']) {
    test(`meeting page ${path} does not falsely activate the Profile tab`, () => {
        const context = vm.createContext({
            defineProps: () => ({}), withDefaults: (props) => props,
            computed: (getter) => ({ get value() { return getter(); } }),
            usePage: () => ({ url: path, props: { auth: { user: { id: 1, name: 'Dummy student' } }, nav: {} } }),
            useIntervalFn: () => {},
        });
        vm.runInContext(`${layoutJs}\nglobalThis.active = items.value.filter(isActive).map((item) => item.label);`, context);
        assert.equal(context.active.includes('Profile'), false);
    });
}
