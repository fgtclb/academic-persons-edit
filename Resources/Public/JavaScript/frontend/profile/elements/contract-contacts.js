/* Generated from Resources/Private/TypeScript — do not edit. */
import {
  hooks,
  setDisabled,
  setExpanded,
  setHiddenState
} from "@fgtclb/academic-persons-edit/frontend/profile/common.js";
import {
  ownerEditingContext
} from "@fgtclb/academic-persons-edit/frontend/profile/context.js";
import { ProfileEditingElement } from "@fgtclb/academic-persons-edit/frontend/profile/elements/base.js";
import {
  applyFieldErrors,
  cloneDisplayRow,
  cloneField,
  fieldControlId
} from "@fgtclb/academic-persons-edit/frontend/profile/elements/field-clone.js";
import { profileContractContactsElementName } from "@fgtclb/academic-persons-edit/frontend/profile/elements/names.js";
import { fillPrototype } from "@fgtclb/academic-persons-edit/frontend/profile/prototypes.js";
const contractContactFieldIdPrefix = "profile-editing-contract-contact-field";
const emptyContractContactEditor = Object.freeze({
  deleteConfirmation: "",
  error: "",
  errors: {},
  fields: [],
  mode: "view",
  open: false,
  pending: false,
  record: null,
  section: "",
  title: "",
  values: {}
});
const editorId = (section) => `profile-editing-contract-contact-editor-${typeof section === "string" ? section : section.identifier}`;
const editorShape = (editor) => [
  editor.open,
  editor.mode,
  editor.section,
  editor.record,
  editor.title,
  editor.deleteConfirmation
].join("|");
const rowShape = (item) => JSON.stringify([item.uid, item.hidden, item.summary]);
const reconcile = (parent, keys, keyOf, create, update) => {
  const existing = /* @__PURE__ */ new Map();
  Array.from(parent.children).forEach((child) => {
    if (child instanceof HTMLElement) {
      const key = keyOf(child);
      if (key !== void 0) {
        existing.set(key, child);
      }
    }
  });
  let previous = null;
  keys.forEach((key, index) => {
    const element = update(existing.get(key) ?? create(key), key, index);
    existing.delete(key);
    const expected = previous === null ? parent.firstElementChild : previous.nextElementSibling;
    if (expected !== element) {
      parent.insertBefore(element, expected);
    }
    previous = element;
  });
  existing.forEach((element) => {
    element.remove();
  });
};
class ProfileContractContactsElement extends ProfileEditingElement {
  #context = null;
  /** The contract of `Templates/Profile/Index.html`, assigned by the owner. */
  get context() {
    return this.#context;
  }
  set context(value) {
    const previous = this.#context;
    this.#context = value;
    this.requestUpdate("context", previous);
  }
  #contract = null;
  /** The uid of the contract these contacts belong to. */
  get contract() {
    return this.#contract;
  }
  set contract(value) {
    const previous = this.#contract;
    this.#contract = value;
    this.requestUpdate("contract", previous);
  }
  #editor = emptyContractContactEditor;
  /** Which contact is being edited, and with which values and refusals. */
  get editor() {
    return this.#editor;
  }
  set editor(value) {
    const previous = this.#editor;
    this.#editor = value;
    this.requestUpdate("editor", previous);
  }
  #emptyMessage = "";
  /** The message a section with no contacts shows. */
  get emptyMessage() {
    return this.#emptyMessage;
  }
  set emptyMessage(value) {
    const previous = this.#emptyMessage;
    this.#emptyMessage = value;
    this.requestUpdate("emptyMessage", previous);
  }
  #sections = [];
  /** The sections and their contacts, as the last response answered them. */
  get sections() {
    return this.#sections;
  }
  set sections(value) {
    const previous = this.#sections;
    this.#sections = value;
    this.requestUpdate("sections", previous);
  }
  /**
   * The shape a row was built from, keyed by section *and* record: the three
   * contact kinds are three tables, and a uid that exists in two of them
   * would otherwise make each section rebuild the other's row.
   */
  #rowShapes = /* @__PURE__ */ new Map();
  #panel = null;
  #panelShape = "";
  #panelFields = null;
  connectedCallback() {
    this.context ??= ownerEditingContext(this);
    super.connectedCallback();
  }
  updated() {
    var _a;
    const source = (_a = this.context) == null ? void 0 : _a.root;
    if (source === void 0) {
      return;
    }
    this.#syncSections(source);
    this.#syncEditor(source);
  }
  /** The sections and their rows, reconciled rather than replaced. */
  #syncSections(source) {
    reconcile(
      this,
      this.sections.map((section) => section.identifier),
      (element) => hooks(element).peContractContactSection,
      (key) => {
        var _a;
        return fillPrototype(source, "contact-section", {
          addDisabled: void 0,
          addEditorHidden: true,
          addExpanded: "false",
          editorId: editorId(key),
          emptyHidden: true,
          emptyMessage: this.emptyMessage,
          identifier: key,
          label: ((_a = this.#section(key)) == null ? void 0 : _a.label) ?? "",
          rowsHidden: true
        }).element;
      },
      (element, key) => {
        const section = this.#section(key);
        if (section !== void 0) {
          this.#syncSection(source, element, section);
        }
        return element;
      }
    );
  }
  #syncSection(source, element, section) {
    const pending = this.editor.pending;
    const add = element.querySelector(
      "[data-pe-contract-contact-add]"
    );
    if (add !== null) {
      add.disabled = pending;
      add.setAttribute(
        "aria-expanded",
        this.#isOpen("add", section.identifier, null) ? "true" : "false"
      );
    }
    const rows = element.querySelector('[data-pe-list="rows"]');
    const empty = element.querySelector("p[role='status']");
    if (empty !== null) {
      empty.textContent = this.emptyMessage;
      empty.hidden = section.items.length > 0;
    }
    if (rows === null) {
      return;
    }
    rows.hidden = section.items.length === 0;
    reconcile(
      rows,
      section.items.map((item) => String(item.uid)),
      (row) => hooks(row).peContractContactItem,
      (key) => this.#createRow(source, section, key),
      (row, key, index) => {
        const item = section.items[index];
        if (item === void 0) {
          return row;
        }
        if (this.#rowShapes.get(`${section.identifier}:${key}`) !== rowShape(item)) {
          const replacement = this.#createRow(source, section, key);
          row.replaceWith(replacement);
          return replacement;
        }
        this.#syncRowControls(row, section, index);
        return row;
      }
    );
  }
  /** One contact: its summary columns, its controls and its editor slot. */
  #createRow(source, section, key) {
    const index = section.items.findIndex(
      (candidate) => String(candidate.uid) === key
    );
    const item = section.items[index];
    if (item === void 0) {
      throw new Error(`The contact "${key}" is not in its section any more.`);
    }
    const clone = fillPrototype(source, "contact-row", {
      deleteExpanded: "false",
      editExpanded: "false",
      editorHidden: true,
      editorId: editorId(section),
      // A truthy value, because the same key marks the row for the stylesheet
      // through `data-pe-attr` and shows the "hidden" badge through
      // `data-pe-when` - and an empty string is falsy to the latter.
      hidden: item.hidden ? "hidden" : void 0,
      uid: item.uid,
      viewExpanded: "false"
    });
    clone.list(
      "summary",
      item.summary.map(
        (summary) => fillPrototype(source, "contact-summary-cell", {
          hasValue: summary.value !== "",
          isEmpty: summary.value === "",
          label: summary.label,
          value: summary.value
        }).fragment
      )
    );
    this.#rowShapes.set(`${section.identifier}:${key}`, rowShape(item));
    this.#syncRowControls(clone.element, section, index);
    return clone.element;
  }
  /**
   * The state of the six controls of one row.
   *
   * The two arrows are disabled at their end of the list, and the position
   * they read is the one in the list this element is rendering - never a flag
   * the server computed, which a create, a delete or a sort in the browser
   * would leave stale one interaction later.
   */
  #syncRowControls(row, section, index) {
    const pending = this.editor.pending;
    const uid = Number(hooks(row).peContractContactItem ?? "0");
    const expanded = (mode) => this.#isOpen(mode, section.identifier, uid);
    row.querySelectorAll(
      "[data-pe-contract-contact-view], [data-pe-contract-contact-edit], [data-pe-contract-contact-delete]"
    ).forEach((button) => {
      setDisabled(button, pending);
    });
    [
      ["view", "[data-pe-contract-contact-view]"],
      ["edit", "[data-pe-contract-contact-edit]"],
      ["delete", "[data-pe-contract-contact-delete]"]
    ].forEach(([mode, selector]) => {
      const control = row.querySelector(selector);
      if (control !== null) {
        setExpanded(control, expanded(mode));
      }
    });
    const item = section.items[index];
    const visibility = row.querySelector(
      "[data-pe-contract-contact-hide]"
    );
    if (visibility !== null && item !== void 0) {
      setDisabled(visibility, pending);
      setHiddenState(visibility, item.hidden);
    }
    row.querySelectorAll("[data-pe-contract-contact-sort]").forEach((button) => {
      const atEnd = hooks(button).peContractContactSort === "up" ? index === 0 : index === section.items.length - 1;
      setDisabled(button, pending || atEnd);
    });
  }
  /**
   * Puts the open editor where it belongs, and takes it away again.
   *
   * Separate from the list on purpose: opening and closing an editor must not
   * touch the row it opens from, or the control the visitor pressed would be
   * replaced under the caret that is still on it.
   */
  #syncEditor(source) {
    var _a, _b;
    const editor = this.editor;
    const target = this.#editorTarget();
    if (!editor.open || target === null) {
      (_a = this.#panel) == null ? void 0 : _a.remove();
      this.#panel = null;
      this.#panelShape = "";
      this.#panelFields = null;
      this.querySelectorAll(
        '[data-pe-list="editor"], [data-pe-list="addEditor"]'
      ).forEach((container) => {
        container.hidden = true;
      });
      return;
    }
    if (this.#panel === null || this.#panelShape !== editorShape(editor) || this.#panelFields !== editor.fields || this.#panel.parentElement !== target) {
      (_b = this.#panel) == null ? void 0 : _b.remove();
      this.#panel = this.#createEditor(source);
      this.#panelShape = editorShape(editor);
      this.#panelFields = editor.fields;
      target.replaceChildren(this.#panel);
    }
    this.querySelectorAll(
      '[data-pe-list="editor"], [data-pe-list="addEditor"]'
    ).forEach((container) => {
      container.hidden = container !== target;
    });
    this.#patchEditor();
  }
  /** The container the open editor belongs in, or `null` for none. */
  #editorTarget() {
    const editor = this.editor;
    if (!editor.open) {
      return null;
    }
    const section = this.querySelector(
      `[data-pe-contract-contact-section="${CSS.escape(editor.section)}"]`
    );
    if (section === null) {
      return null;
    }
    if (editor.mode === "add") {
      return section.querySelector('[data-pe-list="addEditor"]');
    }
    const row = section.querySelector(
      `[data-pe-contract-contact-item="${CSS.escape(String(editor.record ?? ""))}"]`
    );
    return (row == null ? void 0 : row.querySelector('[data-pe-list="editor"]')) ?? null;
  }
  /** The editor itself, identical wherever the list puts it. */
  #createEditor(source) {
    const editor = this.editor;
    const editing = editor.mode === "add" || editor.mode === "edit";
    const clone = fillPrototype(source, "contact-editor-panel", {
      busy: editor.pending ? "true" : "false",
      deleteConfirmation: editor.deleteConfirmation,
      editorId: `profile-editing-contract-contact-editor-${editor.section}`,
      error: editor.error,
      errorHidden: editor.error === "" ? true : void 0,
      isDelete: editor.mode === "delete",
      isSave: editor.mode !== "delete",
      pending: editor.pending ? true : void 0,
      showActions: editor.mode !== "view",
      showClose: editor.mode !== "delete",
      showDisplay: editor.mode === "view",
      showFields: editing,
      spinnerHidden: editor.pending ? void 0 : true,
      title: editor.title
    });
    clone.list(
      "displayRows",
      editor.mode === "view" ? editor.fields.map(
        (field) => {
          var _a;
          return cloneDisplayRow(
            source,
            field,
            ((_a = this.context) == null ? void 0 : _a.labels.documentEmpty) ?? ""
          );
        }
      ) : []
    );
    clone.list(
      "fields",
      editing ? editor.fields.map(
        (field, index) => cloneField({
          error: editor.errors[field.name],
          field,
          hook: "contactField",
          idPrefix: contractContactFieldIdPrefix,
          index,
          pending: editor.pending,
          source,
          value: editor.values[field.name]
        })
      ) : []
    );
    return clone.element;
  }
  /**
   * Writes the pending state and the messages onto the panel that is there.
   *
   * A refusal must not replace the controls the visitor typed in, which is
   * what a rebuild of the panel would do.
   */
  #patchEditor() {
    const panel = this.#panel;
    const editor = this.editor;
    if (panel === null) {
      return;
    }
    panel.setAttribute("aria-busy", editor.pending ? "true" : "false");
    const alert = panel.querySelector(".alert[role='alert']");
    if (alert !== null) {
      alert.textContent = editor.error;
      alert.hidden = editor.error === "";
    }
    applyFieldErrors(
      panel,
      editor.fields,
      contractContactFieldIdPrefix,
      editor.errors
    );
    editor.fields.forEach((field, index) => {
      const control = panel.querySelector(
        `#${CSS.escape(fieldControlId(contractContactFieldIdPrefix, index, field))}`
      );
      if (control instanceof HTMLInputElement || control instanceof HTMLSelectElement || control instanceof HTMLTextAreaElement) {
        control.disabled = field.disabled || editor.pending;
      }
    });
    panel.querySelectorAll(
      "[data-pe-contract-contact-cancel], [data-pe-contract-contact-save]"
    ).forEach((button) => {
      button.disabled = editor.pending;
    });
    panel.querySelectorAll(
      "[data-pe-contract-contact-save] .spinner-border"
    ).forEach((spinner) => {
      spinner.hidden = !editor.pending;
    });
  }
  #section(identifier) {
    return this.sections.find(
      (section) => section.identifier === identifier
    );
  }
  /** Whether the open editor is the one this control would open. */
  #isOpen(mode, section, record) {
    return this.editor.open && this.editor.mode === mode && this.editor.section === section && this.editor.record === record;
  }
}
const registerProfileContractContactsElement = () => {
  if (customElements.get(profileContractContactsElementName) !== void 0) {
    return;
  }
  customElements.define(
    profileContractContactsElementName,
    ProfileContractContactsElement
  );
};
export {
  ProfileContractContactsElement,
  contractContactFieldIdPrefix,
  emptyContractContactEditor,
  profileContractContactsElementName,
  registerProfileContractContactsElement
};
