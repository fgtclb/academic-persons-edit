import { Bold, Italic } from "@ckeditor/ckeditor5-basic-styles";
import {
  ClassicEditor,
  type ClassicEditorInstance,
} from "@ckeditor/ckeditor5-editor-classic";
import { Essentials } from "@ckeditor/ckeditor5-essentials";
import { Link } from "@ckeditor/ckeditor5-link";
import { List } from "@ckeditor/ckeditor5-list";
import { Paragraph } from "@ckeditor/ckeditor5-paragraph";
import {
  hooks,
  showStatus,
} from "@fgtclb/academic-persons-edit/frontend/profile/common.js";
import {
  toEditingContext,
  type EditingTarget,
} from "@fgtclb/academic-persons-edit/frontend/profile/context.js";

const richTextFieldSelector = "[data-pe-rich-text]";
const richTextPreviewSelector = "[data-pe-rich-text-preview]";
const richTextPreviewContentSelector = "[data-pe-rich-text-preview-content]";
const richTextEditors = new WeakMap<HTMLTextAreaElement, ClassicEditorInstance>();
const richTextEditorPromises = new WeakMap<
  HTMLTextAreaElement,
  Promise<ClassicEditorInstance>
>();
const richTextInitialValues = new WeakMap<HTMLTextAreaElement, string>();
const richTextAcceptedValues = new WeakMap<HTMLTextAreaElement, string>();
const allowedRichTextPreviewTags = new Set([
  "a",
  "br",
  "em",
  "li",
  "ol",
  "p",
  "strong",
  "ul",
]);
const blockedRichTextPreviewTags = new Set([
  "iframe",
  "math",
  "object",
  "script",
  "style",
  "svg",
  "template",
]);
const allowedRichTextLinkSchemes = new Set(["http", "https", "mailto", "tel"]);
// The editor's own user interface follows the site language rather than staying
// English: the root element carries it, and CKEditor reads its translations from
// a global the language module fills. The module is imported once per language
// and a language it does not ship is simply not registered, which leaves the
// interface in the English default rather than failing the editor.
const loadedEditorLanguages = new Map<string, Promise<void>>();

const loadEditorLanguage = (language: string): Promise<void> => {
  const pending = loadedEditorLanguages.get(language);
  if (pending !== undefined) {
    return pending;
  }
  const request =
    language === "" || language === "en"
      ? Promise.resolve()
      : import(`@typo3/ckeditor5/translations/${language}.js`).then(
          (): void => undefined,
          (): void => undefined,
        );
  loadedEditorLanguages.set(language, request);
  return request;
};

const richTextEditorConfig: Record<string, unknown> = {
  licenseKey: "GPL",
  plugins: [Essentials, Paragraph, Bold, Italic, List, Link],
  toolbar: {
    items: [
      "undo",
      "redo",
      "|",
      "bold",
      "italic",
      "|",
      "bulletedList",
      "numberedList",
      "|",
      "link",
    ],
    shouldNotGroupWhenFull: false,
  },
  link: {
    allowedProtocols: ["http", "https", "mailto", "tel"],
    defaultProtocol: "https://",
  },
};

export const isRichTextField = (
  field: Element,
): field is HTMLTextAreaElement =>
  field instanceof HTMLTextAreaElement && field.matches(richTextFieldSelector);

export const getRichTextEditorValue = (
  field: HTMLTextAreaElement,
): string | null => {
  const editor = richTextEditors.get(field);
  if (editor === undefined) {
    return null;
  }
  const value = editor.getData();
  field.value = value;
  return value;
};

export const setRichTextEditorValue = (
  field: HTMLTextAreaElement,
  value: string,
): void => {
  const editor = richTextEditors.get(field);
  richTextAcceptedValues.set(field, value);
  field.value = value;
  if (editor !== undefined && editor.getData() !== value) {
    editor.setData(value);
  }
  updateRichTextCharacterCounter(
    field.getRootNode() as Document | ShadowRoot,
    field,
    value,
  );
};

