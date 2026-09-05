/**
 * One field of a `documentForm` or `contractContactForm` response, as DOM.
 *
 * Both editors build their fields from the same descriptors and used to build
 * them twice - which is how the checkbox of the document editor ended up with
 * `form-check-input` and the checkbox of the contact editor with
 * `form-control`, shipped and reviewed. There is one builder now, it clones
 * the prototypes of `Partials/Profile/Prototypes.html`, and the two callers
 * differ in exactly two arguments: the id prefix and which of the two field
 * hooks the control carries.
 *
 * Nothing here writes a tag name, a class or a label.
 */
import {
  fillPrototype,
  type PrototypeInstance,
  type SlotValue,
} from "@fgtclb/academic-persons-edit/frontend/profile/prototypes.js";
import { parseRichTextPreview } from "@fgtclb/academic-persons-edit/frontend/profile/rich-text.js";
import type {
  DocumentField,
  DocumentValue,
} from "@fgtclb/academic-persons-edit/frontend/profile/documents.js";

/** Which of the two field hooks the control of a clone carries. */
export type FieldHook = "documentField" | "contactField";

/** Everything one field row is built from. */
export interface FieldCloneOptions {
  /** Where the `<template data-pe-proto>` blocks are, i.e. the editor root. */
  readonly source: ParentNode;
  readonly field: DocumentField;
  readonly index: number;
  readonly idPrefix: string;
  readonly hook: FieldHook;
  readonly value: DocumentValue | undefined;
  readonly error: string | undefined;
  readonly pending: boolean;
}

/** The id of the control of one field. */
export const fieldControlId = (
  prefix: string,
  index: number,
  field: DocumentField,
): string => `${prefix}-${index}-${field.name}`;

/** The id of the message of one field, which `aria-describedby` points at. */
export const fieldErrorId = (
  prefix: string,
  index: number,
  field: DocumentField,
): string => `${prefix}-error-${index}-${field.name}`;

const text = (value: DocumentValue | undefined): string =>
  value === null || value === undefined ? "" : String(value);

const flag = (value: boolean | undefined): true | undefined =>
  value === true ? true : undefined;

/** A numeric control bound, or nothing where the field declares none. */
const bound = (value: number | null | undefined): number | undefined =>
  typeof value === "number" ? value : undefined;

/**
 * The `type` attribute an input control of a field type carries.
 *
 * Every type is its own input type but two. An empty one is a text input, and
 * so is `date`: the two contract dates are deliberately *not* an
 * `<input type="date">`. The native control forces the locale of the browser
 * rather than the one of the site, it cannot be styled with the rest of the
 * editor, and its calendar is not the date picker this editor is meant to get
 * - that is a feature of its own and is not shipped yet. Until it is, the
 * control is a text input, the field type stays `date`, and it is the field
 * type a picker will be mounted on.
 */
const inputTypeOf = (type: string): string =>
  type === "" || type === "date" ? "text" : type;

/**
 * The control of one field, as its prototype plus the values that shape it.
 *
 * A `<select>` and a rich text control need one write after the clone that no
 * verb can do: the selected option only exists once the options have been
 * appended, and the textarea of a rich text field is inside the element that
 * wraps it. Both are state, not markup.
 */
const cloneControl = (options: FieldCloneOptions, controlId: string, errorId: string): DocumentFragment => {
  const { field, hook, value } = options;
  const disabled = flag(field.disabled === true || options.pending);
  const invalid = options.error === undefined || options.error === "" ? "false" : "true";
  const shared = {
    contactField: hook === "contactField" ? field.name : undefined,
    controlId,
    describedBy: errorId,
    disabled,
    documentField: hook === "documentField" ? field.name : undefined,
    invalid,
    name: field.name,
  } satisfies Record<string, SlotValue>;
  // Every control but the checkbox: a checkbox that is "required" would have
  // to be ticked to submit, which is not what a required document field means,
  // and "Profile/Field/Control.html" therefore binds no "required" on it.
  const required = { required: flag(field.required) };

  if (field.type === "select") {
    const control = fillPrototype(options.source, "control-select", {
      ...shared,
      ...required,
    });
    const select = control.query<HTMLSelectElement>("select");
    control.list(
      "options",
      (field.options ?? []).map(
        (option): Node =>
          fillPrototype(options.source, "option", {
            label: option.label,
            value: text(option.value),
          }).fragment,
      ),
    );
    if (select !== null) {
      select.value = text(value);
    }

    return control.fragment;
  }
  if (field.type === "checkbox") {
    const control = fillPrototype(options.source, "control-checkbox", {
      ...shared,
      checked: value === true ? true : undefined,
      // The three hooks of the permanent profile fields. A document or
      // contact checkbox has no autosave and no state labels, so the filler
      // takes the attributes off the clone rather than the prototype
      // carrying two shapes.
      autosave: undefined,
      checkedLabel: undefined,
      uncheckedLabel: undefined,
    });

    return control.fragment;
  }
  if (field.type === "textarea" && field.richText === true) {
    const control = fillPrototype(options.source, "control-rich-text", {
      ...shared,
      ...required,
      characterLimit:
        field.characterLimit === undefined || field.characterLimit <= 0
          ? undefined
          : field.characterLimit,
      readOnly: flag(field.readOnly),
    });
    const textarea = control.query<HTMLTextAreaElement>("textarea");
    if (textarea !== null) {
      textarea.value = text(value);
    }

    return control.fragment;
  }
  if (field.type === "textarea") {
    const control = fillPrototype(options.source, "control-textarea", {
      ...shared,
      ...required,
      readOnly: flag(field.readOnly),
    });
    const textarea = control.query<HTMLTextAreaElement>("textarea");
    if (textarea !== null) {
      textarea.value = text(value);
    }

    return control.fragment;
  }

  return fillPrototype(options.source, "control-input", {
    ...shared,
    ...required,
    autocomplete:
      field.autocomplete === undefined || field.autocomplete === ""
        ? undefined
        : field.autocomplete,
    inputType: inputTypeOf(field.type),
    // The bounds of a number control. `undefined` takes the attribute off the
    // clone, so a text input carries none of the three.
    max: bound(field.max),
    min: bound(field.min),
    // The hint of a date control. Server side text, like every other label of
    // a field, so nothing here spells it.
    placeholder:
      field.placeholder === undefined || field.placeholder === ""
        ? undefined
        : field.placeholder,
    readOnly: flag(field.readOnly),
    step: bound(field.step),
    value: text(value),
  }).fragment;
};

