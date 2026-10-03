/**
 * The source overview's place in the DOM: right under Bard's editor frame,
 * inside the field.
 *
 * Bard has no footer slot. Its extension callback hands over the Bard
 * component itself (`bard`), so a ProseMirror plugin view — created and
 * destroyed with every editor, including the one Bard builds anew when
 * it enters or leaves fullscreen — mounts the Vue component through
 * TipTap's own VueRenderer, the mechanism behind every Vue node view: it
 * renders with the app context and the provides of Bard's EditorContent
 * (globals, `__`, v-tooltip, the injected `bard`, the portal targets the
 * stack opens into). Its element is placed after `.bard-editor`, the
 * frame's direct child, and before core's footer toolbar (reading time).
 *
 * Mounted lazily, on the first footnote with a source: a field without
 * footnotes gets no element and no Vue render at all.
 */
import { hasFootnotes } from './footnotes.js';

const MAX_WAIT_FRAMES = 120;

export function sourcesPanelView({ view, editor, bard, VueRenderer, component }) {
    let renderer = null;
    let element = null;
    let frame = null;
    let waited = 0;
    let destroyed = false;

    // The editor frame of THIS Bard: a direct child of the component's own
    // container ref — never a nested Bard inside a set.
    const editorFrame = () => {
        const container = bard?.$refs?.container;

        return container ? [...container.children].find((child) => child.classList.contains('bard-editor')) ?? null : null;
    };

    const mount = () => {
        frame = null;

        if (destroyed || renderer) {
            return;
        }

        const anchor = editorFrame();

        // EditorContent hands the editor its app context a tick after it
        // mounts; until then a render would have no globals and no provides.
        if (!editor.appContext || !anchor) {
            if (waited++ < MAX_WAIT_FRAMES) {
                frame = requestAnimationFrame(mount);
            }

            return;
        }

        renderer = new VueRenderer(component, { editor, props: { editor } });
        element = renderer.el;
        anchor.after(element);
    };

    const sync = (doc) => {
        if (!renderer && !frame && hasFootnotes(doc)) {
            waited = 0;
            mount();
        }
    };

    // The plugin view is built while the EditorView is: `view`, not
    // `editor.view` (not assigned yet), holds the first document.
    sync(view.state.doc);

    return {
        update(current, previous) {
            if (current.state.doc !== previous.doc) {
                sync(current.state.doc);
            }
        },
        destroy() {
            destroyed = true;

            if (frame) {
                cancelAnimationFrame(frame);
            }

            // destroy() unmounts the component (its editor listener goes
            // with it); the element is ours to take out of Bard's frame.
            renderer?.destroy();
            element?.remove();
            renderer = null;
            element = null;
        },
    };
}