export const getRichTextInitialValue = (
  field: HTMLTextAreaElement,
): string | undefined => richTextInitialValues.get(field);

export const getPlainText = (value: string): string => {
  const parsedDocument = new DOMParser().parseFromString(value, "text/html");
  return (parsedDocument.body.textContent ?? "")
    .replaceAll("\u00a0", " ")
    .replace(/\s+/g, " ")
    .trim();
};

const countRichTextCharacters = (value: string): number =>
  Array.from(getPlainText(value)).length;

const getRichTextCharacterLimit = (
  field: HTMLTextAreaElement,
): number => {
  const configuredLimit = hooks(field).peCharacterLimit?.trim() ?? "";
  if (!/^\d+$/.test(configuredLimit)) {
    return 0;
  }
  const limit = Number.parseInt(configuredLimit, 10);
  return Number.isSafeInteger(limit) && limit > 0 ? limit : 0;
};

const updateRichTextCharacterCounter = (
  root: ParentNode,
  field: HTMLTextAreaElement,
  value: string,
): void => {
  const limit = getRichTextCharacterLimit(field);
  if (limit === 0 || field.id === "") {
    return;
  }
  const counter = root.querySelector<HTMLElement>(
    `[data-pe-character-counter][data-pe-for="${CSS.escape(field.id)}"]`,
  );
  if (counter === null) {
    return;
  }
  const count = countRichTextCharacters(value);
  counter.textContent = `${count} / ${limit}`;
  counter.classList.toggle("text-danger", count > limit);
};

export const isAllowedRichTextLink = (value: string): boolean => {
  // A browser drops the C0 controls and the space characters inside a url
  // before it resolves the scheme, so `\u0001javascript:` is `javascript:`
  // to it. They are removed here for the same reason, and `trim()` alone
  // does not: it only takes them off the ends.
  const normalizedValue = Array.from(value)
    .filter((character): boolean => (character.codePointAt(0) ?? 0) > 0x20)
    .join("");
  if (normalizedValue === "" || normalizedValue.startsWith("//")) {
    return false;
  }
  const scheme = normalizedValue.match(/^([a-z][a-z\d+.-]*):/i)?.[1];
  return scheme === undefined || allowedRichTextLinkSchemes.has(scheme.toLowerCase());
};

export const parseRichTextPreview = (value: string): Document => {
  const parsedDocument = new DOMParser().parseFromString(value, "text/html");
  Array.from(parsedDocument.body.querySelectorAll("*")).forEach((element): void => {
    const tagName = element.tagName.toLowerCase();
    if (blockedRichTextPreviewTags.has(tagName)) {
      element.remove();
      return;
    }
    if (!allowedRichTextPreviewTags.has(tagName)) {
      element.replaceWith(...Array.from(element.childNodes));
      return;
    }
    Array.from(element.attributes).forEach((attribute): void => {
      const keepsHref =
        tagName === "a" &&
        attribute.name === "href" &&
        isAllowedRichTextLink(attribute.value);
      if (!keepsHref) {
        element.removeAttribute(attribute.name);
      }
    });
    if (tagName === "a") {
      element.setAttribute("rel", "noopener noreferrer");
    }
  });
  return parsedDocument;
};

export const renderRichTextPreview = (
  root: HTMLElement,
  field: HTMLTextAreaElement,
  value: unknown,
): void => {
  if (field.id === "") {
    return;
  }
  const preview = root.querySelector<HTMLElement>(
    `${richTextPreviewSelector}[data-pe-for="${CSS.escape(field.id)}"]`,
  );
  const content = preview?.querySelector<HTMLElement>(richTextPreviewContentSelector);
  if (preview === null || content === null || content === undefined) {
    return;
  }
  const normalizedValue = value === null || value === undefined ? "" : String(value);
  if (getPlainText(normalizedValue) === "") {
    const emptyLabel = document.createElement("span");
    emptyLabel.className = "text-body-secondary";
    emptyLabel.textContent = preview.dataset.emptyLabel ?? "";
    content.replaceChildren(emptyLabel);
    return;
  }
  const parsedDocument = parseRichTextPreview(normalizedValue);
  const fragment = document.createDocumentFragment();
  Array.from(parsedDocument.body.childNodes).forEach((node): void => {
    fragment.append(document.importNode(node, true));
  });
  content.replaceChildren(fragment);
};

