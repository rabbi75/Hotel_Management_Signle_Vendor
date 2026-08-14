import type { BlockRenderer } from './support';

/*
|------------------------------------------------------------------------------
| Block renderer registry
|------------------------------------------------------------------------------
|
| Discovered from the filesystem rather than listed by hand, so adding a block
| type stays what it is meant to be: one PHP schema class and one component in
| this directory. A file named `hero.tsx` whose default export is a component
| becomes the renderer for the `hero` block.
|
*/

interface RendererModule {
    default?: unknown;
}

const modules = import.meta.glob<RendererModule>('./*.tsx', { eager: true });

function isRenderer(value: unknown): value is BlockRenderer {
    return typeof value === 'function' || (typeof value === 'object' && value !== null && '$$typeof' in value);
}

const renderers: Record<string, BlockRenderer> = {};

for (const [path, module] of Object.entries(modules)) {
    const type = path.replace(/^\.\//, '').replace(/\.tsx$/, '');
    const component = module.default;

    if (isRenderer(component)) {
        renderers[type] = component;
    }
}

export function blockRenderer(type: string): BlockRenderer | null {
    return renderers[type] ?? null;
}

export function registeredBlockTypes(): string[] {
    return Object.keys(renderers).sort();
}
