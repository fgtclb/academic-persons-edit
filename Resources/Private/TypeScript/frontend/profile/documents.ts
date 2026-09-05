import {
  hooks,
  initializePopover,
  isEditableField,
  requestJson,
  setDisabled,
  setExpanded,
  showStatus,
} from "@fgtclb/academic-persons-edit/frontend/profile/common.js";
import {
  toEditingContext,
  type EditingContext,
  type EditingTarget,
} from "@fgtclb/academic-persons-edit/frontend/profile/context.js";
import {
  documentEditorClosedEvent,
  documentEditorCloseEvent,
  documentEditorInputEvent,
  documentEditorSubmitEvent,
  registerProfileDocumentEditorElement,
  type ProfileDocumentEditorElement,
  type ProfileDocumentEditorInputDetail,
} from "@fgtclb/academic-persons-edit/frontend/profile/elements/document-editor.js";
import { registerProfileContractContactsElement } from "@fgtclb/academic-persons-edit/frontend/profile/elements/contract-contacts.js";
import { profileDocumentEditorElementName } from "@fgtclb/academic-persons-edit/frontend/profile/elements/names.js";
import { registerProfileRichTextElement } from "@fgtclb/academic-persons-edit/frontend/profile/elements/rich-text.js";
import {
  destroyRichTextEditors,
  ensureRichTextEditor,
  getPlainText,
  getRichTextEditorValue,
  isAllowedRichTextLink,
  parseRichTextPreview,
} from "@fgtclb/academic-persons-edit/frontend/profile/rich-text.js";

export type DocumentMode = "add" | "view" | "edit" | "delete";
export type DocumentValue = string | boolean | number | null;

export interface DocumentOption {
  label: string;
  value: DocumentValue;
}

export interface DocumentField {
  autocomplete?: string;
  characterLimit?: number;
  columnClass?: string;
  compactCheckbox?: boolean;
  disabled: boolean;
  displayValue?: string;
  helptext?: string;
  label: string;
  max?: number | null;
  min?: number | null;
  name: string;
  options?: DocumentOption[];
  placeholder?: string;
  readOnly: boolean;
  required: boolean;
  richText: boolean;
  step?: number | null;
  type: string;
  value: DocumentValue;
}

interface DocumentItem {
  display?: Record<string, unknown>;
  sorting?: number;
  uid?: number;
  values?: Record<string, unknown>;
}

export interface ContractContactSummary {
  label: string;
  value: string;
}

export interface ContractContactItem extends DocumentItem {
  hidden: boolean;
  summary: ContractContactSummary[];
  uid: number;
}

export interface ContractContactSection {
  identifier: string;
  items: ContractContactItem[];
  label: string;
  singularLabel: string;
}

interface ContractContactState {
  deleteConfirmation: string;
  error: string;
  errors: Record<string, string>;
  fields: DocumentField[];
  mode: DocumentMode;
  open: boolean;
  pending: boolean;
  record: number | null;
  section: string;
  title: string;
  values: Record<string, DocumentValue>;
}

interface DocumentState {
  contactEmptyMessage: string;
  contactSections: ContractContactSection[];
  deleteConfirmation: string;
  error: string;
  errors: Record<string, string>;
  fields: DocumentField[];
  kind: "document" | "contract";
  mode: DocumentMode;
  open: boolean;
  pending: boolean;
  record: number | null;
  section: string;
  title: string;
  target: string;
  values: Record<string, DocumentValue>;
}

interface RequestError extends Error {
  result?: {
    errors?: Record<string, unknown>;
    message?: string;
  };
}

export interface DocumentEditingController {
  contractContact: ContractContactState;
  document: DocumentState;
  openDocument(mode: string, event: Event): Promise<void>;
  closeDocument(): void;
  finishDocumentClose(element: Element, restoreState?: boolean): void;
  submitDocument(): Promise<void>;
  openContractContact(
    mode: string,
    section: string,
    event: Event,
    record?: number,
  ): Promise<void>;
  closeContractContact(): void;
  submitContractContact(): Promise<void>;
  sortContractContact(direction: string, section: string, record: number): Promise<void>;
  sortDocument(direction: string, event: Event): Promise<void>;
  toggleContractContactVisibility(section: string, record: number): Promise<void>;
}

interface DragState {
  dropPosition: "before" | "after" | "end" | null;
  dropRow: HTMLElement | null;
  handle: HTMLButtonElement;
  items: HTMLElement;
  order: number[];
  row: HTMLElement;
  section: HTMLElement;
}

const sectionSelector = "[data-pe-document-section]";
const itemSelector = "[data-pe-document-item]";
const itemsSelector = "[data-pe-document-items]";
const itemTemplateSelector = "[data-pe-document-item-template]";
const emptyStateSelector = "[data-pe-document-empty-state]";
const listHeaderSelector = "[data-pe-document-list-header]";
const documentViewSelector = "[data-pe-document-view-container]";
const addCollapseTargetSelector = "[data-pe-document-add-collapse-target]";
const itemCollapseTargetSelector = "[data-pe-document-item-collapse-target]";
let collapseTargetSequence = 0;

const isDocumentMode = (value: string): value is DocumentMode =>
  ["add", "view", "edit", "delete"].includes(value);

const getDocumentRows = (section: HTMLElement): HTMLElement[] => {
  const items = section.querySelector<HTMLElement>(itemsSelector);
  if (items === null) {
    return [];
  }
  return Array.from(items.children).filter(
    (element): element is HTMLElement =>
      element instanceof HTMLElement && element.matches(itemSelector),
  );
};

const asDocumentField = (value: unknown): DocumentField | null => {
  if (typeof value !== "object" || value === null) {
    return null;
  }
  const field = value as Partial<DocumentField>;
  if (typeof field.name !== "string") {
    return null;
  }
  return {
    autocomplete: String(field.autocomplete ?? ""),
    characterLimit: field.characterLimit,
    columnClass: field.columnClass,
    compactCheckbox: field.compactCheckbox,
    disabled: field.disabled === true,
    displayValue: String(field.displayValue ?? ""),
    helptext: String(field.helptext ?? ""),
    label: String(field.label ?? field.name),
    max: field.max ?? null,
    min: field.min ?? null,
    name: field.name,
    options: Array.isArray(field.options) ? field.options : [],
    placeholder: String(field.placeholder ?? ""),
    readOnly: field.readOnly === true,
    required: field.required === true,
    richText: field.richText === true,
    step: field.step ?? null,
    type: String(field.type ?? "text"),
    value: field.value ?? "",
  };
};

const getResponseFields = (value: unknown): DocumentField[] =>
  Array.isArray(value)
    ? value
        .map(asDocumentField)
        .filter((field): field is DocumentField => field !== null)
    : [];

