/**
 * `<academic-persons-edit-contract-contacts>` - the addresses, phone numbers
 * and mail addresses of one contract, and the inline editor that adds, shows,
 * changes and deletes one of them.
 *
 * ## It controls markup, it does not render any
 *
 * The three shapes it uses are
 * `Partials/Profile/Documents/ContractContacts.html` - a section, a row and
 * one summary cell - and the editor is
 * `Partials/Profile/Documents/ContractContactEditor.html`. Both partials keep
 * the names and the role they had before ACE-262: they are the override point
 * for the contact list, they just render a `<template data-pe-proto>` instead
 * of finished markup, because the sections, their items, the summary columns
 * of a row and the fields of the editor all come from responses.
 *
 * The variable length of the summary was the one place this design was
 * expected to break, and it did not: a row's cells are a `data-pe-list`, one
 * clone per column the server sent. The row's action buttons are the second
 * half of that risk - whether the arrows are disabled depends on where the row
 * stands in its section - and that is answered here rather than by the server
 * sending `sortable` per item: the list is edited in the browser (a create
 * appends, a delete removes, a sort reorders), so a flag the server computed
 * would be stale one interaction later, while the position in the list this
 * element is rendering never is. What reaches the filler is two booleans, and
 * the filler still only toggles an attribute.
 *
 * ## What it does not own
 *
 * The requests. `profile/documents.ts` keeps `openContractContact()`,
 * `closeContractContact()`, `submitContractContact()` and
 * `sortContractContact()`, keeps the state they write and keeps the endpoints
 * - this element renders that state and reports the presses. The presses are
 * reported as *native clicks* on the buttons carrying
 * `data-pe-contract-contact-*`, delegated on the plugin root by the
 * controller, rather than as custom events: the document editor creates this
 * element inside its own panel, so the controller never holds it, and
 * `openContractContact()` takes the event to find the button focus has to
 * return to.
 */
import {
  hooks,
  setDisabled,
  setExpanded,
  setHiddenState,
} from "@fgtclb/academic-persons-edit/frontend/profile/common.js";
import {
  ownerEditingContext,
  type EditingContext,
} from "@fgtclb/academic-persons-edit/frontend/profile/context.js";
import { ProfileEditingElement } from "@fgtclb/academic-persons-edit/frontend/profile/elements/base.js";
import {
  applyFieldErrors,
  cloneDisplayRow,
  cloneField,
  fieldControlId,
} from "@fgtclb/academic-persons-edit/frontend/profile/elements/field-clone.js";
import { profileContractContactsElementName } from "@fgtclb/academic-persons-edit/frontend/profile/elements/names.js";
import { fillPrototype } from "@fgtclb/academic-persons-edit/frontend/profile/prototypes.js";
import type {
  ContractContactItem,
  ContractContactSection,
  DocumentField,
  DocumentMode,
  DocumentValue,
} from "@fgtclb/academic-persons-edit/frontend/profile/documents.js";

/** The tag name of this element. Public API from the moment it ships. */
export { profileContractContactsElementName };

/** The id prefix of every control the contact editor renders. */
export const contractContactFieldIdPrefix =
  "profile-editing-contract-contact-field";

/**
 * The open contact editor, as this element needs it.
 *
 * One property rather than eleven: it is a snapshot of the controller's own
 * state and is assigned in one place, and a partially applied set would render
 * an editor that is briefly wrong - the fields of one contact under the
 * heading of another.
 *
 * `readonly` throughout, which is the whole mechanism: the controller hands
 * over a fresh object every time, so this element can neither write into the
 * one it was given nor be surprised by a write from elsewhere.
 */
export interface ProfileContractContactEditorState {
  readonly deleteConfirmation: string;
  readonly error: string;
  readonly errors: Record<string, string>;
  readonly fields: DocumentField[];
  readonly mode: DocumentMode;
  readonly open: boolean;
  readonly pending: boolean;
  readonly record: number | null;
  readonly section: string;
  readonly title: string;
  readonly values: Record<string, DocumentValue>;
}

