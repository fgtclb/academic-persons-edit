/* Generated from Resources/Private/TypeScript — do not edit. */
import { hooks } from "@fgtclb/academic-persons-edit/frontend/profile/common.js";
import {
  ownerEditingContext
} from "@fgtclb/academic-persons-edit/frontend/profile/context.js";
import {
  ProfileEditingElement
} from "@fgtclb/academic-persons-edit/frontend/profile/elements/base.js";
import {
  applyFieldErrors,
  cloneDisplayRow,
  cloneField,
  fieldControlId
} from "@fgtclb/academic-persons-edit/frontend/profile/elements/field-clone.js";
import {
  profileContractContactsElementName,
  profileDocumentEditorElementName
} from "@fgtclb/academic-persons-edit/frontend/profile/elements/names.js";
import {
  emptyContractContactEditor
} from "@fgtclb/academic-persons-edit/frontend/profile/elements/contract-contacts.js";
import { createElementTransition } from "@fgtclb/academic-persons-edit/frontend/profile/elements/transition.js";
import { fillPrototype } from "@fgtclb/academic-persons-edit/frontend/profile/prototypes.js";
const documentEditorCloseEvent = "pe:document-close";
const documentEditorSubmitEvent = "pe:document-submit";
const documentEditorInputEvent = "pe:document-input";
const documentEditorClosedEvent = "pe:document-closed";
const documentFieldIdPrefix = "profile-editing-document-field";
const runDocumentTransition = createElementTransition(
  "academic-persons-profile-editing-document-collapse"
);
const structuralProperties = [
  "context",
  "deleteConfirmation",
  "fields",
  "heading",
  "kind",
  "mode",
  "record"
];
class ProfileDocumentEditorElement extends ProfileEditingElement {
  #contactEditor = emptyContractContactEditor;
  /** The contact editor the contract's list shows, forwarded to it unread. */
  get contactEditor() {
    return this.#contactEditor;
  }
  set contactEditor(value) {
    const previous = this.#contactEditor;
    this.#contactEditor = value;
    this.requestUpdate("contactEditor", previous);
  }
  #contactEmptyMessage = "";
  /** The message the contract's contact list shows for an empty section. */
  get contactEmptyMessage() {
    return this.#contactEmptyMessage;
  }
  set contactEmptyMessage(value) {
    const previous = this.#contactEmptyMessage;
    this.#contactEmptyMessage = value;
    this.requestUpdate("contactEmptyMessage", previous);
  }
  #contactSections = [];
  /** The contact sections of a contract, forwarded to the list unread. */
  get contactSections() {
    return this.#contactSections;
  }
  set contactSections(value) {
    const previous = this.#contactSections;
    this.#contactSections = value;
    this.requestUpdate("contactSections", previous);
  }
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
  #deleteConfirmation = "";
  /** The question the delete mode asks. */
  get deleteConfirmation() {
    return this.#deleteConfirmation;
  }
  set deleteConfirmation(value) {
    const previous = this.#deleteConfirmation;
    this.#deleteConfirmation = value;
    this.requestUpdate("deleteConfirmation", previous);
  }
  #error = "";
  /** The refusal that belongs to the panel as a whole, or the empty string. */
  get error() {
    return this.#error;
  }
  set error(value) {
    const previous = this.#error;
    this.#error = value;
    this.requestUpdate("error", previous);
  }
  #errors = {};
  /** The refusals per field name. */
  get errors() {
    return this.#errors;
  }
  set errors(value) {
    const previous = this.#errors;
    this.#errors = value;
    this.requestUpdate("errors", previous);
  }
  #fields = [];
  /** The field descriptors the panel is built from. */
  get fields() {
    return this.#fields;
  }
  set fields(value) {
    const previous = this.#fields;
    this.#fields = value;
    this.requestUpdate("fields", previous);
  }
  #heading = "";
  /** The heading of the panel. */
  get heading() {
    return this.#heading;
  }
  set heading(value) {
    const previous = this.#heading;
    this.#heading = value;
    this.requestUpdate("heading", previous);
  }
  #kind = "document";
  /** Which of the two record types this panel edits. */
  get kind() {
    return this.#kind;
  }
  set kind(value) {
    const previous = this.#kind;
    this.#kind = value;
    this.requestUpdate("kind", previous);
  }
  #mode = "view";
  /** What the panel does: view, add, edit or delete. */
  get mode() {
    return this.#mode;
  }
  set mode(value) {
    const previous = this.#mode;
    this.#mode = value;
    this.requestUpdate("mode", previous);
  }
  #open = false;
  /** Whether the panel is open. Assigning it runs the collapse transition. */
  get open() {
    return this.#open;
  }
  set open(value) {
    const previous = this.#open;
    this.#open = value;
    this.requestUpdate("open", previous);
  }
  #pending = false;
  /** Whether a request is in flight, which disables every control. */
  get pending() {
    return this.#pending;
  }
  set pending(value) {
    const previous = this.#pending;
    this.#pending = value;
    this.requestUpdate("pending", previous);
  }
  #record = null;
  /** The uid of the record being edited, or `null` while it is being added. */
  get record() {
    return this.#record;
  }
  set record(value) {
    const previous = this.#record;
    this.#record = value;
    this.requestUpdate("record", previous);
  }
  #values = {};
  /** The current value per field name. */
  get values() {
    return this.#values;
  }
  set values(value) {
    const previous = this.#values;
    this.#values = value;
    this.requestUpdate("values", previous);
  }
  #cancelTransition = null;
  #transitioned = false;
  #built = false;
  #contacts = null;
  constructor() {
    super();
    this.addEventListener("click", this.#onClick);
    this.addEventListener("submit", this.#onSubmit);
    this.addEventListener("input", this.#onValueChanged);
    this.addEventListener("change", this.#onValueChanged);
  }
  /**
   * Resolves once the contract contacts below have rendered as well.
   *
   * `updateComplete` is per element, and the contacts are an element of their
   * own: this one assigns their properties while it builds, which schedules
   * *their* update for a later microtask. A caller that awaits this element
   * and then queries for the contact editor - which is what
   * `openContractContact()` does before it focuses the first control - would
   * otherwise look at the document before the list is in it.
   */
  async getUpdateComplete() {
    const completed = await super.getUpdateComplete();
    const contacts = this.querySelector(profileContractContactsElementName);
    await (contacts == null ? void 0 : contacts.updateComplete);
    return completed;
  }
  connectedCallback() {
    this.context ??= ownerEditingContext(this);
    super.connectedCallback();
  }
  updated(changed) {
    if (this.#needsBuild(changed)) {
      this.#build();
    } else {
      this.#patch(changed);
    }
    this.#syncContacts();
    this.#syncTransition(changed);
  }
  #needsBuild(changed) {
    if (!this.#built) {
      return true;
    }
    return structuralProperties.some((name) => changed.has(name));
  }
  /** Clones the panel prototype and puts it below this element. */
  #build() {
    var _a;
    const source = (_a = this.context) == null ? void 0 : _a.root;
    if (source === void 0) {
      return;
    }
    const editing = this.mode === "add" || this.mode === "edit";
    const showContacts = this.mode === "view" && this.kind === "contract";
    const panel = fillPrototype(source, "document-panel", {
      busy: this.pending ? "true" : "false",
      deleteConfirmation: this.deleteConfirmation,
      error: this.error,
      errorHidden: this.error === "" ? true : void 0,
      heading: this.heading,
      isDelete: this.mode === "delete",
      isSave: this.mode !== "delete",
      kind: this.kind,
      pending: this.pending ? true : void 0,
      showActions: this.mode !== "view",
      showContacts,
      showDisplay: this.mode === "view",
      showFields: editing,
      spinnerHidden: this.pending ? void 0 : true
    });
    panel.list(
      "displayRows",
      this.mode === "view" ? this.fields.map(
        (field) => {
          var _a2;
          return cloneDisplayRow(
            source,
            field,
            ((_a2 = this.context) == null ? void 0 : _a2.labels.documentEmpty) ?? ""
          );
        }
      ) : []
    );
    panel.list(
      "fields",
      editing ? this.fields.map(
        (field, index) => cloneField({
          error: this.errors[field.name],
          field,
          hook: "documentField",
          idPrefix: documentFieldIdPrefix,
          index,
          pending: this.pending,
          source,
          value: this.values[field.name]
        })
      ) : []
    );
    this.#contacts = showContacts ? document.createElement(profileContractContactsElementName) : null;
    panel.list("contacts", this.#contacts === null ? [] : [this.#contacts]);
    this.replaceChildren(panel.fragment);
    this.#built = true;
  }
  /** Writes what changed onto the panel that is already there. */
  #patch(changed) {
    const section = this.querySelector(
      "[data-pe-document-view-container]"
    );
    if (section === null) {
      return;
    }
    if (changed.has("pending")) {
      section.setAttribute("aria-busy", this.pending ? "true" : "false");
      this.querySelectorAll(
        "[data-pe-document-cancel], [data-pe-document-save]"
      ).forEach((button) => {
        button.disabled = this.pending;
      });
      this.querySelectorAll(
        "[data-pe-document-save] .spinner-border"
      ).forEach((spinner) => {
        spinner.hidden = !this.pending;
      });
      this.fields.forEach((field, index) => {
        const control = section.querySelector(
          `#${CSS.escape(fieldControlId(documentFieldIdPrefix, index, field))}`
        );
        if (control instanceof HTMLInputElement || control instanceof HTMLSelectElement || control instanceof HTMLTextAreaElement) {
          control.disabled = field.disabled || this.pending;
        }
      });
    }
    if (changed.has("error")) {
      const alert = section.querySelector(".alert[role='alert']");
      if (alert !== null) {
        alert.textContent = this.error;
        alert.hidden = this.error === "";
      }
    }
    if (changed.has("errors")) {
      applyFieldErrors(section, this.fields, documentFieldIdPrefix, this.errors);
    }
  }
  /** Forwards the five properties the contact list is driven by. */
  #syncContacts() {
    const contacts = this.#contacts;
    if (contacts === null) {
      return;
    }
    contacts.context = this.context;
    contacts.contract = this.record;
    contacts.sections = this.contactSections;
    contacts.emptyMessage = this.contactEmptyMessage;
    contacts.editor = this.contactEditor;
  }
  #syncTransition(changed) {
    if (this.open && !this.#transitioned) {
      this.#transitioned = true;
      this.#startTransition("enter", () => void 0);
      return;
    }
    if (!this.open && this.#transitioned && changed.has("open")) {
      this.#transitioned = false;
      this.#startTransition("leave", () => this.#reportClosed());
    }
  }
  #onClick = (event) => {
    const target = event.target instanceof Element ? event.target : null;
    const button = target == null ? void 0 : target.closest(
      "[data-pe-document-cancel]"
    );
    if (button === null || button === void 0 || button.disabled) {
      return;
    }
    this.dispatchEvent(
      new CustomEvent(documentEditorCloseEvent, { bubbles: true })
    );
  };
  #onSubmit = (event) => {
    event.preventDefault();
    if (this.pending) {
      return;
    }
    this.dispatchEvent(
      new CustomEvent(documentEditorSubmitEvent, { bubbles: true })
    );
  };
  #onValueChanged = (event) => {
    const control = event.target;
    if (!(control instanceof HTMLInputElement) && !(control instanceof HTMLSelectElement) && !(control instanceof HTMLTextAreaElement)) {
      return;
    }
    const name = hooks(control).peDocumentField;
    if (name === void 0) {
      return;
    }
    const value = control instanceof HTMLInputElement && control.type === "checkbox" ? control.checked : control.value;
    this.dispatchEvent(
      new CustomEvent(
        documentEditorInputEvent,
        { bubbles: true, detail: { name, value } }
      )
    );
  };
  #reportClosed() {
    globalThis.requestAnimationFrame(() => {
      this.dispatchEvent(
        new CustomEvent(documentEditorClosedEvent, { bubbles: true })
      );
    });
  }
  #startTransition(kind, done) {
    var _a;
    const section = this.querySelector(
      "[data-pe-document-view-container]"
    );
    if (section === null) {
      done();
      return;
    }
    (_a = this.#cancelTransition) == null ? void 0 : _a.call(this);
    this.#cancelTransition = null;
    let settled = false;
    const cancel = runDocumentTransition(section, kind, () => {
      settled = true;
      this.#cancelTransition = null;
      done();
    });
    if (!settled) {
      this.#cancelTransition = cancel;
    }
  }
}
const registerProfileDocumentEditorElement = () => {
  if (customElements.get(profileDocumentEditorElementName) !== void 0) {
    return;
  }
  customElements.define(
    profileDocumentEditorElementName,
    ProfileDocumentEditorElement
  );
};
export {
  ProfileDocumentEditorElement,
  documentEditorCloseEvent,
  documentEditorClosedEvent,
  documentEditorInputEvent,
  documentEditorSubmitEvent,
  documentFieldIdPrefix,
  profileDocumentEditorElementName,
  registerProfileDocumentEditorElement
};