const asRecord = (value: unknown): Record<string, unknown> =>
  typeof value === "object" && value !== null
    ? (value as Record<string, unknown>)
    : {};

const asContractContactItem = (value: unknown): ContractContactItem | null => {
  const item = asRecord(value);
  const uid = Number(item.uid);
  if (!Number.isInteger(uid) || uid <= 0) {
    return null;
  }
  return {
    display: asRecord(item.display),
    hidden: item.hidden === true,
    sorting: Number(item.sorting) || 0,
    summary: Array.isArray(item.summary)
      ? item.summary.map((entry): ContractContactSummary => {
          const summary = asRecord(entry);
          return {
            label: String(summary.label ?? ""),
            value: String(summary.value ?? ""),
          };
        })
      : [],
    uid,
    values: asRecord(item.values),
  };
};

const getResponseContactSections = (value: unknown): ContractContactSection[] =>
  Array.isArray(value)
    ? value.flatMap((entry): ContractContactSection[] => {
        const section = asRecord(entry);
        const identifier = String(section.identifier ?? "");
        if (identifier === "") {
          return [];
        }
        return [{
          identifier,
          items: Array.isArray(section.items)
            ? section.items
                .map(asContractContactItem)
                .filter((item): item is ContractContactItem => item !== null)
            : [],
          label: String(section.label ?? identifier),
          singularLabel: String(section.singularLabel ?? section.label ?? identifier),
        }];
      })
    : [];

/**
 * The sections with the items of one of them replaced, as new objects.
 *
 * Every write to a contact list goes through here, because none of them may be
 * a write *into* the list: `documentState.contactSections` is handed to
 * `<academic-persons-edit-contract-contacts>` as a property, and the setter
 * compares by identity - an array that was mutated in place is the same array
 * and updates nothing. The section that is not touched keeps its object, so
 * the reconciler keyed by identifier moves no node it does not have to.
 */
const replaceContactItems = (
  sections: ContractContactSection[],
  identifier: string,
  items: (current: ContractContactItem[]) => ContractContactItem[],
): ContractContactSection[] =>
  sections.map((section): ContractContactSection =>
    section.identifier === identifier
      ? { ...section, items: items(section.items) }
      : section,
  );

const getSectionHeading = (section: HTMLElement): string =>
  section.querySelector("h2")?.textContent?.trim() ?? "";

const getDocumentSubject = (
  section: HTMLElement,
  fields: DocumentField[],
): string => {
  const titleField = fields.find((field): boolean => field.name === "title");
  const title = [titleField?.displayValue, titleField?.value]
    .map((value): string => String(value ?? "").trim())
    .find((value): boolean => value !== "");
  return title ?? getSectionHeading(section);
};

const getModeLabel = (context: EditingContext, mode: DocumentMode): string => {
  const labels: Record<DocumentMode, string | undefined> = {
    add: context.labels.documentAdd,
    view: context.labels.documentView,
    edit: context.labels.documentEdit,
    delete: context.labels.documentDelete,
  };
  return labels[mode] ?? "";
};

const getRecordFromButton = (button: HTMLButtonElement): number | null => {
  const row = button.closest<HTMLElement>(itemSelector);
  const record = Number.parseInt(row?.dataset.itemUid ?? "", 10);
  return Number.isInteger(record) && record > 0 ? record : null;
};

const getDocumentCollapseTarget = (
  button: HTMLButtonElement,
  section: HTMLElement,
  mode: DocumentMode,
): HTMLElement | null =>
  mode === "add"
    ? section.querySelector<HTMLElement>(addCollapseTargetSelector)
    : button
        .closest<HTMLElement>(itemSelector)
        ?.querySelector<HTMLElement>(itemCollapseTargetSelector) ?? null;

const getDocumentCollapseTargetSelector = (target: HTMLElement): string => {
  if (target.id === "") {
    collapseTargetSequence += 1;
    target.id = `profile-editing-document-collapse-${collapseTargetSequence}`;
  }
  return `#${CSS.escape(target.id)}`;
};

const requestDocument = (
  context: EditingContext,
  url: string | undefined,
  data: Record<string, unknown>,
): Promise<Record<string, unknown>> => {
  const profile = context.profileUid;
  if (url === undefined || profile === null) {
    return Promise.reject(new Error("The document endpoint is unavailable."));
  }
  return requestJson(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ profile, data }),
  });
};

const appendRichText = (container: HTMLElement, value: string): void => {
  const parsedDocument = parseRichTextPreview(value);
  const fragment = document.createDocumentFragment();
  Array.from(parsedDocument.body.childNodes).forEach((node): void => {
    fragment.append(document.importNode(node, true));
  });
  container.replaceChildren(fragment);
};

const getRowDisplayValue = (item: DocumentItem, name: string): unknown => {
  const display = item.display ?? {};
  if (name === "yearStart" && !display.yearStart) {
    return display.year ?? "";
  }
  if (name === "year" && !display.year) {
    return display.yearStart ?? "";
  }
  return display[name] ?? "";
};

const renderDocumentTitle = (
  container: HTMLElement,
  title: string,
  link: unknown,
): void => {
  const normalizedTitle = title || "—";
  if (typeof link === "string" && isAllowedRichTextLink(link)) {
    const anchor = document.createElement("a");
    anchor.href = link;
    anchor.target = "_blank";
    anchor.rel = "noopener noreferrer";
    anchor.textContent = normalizedTitle;
    container.replaceChildren(anchor);
    return;
  }
  const span = document.createElement("span");
  span.textContent = normalizedTitle;
  container.replaceChildren(span);
};

const updateDocumentRow = (row: HTMLElement, item: DocumentItem): void => {
  row.dataset.itemUid = String(item.uid ?? "");
  row.dataset.itemSorting = String(item.sorting ?? "");
  row.querySelectorAll<HTMLElement>("[data-pe-document-value]").forEach(
    (element): void => {
      const name = hooks(element).peDocumentValue ?? "";
      const value = getRowDisplayValue(item, name);
      if (name === "bodytext") {
        const normalizedValue = String(value ?? "");
        element.classList.toggle("d-none", getPlainText(normalizedValue) === "");
        if (normalizedValue === "") {
          element.replaceChildren();
        } else {
          appendRichText(element, normalizedValue);
        }
        return;
      }
      element.textContent = String(value || "—");
    },
  );
  const title = row.querySelector<HTMLElement>("[data-pe-document-title]");
  if (title !== null) {
    renderDocumentTitle(
      title,
      String(item.display?.title ?? ""),
      item.values?.link,
    );
  }
};