/** No editor open, which is what a contract in view mode starts in. */
export const emptyContractContactEditor: ProfileContractContactEditorState =
  Object.freeze({
    deleteConfirmation: "",
    error: "",
    errors: {},
    fields: [],
    mode: "view" as DocumentMode,
    open: false,
    pending: false,
    record: null,
    section: "",
    title: "",
    values: {},
  });

/**
 * The id of the editor of one section.
 *
 * One per section and not one per item, because only one editor is open at a
 * time: the "add" of the section and the "view", "edit" and "delete" of every
 * row all point their `aria-controls` at the same id.
 */
const editorId = (section: ContractContactSection | string): string =>
  `profile-editing-contract-contact-editor-${typeof section === "string" ? section : section.identifier}`;

/**
 * What decides whether an open editor has to be built again.
 *
 * The controller hands over a *fresh* editor object on every render, so
 * comparing the property is useless; comparing what the markup is derived from
 * is not. `pending`, `error` and `errors` are deliberately absent - those are
 * patched onto the panel that is already open, so that a refusal does not
 * replace the controls the visitor typed in.
 */
const editorShape = (editor: ProfileContractContactEditorState): string =>
  [
    editor.open,
    editor.mode,
    editor.section,
    editor.record,
    editor.title,
    editor.deleteConfirmation,
  ].join("|");

/**
 * What decides whether one row has to be built again.
 *
 * By value and not by identity: a save hands over a whole new sections array
 * with new item objects in it, and rebuilding every row would take the control
 * the visitor is standing in - and the caret with it - out of the document.
 */
const rowShape = (item: ContractContactItem): string =>
  JSON.stringify([item.uid, item.hidden, item.summary]);

/**
 * Puts `keys` in `parent`, in that order, reusing what is already there.
 *
 * The whole reason the list is reconciled rather than replaced: the rows carry
 * the controls the visitor presses and the editor they open, and a node that
 * leaves the document loses the caret. `create()` is called for a key that is
 * new, `update()` for every key on every pass, and whatever is left over is
 * removed.
 */
const reconcile = (
  parent: Element,
  keys: readonly string[],
  keyOf: (element: HTMLElement) => string | undefined,
  create: (key: string) => HTMLElement,
  update: (element: HTMLElement, key: string, index: number) => HTMLElement,
): void => {
  const existing = new Map<string, HTMLElement>();
  Array.from(parent.children).forEach((child): void => {
    if (child instanceof HTMLElement) {
      const key = keyOf(child);
      if (key !== undefined) {
        existing.set(key, child);
      }
    }
  });
  let previous: Element | null = null;
  keys.forEach((key, index): void => {
    const element = update(existing.get(key) ?? create(key), key, index);
    existing.delete(key);
    const expected =
      previous === null ? parent.firstElementChild : previous.nextElementSibling;
    if (expected !== element) {
      parent.insertBefore(element, expected);
    }
    previous = element;
  });
  existing.forEach((element): void => {
    element.remove();
  });
};

/**
 * The element.
 *
 * Public surface: the tag name, the five properties below, and the
 * `data-pe-contract-contact-*` hooks the prototypes carry, which are what the
 * controller delegates on. It observes no attributes, dispatches no events of
 * its own and calls no endpoint.
 */
export class ProfileContractContactsElement extends ProfileEditingElement<ProfileContractContactsElement> {
  #context: EditingContext | null = null;

  /** The contract of `Templates/Profile/Index.html`, assigned by the owner. */
  get context(): EditingContext | null {
    return this.#context;
  }

  set context(value: EditingContext | null) {
    const previous = this.#context;
    this.#context = value;
    this.requestUpdate("context", previous);
  }

  #contract: number | null = null;

  /** The uid of the contract these contacts belong to. */
  get contract(): number | null {
    return this.#contract;
  }

  set contract(value: number | null) {
    const previous = this.#contract;
    this.#contract = value;
    this.requestUpdate("contract", previous);
  }

