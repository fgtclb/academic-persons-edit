/**
 * Configures the CKEditor 4 instances of the frontend form.
 *
 * The editor itself is loaded from a content delivery network by the template,
 * with "defer", so it is not guaranteed to exist when this module runs. Polling
 * for it rather than depending on load order is what the original did and is
 * kept: the alternative would be a load event on a script tag this module does
 * not own.
 */
interface CkEditorWriter {
    setRules(tagName: string, rules: Record<string, boolean>): void;
}

interface CkEditorInstance {
    dataProcessor?: {
        writer?: CkEditorWriter;
    };
}

interface CkEditorStatic {
    replace(elementId: string, config: Record<string, unknown>): void;
}

/**
 * The block elements the stored markup is broken at.
 *
 * Without these rules CKEditor 4 writes a whole field as one line, so the
 * stored value of a list or of several paragraphs is unreadable in every place
 * that shows the raw markup - a database dump, a diff, a backend text field.
 * `breakAfterClose` puts each block on its own line; `indent` stays off so that
 * no leading whitespace enters the stored value.
 */
const blockElements = ['p', 'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'h5', 'h6'];

/**
 * The editor's own interface language: the primary subtag of the language the
 * document declares, so the editor follows the site rather than being fixed to
 * English. CKEditor names its translation bundles by that subtag, and falls
 * back to English itself for one it does not ship.
 */
const editorLanguage = (): string => {
    const declared = document.documentElement.lang.trim().toLowerCase();
    const primarySubtag = declared.split(/[-_]/)[0] ?? '';

    return primarySubtag === '' ? 'en' : primarySubtag;
};

const applyWriterRules = (instance: CkEditorInstance): void => {
    const writer = instance.dataProcessor?.writer;
    if (writer === undefined) {
        return;
    }
    blockElements.forEach((tagName): void => {
        writer.setRules(tagName, {
            breakAfterOpen: false,
            breakBeforeClose: false,
            breakAfterClose: true,
            indent: false,
        });
    });
};

const editorConfig: Record<string, unknown> = {
    language: editorLanguage(),
    height: 200,
    versionCheck: false,
    format_tags: 'p',
    toolbarGroups: [
        { name: 'basicstyles', groups: ['basicstyles'] },
        { name: 'paragraph', groups: ['list'] },
        // The "standard" build shipped by the CDN carries the link plugin
        // already, so the button is a matter of the toolbar group alone. The
        // group is the same one the backend preset for profile information uses
        // (`EXT:academic_persons/Configuration/CKEditor/LinkOnly.yaml`).
        { name: 'links', groups: ['links'] },
        { name: 'clipboard', groups: ['cleanup'] },
    ],
    // An anchor is a link into a page this editor does not control, and the
    // backend preset removes the button for the same reason.
    customConfig: '',
    removeButtons: [
        'Anchor',
        'Strike',
        'Subscript',
        'Superscript',
    ],
    // The advanced tab sets ids, styles and classes on the link. None of them
    // survive the rendering of the public views, so offering them would only
    // produce values that disappear.
    linkShowAdvancedTab: false,
    on: {
        instanceReady(event: { editor: CkEditorInstance }): void {
            applyWriterRules(event.editor);
        },
    },
};

const editor = (): CkEditorStatic | undefined =>
    (window as unknown as { CKEDITOR?: CkEditorStatic }).CKEDITOR;

const waitForEditor = window.setInterval((): void => {
    const ckeditor = editor();
    if (ckeditor === undefined) {
        return;
    }

    window.clearInterval(waitForEditor);

    document.querySelectorAll<HTMLTextAreaElement>('.rich-text').forEach((textarea): void => {
        const identifier = textarea.getAttribute('id');
        // The original passed the attribute through unchecked. CKEditor needs an
        // element id to replace, so a textarea without one was never going to
        // work; skipping it keeps the remaining fields from being lost with it.
        if (identifier !== null) {
            ckeditor.replace(identifier, editorConfig);
        }
    });
}, 100);

export {};