const refreshDocumentRows = (section: HTMLElement): void => {
  const rows = getDocumentRows(section);
  const sortable = section.dataset.sectionSortable === "1" && rows.length > 1;
  rows.forEach((row, index): void => {
    row.dataset.itemPosition = String(index);
    const up = row.querySelector<HTMLButtonElement>(
      '[data-pe-document-sort="up"]',
    );
    const down = row.querySelector<HTMLButtonElement>(
      '[data-pe-document-sort="down"]',
    );
    const drag = row.querySelector<HTMLButtonElement>("[data-pe-document-drag]");
    if (up !== null) {
      setDisabled(up, index === 0);
    }
    if (down !== null) {
      setDisabled(down, index === rows.length - 1);
    }
    if (drag !== null) {
      setDisabled(drag, !sortable);
      drag.draggable = sortable;
    }
  });
  const emptyState = section.querySelector<HTMLElement>(emptyStateSelector);
  emptyState?.classList.toggle("d-none", rows.length > 0);
  const listHeader = section.querySelector<HTMLElement>(listHeaderSelector);
  listHeader?.classList.toggle("d-md-flex", rows.length > 0);
};

const insertDocumentRow = (
  section: HTMLElement,
  item: DocumentItem,
): HTMLElement | null => {
  const template = section.querySelector<HTMLElement>(itemTemplateSelector);
  const items = section.querySelector<HTMLElement>(itemsSelector);
  const templateRow = template?.querySelector<HTMLElement>(itemSelector);
  if (items === null || templateRow === null || templateRow === undefined) {
    return null;
  }
  const row = templateRow.cloneNode(true);
  if (!(row instanceof HTMLElement)) {
    return null;
  }
  updateDocumentRow(row, item);
  items.append(row);
  refreshDocumentRows(section);
  return row;
};

const getDocumentOrder = (section: HTMLElement): number[] =>
  Array.from(getDocumentRows(section), (row): number =>
    Number.parseInt(row.dataset.itemUid ?? "", 10),
  ).filter((uid): boolean => Number.isInteger(uid) && uid > 0);

const applyDocumentOrder = (
  section: HTMLElement,
  order: number[],
): void => {
  const items = section.querySelector<HTMLElement>(itemsSelector);
  if (items === null) {
    return;
  }
  const rowsByUid = new Map(
    getDocumentRows(section).map(
      (row): [string | undefined, HTMLElement] => [row.dataset.itemUid, row],
    ),
  );
  order.forEach((uid): void => {
    const row = rowsByUid.get(String(uid));
    if (row !== undefined) {
      items.append(row);
    }
  });
  refreshDocumentRows(section);
};

const setSectionPending = (section: HTMLElement, pending: boolean): void => {
  section.setAttribute("aria-busy", String(pending));
  section.style.cursor = pending ? "wait" : "";
  section.querySelectorAll<HTMLButtonElement>("button").forEach((button): void => {
    if (pending) {
      if (hooks(button).peDocumentWasDisabled === undefined) {
        hooks(button).peDocumentWasDisabled = button.disabled ? "1" : "0";
      }
      button.disabled = true;
    } else if (hooks(button).peDocumentWasDisabled !== undefined) {
      button.disabled = hooks(button).peDocumentWasDisabled === "1";
      delete hooks(button).peDocumentWasDisabled;
    }
  });
};

const persistDocumentOrder = async (
  context: EditingContext,
  section: HTMLElement,
  order: number[],
  previousOrder: number[],
): Promise<void> => {
  setSectionPending(section, true);
  try {
    const response = await requestDocument(context, context.urls.sortDocument, {
      section: section.dataset.sectionKey,
      order,
    });
    applyDocumentOrder(section, Array.isArray(response.order) ? response.order.map(Number) : order);
    showStatus(context, "success", context.messages.documentSorted ?? null);
  } catch (error) {
    applyDocumentOrder(section, previousOrder);
    showStatus(context, "danger", (error as RequestError).result?.message ?? null);
  } finally {
    setSectionPending(section, false);
    refreshDocumentRows(section);
  }
};

const clearDocumentDropPosition = (state: DragState): void => {
  state.section
    .querySelectorAll(`${itemSelector}.is-drop-before, ${itemSelector}.is-drop-after`)
    .forEach((row): void => row.classList.remove("is-drop-before", "is-drop-after"));
  state.items.classList.remove("is-drop-at-end");
  state.dropRow = null;
  state.dropPosition = null;
};

const getDocumentDropPosition = (
  state: DragState,
  targetRow: HTMLElement,
  clientY: number,
): "before" | "after" => {
  const bounds = targetRow.getBoundingClientRect();
  if (Number.isFinite(clientY) && bounds.height > 0) {
    return clientY < bounds.top + bounds.height / 2 ? "before" : "after";
  }
  const rows = Array.from(state.items.querySelectorAll(itemSelector));
  return rows.indexOf(state.row) < rows.indexOf(targetRow) ? "after" : "before";
};

const updateDocumentDropPosition = (
  state: DragState,
  target: Element,
  clientY: number,
): void => {
  clearDocumentDropPosition(state);
  const targetRow = target.closest<HTMLElement>(itemSelector);
  if (targetRow === state.row) {
    return;
  }
  if (targetRow !== null && targetRow.closest(itemsSelector) === state.items) {
    const position = getDocumentDropPosition(state, targetRow, clientY);
    targetRow.classList.add(position === "before" ? "is-drop-before" : "is-drop-after");
    state.dropRow = targetRow;
    state.dropPosition = position;
    return;
  }
  if (target.closest(itemsSelector) === state.items) {
    state.items.classList.add("is-drop-at-end");
    state.dropPosition = "end";
  }
};

/**
 * Sorting a list by dragging one of its rows.
 *
 * The drag is delegated on the plugin root and the state of the one drag in
 * progress is a variable of this call. It used to be a module level `WeakMap`
 * keyed by the root, which is what a function without an owner has to do to
 * hold per editor state; the owner exists now, and it is the closure of the
 * document editing this belongs to. Called exactly once per editor.
 */
