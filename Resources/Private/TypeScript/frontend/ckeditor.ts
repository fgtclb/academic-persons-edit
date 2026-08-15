/**
 * Configures the CKEditor 4 instances of the frontend form.
 *
 * The editor itself is loaded from a content delivery network by the template,
 * with "defer", so it is not guaranteed to exist when this module runs. Polling
 * for it rather than depending on load order is what the original did and is
 * kept: the alternative would be a load event on a script tag this module does
 * not own.
 */
interface CkEditorStatic {
    replace(elementId: string, config: Record<string, unknown>): void;
}

const editorConfig: Record<string, unknown> = {
    language: 'en',
    height: 200,
    versionCheck: false,
    format_tags: 'p',
    toolbarGroups: [
        { name: 'basicstyles', groups: ['basicstyles'] },
        { name: 'paragraph', groups: ['list'] },
        { name: 'clipboard', groups: ['cleanup'] },
    ],
    customConfig: '',
    removeButtons: [
        'Strike',
        'Subscript',
        'Superscript',
    ],
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
