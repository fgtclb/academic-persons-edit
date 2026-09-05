/* Generated from Resources/Private/TypeScript — do not edit. */
import {
  fillPrototype
} from "@fgtclb/academic-persons-edit/frontend/profile/prototypes.js";
import { parseRichTextPreview } from "@fgtclb/academic-persons-edit/frontend/profile/rich-text.js";
const fieldControlId = (prefix, index, field) => `${prefix}-${index}-${field.name}`;
const fieldErrorId = (prefix, index, field) => `${prefix}-error-${index}-${field.name}`;
const text = (value) => value === null || value === void 0 ? "" : String(value);
const flag = (value) => value === true ? true : void 0;
const bound = (value) => typeof value === "number" ? value : void 0;
const inputTypeOf = (type) => type === "" || type === "date" ? "text" : type;
const cloneControl = (options, controlId, errorId) => {
  const { field, hook, value } = options;
  const disabled = flag(field.disabled === true || options.pending);
  const invalid = options.error === void 0 || options.error === "" ? "false" : "true";
  const shared = {
    contactField: hook === "contactField" ? field.name : void 0,
    controlId,
    describedBy: errorId,
    disabled,
    documentField: hook === "documentField" ? field.name : void 0,
    invalid,
    name: field.name
  };
  const required = { required: flag(field.required) };
  if (field.type === "select") {
    const control = fillPrototype(options.source, "control-select", {
      ...shared,
      ...required
    });
    const select = control.query("select");
    control.list(
      "options",
      (field.options ?? []).map(
        (option) => fillPrototype(options.source, "option", {
          label: option.label,
          value: text(option.value)
        }).fragment
      )
    );
    if (select !== null) {
      select.value = text(value);
    }
    return control.fragment;
  }
  if (field.type === "checkbox") {
    const control = fillPrototype(options.source, "control-checkbox", {
      ...shared,
      checked: value === true ? true : void 0,
      // The three hooks of the permanent profile fields. A document or
      // contact checkbox has no autosave and no state labels, so the filler
      // takes the attributes off the clone rather than the prototype
      // carrying two shapes.
      autosave: void 0,
      checkedLabel: void 0,
      uncheckedLabel: void 0
    });
    return control.fragment;
  }
  if (field.type === "textarea" && field.richText === true) {
    const control = fillPrototype(options.source, "control-rich-text", {
      ...shared,
      ...required,
      characterLimit: field.characterLimit === void 0 || field.characterLimit <= 0 ? void 0 : field.characterLimit,
      readOnly: flag(field.readOnly)
    });
    const textarea = control.query("textarea");
    if (textarea !== null) {
      textarea.value = text(value);
    }
    return control.fragment;
  }
  if (field.type === "textarea") {
    const control = fillPrototype(options.source, "control-textarea", {
      ...shared,
      ...required,
      readOnly: flag(field.readOnly)
    });
    const textarea = control.query("textarea");
    if (textarea !== null) {
      textarea.value = text(value);
    }
    return control.fragment;
  }
  return fillPrototype(options.source, "control-input", {
    ...shared,
    ...required,
    autocomplete: field.autocomplete === void 0 || field.autocomplete === "" ? void 0 : field.autocomplete,
    inputType: inputTypeOf(field.type),
    // The bounds of a number control. `undefined` takes the attribute off the
    // clone, so a text input carries none of the three.
    max: bound(field.max),
    min: bound(field.min),
    // The hint of a date control. Server side text, like every other label of
    // a field, so nothing here spells it.
    placeholder: field.placeholder === void 0 || field.placeholder === "" ? void 0 : field.placeholder,
    readOnly: flag(field.readOnly),
    step: bound(field.step),
    value: text(value)
  }).fragment;
};
const cloneHelptext = (options) => {
  const { field } = options;
  if (field.helptext === void 0 || field.helptext === "") {
    return [];
  }
  return [
    fillPrototype(options.source, "helptext-button", {
      ariaLabel: `${field.label}: ${field.helptext}`,
      content: field.helptext,
      title: field.label
    }).fragment
  ];
};
const cloneField = (options) => {
  const { field, index, idPrefix } = options;
  const controlId = fieldControlId(idPrefix, index, field);
  const errorId = fieldErrorId(idPrefix, index, field);
  const message = options.error ?? "";
  const wrapper = field.type === "checkbox" ? fillPrototype(options.source, "field-checkbox", {
    // `undefined` keeps the column the prototype carries.
    columnClass: field.columnClass === "" ? void 0 : field.columnClass,
    compact: flag(field.compactCheckbox),
    controlId,
    error: message,
    errorHidden: message === "" ? true : void 0,
    errorId,
    label: field.label
  }) : fillPrototype(
    options.source,
    field.type === "textarea" ? "field-wide" : "field-default",
    {
      characterLimit: field.characterLimit,
      columnClass: field.columnClass === "" ? void 0 : field.columnClass,
      compact: flag(field.compactCheckbox),
      controlId,
      error: message,
      errorHidden: message === "" ? true : void 0,
      errorId,
      hasCharacterLimit: field.richText === true && field.characterLimit !== void 0 && field.characterLimit > 0,
      label: field.label,
      required: flag(field.required)
    }
  );
  wrapper.list("helptext", cloneHelptext(options));
  wrapper.list("control", [cloneControl(options, controlId, errorId)]);
  return wrapper.fragment;
};
const cloneDisplayRow = (source, field, emptyLabel) => {
  const value = field.displayValue ?? "";
  const rich = field.richText === true && value !== "";
  const row = fillPrototype(source, "display-row", {
    label: field.label,
    plain: !rich,
    richText: rich,
    value: value === "" ? emptyLabel : value
  });
  if (rich) {
    const parsed = parseRichTextPreview(value);
    row.list(
      "richValue",
      Array.from(parsed.body.childNodes).map(
        (node) => document.importNode(node, true)
      )
    );
  }
  return row.fragment;
};
const applyFieldErrors = (panel, fields, idPrefix, errors) => {
  fields.forEach((field, index) => {
    const message = errors[field.name] ?? "";
    const target = panel.querySelector(
      `#${CSS.escape(fieldErrorId(idPrefix, index, field))}`
    );
    if (target !== null) {
      target.textContent = message;
      target.hidden = message === "";
    }
    const control = panel.querySelector(
      `#${CSS.escape(fieldControlId(idPrefix, index, field))}`
    );
    control == null ? void 0 : control.setAttribute("aria-invalid", message === "" ? "false" : "true");
  });
};
export {
  applyFieldErrors,
  cloneDisplayRow,
  cloneField,
  fieldControlId,
  fieldErrorId
};