const initializeDocumentDragAndDrop = (context: EditingContext): void => {
  const root = context.root;
  let dragState: DragState | null = null;

  const clearDragState = (): void => {
    if (dragState === null) {
      return;
    }
    clearDocumentDropPosition(dragState);
    dragState.items.classList.remove("is-drag-active");
    dragState.row.classList.remove("is-dragging");
    dragState = null;
  };

  root.addEventListener("dragstart", (event): void => {
    const target = event.target instanceof Element ? event.target : null;
    const handle = target?.closest<HTMLButtonElement>("[data-pe-document-drag]");
    const row = handle?.closest<HTMLElement>(itemSelector);
    const section = row?.closest<HTMLElement>(sectionSelector);
    const items = row?.closest<HTMLElement>(itemsSelector);
    if (
      handle === null ||
      handle === undefined ||
      handle.disabled ||
      row === null ||
      row === undefined ||
      section === null ||
      section === undefined ||
      items === null ||
      items === undefined ||
      section.dataset.sectionSortable !== "1"
    ) {
      return;
    }
    dragState = {
      section,
      items,
      row,
      handle,
      order: getDocumentOrder(section),
      dropRow: null,
      dropPosition: null,
    };
    items.classList.add("is-drag-active");
    row.classList.add("is-dragging");
    if (event.dataTransfer !== null) {
      event.dataTransfer.effectAllowed = "move";
      event.dataTransfer.setData("text/plain", row.dataset.itemUid ?? "");
      const bounds = row.getBoundingClientRect();
      const offsetX = Math.min(Math.max(event.clientX - bounds.left, 0), bounds.width);
      const offsetY = Math.min(Math.max(event.clientY - bounds.top, 0), bounds.height);
      event.dataTransfer.setDragImage(row, offsetX, offsetY);
    }
  });
  root.addEventListener("dragover", (event): void => {
    const state = dragState;
    const target = event.target instanceof Element ? event.target : null;
    if (state === null || target === null) {
      return;
    }
    if (target.closest(sectionSelector) !== state.section) {
      clearDocumentDropPosition(state);
      return;
    }
    event.preventDefault();
    updateDocumentDropPosition(state, target, event.clientY);
    if (event.dataTransfer !== null) {
      event.dataTransfer.dropEffect = "move";
    }
  });
  root.addEventListener("drop", (event): void => {
    const state = dragState;
    const target = event.target instanceof Element ? event.target : null;
    if (state === null || target?.closest(sectionSelector) !== state.section) {
      return;
    }
    event.preventDefault();
    updateDocumentDropPosition(state, target, event.clientY);
    if (state.dropRow !== null && state.dropPosition === "before") {
      state.dropRow.before(state.row);
    } else if (state.dropRow !== null && state.dropPosition === "after") {
      state.dropRow.after(state.row);
    } else if (state.dropPosition === "end") {
      state.items.append(state.row);
    }
    const previousOrder = state.order;
    const order = getDocumentOrder(state.section);
    const changed =
      order.length === previousOrder.length &&
      order.some((uid, index): boolean => uid !== previousOrder[index]);
    const section = state.section;
    clearDragState();
    refreshDocumentRows(section);
    if (changed) {
      void persistDocumentOrder(context, section, order, previousOrder);
    }
  });
  root.addEventListener("dragend", (): void => clearDragState());
};

/**
 * The state of the open document editor, the requests behind it, and the
 * element that renders it.
 *
 * ## Why the state is a plain object again
 *
 * A deep reactive proxy would update whenever one of its properties was
 * written, including a property nested two levels down. The elements do not
 * have that and do not need it: the element below updates when a *property of
 * the element* is assigned, so this object is the controller's own bookkeeping
 * and
 * `renderDocumentEditor()` is the one place it is handed over. Two things
 * follow, and both are improvements:
 *
 * - What causes a render is a single call site rather than every assignment
 *   anywhere in this file.
 * - The controller stays testable without a custom element registry. The
 *   behavioural suite drives these methods and asserts on this state, which is
 *   what an element-free test can assert on.
 */