/**
 * Destroys the editors of a subtree. The document editor is removed from the DOM
 * on every close, and a ClassicEditor that is dropped without being destroyed
 * keeps its window and document listeners until it is garbage collected.
 *
 * The scope is the subtree that is going away, never the plugin root: the
 * profile fields render a permanent `[data-pe-rich-text]` textarea each, and
 * destroying those takes a field editor the visitor still has open off screen.
 */
export const destroyRichTextEditors = async (scope: ParentNode): Promise<void> => {
  const fields = Array.from(
    scope.querySelectorAll<HTMLTextAreaElement>(richTextFieldSelector),
  );
  await Promise.all(
    fields.map(async (field): Promise<void> => {
      const pending = richTextEditorPromises.get(field);
      if (pending !== undefined) {
        await pending.catch((): null => null);
      }
      const editor = richTextEditors.get(field);
      richTextEditors.delete(field);
      richTextInitialValues.delete(field);
      richTextAcceptedValues.delete(field);
      await editor?.destroy();
    }),
  );
};

export const ensureRichTextEditor = (
  editingTarget: EditingTarget,
  field: HTMLTextAreaElement,
): Promise<ClassicEditorInstance | null> => {
  if (!isRichTextField(field) || field.disabled || field.readOnly) {
    return Promise.resolve(null);
  }
  const editor = richTextEditors.get(field);
  if (editor !== undefined) {
    return Promise.resolve(editor);
  }
  const pendingEditor = richTextEditorPromises.get(field);
  if (pendingEditor !== undefined) {
    return pendingEditor;
  }
  const context = toEditingContext(editingTarget);
  const root = context.root;
  const language = context.editorLanguage;
  const editorPromise = loadEditorLanguage(language)
    .then(
      (): Promise<ClassicEditorInstance> =>
        ClassicEditor.create(field, {
          ...richTextEditorConfig,
          language,
        }),
    )
    .then((createdEditor): ClassicEditorInstance => {
      richTextEditors.set(field, createdEditor);
      const initialValue = createdEditor.getData();
      richTextInitialValues.set(field, initialValue);
      richTextAcceptedValues.set(field, initialValue);
      updateRichTextCharacterCounter(root, field, initialValue);
      createdEditor.model.document.on("change:data", (): void => {
        const value = createdEditor.getData();
        const acceptedValue = richTextAcceptedValues.get(field) ?? "";
        const limit = getRichTextCharacterLimit(field);
        const count = countRichTextCharacters(value);
        const acceptedCount = countRichTextCharacters(acceptedValue);
        if (limit > 0 && count > limit && count >= acceptedCount) {
          if (value !== acceptedValue) {
            createdEditor.setData(acceptedValue);
          }
          field.value = acceptedValue;
          updateRichTextCharacterCounter(root, field, acceptedValue);
          return;
        }
        richTextAcceptedValues.set(field, value);
        field.value = value;
        updateRichTextCharacterCounter(root, field, value);
        field.dispatchEvent(new Event("input", { bubbles: true }));
      });
      return createdEditor;
    })
    .catch((error: unknown): never => {
      showStatus(context, "danger", context.messages.editorError ?? null);
      throw error;
    })
    .finally((): void => {
      richTextEditorPromises.delete(field);
    });
  richTextEditorPromises.set(field, editorPromise);
  return editorPromise;
};
