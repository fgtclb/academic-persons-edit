/* Generated from Resources/Private/TypeScript — do not edit. */
import {
  hooks,
  initializePopover,
  isEditableField,
  requestJson,
  setDisabled,
  setExpanded,
  showStatus
} from "@fgtclb/academic-persons-edit/frontend/profile/common.js";
import {
  toEditingContext
} from "@fgtclb/academic-persons-edit/frontend/profile/context.js";
import {
  documentEditorClosedEvent,
  documentEditorCloseEvent,
  documentEditorInputEvent,
  documentEditorSubmitEvent,
  registerProfileDocumentEditorElement
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
  parseRichTextPreview
} from "@fgtclb/academic-persons-edit/frontend/profile/rich-text.js";
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
const isDocumentMode = (value) => ["add", "view", "edit", "delete"].includes(value);
const getDocumentRows = (section) => {
  const items = section.querySelector(itemsSelector);
  if (items === null) {
    return [];
  }
  return Array.from(items.children).filter(
    (element) => element instanceof HTMLElement && element.matches(itemSelector)
  );
};
const asDocumentField = (value) => {
  if (typeof value !== "object" || value === null) {
    return null;
  }
  const field = value;
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
    value: field.value ?? ""
  };
};
const getResponseFields = (value) => Array.isArray(value) ? value.map(asDocumentField).filter((field) => field !== null) : [];
const asRecord = (value) => typeof value === "object" && value !== null ? value : {};
const asContractContactItem = (value) => {
  const item = asRecord(value);
  const uid = Number(item.uid);
  if (!Number.isInteger(uid) || uid <= 0) {
    return null;
  }
  return {
    display: asRecord(item.display),
    hidden: item.hidden === true,
    sorting: Number(item.sorting) || 0,
    summary: Array.isArray(item.summary) ? item.summary.map((entry) => {
      const summary = asRecord(entry);
      return {
        label: String(summary.label ?? ""),
        value: String(summary.value ?? "")
      };
    }) : [],
    uid,
    values: asRecord(item.values)
  };
};
const getResponseContactSections = (value) => Array.isArray(value) ? value.flatMap((entry) => {
  const section = asRecord(entry);
  const identifier = String(section.identifier ?? "");
  if (identifier === "") {
    return [];
  }
  return [{
    identifier,
    items: Array.isArray(section.items) ? section.items.map(asContractContactItem).filter((item) => item !== null) : [],
    label: String(section.label ?? identifier),
    singularLabel: String(section.singularLabel ?? section.label ?? identifier)
  }];
}) : [];
const replaceContactItems = (sections, identifier, items) => sections.map(
  (section) => section.identifier === identifier ? { ...section, items: items(section.items) } : section
);
const getSectionHeading = (section) => {
  var _a, _b;
  return ((_b = (_a = section.querySelector("h2")) == null ? void 0 : _a.textContent) == null ? void 0 : _b.trim()) ?? "";
};
const getDocumentSubject = (section, fields) => {
  const titleField = fields.find((field) => field.name === "title");
  const title = [titleField == null ? void 0 : titleField.displayValue, titleField == null ? void 0 : titleField.value].map((value) => String(value ?? "").trim()).find((value) => value !== "");
  return title ?? getSectionHeading(section);
};
const getModeLabel = (context, mode) => {
  const labels = {
    add: context.labels.documentAdd,
    view: context.labels.documentView,
    edit: context.labels.documentEdit,
    delete: context.labels.documentDelete
  };
  return labels[mode] ?? "";
};
const getRecordFromButton = (button) => {
  const row = button.closest(itemSelector);
  const record = Number.parseInt((row == null ? void 0 : row.dataset.itemUid) ?? "", 10);
  return Number.isInteger(record) && record > 0 ? record : null;
};
const getDocumentCollapseTarget = (button, section, mode) => {
  var _a;
  return mode === "add" ? section.querySelector(addCollapseTargetSelector) : ((_a = button.closest(itemSelector)) == null ? void 0 : _a.querySelector(itemCollapseTargetSelector)) ?? null;
};
const getDocumentCollapseTargetSelector = (target) => {
  if (target.id === "") {
    collapseTargetSequence += 1;
    target.id = `profile-editing-document-collapse-${collapseTargetSequence}`;
  }
  return `#${CSS.escape(target.id)}`;
};
const requestDocument = (context, url, data) => {
  const profile = context.profileUid;
  if (url === void 0 || profile === null) {
    return Promise.reject(new Error("The document endpoint is unavailable."));
  }
  return requestJson(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ profile, data })
  });
};
const appendRichText = (container, value) => {
  const parsedDocument = parseRichTextPreview(value);
  const fragment = document.createDocumentFragment();
  Array.from(parsedDocument.body.childNodes).forEach((node) => {
    fragment.append(document.importNode(node, true));
  });
  container.replaceChildren(fragment);
};
const getRowDisplayValue = (item, name) => {
  const display = item.display ?? {};
  if (name === "yearStart" && !display.yearStart) {
    return display.year ?? "";
  }
  if (name === "year" && !display.year) {
    return display.yearStart ?? "";
  }
  return display[name] ?? "";
};
const renderDocumentTitle = (container, title, link) => {
  const normalizedTitle = title || "\u2014";
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
const updateDocumentRow = (row, item) => {
  var _a, _b;
  row.dataset.itemUid = String(item.uid ?? "");
  row.dataset.itemSorting = String(item.sorting ?? "");
  row.querySelectorAll("[data-pe-document-value]").forEach(
    (element) => {
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
      element.textContent = String(value || "\u2014");
    }
  );
  const title = row.querySelector("[data-pe-document-title]");
  if (title !== null) {
    renderDocumentTitle(
      title,
      String(((_a = item.display) == null ? void 0 : _a.title) ?? ""),
      (_b = item.values) == null ? void 0 : _b.link
    );
  }
};
const refreshDocumentRows = (section) => {
  const rows = getDocumentRows(section);
  const sortable = section.dataset.sectionSortable === "1" && rows.length > 1;
  rows.forEach((row, index) => {
    row.dataset.itemPosition = String(index);
    const up = row.querySelector(
      '[data-pe-document-sort="up"]'
    );
    const down = row.querySelector(
      '[data-pe-document-sort="down"]'
    );
    const drag = row.querySelector("[data-pe-document-drag]");
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
  const emptyState = section.querySelector(emptyStateSelector);
  emptyState == null ? void 0 : emptyState.classList.toggle("d-none", rows.length > 0);
  const listHeader = section.querySelector(listHeaderSelector);
  listHeader == null ? void 0 : listHeader.classList.toggle("d-md-flex", rows.length > 0);
};
const insertDocumentRow = (section, item) => {
  const template = section.querySelector(itemTemplateSelector);
  const items = section.querySelector(itemsSelector);
  const templateRow = template == null ? void 0 : template.querySelector(itemSelector);
  if (items === null || templateRow === null || templateRow === void 0) {
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
const getDocumentOrder = (section) => Array.from(
  getDocumentRows(section),
  (row) => Number.parseInt(row.dataset.itemUid ?? "", 10)
).filter((uid) => Number.isInteger(uid) && uid > 0);
const applyDocumentOrder = (section, order) => {
  const items = section.querySelector(itemsSelector);
  if (items === null) {
    return;
  }
  const rowsByUid = new Map(
    getDocumentRows(section).map(
      (row) => [row.dataset.itemUid, row]
    )
  );
  order.forEach((uid) => {
    const row = rowsByUid.get(String(uid));
    if (row !== void 0) {
      items.append(row);
    }
  });
  refreshDocumentRows(section);
};
const setSectionPending = (section, pending) => {
  section.setAttribute("aria-busy", String(pending));
  section.style.cursor = pending ? "wait" : "";
  section.querySelectorAll("button").forEach((button) => {
    if (pending) {
      if (hooks(button).peDocumentWasDisabled === void 0) {
        hooks(button).peDocumentWasDisabled = button.disabled ? "1" : "0";
      }
      button.disabled = true;
    } else if (hooks(button).peDocumentWasDisabled !== void 0) {
      button.disabled = hooks(button).peDocumentWasDisabled === "1";
      delete hooks(button).peDocumentWasDisabled;
    }
  });
};
const persistDocumentOrder = async (context, section, order, previousOrder) => {
  var _a;
  setSectionPending(section, true);
  try {
    const response = await requestDocument(context, context.urls.sortDocument, {
      section: section.dataset.sectionKey,
      order
    });
    applyDocumentOrder(section, Array.isArray(response.order) ? response.order.map(Number) : order);
    showStatus(context, "success", context.messages.documentSorted ?? null);
  } catch (error) {
    applyDocumentOrder(section, previousOrder);
    showStatus(context, "danger", ((_a = error.result) == null ? void 0 : _a.message) ?? null);
  } finally {
    setSectionPending(section, false);
    refreshDocumentRows(section);
  }
};
const clearDocumentDropPosition = (state) => {
  state.section.querySelectorAll(`${itemSelector}.is-drop-before, ${itemSelector}.is-drop-after`).forEach((row) => row.classList.remove("is-drop-before", "is-drop-after"));
  state.items.classList.remove("is-drop-at-end");
  state.dropRow = null;
  state.dropPosition = null;
};
const getDocumentDropPosition = (state, targetRow, clientY) => {
  const bounds = targetRow.getBoundingClientRect();
  if (Number.isFinite(clientY) && bounds.height > 0) {
    return clientY < bounds.top + bounds.height / 2 ? "before" : "after";
  }
  const rows = Array.from(state.items.querySelectorAll(itemSelector));
  return rows.indexOf(state.row) < rows.indexOf(targetRow) ? "after" : "before";
};
const updateDocumentDropPosition = (state, target, clientY) => {
  clearDocumentDropPosition(state);
  const targetRow = target.closest(itemSelector);
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
const initializeDocumentDragAndDrop = (context) => {
  const root = context.root;
  let dragState = null;
  const clearDragState = () => {
    if (dragState === null) {
      return;
    }
    clearDocumentDropPosition(dragState);
    dragState.items.classList.remove("is-drag-active");
    dragState.row.classList.remove("is-dragging");
    dragState = null;
  };
  root.addEventListener("dragstart", (event) => {
    const target = event.target instanceof Element ? event.target : null;
    const handle = target == null ? void 0 : target.closest("[data-pe-document-drag]");
    const row = handle == null ? void 0 : handle.closest(itemSelector);
    const section = row == null ? void 0 : row.closest(sectionSelector);
    const items = row == null ? void 0 : row.closest(itemsSelector);
    if (handle === null || handle === void 0 || handle.disabled || row === null || row === void 0 || section === null || section === void 0 || items === null || items === void 0 || section.dataset.sectionSortable !== "1") {
      return;
    }
    dragState = {
      section,
      items,
      row,
      handle,
      order: getDocumentOrder(section),
      dropRow: null,
      dropPosition: null
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
  root.addEventListener("dragover", (event) => {
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
  root.addEventListener("drop", (event) => {
    const state = dragState;
    const target = event.target instanceof Element ? event.target : null;
    if (state === null || (target == null ? void 0 : target.closest(sectionSelector)) !== state.section) {
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
    const changed = order.length === previousOrder.length && order.some((uid, index) => uid !== previousOrder[index]);
    const section = state.section;
    clearDragState();
    refreshDocumentRows(section);
    if (changed) {
      void persistDocumentOrder(context, section, order, previousOrder);
    }
  });
  root.addEventListener("dragend", () => clearDragState());
};
const createDocumentEditing = (editingTarget) => {
  const context = toEditingContext(editingTarget);
  const root = context.root;
  const documentState = {
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
    values: {}
  };
  const contractContactState = {
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
    values: {}
  };
  let activeSection = null;
  let trigger = null;
  let contractContactTrigger = null;
  let rowPendingRemoval = null;
  let sectionPendingRefresh = null;
  let editorElement = null;
  const renderDocumentEditor = () => {
    const element = editorElement;
    if (element === null) {
      return;
    }
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
    element.open = documentState.open;
  };
  const createDocumentEditor = (target) => {
    registerProfileDocumentEditorElement();
    registerProfileRichTextElement();
    registerProfileContractContactsElement();
    const element = document.createElement(
      profileDocumentEditorElementName
    );
    element.context = context;
    element.addEventListener(documentEditorCloseEvent, () => closeDocument());
    element.addEventListener(documentEditorSubmitEvent, () => {
      void submitDocument();
    });
    element.addEventListener(documentEditorInputEvent, (event) => {
      const detail = event.detail;
      documentState.values = { ...documentState.values, [detail.name]: detail.value };
    });
    element.addEventListener(documentEditorClosedEvent, () => {
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
  const initializeDocumentEditors = async () => {
    var _a;
    await (editorElement == null ? void 0 : editorElement.updateComplete);
    const view = root.querySelector(documentViewSelector);
    if (view === null) {
      return;
    }
    initializePopover(view);
    await Promise.all(
      Array.from(
        view.querySelectorAll("textarea[data-pe-rich-text]")
      ).map((field) => ensureRichTextEditor(context, field))
    );
    if (documentState.mode === "add" || documentState.mode === "edit") {
      const firstField = view.querySelector("[data-pe-document-field]:not([disabled])");
      if (firstField instanceof HTMLTextAreaElement && firstField.matches("[data-pe-rich-text]")) {
        const editor = await ensureRichTextEditor(context, firstField);
        editor == null ? void 0 : editor.editing.view.focus();
      } else {
        firstField == null ? void 0 : firstField.focus();
      }
      return;
    }
    (_a = view.querySelector("[data-pe-document-heading]")) == null ? void 0 : _a.focus();
  };
  const collapseDocument = () => {
    contractContactState.open = false;
    documentState.open = false;
    if (trigger !== null) {
      setExpanded(trigger, false);
    }
    renderDocumentEditor();
  };
  const discardDocumentEditor = () => {
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
    finishDocumentClose(element);
    element.remove();
  };
  const openDocument = async (modeValue, event) => {
    var _a;
    if (!isDocumentMode(modeValue) || documentState.pending) {
      return;
    }
    const button = event.currentTarget instanceof HTMLButtonElement ? event.currentTarget : event.target instanceof Element ? event.target.closest("button") : null;
    const section = button == null ? void 0 : button.closest(sectionSelector);
    if (button === null || section === null || section === void 0) {
      return;
    }
    if (documentState.open && trigger === button && documentState.mode === modeValue) {
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
    let created = false;
    try {
      const response = await requestDocument(context, context.urls.documentForm, {
        section: section.dataset.sectionKey,
        record: record ?? 0,
        mode: modeValue
      });
      const fields = getResponseFields(response.fields);
      activeSection = section;
      documentState.fields = fields;
      documentState.contactSections = getResponseContactSections(
        response.contactSections
      );
      documentState.kind = section.dataset.sectionKind === "contract" ? "contract" : "document";
      documentState.mode = modeValue;
      documentState.record = typeof response.record === "number" ? response.record : record;
      documentState.section = section.dataset.sectionKey ?? "";
      documentState.title = [
        getModeLabel(context, modeValue),
        getDocumentSubject(section, fields)
      ].filter(Boolean).join(": ");
      documentState.values = Object.fromEntries(
        fields.map((field) => [field.name, field.value])
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
      if (created) {
        discardDocumentEditor();
      }
      showStatus(context, "danger", ((_a = error.result) == null ? void 0 : _a.message) ?? null);
    } finally {
      documentState.pending = false;
      renderDocumentEditor();
    }
  };
  const closeDocument = () => {
    if (documentState.pending) {
      return;
    }
    collapseDocument();
  };
  const finishDocumentClose = (element, restoreState = true) => {
    const focusTarget = trigger;
    void destroyRichTextEditors(element);
    rowPendingRemoval == null ? void 0 : rowPendingRemoval.remove();
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
    if ((focusTarget == null ? void 0 : focusTarget.isConnected) === true) {
      focusTarget.focus({ preventScroll: true });
    }
  };
  const collectDocumentValues = () => {
    const values = { ...documentState.values };
    const view = root.querySelector(documentViewSelector);
    documentState.fields.forEach((field) => {
      if (!field.richText || view === null) {
        return;
      }
      const control = view.querySelector(
        `[data-pe-document-field="${CSS.escape(field.name)}"]`
      );
      if (control !== null) {
        values[field.name] = getRichTextEditorValue(control) ?? control.value;
      }
    });
    return values;
  };
  const submitDocument = async () => {
    if (documentState.pending || documentState.mode === "view" || activeSection === null) {
      return;
    }
    const form = root.querySelector("[data-pe-document-form]");
    if (documentState.mode !== "delete" && form !== null && !form.reportValidity()) {
      return;
    }
    const endpoint = documentState.mode === "add" ? context.urls.createDocument : documentState.mode === "edit" ? context.urls.updateDocument : context.urls.deleteDocument;
    const data = { section: documentState.section };
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
      const item = response.item;
      if (documentState.mode === "add" && item !== void 0) {
        insertDocumentRow(activeSection, item);
      } else if (documentState.mode === "edit" && item !== void 0) {
        const row = activeSection.querySelector(
          `${itemSelector}[data-item-uid="${CSS.escape(String(documentState.record))}"]`
        );
        if (row !== null) {
          updateDocumentRow(row, item);
        }
        refreshDocumentRows(activeSection);
      } else if (documentState.mode === "delete") {
        rowPendingRemoval = activeSection.querySelector(
          `${itemSelector}[data-item-uid="${CSS.escape(String(documentState.record))}"]`
        );
        sectionPendingRefresh = activeSection;
      }
      const successMessage = documentState.mode === "delete" ? context.messages.documentDeleted : context.messages.documentSaved;
      documentState.pending = false;
      closeDocument();
      showStatus(context, "success", successMessage ?? null);
    } catch (error) {
      const result = error.result;
      documentState.error = (result == null ? void 0 : result.message) ?? context.messages.errorMessage ?? "";
      documentState.errors = Object.fromEntries(
        Object.entries((result == null ? void 0 : result.errors) ?? {}).map(([name, messages]) => [
          name,
          Array.isArray(messages) ? messages.map(String).join(" ") : String(messages)
        ])
      );
    } finally {
      documentState.pending = false;
      renderDocumentEditor();
    }
  };
  const sortDocument = async (direction, event) => {
    var _a;
    const button = event.currentTarget instanceof HTMLButtonElement ? event.currentTarget : event.target instanceof Element ? event.target.closest("button") : null;
    const section = button == null ? void 0 : button.closest(sectionSelector);
    const record = button === null ? null : getRecordFromButton(button);
    if (button === null || section === null || section === void 0 || record === null || !["up", "down"].includes(direction)) {
      return;
    }
    setSectionPending(section, true);
    try {
      const response = await requestDocument(context, context.urls.sortDocument, {
        section: section.dataset.sectionKey,
        record,
        direction
      });
      applyDocumentOrder(
        section,
        Array.isArray(response.order) ? response.order.map(Number) : []
      );
      showStatus(context, "success", context.messages.documentSorted ?? null);
    } catch (error) {
      showStatus(context, "danger", ((_a = error.result) == null ? void 0 : _a.message) ?? null);
    } finally {
      setSectionPending(section, false);
      refreshDocumentRows(section);
    }
  };
  const openContractContact = async (modeValue, section, event, record = 0) => {
    var _a;
    if (!isDocumentMode(modeValue) || documentState.record === null || contractContactState.pending) {
      return;
    }
    const normalizedRecord = modeValue === "add" ? null : record;
    if (modeValue !== "add" && (!Number.isInteger(record) || record <= 0)) {
      return;
    }
    const button = event.currentTarget instanceof HTMLButtonElement ? event.currentTarget : event.target instanceof Element ? event.target.closest("button") : null;
    if (contractContactState.open && contractContactState.mode === modeValue && contractContactState.section === section && contractContactState.record === normalizedRecord) {
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
          mode: modeValue
        }
      );
      const fields = getResponseFields(response.fields);
      contractContactState.fields = fields;
      contractContactState.mode = modeValue;
      contractContactState.record = typeof response.record === "number" ? response.record : normalizedRecord;
      contractContactState.section = section;
      contractContactState.title = [
        getModeLabel(context, modeValue),
        String(response.title ?? "")
      ].filter(Boolean).join(": ");
      contractContactState.values = Object.fromEntries(
        fields.map((field) => [field.name, field.value])
      );
      contractContactTrigger = button;
      contractContactState.open = true;
      contractContactState.pending = false;
      renderDocumentEditor();
      await (editorElement == null ? void 0 : editorElement.updateComplete);
      const editor = root.querySelector(
        "[data-pe-contract-contact-editor]"
      );
      if (editor !== null) {
        initializePopover(editor);
        editor.scrollIntoView({ behavior: "smooth", block: "nearest" });
        const focusTarget = contractContactState.mode === "add" || contractContactState.mode === "edit" ? editor.querySelector(
          "input:not([disabled]), select:not([disabled])"
        ) : editor.querySelector("[data-pe-contract-contact-heading]");
        focusTarget == null ? void 0 : focusTarget.focus({ preventScroll: true });
      }
    } catch (error) {
      showStatus(context, "danger", ((_a = error.result) == null ? void 0 : _a.message) ?? null);
    } finally {
      contractContactState.pending = false;
      renderDocumentEditor();
    }
  };
  const closeContractContact = () => {
    if (!contractContactState.pending) {
      contractContactState.open = false;
      const focusTarget = contractContactTrigger;
      contractContactTrigger = null;
      renderDocumentEditor();
      void Promise.resolve(editorElement == null ? void 0 : editorElement.updateComplete).then(() => {
        if ((focusTarget == null ? void 0 : focusTarget.isConnected) === true) {
          focusTarget.focus({ preventScroll: true });
        }
      });
    }
  };
  const submitContractContact = async () => {
    if (contractContactState.pending || contractContactState.mode === "view" || documentState.record === null) {
      return;
    }
    const form = root.querySelector("[data-pe-document-form]");
    if (contractContactState.mode !== "delete" && form !== null && !form.reportValidity()) {
      return;
    }
    const endpoint = contractContactState.mode === "add" ? context.urls.createContractContact : contractContactState.mode === "edit" ? context.urls.updateContractContact : context.urls.deleteContractContact;
    const data = {
      contract: documentState.record,
      section: contractContactState.section
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
      documentState.contactSections = replaceContactItems(
        documentState.contactSections,
        contractContactState.section,
        (items) => {
          if (mode === "add" && item !== null) {
            return [...items, item];
          }
          if (mode === "edit" && item !== null) {
            return items.map(
              (candidate) => candidate.uid === record ? item : candidate
            );
          }
          if (mode === "delete") {
            return items.filter(
              (candidate) => candidate.uid !== record
            );
          }
          return items;
        }
      );
      contractContactState.pending = false;
      contractContactState.open = false;
      renderDocumentEditor();
      showStatus(
        context,
        "success",
        contractContactState.mode === "delete" ? context.messages.documentDeleted ?? null : context.messages.documentSaved ?? null
      );
    } catch (error) {
      const result = error.result;
      contractContactState.error = (result == null ? void 0 : result.message) ?? context.messages.errorMessage ?? "";
      contractContactState.errors = Object.fromEntries(
        Object.entries((result == null ? void 0 : result.errors) ?? {}).map(
          ([name, messages]) => [
            name,
            Array.isArray(messages) ? messages.map(String).join(" ") : String(messages)
          ]
        )
      );
    } finally {
      contractContactState.pending = false;
      renderDocumentEditor();
    }
  };
  const sortContractContact = async (direction, sectionIdentifier, record) => {
    var _a;
    if (contractContactState.pending || documentState.record === null || !["up", "down"].includes(direction)) {
      return;
    }
    const section = documentState.contactSections.find(
      (candidate) => candidate.identifier === sectionIdentifier
    );
    if (section === void 0) {
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
          direction
        }
      );
      const itemsByUid = new Map(
        section.items.map((item) => [item.uid, item])
      );
      const order = Array.isArray(response.order) ? response.order.map(Number) : [];
      const sortedItems = order.flatMap((uid) => {
        const item = itemsByUid.get(uid);
        return item === void 0 ? [] : [item];
      });
      if (sortedItems.length === section.items.length) {
        documentState.contactSections = replaceContactItems(
          documentState.contactSections,
          sectionIdentifier,
          () => sortedItems.map((item, index) => ({
            ...item,
            sorting: (index + 1) * 10
          }))
        );
      }
      renderDocumentEditor();
      showStatus(context, "success", context.messages.documentSorted ?? null);
    } catch (error) {
      showStatus(context, "danger", ((_a = error.result) == null ? void 0 : _a.message) ?? null);
    } finally {
      contractContactState.pending = false;
      renderDocumentEditor();
    }
  };
  const toggleContractContactVisibility = async (sectionIdentifier, record) => {
    var _a, _b;
    if (contractContactState.pending || documentState.record === null) {
      return;
    }
    const section = documentState.contactSections.find(
      (candidate) => candidate.identifier === sectionIdentifier
    );
    const item = section == null ? void 0 : section.items.find((candidate) => candidate.uid === record);
    if (section === void 0 || item === void 0) {
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
          hidden
        }
      );
      const stored = asContractContactItem(response.item) ?? { ...item, hidden };
      documentState.contactSections = replaceContactItems(
        documentState.contactSections,
        sectionIdentifier,
        (items) => items.map(
          (candidate) => candidate.uid === record ? stored : candidate
        )
      );
      contractContactState.pending = false;
      renderDocumentEditor();
      showStatus(
        context,
        "success",
        (hidden ? context.messages.contractContactHidden : context.messages.contractContactShown) ?? null
      );
      await (editorElement == null ? void 0 : editorElement.updateComplete);
      (_a = root.querySelector(
        `[data-pe-contract-contact-item="${CSS.escape(String(record))}"] [data-pe-contract-contact-hide]`
      )) == null ? void 0 : _a.focus({ preventScroll: true });
    } catch (error) {
      showStatus(context, "danger", ((_b = error.result) == null ? void 0 : _b.message) ?? null);
    } finally {
      contractContactState.pending = false;
      renderDocumentEditor();
    }
  };
  const onContractContactClick = (event) => {
    const target = event.target instanceof Element ? event.target : null;
    const button = target == null ? void 0 : target.closest(
      "[data-pe-contract-contact-add], [data-pe-contract-contact-view], [data-pe-contract-contact-edit], [data-pe-contract-contact-delete], [data-pe-contract-contact-sort], [data-pe-contract-contact-hide], [data-pe-contract-contact-cancel], [data-pe-contract-contact-save]"
    );
    if (button === null || button === void 0 || button.disabled) {
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
    const sectionElement = button.closest(
      "[data-pe-contract-contact-section]"
    );
    const itemElement = button.closest("[data-pe-contract-contact-item]");
    const section = sectionElement === null ? "" : hooks(sectionElement).peContractContactSection ?? "";
    const record = Number(
      itemElement === null ? 0 : hooks(itemElement).peContractContactItem ?? 0
    );
    const direction = hooks(button).peContractContactSort;
    if (direction !== void 0) {
      void sortContractContact(direction, section, record);
      return;
    }
    if (button.matches("[data-pe-contract-contact-hide]")) {
      void toggleContractContactVisibility(section, record);
      return;
    }
    const mode = button.matches("[data-pe-contract-contact-add]") ? "add" : button.matches("[data-pe-contract-contact-view]") ? "view" : button.matches("[data-pe-contract-contact-edit]") ? "edit" : "delete";
    void openContractContact(mode, section, event, record);
  };
  const onContractContactInput = (event) => {
    const control = event.target;
    if (!isEditableField(control)) {
      return;
    }
    const name = hooks(control).peContractContactField;
    if (name === void 0) {
      return;
    }
    const value = control instanceof HTMLInputElement && control.type === "checkbox" ? control.checked : control.value;
    contractContactState.values = { ...contractContactState.values, [name]: value };
  };
  initializeDocumentDragAndDrop(context);
  root.addEventListener("click", onContractContactClick);
  root.addEventListener("input", onContractContactInput);
  root.addEventListener("change", onContractContactInput);
  root.addEventListener("click", (event) => {
    const target = event.target instanceof Element ? event.target : null;
    const button = target == null ? void 0 : target.closest(
      "[data-pe-document-add], [data-pe-document-view], [data-pe-document-edit], [data-pe-document-delete], [data-pe-document-sort]"
    );
    if (button === null || button === void 0 || button.disabled) {
      return;
    }
    const direction = hooks(button).peDocumentSort;
    if (direction !== void 0) {
      void sortDocument(direction, event);
      return;
    }
    const mode = button.matches("[data-pe-document-add]") ? "add" : button.matches("[data-pe-document-view]") ? "view" : button.matches("[data-pe-document-edit]") ? "edit" : "delete";
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
    toggleContractContactVisibility
  };
};
const initializeDocumentSections = (editingTarget) => {
  toEditingContext(editingTarget).root.querySelectorAll(sectionSelector).forEach(refreshDocumentRows);
};
export {
  createDocumentEditing,
  initializeDocumentSections
};