export const createDocumentEditing = (
  editingTarget: EditingTarget,
): DocumentEditingController => {
  const context = toEditingContext(editingTarget);
  const root = context.root;
  const documentState: DocumentState = {
    contactEmptyMessage: context.messages.contractContactEmpty ?? "",
    contactSections: [],
    deleteConfirmation: context.messages.documentDeleteConfirm ?? "",
    error: "",
    errors: {},
    fields: [],
    kind: "document",
    mode: "view",
    open: false,
    pending: false,
    record: null,
    section: "",
    target: "",
    title: "",
    values: {},
  };
  const contractContactState: ContractContactState = {
    deleteConfirmation: context.messages.contractContactDeleteConfirm ?? "",
    error: "",
    errors: {},
    fields: [],
    mode: "view",
    open: false,
    pending: false,
    record: null,
    section: "",
    title: "",
    values: {},
  };
  let activeSection: HTMLElement | null = null;
  let trigger: HTMLElement | null = null;
  let contractContactTrigger: HTMLElement | null = null;
  let rowPendingRemoval: HTMLElement | null = null;
  let sectionPendingRefresh: HTMLElement | null = null;
  let editorElement: ProfileDocumentEditorElement | null = null;

  /** Hands the state to the element. The one place a render is caused. */
  const renderDocumentEditor = (): void => {
    const element = editorElement;
    if (element === null) {
      return;
    }
    // A fresh object every time, and that is the mechanism rather than a copy
    // for its own sake: the element updates on the assignment of a property and
    // never on a write inside the object it already holds, and every method
    // below writes this state field by field.
    element.contactEditor = { ...contractContactState };
    element.contactEmptyMessage = documentState.contactEmptyMessage;
    element.contactSections = documentState.contactSections;
    element.deleteConfirmation = documentState.deleteConfirmation;
    element.error = documentState.error;
    element.errors = documentState.errors;
    element.fields = documentState.fields;
    element.heading = documentState.title;
    element.kind = documentState.kind;
    element.mode = documentState.mode;
    element.pending = documentState.pending;
    element.record = documentState.record;
    element.values = documentState.values;
    // Last, because it is the one that starts a transition: the element has to
    // know what it is showing before it is told to show it.
    element.open = documentState.open;
  };

  /**
   * Creates the editor inside the collapse target of the row or of the section.
   *
   * The element is created where it belongs rather than rendered elsewhere and
   * moved there. The target keeps its generated id, because the trigger's
   * `aria-controls` needs one.
   *
   * Nothing is ever *moved*: a move disconnects and reconnects the element,
   * which would destroy and recreate the CKEditor instances below it.
   */
  const createDocumentEditor = (target: HTMLElement): void => {
    registerProfileDocumentEditorElement();
    registerProfileRichTextElement();
    registerProfileContractContactsElement();
    const element = document.createElement(
      profileDocumentEditorElementName,
    ) as ProfileDocumentEditorElement;
    element.context = context;
    element.addEventListener(documentEditorCloseEvent, (): void => closeDocument());
    element.addEventListener(documentEditorSubmitEvent, (): void => {
      void submitDocument();
    });
    element.addEventListener(documentEditorInputEvent, (event: Event): void => {
      const detail = (event as CustomEvent<ProfileDocumentEditorInputDetail>).detail;
      // Written without a render on purpose: the control the visitor is typing
      // in already shows the value, and re-rendering the form on every
      // keystroke is what a controlled input does not have to do. The `live()`
      // bindings of the element realign the controls with this object the next
      // time something else does cause a render.
      documentState.values = { ...documentState.values, [detail.name]: detail.value };
    });
    element.addEventListener(documentEditorClosedEvent, (): void => {
      // The element that fired, never whatever `editorElement` happens to be,
      // and never the plugin root. Closing does not set `pending`, so a reopen
      // is accepted while the previous panel is still animating out - the leave
      // transition is 0.3s and one `documentForm` request fits into it - and
      // reading `editorElement` here then tears down the editors of the panel
      // the visitor just opened and takes it out of the document. The `?? root`
      // fallback that stood here was worse still: the plugin root is the one
      // scope `destroyRichTextEditors()` must never be given, because every
      // permanent profile field carries a `[data-pe-rich-text]` textarea.
      const superseded = editorElement !== element;
      if (!superseded) {
        editorElement = null;
      }
      finishDocumentClose(element, !superseded);
      element.remove();
    });
    editorElement = element;
    renderDocumentEditor();
    target.replaceChildren(element);
  };

  const initializeDocumentEditors = async (): Promise<void> => {
    // The markup exists once the element has updated, which is what
    // "updateComplete" answers and what `nextTick()` used to be.
    await editorElement?.updateComplete;
    const view = root.querySelector<HTMLElement>(documentViewSelector);
    if (view === null) {
      return;
    }
    initializePopover(view);
    await Promise.all(
      Array.from(
        view.querySelectorAll<HTMLTextAreaElement>("textarea[data-pe-rich-text]"),
      ).map((field) => ensureRichTextEditor(context, field)),
    );
    if (documentState.mode === "add" || documentState.mode === "edit") {
      const firstField = view.querySelector<
        HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement
      >("[data-pe-document-field]:not([disabled])");
      if (
        firstField instanceof HTMLTextAreaElement &&
        firstField.matches("[data-pe-rich-text]")
      ) {
        const editor = await ensureRichTextEditor(context, firstField);
        editor?.editing.view.focus();
      } else {
        firstField?.focus();
      }
      return;
    }
    view.querySelector<HTMLElement>("[data-pe-document-heading]")?.focus();
  };

  const collapseDocument = (): void => {
    contractContactState.open = false;
    documentState.open = false;
    if (trigger !== null) {
      setExpanded(trigger, false);
    }
    renderDocumentEditor();
  };

  /**
   * Takes an editor that could not be brought up out of the document again.
   *
   * The element is half built - it has no panel, and its state says it is
   * open - so every later render of it would fail exactly as the first one
   * did. Whatever threw is reported once, by the caller, and the section goes
   * back to where it was before the button was pressed.
   */
  const discardDocumentEditor = (): void => {
    const element = editorElement;
    if (element === null) {
      return;
    }
    editorElement = null;
    documentState.open = false;
    contractContactState.open = false;
    if (trigger !== null) {
      setExpanded(trigger, false);
    }
    // The teardown of a close, and for the same reasons: the field state of
    // an editor that is gone belongs to nothing, and the caret is in a
    // control that is about to leave the document. There is nothing to
    // destroy below the element - the panel is what failed to be built - and
    // that path is idempotent anyway.
    finishDocumentClose(element);
    element.remove();
  };

  const openDocument = async (modeValue: string, event: Event): Promise<void> => {
    if (!isDocumentMode(modeValue) || documentState.pending) {
      return;
    }
    const button = event.currentTarget instanceof HTMLButtonElement
      ? event.currentTarget
      : event.target instanceof Element
        ? event.target.closest<HTMLButtonElement>("button")
        : null;
    const section = button?.closest<HTMLElement>(sectionSelector);
    if (button === null || section === null || section === undefined) {
      return;
    }
    if (
      documentState.open &&
      trigger === button &&
      documentState.mode === modeValue
    ) {
      collapseDocument();
      return;
    }
    const collapseTarget = getDocumentCollapseTarget(button, section, modeValue);
    if (collapseTarget === null) {
      return;
    }
    const record = modeValue === "add" ? null : getRecordFromButton(button);
    if (modeValue !== "add" && record === null) {
      return;
    }
    documentState.pending = true;
    documentState.error = "";
    documentState.errors = {};
    // Whether the failure below happened before or after the editor was put in
    // the document. A refused `documentForm` request leaves the editor that is
    // open where it is; a refusal of the update that follows leaves one behind
    // that cannot render.
    let created = false;
    try {
      const response = await requestDocument(context, context.urls.documentForm, {
        section: section.dataset.sectionKey,
        record: record ?? 0,
        mode: modeValue,
      });
      const fields = getResponseFields(response.fields);
      activeSection = section;
      documentState.fields = fields;
      documentState.contactSections = getResponseContactSections(
        response.contactSections,
      );
      documentState.kind =
        section.dataset.sectionKind === "contract" ? "contract" : "document";
      documentState.mode = modeValue;
      documentState.record =
        typeof response.record === "number" ? response.record : record;
      documentState.section = section.dataset.sectionKey ?? "";
      documentState.title = [
        getModeLabel(context, modeValue),
        getDocumentSubject(section, fields),
      ]
        .filter(Boolean)
        .join(": ");
      documentState.values = Object.fromEntries(
        fields.map((field): [string, DocumentValue] => [field.name, field.value]),
      );
      if (trigger !== null) {
        setExpanded(trigger, false);
      }
      trigger = button;
      documentState.target = getDocumentCollapseTargetSelector(collapseTarget);
      button.setAttribute("aria-controls", collapseTarget.id);
      setExpanded(button, true);
      documentState.open = true;
      documentState.pending = false;
      createDocumentEditor(collapseTarget);
      created = true;
      await initializeDocumentEditors();
    } catch (error) {
      // An editor that came up and then threw is discarded rather than left
      // standing. `Partials/Profile/Prototypes.html` is an override point and
      // an override that drops a prototype fails in the element's update, so
      // this is reachable without a single line of this repository being
      // wrong.
      if (created) {
        discardDocumentEditor();
      }
      showStatus(context, "danger", (error as RequestError).result?.message ?? null);
    } finally {
      documentState.pending = false;
      renderDocumentEditor();
    }
  };

  const closeDocument = (): void => {
    if (documentState.pending) {
      return;
    }
    collapseDocument();
  };

  /**
   * The close of one editor, once its leave transition is over.
   *
   * `restoreState` is false for an editor that a reopen superseded while it was
   * still animating: the rows it left behind are still this call's to flush,
   * but the trigger, the target and the field state belong to the editor that
   * is open now.
   */
  const finishDocumentClose = (element: Element, restoreState = true): void => {
    const focusTarget = trigger;
    // The scope is the subtree that is going away, never the plugin root. The
    // profile fields render a permanent `[data-pe-rich-text]` textarea each,
    // and a query that started at the root would destroy a field editor the
    // visitor still has open - while destroying none of the closing view once
    // it has been detached. A CKEditor that is dropped without being destroyed
    // keeps its window and document listeners until it is collected.
    //
    // The rich text elements of the closing subtree destroy their own editors
    // when they are disconnected, so this and that are two paths to the same
    // end. Both are idempotent: `destroyRichTextEditors()` forgets an editor
    // before it awaits its `destroy()`, so whichever runs second finds none.
    void destroyRichTextEditors(element);
    rowPendingRemoval?.remove();
    if (sectionPendingRefresh !== null) {
      refreshDocumentRows(sectionPendingRefresh);
    }
    rowPendingRemoval = null;
    sectionPendingRefresh = null;
    if (!restoreState) {
      return;
    }
    activeSection = null;
    trigger = null;
    documentState.target = "";
    documentState.fields = [];
    documentState.contactSections = [];
    documentState.values = {};
    contractContactState.fields = [];
    contractContactState.values = {};
    if (focusTarget?.isConnected === true) {
      focusTarget.focus({ preventScroll: true });
    }
  };

  const collectDocumentValues = (): Record<string, DocumentValue> => {
    const values = { ...documentState.values };
    const view = root.querySelector<HTMLElement>(documentViewSelector);
    documentState.fields.forEach((field): void => {
      if (!field.richText || view === null) {
        return;
      }
      const control = view.querySelector<HTMLTextAreaElement>(
        `[data-pe-document-field="${CSS.escape(field.name)}"]`,
      );
      if (control !== null) {
        values[field.name] = getRichTextEditorValue(control) ?? control.value;
      }
    });
    return values;
  };

  const submitDocument = async (): Promise<void> => {
    if (documentState.pending || documentState.mode === "view" || activeSection === null) {
      return;
    }
    const form = root.querySelector<HTMLFormElement>("[data-pe-document-form]");
    if (documentState.mode !== "delete" && form !== null && !form.reportValidity()) {
      return;
    }
    const endpoint =
      documentState.mode === "add"
        ? context.urls.createDocument
        : documentState.mode === "edit"
          ? context.urls.updateDocument
          : context.urls.deleteDocument;
    const data: Record<string, unknown> = { section: documentState.section };
    if (documentState.mode !== "add") {
      data.record = documentState.record;
    }
    if (documentState.mode !== "delete") {
      data.fields = collectDocumentValues();
    }
    documentState.pending = true;
    documentState.error = "";
    documentState.errors = {};
    renderDocumentEditor();
    showStatus(context, "info", context.messages.saving ?? null);
    try {
      const response = await requestDocument(context, endpoint, data);
      const item = response.item as DocumentItem | undefined;
      if (documentState.mode === "add" && item !== undefined) {
        insertDocumentRow(activeSection, item);
      } else if (documentState.mode === "edit" && item !== undefined) {
        const row = activeSection.querySelector<HTMLElement>(
          `${itemSelector}[data-item-uid="${CSS.escape(String(documentState.record))}"]`,
        );
        if (row !== null) {
          updateDocumentRow(row, item);
        }
        refreshDocumentRows(activeSection);
      } else if (documentState.mode === "delete") {
        rowPendingRemoval = activeSection.querySelector<HTMLElement>(
          `${itemSelector}[data-item-uid="${CSS.escape(String(documentState.record))}"]`,
        );
        sectionPendingRefresh = activeSection;
      }
      const successMessage =
        documentState.mode === "delete"
          ? context.messages.documentDeleted
          : context.messages.documentSaved;
      documentState.pending = false;
      closeDocument();
      showStatus(context, "success", successMessage ?? null);
    } catch (error) {
      const result = (error as RequestError).result;
      documentState.error = result?.message ?? context.messages.errorMessage ?? "";
      documentState.errors = Object.fromEntries(
        Object.entries(result?.errors ?? {}).map(([name, messages]): [string, string] => [
          name,
          Array.isArray(messages) ? messages.map(String).join(" ") : String(messages),
        ]),
      );
    } finally {
      documentState.pending = false;
      renderDocumentEditor();
    }
  };

  const sortDocument = async (direction: string, event: Event): Promise<void> => {
    const button = event.currentTarget instanceof HTMLButtonElement
      ? event.currentTarget
      : event.target instanceof Element
        ? event.target.closest<HTMLButtonElement>("button")
        : null;
    const section = button?.closest<HTMLElement>(sectionSelector);
    const record = button === null ? null : getRecordFromButton(button);
    if (
      button === null ||
      section === null ||
      section === undefined ||
      record === null ||
      !["up", "down"].includes(direction)
    ) {
      return;
    }
    setSectionPending(section, true);
    try {
      const response = await requestDocument(context, context.urls.sortDocument, {
        section: section.dataset.sectionKey,
        record,
        direction,
      });
      applyDocumentOrder(
        section,
        Array.isArray(response.order) ? response.order.map(Number) : [],
      );
      showStatus(context, "success", context.messages.documentSorted ?? null);
    } catch (error) {
      showStatus(context, "danger", (error as RequestError).result?.message ?? null);
    } finally {
      setSectionPending(section, false);
      refreshDocumentRows(section);
    }
  };

  const openContractContact = async (
    modeValue: string,
    section: string,
    event: Event,
    record = 0,
  ): Promise<void> => {
    if (
      !isDocumentMode(modeValue) ||
      documentState.record === null ||
      contractContactState.pending
    ) {
      return;
    }
    const normalizedRecord = modeValue === "add" ? null : record;
    if (modeValue !== "add" && (!Number.isInteger(record) || record <= 0)) {
      return;
    }
    // "HTMLButtonElement" and not "HTMLElement", exactly as "openDocument()"
    // reads it: the listener is delegated on the plugin root now, so
    // "currentTarget" is that root during the dispatch and a wider test would
    // make the whole editor the control focus returns to.
    const button = event.currentTarget instanceof HTMLButtonElement
      ? event.currentTarget
      : event.target instanceof Element
        ? event.target.closest<HTMLElement>("button")
        : null;
    if (
      contractContactState.open &&
      contractContactState.mode === modeValue &&
      contractContactState.section === section &&
      contractContactState.record === normalizedRecord
    ) {
      closeContractContact();
      return;
    }
    contractContactState.pending = true;
    contractContactState.error = "";
    contractContactState.errors = {};
    try {
      const response = await requestDocument(
        context,
        context.urls.contractContactForm,
        {
          contract: documentState.record,
          section,
          record: normalizedRecord ?? 0,
          mode: modeValue,
        },
      );
      const fields = getResponseFields(response.fields);
      contractContactState.fields = fields;
      contractContactState.mode = modeValue;
      contractContactState.record =
        typeof response.record === "number" ? response.record : normalizedRecord;
      contractContactState.section = section;
      contractContactState.title = [
        getModeLabel(context, modeValue),
        String(response.title ?? ""),
      ]
        .filter(Boolean)
        .join(": ");
      contractContactState.values = Object.fromEntries(
        fields.map((field): [string, DocumentValue] => [field.name, field.value]),
      );
      contractContactTrigger = button;
      contractContactState.open = true;
      // Before the render, and that is the whole reason the line is here: the
      // controls are disabled while a request runs, and the focus below looks
      // for the first one that is not. Writing it in the "finally" below would
      // bring the editor up enabled but unfocused; the element updates when
      // this function says so, so it says so once - with the request over.
      contractContactState.pending = false;
      renderDocumentEditor();
      await editorElement?.updateComplete;
      const editor = root.querySelector<HTMLElement>(
        "[data-pe-contract-contact-editor]",
      );
      if (editor !== null) {
        initializePopover(editor);
        editor.scrollIntoView({ behavior: "smooth", block: "nearest" });
        const focusTarget =
          contractContactState.mode === "add" || contractContactState.mode === "edit"
            ? editor.querySelector<HTMLElement>(
                "input:not([disabled]), select:not([disabled])",
              )
            : editor.querySelector<HTMLElement>("[data-pe-contract-contact-heading]");
        focusTarget?.focus({ preventScroll: true });
      }
    } catch (error) {
      showStatus(context, "danger", (error as RequestError).result?.message ?? null);
    } finally {
      contractContactState.pending = false;
      renderDocumentEditor();
    }
  };

  const closeContractContact = (): void => {
    if (!contractContactState.pending) {
      contractContactState.open = false;
      const focusTarget = contractContactTrigger;
      contractContactTrigger = null;
      renderDocumentEditor();
      void Promise.resolve(editorElement?.updateComplete).then((): void => {
        if (focusTarget?.isConnected === true) {
          focusTarget.focus({ preventScroll: true });
        }
      });
    }
  };

  const submitContractContact = async (): Promise<void> => {
    if (
      contractContactState.pending ||
      contractContactState.mode === "view" ||
      documentState.record === null
    ) {
      return;
    }
    const form = root.querySelector<HTMLFormElement>("[data-pe-document-form]");
    if (
      contractContactState.mode !== "delete" &&
      form !== null &&
      !form.reportValidity()
    ) {
      return;
    }
    const endpoint =
      contractContactState.mode === "add"
        ? context.urls.createContractContact
        : contractContactState.mode === "edit"
          ? context.urls.updateContractContact
          : context.urls.deleteContractContact;
    const data: Record<string, unknown> = {
      contract: documentState.record,
      section: contractContactState.section,
    };
    if (contractContactState.mode !== "add") {
      data.record = contractContactState.record;
    }
    if (contractContactState.mode !== "delete") {
      data.fields = { ...contractContactState.values };
    }
    contractContactState.pending = true;
    contractContactState.error = "";
    contractContactState.errors = {};
    renderDocumentEditor();
    showStatus(context, "info", context.messages.saving ?? null);
    try {
      const response = await requestDocument(context, endpoint, data);
      const item = asContractContactItem(response.item);
      const record = contractContactState.record;
      const mode = contractContactState.mode;
      // Reassigned rather than spliced. The list is a property of the contacts
      // element, and its setter compares by identity: a "push()" into the array
      // it already holds changes what a visitor would see and nothing that
      // would make it update.
      documentState.contactSections = replaceContactItems(
        documentState.contactSections,
        contractContactState.section,
        (items): ContractContactItem[] => {
          if (mode === "add" && item !== null) {
            return [...items, item];
          }
          if (mode === "edit" && item !== null) {
            return items.map((candidate): ContractContactItem =>
              candidate.uid === record ? item : candidate,
            );
          }
          if (mode === "delete") {
            return items.filter(
              (candidate): boolean => candidate.uid !== record,
            );
          }

          return items;
        },
      );
      contractContactState.pending = false;
      contractContactState.open = false;
      renderDocumentEditor();
      showStatus(
        context,
        "success",
        contractContactState.mode === "delete"
          ? context.messages.documentDeleted ?? null
          : context.messages.documentSaved ?? null,
      );
    } catch (error) {
      const result = (error as RequestError).result;
      contractContactState.error =
        result?.message ?? context.messages.errorMessage ?? "";
      contractContactState.errors = Object.fromEntries(
        Object.entries(result?.errors ?? {}).map(
          ([name, messages]): [string, string] => [
            name,
            Array.isArray(messages)
              ? messages.map(String).join(" ")
              : String(messages),
          ],
        ),
      );
    } finally {
      contractContactState.pending = false;
      renderDocumentEditor();
    }
  };

  const sortContractContact = async (
    direction: string,
    sectionIdentifier: string,
    record: number,
  ): Promise<void> => {
    if (
      contractContactState.pending ||
      documentState.record === null ||
      !["up", "down"].includes(direction)
    ) {
      return;
    }
    const section = documentState.contactSections.find(
      (candidate): boolean => candidate.identifier === sectionIdentifier,
    );
    if (section === undefined) {
      return;
    }
    contractContactState.pending = true;
    renderDocumentEditor();
    try {
      const response = await requestDocument(
        context,
        context.urls.sortContractContact,
        {
          contract: documentState.record,
          section: sectionIdentifier,
          record,
          direction,
        },
      );
      const itemsByUid = new Map(
        section.items.map((item): [number, ContractContactItem] => [item.uid, item]),
      );
      const order = Array.isArray(response.order)
        ? response.order.map(Number)
        : [];
      const sortedItems = order.flatMap((uid): ContractContactItem[] => {
        const item = itemsByUid.get(uid);
        return item === undefined ? [] : [item];
      });
      if (sortedItems.length === section.items.length) {
        // A new item object per position rather than a write into the one that
        // moved, for the same reason as above and one more: `repeat()` keys the
        // rows by uid, so the objects it holds are compared by identity when it
        // decides which node moves where.
        documentState.contactSections = replaceContactItems(
          documentState.contactSections,
          sectionIdentifier,
          (): ContractContactItem[] =>
            sortedItems.map((item, index): ContractContactItem => ({
              ...item,
              sorting: (index + 1) * 10,
            })),
        );
      }
      renderDocumentEditor();
      showStatus(context, "success", context.messages.documentSorted ?? null);
    } catch (error) {
      showStatus(context, "danger", (error as RequestError).result?.message ?? null);
    } finally {
      contractContactState.pending = false;
      renderDocumentEditor();
    }
  };

  /**
   * Hides a contact in the frontend, or shows it again.
   *
   * The flag is the record's own `hidden` column, and the row is replaced
   * with what the server answers rather than flipped locally: the list is a
   * property of the contacts element and compares by identity, and the
   * element rebuilds a row whose `hidden` changed - which is why the caret is
   * put back on the toggle of the rebuilt row afterwards.
   */
  const toggleContractContactVisibility = async (
    sectionIdentifier: string,
    record: number,
  ): Promise<void> => {
    if (contractContactState.pending || documentState.record === null) {
      return;
    }
    const section = documentState.contactSections.find(
      (candidate): boolean => candidate.identifier === sectionIdentifier,
    );
    const item = section?.items.find((candidate): boolean => candidate.uid === record);
    if (section === undefined || item === undefined) {
      return;
    }
    const hidden = !item.hidden;
    contractContactState.pending = true;
    renderDocumentEditor();
    try {
      const response = await requestDocument(
        context,
        context.urls.toggleContractContactVisibility,
        {
          contract: documentState.record,
          section: sectionIdentifier,
          record,
          hidden,
        },
      );
      const stored = asContractContactItem(response.item) ?? { ...item, hidden };
      documentState.contactSections = replaceContactItems(
        documentState.contactSections,
        sectionIdentifier,
        (items): ContractContactItem[] =>
          items.map((candidate): ContractContactItem =>
            candidate.uid === record ? stored : candidate,
          ),
      );
      contractContactState.pending = false;
      renderDocumentEditor();
      showStatus(
        context,
        "success",
        (hidden
          ? context.messages.contractContactHidden
          : context.messages.contractContactShown) ?? null,
      );
      await editorElement?.updateComplete;
      root
        .querySelector<HTMLElement>(
          `[data-pe-contract-contact-item="${CSS.escape(String(record))}"] ` +
            "[data-pe-contract-contact-hide]",
        )
        ?.focus({ preventScroll: true });
    } catch (error) {
      showStatus(context, "danger", (error as RequestError).result?.message ?? null);
    } finally {
      contractContactState.pending = false;
      renderDocumentEditor();
    }
  };

  /**
   * The controls of the contact list, which
   * `<academic-persons-edit-contract-contacts>` renders.
   *
   * Delegated on the plugin root rather than bound on the element, and both
   * halves of that are deliberate. The element is created by the document
   * editor's *template*, so this controller never holds it and has nothing to
   * bind to; and `openContractContact()` reads the pressed button off the
   * event, because that is where focus returns to when the editor closes - a
   * custom event of the element would have to carry the button back anyway. It
   * is the same mechanism the document list itself uses, and the two selectors
   * are disjoint, so the two listeners never see each other's buttons.
   */
  const onContractContactClick = (event: Event): void => {
    const target = event.target instanceof Element ? event.target : null;
    const button = target?.closest<HTMLButtonElement>(
      "[data-pe-contract-contact-add], [data-pe-contract-contact-view], " +
        "[data-pe-contract-contact-edit], [data-pe-contract-contact-delete], " +
        "[data-pe-contract-contact-sort], [data-pe-contract-contact-hide], " +
        "[data-pe-contract-contact-cancel], [data-pe-contract-contact-save]",
    );
    if (button === null || button === undefined || button.disabled) {
      return;
    }
    if (button.matches("[data-pe-contract-contact-cancel]")) {
      closeContractContact();
      return;
    }
    if (button.matches("[data-pe-contract-contact-save]")) {
      void submitContractContact();
      return;
    }
    const sectionElement = button.closest<HTMLElement>(
      "[data-pe-contract-contact-section]",
    );
    const itemElement = button.closest<HTMLElement>("[data-pe-contract-contact-item]");
    const section =
      sectionElement === null ? "" : (hooks(sectionElement).peContractContactSection ?? "");
    const record = Number(
      itemElement === null ? 0 : (hooks(itemElement).peContractContactItem ?? 0),
    );
    const direction = hooks(button).peContractContactSort;
    if (direction !== undefined) {
      void sortContractContact(direction, section, record);
      return;
    }
    if (button.matches("[data-pe-contract-contact-hide]")) {
      void toggleContractContactVisibility(section, record);
      return;
    }
    const mode = button.matches("[data-pe-contract-contact-add]")
      ? "add"
      : button.matches("[data-pe-contract-contact-view]")
        ? "view"
        : button.matches("[data-pe-contract-contact-edit]")
          ? "edit"
          : "delete";
    void openContractContact(mode, section, event, record);
  };

  /**
   * What the visitor typed into a contact field.
   *
   * Written without a render, exactly as the document editor's own input is:
   * the control already shows the value, and re-rendering the form on every
   * keystroke is what a controlled input does not have to do. The `live()`
   * bindings of the element realign the controls with this object the next time
   * something else does cause a render.
   */
  const onContractContactInput = (event: Event): void => {
    const control = event.target;
    if (!isEditableField(control)) {
      return;
    }
    const name = hooks(control).peContractContactField;
    if (name === undefined) {
      return;
    }
    const value =
      control instanceof HTMLInputElement && control.type === "checkbox"
        ? control.checked
        : control.value;
    contractContactState.values = { ...contractContactState.values, [name]: value };
  };

  // One drag per editor, and the listeners are the editor's for as long as it
  // lives. Registered here rather than in `initializeDocumentSections()`,
  // which is what freed the module of the `WeakMap`s that used to pair a root
  // element with the controller and the drag in progress belonging to it.
  initializeDocumentDragAndDrop(context);
  root.addEventListener("click", onContractContactClick);
  // Both, because a select reports a "change" and a text field an "input", and
  // the contact forms carry either.
  root.addEventListener("input", onContractContactInput);
  root.addEventListener("change", onContractContactInput);
  root.addEventListener("click", (event): void => {
    const target = event.target instanceof Element ? event.target : null;
    const button = target?.closest<HTMLButtonElement>(
      "[data-pe-document-add], [data-pe-document-view], [data-pe-document-edit], " +
        "[data-pe-document-delete], [data-pe-document-sort]",
    );
    if (button === null || button === undefined || button.disabled) {
      return;
    }
    const direction = hooks(button).peDocumentSort;
    if (direction !== undefined) {
      void sortDocument(direction, event);
      return;
    }
    const mode = button.matches("[data-pe-document-add]")
      ? "add"
      : button.matches("[data-pe-document-view]")
        ? "view"
        : button.matches("[data-pe-document-edit]")
          ? "edit"
          : "delete";
    void openDocument(mode, event);
  });

  return {
    contractContact: contractContactState,
    document: documentState,
    openDocument,
    closeDocument,
    finishDocumentClose,
    submitDocument,
    openContractContact,
    closeContractContact,
    submitContractContact,
    sortContractContact,
    sortDocument,
    toggleContractContactVisibility,
  };
};

/**
 * The row bookkeeping of every section: the position labels, the disabled ends
 * of the sort buttons, the striping, the drag handles and the empty state.
 *
 * Called after the markup of the editor is in the document. It no longer
 * registers anything - the delegated click and the drag and drop belong to the
 * controller of `createDocumentEditing()` - so calling it again is a refresh
 * and not a second wiring, and there is nothing left to remember which roots
 * have already been seen.
 */
export const initializeDocumentSections = (
  editingTarget: EditingTarget,
): void => {
  toEditingContext(editingTarget)
    .root.querySelectorAll<HTMLElement>(sectionSelector)
    .forEach(refreshDocumentRows);
};
