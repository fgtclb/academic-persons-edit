/* Generated from Resources/Private/TypeScript — do not edit. */
import { Bold, Italic } from "@ckeditor/ckeditor5-basic-styles";
import {
  ClassicEditor
} from "@ckeditor/ckeditor5-editor-classic";
import { Essentials } from "@ckeditor/ckeditor5-essentials";
import { Link } from "@ckeditor/ckeditor5-link";
import { List } from "@ckeditor/ckeditor5-list";
import { Paragraph } from "@ckeditor/ckeditor5-paragraph";
import {
  hooks,
  showStatus
} from "@fgtclb/academic-persons-edit/frontend/profile/common.js";
import {
  toEditingContext
} from "@fgtclb/academic-persons-edit/frontend/profile/context.js";
const richTextFieldSelector = "[data-pe-rich-text]";
const richTextPreviewSelector = "[data-pe-rich-text-preview]";
const richTextPreviewContentSelector = "[data-pe-rich-text-preview-content]";
const richTextEditors = /* @__PURE__ */ new WeakMap();
const richTextEditorPromises = /* @__PURE__ */ new WeakMap();
const richTextInitialValues = /* @__PURE__ */ new WeakMap();
const richTextAcceptedValues = /* @__PURE__ */ new WeakMap();
const allowedRichTextPreviewTags = /* @__PURE__ */ new Set([
  "a",
  "br",
  "em",
  "li",
  "ol",
  "p",
  "strong",
  "ul"
]);
const blockedRichTextPreviewTags = /* @__PURE__ */ new Set([
  "iframe",
  "math",
  "object",
  "script",
  "style",
  "svg",
  "template"
]);
const allowedRichTextLinkSchemes = /* @__PURE__ */ new Set(["http", "https", "mailto", "tel"]);
const loadedEditorLanguages = /* @__PURE__ */ new Map();
const loadEditorLanguage = (language) => {
  const pending = loadedEditorLanguages.get(language);
  if (pending !== void 0) {
    return pending;
  }
  const request = language === "" || language === "en" ? Promise.resolve() : import(`@typo3/ckeditor5/translations/${language}.js`).then(
    () => void 0,
    () => void 0
  );
  loadedEditorLanguages.set(language, request);
  return request;
};
const richTextEditorConfig = {
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
      "link"
    ],
    shouldNotGroupWhenFull: false
  },
  link: {
    allowedProtocols: ["http", "https", "mailto", "tel"],
    defaultProtocol: "https://"
  }
};
const isRichTextField = (field) => field instanceof HTMLTextAreaElement && field.matches(richTextFieldSelector);
const getRichTextEditorValue = (field) => {
  const editor = richTextEditors.get(field);
  if (editor === void 0) {
    return null;
  }
  const value = editor.getData();
  field.value = value;
  return value;
};
const setRichTextEditorValue = (field, value) => {
  const editor = richTextEditors.get(field);
  richTextAcceptedValues.set(field, value);
  field.value = value;
  if (editor !== void 0 && editor.getData() !== value) {
    editor.setData(value);
  }
  updateRichTextCharacterCounter(
    field.getRootNode(),
    field,
    value
  );
};
const getRichTextInitialValue = (field) => richTextInitialValues.get(field);
const getPlainText = (value) => {
  const parsedDocument = new DOMParser().parseFromString(value, "text/html");
  return (parsedDocument.body.textContent ?? "").replaceAll("\xA0", " ").replace(/\s+/g, " ").trim();
};
const countRichTextCharacters = (value) => Array.from(getPlainText(value)).length;
const getRichTextCharacterLimit = (field) => {
  var _a;
  const configuredLimit = ((_a = hooks(field).peCharacterLimit) == null ? void 0 : _a.trim()) ?? "";
  if (!/^\d+$/.test(configuredLimit)) {
    return 0;
  }
  const limit = Number.parseInt(configuredLimit, 10);
  return Number.isSafeInteger(limit) && limit > 0 ? limit : 0;
};
const updateRichTextCharacterCounter = (root, field, value) => {
  const limit = getRichTextCharacterLimit(field);
  if (limit === 0 || field.id === "") {
    return;
  }
  const counter = root.querySelector(
    `[data-pe-character-counter][data-pe-for="${CSS.escape(field.id)}"]`
  );
  if (counter === null) {
    return;
  }
  const count = countRichTextCharacters(value);
  counter.textContent = `${count} / ${limit}`;
  counter.classList.toggle("text-danger", count > limit);
};
const isAllowedRichTextLink = (value) => {
  var _a;
  const normalizedValue = Array.from(value).filter((character) => (character.codePointAt(0) ?? 0) > 32).join("");
  if (normalizedValue === "" || normalizedValue.startsWith("//")) {
    return false;
  }
  const scheme = (_a = normalizedValue.match(/^([a-z][a-z\d+.-]*):/i)) == null ? void 0 : _a[1];
  return scheme === void 0 || allowedRichTextLinkSchemes.has(scheme.toLowerCase());
};
const parseRichTextPreview = (value) => {
  const parsedDocument = new DOMParser().parseFromString(value, "text/html");
  Array.from(parsedDocument.body.querySelectorAll("*")).forEach((element) => {
    const tagName = element.tagName.toLowerCase();
    if (blockedRichTextPreviewTags.has(tagName)) {
      element.remove();
      return;
    }
    if (!allowedRichTextPreviewTags.has(tagName)) {
      element.replaceWith(...Array.from(element.childNodes));
      return;
    }
    Array.from(element.attributes).forEach((attribute) => {
      const keepsHref = tagName === "a" && attribute.name === "href" && isAllowedRichTextLink(attribute.value);
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
const renderRichTextPreview = (root, field, value) => {
  if (field.id === "") {
    return;
  }
  const preview = root.querySelector(
    `${richTextPreviewSelector}[data-pe-for="${CSS.escape(field.id)}"]`
  );
  const content = preview == null ? void 0 : preview.querySelector(richTextPreviewContentSelector);
  if (preview === null || content === null || content === void 0) {
    return;
  }
  const normalizedValue = value === null || value === void 0 ? "" : String(value);
  if (getPlainText(normalizedValue) === "") {
    const emptyLabel = document.createElement("span");
    emptyLabel.className = "text-body-secondary";
    emptyLabel.textContent = preview.dataset.emptyLabel ?? "";
    content.replaceChildren(emptyLabel);
    return;
  }
  const parsedDocument = parseRichTextPreview(normalizedValue);
  const fragment = document.createDocumentFragment();
  Array.from(parsedDocument.body.childNodes).forEach((node) => {
    fragment.append(document.importNode(node, true));
  });
  content.replaceChildren(fragment);
};
const destroyRichTextEditors = async (scope) => {
  const fields = Array.from(
    scope.querySelectorAll(richTextFieldSelector)
  );
  await Promise.all(
    fields.map(async (field) => {
      const pending = richTextEditorPromises.get(field);
      if (pending !== void 0) {
        await pending.catch(() => null);
      }
      const editor = richTextEditors.get(field);
      richTextEditors.delete(field);
      richTextInitialValues.delete(field);
      richTextAcceptedValues.delete(field);
      await (editor == null ? void 0 : editor.destroy());
    })
  );
};
const ensureRichTextEditor = (editingTarget, field) => {
  if (!isRichTextField(field) || field.disabled || field.readOnly) {
    return Promise.resolve(null);
  }
  const editor = richTextEditors.get(field);
  if (editor !== void 0) {
    return Promise.resolve(editor);
  }
  const pendingEditor = richTextEditorPromises.get(field);
  if (pendingEditor !== void 0) {
    return pendingEditor;
  }
  const context = toEditingContext(editingTarget);
  const root = context.root;
  const language = context.editorLanguage;
  const editorPromise = loadEditorLanguage(language).then(
    () => ClassicEditor.create(field, {
      ...richTextEditorConfig,
      language
    })
  ).then((createdEditor) => {
    richTextEditors.set(field, createdEditor);
    const initialValue = createdEditor.getData();
    richTextInitialValues.set(field, initialValue);
    richTextAcceptedValues.set(field, initialValue);
    updateRichTextCharacterCounter(root, field, initialValue);
    createdEditor.model.document.on("change:data", () => {
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
  }).catch((error) => {
    showStatus(context, "danger", context.messages.editorError ?? null);
    throw error;
  }).finally(() => {
    richTextEditorPromises.delete(field);
  });
  richTextEditorPromises.set(field, editorPromise);
  return editorPromise;
};
export {
  destroyRichTextEditors,
  ensureRichTextEditor,
  getPlainText,
  getRichTextEditorValue,
  getRichTextInitialValue,
  isAllowedRichTextLink,
  isRichTextField,
  parseRichTextPreview,
  renderRichTextPreview,
  setRichTextEditorValue
};