  #editor: ProfileContractContactEditorState = emptyContractContactEditor;

  /** Which contact is being edited, and with which values and refusals. */
  get editor(): ProfileContractContactEditorState {
    return this.#editor;
  }

  set editor(value: ProfileContractContactEditorState) {
    const previous = this.#editor;
    this.#editor = value;
    this.requestUpdate("editor", previous);
  }

  #emptyMessage: string = "";

  /** The message a section with no contacts shows. */
  get emptyMessage(): string {
    return this.#emptyMessage;
  }

  set emptyMessage(value: string) {
    const previous = this.#emptyMessage;
    this.#emptyMessage = value;
    this.requestUpdate("emptyMessage", previous);
  }

  #sections: ContractContactSection[] = [];

  /** The sections and their contacts, as the last response answered them. */
  get sections(): ContractContactSection[] {
    return this.#sections;
  }

  set sections(value: ContractContactSection[]) {
    const previous = this.#sections;
    this.#sections = value;
    this.requestUpdate("sections", previous);
  }

  /**
   * The shape a row was built from, keyed by section *and* record: the three
   * contact kinds are three tables, and a uid that exists in two of them
   * would otherwise make each section rebuild the other's row.
   */
  #rowShapes = new Map<string, string>();
  #panel: HTMLElement | null = null;
  #panelShape = "";
  #panelFields: DocumentField[] | null = null;

  override connectedCallback(): void {
    // Assigned by the document editor, which creates this element. The
    // fallback covers nothing today and costs one call; it is what keeps the
    // element usable without its creator.
    this.context ??= ownerEditingContext(this);
    super.connectedCallback();
  }

  override updated(): void {
    const source = this.context?.root;
    if (source === undefined) {
      return;
    }
    this.#syncSections(source);
    this.#syncEditor(source);
  }

