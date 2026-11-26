import { raw } from './dom.js';

let parser = null;

/**
 * Renders admin-authored Markdown into a detached element.
 * Falls back to the original HTML when markdown-it is unavailable.
 */
export function markdown(text) {
    const source = text == null ? '' : String(text);
    if (typeof window.markdownit !== 'function') return raw(source);
    if (!parser) {
        parser = window.markdownit({
            html: true,
            linkify: true,
            typographer: true
        });
    }
    try {
        return raw(parser.render(source));
    } catch {
        return raw(source);
    }
}