const cloneHelptext = (options: FieldCloneOptions): Node[] => {
  const { field } = options;
  if (field.helptext === undefined || field.helptext === "") {
    return [];
  }

  return [
    fillPrototype(options.source, "helptext-button", {
      ariaLabel: `${field.label}: ${field.helptext}`,
      content: field.helptext,
      title: field.label,
    }).fragment,
  ];
};

/**
 * One field row: the wrapper its type asks for, its label, its help button,
 * its control, its character counter and its message.
 */
export const cloneField = (options: FieldCloneOptions): DocumentFragment => {
  const { field, index, idPrefix } = options;
  const controlId = fieldControlId(idPrefix, index, field);
  const errorId = fieldErrorId(idPrefix, index, field);
  const message = options.error ?? "";
  const wrapper: PrototypeInstance<"field-checkbox" | "field-default" | "field-wide"> =
    field.type === "checkbox"
      ? fillPrototype(options.source, "field-checkbox", {
          // `undefined` keeps the column the prototype carries.
          columnClass: field.columnClass === "" ? undefined : field.columnClass,
          compact: flag(field.compactCheckbox),
          controlId,
          error: message,
          errorHidden: message === "" ? true : undefined,
          errorId,
          label: field.label,
        })
      : fillPrototype(
          options.source,
          field.type === "textarea" ? "field-wide" : "field-default",
          {
            characterLimit: field.characterLimit,
            columnClass: field.columnClass === "" ? undefined : field.columnClass,
            compact: flag(field.compactCheckbox),
            controlId,
            error: message,
            errorHidden: message === "" ? true : undefined,
            errorId,
            hasCharacterLimit:
              field.richText === true &&
              field.characterLimit !== undefined &&
              field.characterLimit > 0,
            label: field.label,
            required: flag(field.required),
          },
        );
  wrapper.list("helptext", cloneHelptext(options));
  wrapper.list("control", [cloneControl(options, controlId, errorId)]);

  return wrapper.fragment;
};

/**
 * The term and the description of one field in a view mode.
 *
 * A rich text value is parsed by `parseRichTextPreview()`, the allow list the
 * whole editor writes markup through, and the resulting nodes are imported
 * rather than assigned as a string, so nothing is parsed a second time.
 */
export const cloneDisplayRow = (
  source: ParentNode,
  field: DocumentField,
  emptyLabel: string,
): DocumentFragment => {
  const value = field.displayValue ?? "";
  const rich = field.richText === true && value !== "";
  const row = fillPrototype(source, "display-row", {
    label: field.label,
    plain: !rich,
    richText: rich,
    value: value === "" ? emptyLabel : value,
  });
  if (rich) {
    const parsed = parseRichTextPreview(value);
    row.list(
      "richValue",
      Array.from(parsed.body.childNodes).map((node): Node =>
        document.importNode(node, true),
      ),
    );
  }

  return row.fragment;
};

/**
 * Writes the messages of one editing panel onto the fields it already
 * rendered.
 *
 * A rebuild would be the simpler code and the wrong behaviour: a refusal
 * arrives while the visitor is looking at what they typed, and rebuilding the
 * panel would replace every control - and every live CKEditor - with a fresh
 * one. So the message nodes are always in the clone and this only fills them.
 */
export const applyFieldErrors = (
  panel: ParentNode,
  fields: readonly DocumentField[],
  idPrefix: string,
  errors: Readonly<Record<string, string>>,
): void => {
  fields.forEach((field, index): void => {
    const message = errors[field.name] ?? "";
    const target = panel.querySelector<HTMLElement>(
      `#${CSS.escape(fieldErrorId(idPrefix, index, field))}`,
    );
    if (target !== null) {
      target.textContent = message;
      target.hidden = message === "";
    }
    const control = panel.querySelector<HTMLElement>(
      `#${CSS.escape(fieldControlId(idPrefix, index, field))}`,
    );
    control?.setAttribute("aria-invalid", message === "" ? "false" : "true");
  });
};
