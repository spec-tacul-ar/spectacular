import assert from 'node:assert/strict';
import { mkdtemp, readFile, readdir, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
import test from 'node:test';
import { pathToFileURL } from 'node:url';
import { build } from 'esbuild';

const workspace = path.resolve(import.meta.dirname, '../..');
const temporary = await mkdtemp(path.join(tmpdir(), 'spectacular-live-updates-'));
const output = path.join(temporary, 'stores.mjs');

try {
    // Bundle the real application stores, including Vite's eager translation glob.
    await build({
        stdin: {
            contents: `export { default as Model } from './resources/js/app/stores/Model.js';
                export { default as pinia } from './resources/js/app/stores/index.js';
                export { setActivePinia } from 'pinia';`,
            resolveDir: workspace,
        },
        outfile: output,
        bundle: true,
        platform: 'node',
        format: 'esm',
        alias: { '@': path.join(workspace, 'resources/js/app') },
        plugins: [{
            name: 'translations',
            setup(builder) {
                builder.onLoad({ filter: /\/translations\.js$/ }, async ({ path: file }) => {
                    const directory = path.join(workspace, 'lang/spectacular');
                    const files = {};
                    for (const name of await readdir(directory)) {
                        if (name.endsWith('.json')) {
                            files[`/lang/spectacular/${name}`] = JSON.parse(await readFile(path.join(directory, name), 'utf8'));
                        }
                    }
                    const source = await readFile(file, 'utf8');
                    return { contents: source.replace(/import\.meta\.glob\([\s\S]*?\);/, `${JSON.stringify(files)};`), loader: 'js' };
                });
            },
        }],
    });
    const { Model, pinia, setActivePinia } = await import(pathToFileURL(output));
    setActivePinia(pinia);

    globalThis.localStorage = {
        getItem: () => null,
        setItem: () => {},
        removeItem: () => {},
    };

    function seed() {
        Model.resetRepositories();
        Model.repository('projects').save({ id: 'project', locale: 'en' });
        Model.repository('actors').save({ id: 'waiters', project_id: 'project', name: 'Waiters' });
        Model.repository('features').save({ id: 'orders', project_id: 'project', name: 'Orders' });
        Model.repository('requirements').save({ id: 'take-orders', feature_id: 'orders', name: 'take orders' });
        Model.repository('assignments').save({ id: 'assignment', actor_id: 'waiters', requirement_id: 'take-orders' });
        Model.repository('tasks').save({ id: 'task', requirement_id: 'take-orders', name: 'Build the form' });
        Model.repository('unknowns').save({ id: 'unknown', requirement_id: 'take-orders', name: 'Which tables?' });
    }

    await test('saved events insert new items, update existing items and move between parents', () => {
        seed();
        const project = Model.repository('projects').find('project');
        const requirement = Model.repository('requirements').find('take-orders');
        assert.equal(project.features.count(), 1);
        assert.equal(requirement.title, 'Waiters can take orders');
        Model.repository('features').save({ id: 'menu', project_id: 'project', name: 'Menu' });
        assert.equal(project.features.count(), 2);
        Model.repository('requirements').save({ id: 'take-orders', feature_id: 'menu' });
        assert.equal(Model.repository('features').find('orders').requirements.count(), 0);
        assert.equal(Model.repository('features').find('menu').requirements.count(), 1);
        Model.repository('requirements').save({ id: 'take-orders', name: 'review orders' });
        assert.equal(Model.repository('requirements').collection.count(), 1);
        assert.equal(requirement.title, 'Waiters can review orders');
    });

    await test('individual removal events update the visible specification', () => {
        seed();
        const requirement = Model.repository('requirements').find('take-orders');
        Model.repository('tasks').delete('task');
        Model.repository('unknowns').delete('unknown');
        Model.repository('assignments').delete('assignment');
        assert.equal(requirement.tasks.count(), 0);
        assert.equal(requirement.unknowns.count(), 0);
        assert.equal(requirement.title, 'Users can take orders');
        Model.repository('actors').delete('waiters');
        assert.equal(Model.repository('projects').find('project').actors.count(), 0);
    });

    await test('parent removal clears cached descendants and tolerates later child events', () => {
        seed();
        Model.repository('features').delete('orders');
        for (const repository of ['features', 'requirements', 'assignments', 'tasks', 'unknowns']) {
            assert.equal(Model.repository(repository).collection.count(), 0);
        }
        Model.repository('requirements').delete('take-orders');
        Model.repository('assignments').delete('assignment');
        Model.repository('tasks').delete('task');
        Model.repository('unknowns').delete('unknown');
        assert.equal(Model.repository('projects').find('project').features.count(), 0);
    });
} finally {
    await rm(temporary, { recursive: true, force: true });
}
