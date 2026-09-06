/* Generated from Resources/Private/TypeScript — do not edit. */
const fieldSlots = [
  "columnClass",
  "compact",
  "controlId",
  "label",
  "errorHidden",
  "errorId",
  "error"
];
const controlSlots = [
  "controlId",
  "name",
  "disabled",
  "describedBy",
  "invalid",
  "documentField",
  "contactField"
];
const panelSlots = [
  "busy",
  "pending",
  "spinnerHidden",
  "errorHidden",
  "error",
  "isDelete",
  "isSave",
  "deleteConfirmation",
  "showDisplay",
  "showFields",
  "showActions"
];
const prototypeSlots = {
  "field-default": [...fieldSlots, "required", "hasCharacterLimit", "characterLimit"],
  "field-wide": [...fieldSlots, "required", "hasCharacterLimit", "characterLimit"],
  "field-checkbox": [...fieldSlots],
  "control-input": [
    ...controlSlots,
    "required",
    "readOnly",
    "inputType",
    "value",
    "autocomplete",
    "placeholder",
    "min",
    "max",
    "step"
  ],
  "control-textarea": [...controlSlots, "required", "readOnly"],
  "control-rich-text": [...controlSlots, "required", "readOnly", "characterLimit"],
  "control-select": [...controlSlots, "required"],
  "control-checkbox": [
    ...controlSlots,
    "checked",
    "autosave",
    "checkedLabel",
    "uncheckedLabel"
  ],
  option: ["label", "value"],
  "display-row": ["label", "value", "plain", "richText"],
  "document-panel": [...panelSlots, "kind", "heading", "showContacts"],
  "helptext-button": ["title", "content", "ariaLabel"],
  "contact-section": [
    "identifier",
    "label",
    "editorId",
    "addExpanded",
    "addDisabled",
    "addEditorHidden",
    "rowsHidden",
    "emptyHidden",
    "emptyMessage"
  ],
  "contact-row": [
    "uid",
    "hidden",
    "editorId",
    "viewExpanded",
    "editExpanded",
    "deleteExpanded",
    "editorHidden"
  ],
  "contact-summary-cell": ["label", "value", "hasValue", "isEmpty"],
  "contact-editor-panel": [...panelSlots, "editorId", "title"]
};
const prototypeLists = {
  "field-default": ["helptext", "control"],
  "field-wide": ["helptext", "control"],
  "field-checkbox": ["helptext", "control"],
  "control-input": [],
  "control-textarea": [],
  "control-rich-text": [],
  "control-select": ["options"],
  "control-checkbox": [],
  option: [],
  "display-row": ["richValue"],
  "document-panel": ["displayRows", "contacts", "fields"],
  "helptext-button": [],
  "contact-section": ["addEditor", "rows"],
  "contact-row": ["summary", "editor"],
  "contact-summary-cell": [],
  "contact-editor-panel": ["displayRows", "fields"]
};
const slotAttribute = "data-pe-slot";
const attrAttribute = "data-pe-attr";
const whenAttribute = "data-pe-when";
const listAttribute = "data-pe-list";
const declaredKeys = /* @__PURE__ */ new WeakMap();
const declaredLists = /* @__PURE__ */ new WeakMap();
const isTruthy = (value) => value !== void 0 && value !== null && value !== false && value !== "" && value !== 0;
const text = (value) => value === void 0 || value === null || value === false ? "" : String(value);
const keysOf = (template) => {
  const cached = declaredKeys.get(template);
  if (cached !== void 0) {
    return cached;
  }
  const keys = /* @__PURE__ */ new Set();
  template.content.querySelectorAll(`[${slotAttribute}], [${whenAttribute}], [${attrAttribute}]`).forEach((node) => {
    const slot = node.getAttribute(slotAttribute);
    if (slot !== null) {
      keys.add(slot);
    }
    const when = node.getAttribute(whenAttribute);
    if (when !== null) {
      keys.add(when);
    }
    parseAttributeBindings(node.getAttribute(attrAttribute)).forEach(
      ([, key]) => {
        keys.add(key);
      }
    );
  });
  declaredKeys.set(template, keys);
  return keys;
};
const listsOf = (template) => {
  const cached = declaredLists.get(template);
  if (cached !== void 0) {
    return cached;
  }
  const keys = /* @__PURE__ */ new Set();
  template.content.querySelectorAll(`[${listAttribute}]`).forEach((node) => {
    const key = node.getAttribute(listAttribute);
    if (key !== null) {
      keys.add(key);
    }
  });
  declaredLists.set(template, keys);
  return keys;
};
const parseAttributeBindings = (value) => {
  if (value === null || value.trim() === "") {
    return [];
  }
  return value.trim().split(/\s+/).map((binding) => {
    const separator = binding.indexOf(":");
    if (separator <= 0 || separator === binding.length - 1) {
      throw new Error(
        `The profile editing prototype binding "${binding}" is not "attribute:key".`
      );
    }
    return [binding.slice(0, separator), binding.slice(separator + 1)];
  });
};
const prototypeTemplate = (source, name) => {
  const template = source.querySelector(
    `template[data-pe-proto="${name}"]`
  );
  if (template === null) {
    throw new Error(
      `The profile editing prototype "${name}" is missing. Partials/Profile/Prototypes.html has to render it.`
    );
  }
  return template;
};
const fillPrototype = (source, name, values) => {
  const template = prototypeTemplate(source, name);
  const declared = keysOf(template);
  Object.keys(values).forEach((key) => {
    if (!declared.has(key)) {
      throw new Error(
        `The profile editing prototype "${name}" declares no slot "${key}".`
      );
    }
  });
  const read = (key) => values[key];
  const fragment = template.content.cloneNode(true);
  Array.from(fragment.querySelectorAll(`[${whenAttribute}]`)).forEach(
    (node) => {
      const key = node.getAttribute(whenAttribute);
      if (key !== null && !isTruthy(read(key))) {
        node.remove();
      }
    }
  );
  fragment.querySelectorAll(`[${slotAttribute}]`).forEach((node) => {
    const key = node.getAttribute(slotAttribute);
    if (key !== null) {
      node.textContent = text(read(key));
    }
  });
  fragment.querySelectorAll(`[${attrAttribute}]`).forEach((node) => {
    parseAttributeBindings(node.getAttribute(attrAttribute)).forEach(
      ([attribute, key]) => {
        const value = read(key);
        if (value === void 0) {
          return;
        }
        if (value === null || value === false) {
          node.removeAttribute(attribute);
          return;
        }
        node.setAttribute(attribute, value === true ? "" : String(value));
      }
    );
  });
  const element = fragment.firstElementChild;
  if (!(element instanceof HTMLElement)) {
    throw new Error(
      `The profile editing prototype "${name}" renders no element.`
    );
  }
  return {
    element,
    fragment,
    list(key, nodes) {
      if (!listsOf(template).has(String(key))) {
        throw new Error(
          `The profile editing prototype "${name}" declares no list "${String(key)}".`
        );
      }
      const marker = fragment.querySelector(
        `[${listAttribute}="${String(key)}"]`
      );
      if (marker === null) {
        return;
      }
      if (marker instanceof HTMLTemplateElement) {
        marker.replaceWith(...nodes);
        return;
      }
      marker.append(...nodes);
    },
    query(selector) {
      return fragment.querySelector(selector);
    }
  };
};
export {
  fillPrototype,
  parseAttributeBindings,
  prototypeLists,
  prototypeSlots,
  prototypeTemplate
};