  /** The sections and their rows, reconciled rather than replaced. */
  #syncSections(source: ParentNode): void {
    reconcile(
      this,
      this.sections.map((section): string => section.identifier),
      (element): string | undefined =>
        hooks(element).peContractContactSection,
      (key): HTMLElement =>
        fillPrototype(source, "contact-section", {
          addDisabled: undefined,
          addEditorHidden: true,
          addExpanded: "false",
          editorId: editorId(key),
          emptyHidden: true,
          emptyMessage: this.emptyMessage,
          identifier: key,
          label: this.#section(key)?.label ?? "",
          rowsHidden: true,
        }).element,
      (element, key): HTMLElement => {
        const section = this.#section(key);
        if (section !== undefined) {
          this.#syncSection(source, element, section);
        }

        return element;
      },
    );
  }

  #syncSection(
    source: ParentNode,
    element: HTMLElement,
    section: ContractContactSection,
  ): void {
    const pending = this.editor.pending;
    const add = element.querySelector<HTMLButtonElement>(
      "[data-pe-contract-contact-add]",
    );
    if (add !== null) {
      add.disabled = pending;
      add.setAttribute(
        "aria-expanded",
        this.#isOpen("add", section.identifier, null) ? "true" : "false",
      );
    }
    const rows = element.querySelector<HTMLElement>('[data-pe-list="rows"]');
    const empty = element.querySelector<HTMLElement>("p[role='status']");
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
      section.items.map((item): string => String(item.uid)),
      (row): string | undefined => hooks(row).peContractContactItem,
      (key): HTMLElement => this.#createRow(source, section, key),
      (row, key, index): HTMLElement => {
        const item = section.items[index];
        if (item === undefined) {
          return row;
        }
        if (this.#rowShapes.get(`${section.identifier}:${key}`) !== rowShape(item)) {
          const replacement = this.#createRow(source, section, key);
          row.replaceWith(replacement);

          return replacement;
        }
        this.#syncRowControls(row, section, index);

        return row;
      },
    );
  }

  /** One contact: its summary columns, its controls and its editor slot. */
  #createRow(
    source: ParentNode,
    section: ContractContactSection,
    key: string,
  ): HTMLElement {
    const index = section.items.findIndex(
      (candidate): boolean => String(candidate.uid) === key,
    );
    const item = section.items[index];
    if (item === undefined) {
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
      hidden: item.hidden ? "hidden" : undefined,
      uid: item.uid,
      viewExpanded: "false",
    });
    clone.list(
      "summary",
      item.summary.map((summary): Node =>
        fillPrototype(source, "contact-summary-cell", {
          hasValue: summary.value !== "",
          isEmpty: summary.value === "",
          label: summary.label,
          value: summary.value,
        }).fragment,
      ),
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
  #syncRowControls(
    row: HTMLElement,
    section: ContractContactSection,
    index: number,
  ): void {
    const pending = this.editor.pending;
    const uid = Number(hooks(row).peContractContactItem ?? "0");
    const expanded = (mode: DocumentMode): boolean =>
      this.#isOpen(mode, section.identifier, uid);
    row
      .querySelectorAll<HTMLButtonElement>(
        "[data-pe-contract-contact-view], [data-pe-contract-contact-edit], " +
          "[data-pe-contract-contact-delete]",
      )
      .forEach((button): void => {
        setDisabled(button, pending);
      });
    (
      [
        ["view", "[data-pe-contract-contact-view]"],
        ["edit", "[data-pe-contract-contact-edit]"],
        ["delete", "[data-pe-contract-contact-delete]"],
      ] as const
    ).forEach(([mode, selector]): void => {
      const control = row.querySelector<HTMLElement>(selector);
      if (control !== null) {
        setExpanded(control, expanded(mode));
      }
    });
    const item = section.items[index];
    const visibility = row.querySelector<HTMLButtonElement>(
      "[data-pe-contract-contact-hide]",
    );
    if (visibility !== null && item !== undefined) {
      setDisabled(visibility, pending);
      setHiddenState(visibility, item.hidden);
    }
    row
      .querySelectorAll<HTMLButtonElement>("[data-pe-contract-contact-sort]")
      .forEach((button): void => {
        const atEnd =
          hooks(button).peContractContactSort === "up"
            ? index === 0
            : index === section.items.length - 1;
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
  #syncEditor(source: ParentNode): void {
    const editor = this.editor;
    const target = this.#editorTarget();
    if (!editor.open || target === null) {
      this.#panel?.remove();
      this.#panel = null;
      this.#panelShape = "";
      this.#panelFields = null;
      this.querySelectorAll<HTMLElement>(
        '[data-pe-list="editor"], [data-pe-list="addEditor"]',
      ).forEach((container): void => {
        container.hidden = true;
      });

      return;
    }
    if (
      this.#panel === null ||
      this.#panelShape !== editorShape(editor) ||
      this.#panelFields !== editor.fields ||
      this.#panel.parentElement !== target
    ) {
      this.#panel?.remove();
      this.#panel = this.#createEditor(source);
      this.#panelShape = editorShape(editor);
      this.#panelFields = editor.fields;
      target.replaceChildren(this.#panel);
    }
    this.querySelectorAll<HTMLElement>(
      '[data-pe-list="editor"], [data-pe-list="addEditor"]',
    ).forEach((container): void => {
      container.hidden = container !== target;
    });
    this.#patchEditor();
  }

  /** The container the open editor belongs in, or `null` for none. */
  #editorTarget(): HTMLElement | null {
    const editor = this.editor;
    if (!editor.open) {
      return null;
    }
    const section = this.querySelector<HTMLElement>(
      `[data-pe-contract-contact-section="${CSS.escape(editor.section)}"]`,
    );
    if (section === null) {
      return null;
    }
    if (editor.mode === "add") {
      return section.querySelector<HTMLElement>('[data-pe-list="addEditor"]');
    }
    const row = section.querySelector<HTMLElement>(
      `[data-pe-contract-contact-item="${CSS.escape(String(editor.record ?? ""))}"]`,
    );

    return row?.querySelector<HTMLElement>('[data-pe-list="editor"]') ?? null;
  }

  /** The editor itself, identical wherever the list puts it. */
  #createEditor(source: ParentNode): HTMLElement {
    const editor = this.editor;
    const editing = editor.mode === "add" || editor.mode === "edit";
    const clone = fillPrototype(source, "contact-editor-panel", {
      busy: editor.pending ? "true" : "false",
      deleteConfirmation: editor.deleteConfirmation,
      editorId: `profile-editing-contract-contact-editor-${editor.section}`,
      error: editor.error,
      errorHidden: editor.error === "" ? true : undefined,
      isDelete: editor.mode === "delete",
      isSave: editor.mode !== "delete",
      pending: editor.pending ? true : undefined,
      showActions: editor.mode !== "view",
      showClose: editor.mode !== "delete",
      showDisplay: editor.mode === "view",
      showFields: editing,
      spinnerHidden: editor.pending ? undefined : true,
      title: editor.title,
    });
    clone.list(
      "displayRows",
      editor.mode === "view"
        ? editor.fields.map((field): Node =>
            cloneDisplayRow(
              source,
              field,
              this.context?.labels.documentEmpty ?? "",
            ),
          )
        : [],
    );
    clone.list(
      "fields",
      editing
        ? editor.fields.map((field, index): Node =>
            cloneField({
              error: editor.errors[field.name],
              field,
              hook: "contactField",
              idPrefix: contractContactFieldIdPrefix,
              index,
              pending: editor.pending,
              source,
              value: editor.values[field.name],
            }),
          )
        : [],
    );

    return clone.element;
  }

  /**
   * Writes the pending state and the messages onto the panel that is there.
   *
   * A refusal must not replace the controls the visitor typed in, which is
   * what a rebuild of the panel would do.
   */
  #patchEditor(): void {
    const panel = this.#panel;
    const editor = this.editor;
    if (panel === null) {
      return;
    }
    panel.setAttribute("aria-busy", editor.pending ? "true" : "false");
    const alert = panel.querySelector<HTMLElement>(".alert[role='alert']");
    if (alert !== null) {
      alert.textContent = editor.error;
      alert.hidden = editor.error === "";
    }
    applyFieldErrors(
      panel,
      editor.fields,
      contractContactFieldIdPrefix,
      editor.errors,
    );
    editor.fields.forEach((field, index): void => {
      const control = panel.querySelector(
        `#${CSS.escape(fieldControlId(contractContactFieldIdPrefix, index, field))}`,
      );
      if (
        control instanceof HTMLInputElement ||
        control instanceof HTMLSelectElement ||
        control instanceof HTMLTextAreaElement
      ) {
        control.disabled = field.disabled || editor.pending;
      }
    });
    panel
      .querySelectorAll<HTMLButtonElement>(
        "[data-pe-contract-contact-cancel], [data-pe-contract-contact-save]",
      )
      .forEach((button): void => {
        button.disabled = editor.pending;
      });
    panel
      .querySelectorAll<HTMLElement>(
        "[data-pe-contract-contact-save] .spinner-border",
      )
      .forEach((spinner): void => {
        spinner.hidden = !editor.pending;
      });
  }

  #section(identifier: string): ContractContactSection | undefined {
    return this.sections.find(
      (section): boolean => section.identifier === identifier,
    );
  }

  /** Whether the open editor is the one this control would open. */
  #isOpen(mode: DocumentMode, section: string, record: number | null): boolean {
    return (
      this.editor.open &&
      this.editor.mode === mode &&
      this.editor.section === section &&
      this.editor.record === record
    );
  }
}

/**
 * Defines the element, idempotently.
 *
 * Called by the entry point and by `profile/documents.ts`, which creates the
 * document editor that renders this one. A second call is a no-op rather than
 * the `NotSupportedError` a repeated `customElements.define()` raises.
 */
export const registerProfileContractContactsElement = (): void => {
  if (customElements.get(profileContractContactsElementName) !== undefined) {
    return;
  }
  customElements.define(
    profileContractContactsElementName,
    ProfileContractContactsElement,
  );
};
