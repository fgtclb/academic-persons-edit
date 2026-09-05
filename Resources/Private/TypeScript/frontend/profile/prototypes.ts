/**
 * The prototype filler: the whole of the JavaScript half of "Fluid owns the
 * markup".
 *
 * `Partials/Profile/Prototypes.html` renders one `<template data-pe-proto>`
 * per shape a browser rendered editor draws. An element clones the one it
 * needs and fills it here. Nothing in this file, and nothing in an element,
 * ever writes a tag name, a class or a label - all three are in the partial,
 * which is therefore an override point like every other Fluid file of this
 * extension.
 *
 * ## Four verbs, and no fifth
 *
 * | Attribute                       | Meaning                                                       |
 * |---------------------------------|---------------------------------------------------------------|
 * | `data-pe-slot="key"`            | The node's `textContent` becomes the value.                   |
 * | `data-pe-attr="attribute:key …"` | Those attributes take the value; `false`/`null` removes them, `true` sets them empty, `undefined` leaves the template's own value. |
 * | `data-pe-when="key"`            | The node is removed when the value is falsy.                  |
 * | `data-pe-list="key"`            | The insertion point of repeated clones.                       |
 *
 * `data-pe-list` inserts *into* the marked element, except on a `<template>`
 * marker, which is *replaced* by the clones - the second form is what puts a
 * control where an element cannot contribute a wrapper, the first is what puts
 * `<option>`s inside a `<select>`.
 *
 * **A prototype contains no branching.** No `<f:if>` inside a `<template>`,
 * except in `Prototypes.html` itself to decide *which* prototype is emitted. A
 * case that needs a fifth verb is the signal that the region should have been
 * server rendered, and it is escalated rather than encoded.
 *
 * ## The slot names are a closed type
 *
 * `PrototypeSlots` and `PrototypeLists` name the keys of every prototype
 * exactly as `ProfileEditingHooks` names the `data-pe-*` hooks. A key an
 * element fills that its prototype does not declare is a `typecheckJs` error,
 * and - because a type says nothing about a partial - also a runtime error
 * here, which the behavioural suite pins. The other direction, a slot the
 * partial stops emitting, is a failure of the functional prototype inventory
 * test in `AcademicPersonsEditProfileEditingPrototypesTest`.
 */

/** Everything a slot, an attribute or a condition can be given. */
export type SlotValue = string | number | boolean | null | undefined;

/** What `Profile/Field/PrototypeWrapper.html` carries in all three shapes. */
const fieldSlots = [
  "columnClass",
  "compact",
  "controlId",
  "label",
  "errorHidden",
  "errorId",
  "error",
] as const;

/** What every control of `Profile/Field/Control.html` carries. */
const controlSlots = [
  "controlId",
  "name",
  "disabled",
  "describedBy",
  "invalid",
  "documentField",
  "contactField",
] as const;

/** What the document panel and the contact editor panel have in common. */
const panelSlots = [
  "busy",
  "showClose",
  "pending",
  "spinnerHidden",
  "errorHidden",
  "error",
  "isDelete",
  "isSave",
  "deleteConfirmation",
  "showDisplay",
  "showFields",
  "showActions",
] as const;

/**
 * The slot, attribute and condition keys of every prototype.
 *
 * One entry per `<template data-pe-proto>` of
 * `Partials/Profile/Prototypes.html` and the partials it renders, and the list
 * is every `data-pe-slot`, `data-pe-attr` and `data-pe-when` key that template
 * carries.
 *
 * A value rather than a type, and the type below is derived from it, so that
 * the declaration can also be *asserted*: `prototypes.test.ts` walks the jsdom
 * fixture against this table and
 * `AcademicPersonsEditProfileEditingPrototypesTest` walks the rendered partial
 * against a copy of it. Without a runtime table the fixture is a transcription
 * nothing checks.
 */
export const prototypeSlots = {
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
    "step",
  ],
  "control-textarea": [...controlSlots, "required", "readOnly"],
  "control-rich-text": [...controlSlots, "required", "readOnly", "characterLimit"],
  "control-select": [...controlSlots, "required"],
  "control-checkbox": [
    ...controlSlots,
    "checked",
    "autosave",
    "checkedLabel",
    "uncheckedLabel",
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
    "emptyMessage",
  ],
  "contact-row": [
    "uid",
    "hidden",
    "editorId",
    "viewExpanded",
    "editExpanded",
    "deleteExpanded",
    "editorHidden",
  ],
  "contact-summary-cell": ["label", "value", "hasValue", "isEmpty"],
  "contact-editor-panel": [...panelSlots, "editorId", "title"],
} as const;

/**
 * The `data-pe-list` keys of every prototype, empty for one that repeats
 * nothing.
 */
export const prototypeLists = {
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
  "contact-editor-panel": ["displayRows", "fields"],
} as const satisfies Record<keyof typeof prototypeSlots, readonly string[]>;

/** The slot keys of one prototype, as a union. */
export type PrototypeSlots = {
  [TName in keyof typeof prototypeSlots]: (typeof prototypeSlots)[TName][number];
};

/** The list keys of one prototype, as a union - `never` where there are none. */
export type PrototypeLists = {
  [TName in keyof typeof prototypeLists]: (typeof prototypeLists)[TName][number];
};

/** The name of one `<template data-pe-proto>`. */
export type PrototypeName = keyof PrototypeSlots;

/**
 * The values one prototype accepts.
 *
 * Total rather than partial, and both directions are why: an unknown key is a
 * type error and a runtime error, and a declared key that is *not* written is a
 * type error here. Without that, a forgotten key reads `undefined`, the filler
 * removes the attribute it is bound to, and an `id` a `<label for>` points at
 * disappears without a word. Where a caller has nothing to write it says so, by
 * passing `undefined` explicitly.
 */
export type PrototypeValues<TName extends PrototypeName> = Required<
  Record<PrototypeSlots[TName], SlotValue>
>;

/** One filled clone. */
export interface PrototypeInstance<TName extends PrototypeName> {
  /** The clone, ready to be inserted. */
  readonly fragment: DocumentFragment;
  /** The first element of the clone, which every prototype here has. */
  readonly element: HTMLElement;
  /** Puts `nodes` at the `data-pe-list="key"` insertion point. */
  list(key: PrototypeLists[TName], nodes: readonly Node[]): void;
  /** A node of the clone, for the two values that are markup, not text. */
  query<TElement extends Element = HTMLElement>(
    selector: string,
  ): TElement | null;
}

const slotAttribute = "data-pe-slot";
const attrAttribute = "data-pe-attr";
const whenAttribute = "data-pe-when";
const listAttribute = "data-pe-list";

const declaredKeys = new WeakMap<HTMLTemplateElement, ReadonlySet<string>>();
const declaredLists = new WeakMap<HTMLTemplateElement, ReadonlySet<string>>();

const isTruthy = (value: SlotValue): boolean =>
  value !== undefined &&
  value !== null &&
  value !== false &&
  value !== "" &&
  value !== 0;

const text = (value: SlotValue): string =>
  value === undefined || value === null || value === false ? "" : String(value);

/**
 * Every key the template mentions, in any of the four verbs.
 *
 * Read from the template rather than from the clone, so that a key which is
 * only used inside a subtree a `data-pe-when` removed still counts as
 * declared. Cached, because the set is a property of the partial and the
 * partial does not change while the page is open.
 */
const keysOf = (template: HTMLTemplateElement): ReadonlySet<string> => {
  const cached = declaredKeys.get(template);
  if (cached !== undefined) {
    return cached;
  }
  const keys = new Set<string>();
  template.content
    .querySelectorAll(`[${slotAttribute}], [${whenAttribute}], [${attrAttribute}]`)
    .forEach((node): void => {
      const slot = node.getAttribute(slotAttribute);
      if (slot !== null) {
        keys.add(slot);
      }
      const when = node.getAttribute(whenAttribute);
      if (when !== null) {
        keys.add(when);
      }
      parseAttributeBindings(node.getAttribute(attrAttribute)).forEach(
        ([, key]): void => {
          keys.add(key);
        },
      );
    });
  declaredKeys.set(template, keys);

  return keys;
};

/** Every `data-pe-list` key the template mentions. */
const listsOf = (template: HTMLTemplateElement): ReadonlySet<string> => {
  const cached = declaredLists.get(template);
  if (cached !== undefined) {
    return cached;
  }
  const keys = new Set<string>();
  template.content
    .querySelectorAll(`[${listAttribute}]`)
    .forEach((node): void => {
      const key = node.getAttribute(listAttribute);
      if (key !== null) {
        keys.add(key);
      }
    });
  declaredLists.set(template, keys);

  return keys;
};

/** `"id:controlId name:name"` as `[["id", "controlId"], ["name", "name"]]`. */
export const parseAttributeBindings = (
  value: string | null,
): readonly (readonly [string, string])[] => {
  if (value === null || value.trim() === "") {
    return [];
  }

  return value
    .trim()
    .split(/\s+/)
    .map((binding): readonly [string, string] => {
      const separator = binding.indexOf(":");
      if (separator <= 0 || separator === binding.length - 1) {
        throw new Error(
          `The profile editing prototype binding "${binding}" is not "attribute:key".`,
        );
      }

      return [binding.slice(0, separator), binding.slice(separator + 1)];
    });
};

/**
 * Finds the `<template data-pe-proto="name">` of one prototype.
 *
 * Exported because the functional suite's counterpart, the behavioural
 * inventory test, asks for the same thing, and because an element that cannot
 * find its prototype has to fail loudly rather than render an empty panel.
 */
export const prototypeTemplate = (
  source: ParentNode,
  name: PrototypeName,
): HTMLTemplateElement => {
  const template = source.querySelector<HTMLTemplateElement>(
    `template[data-pe-proto="${name}"]`,
  );
  if (template === null) {
    throw new Error(
      `The profile editing prototype "${name}" is missing. ` +
        "Partials/Profile/Prototypes.html has to render it.",
    );
  }

  return template;
};

/**
 * Clones one prototype and fills it.
 *
 * @throws when the prototype is absent, or when `values` carries a key the
 *         prototype does not declare - both are contract breaks between the
 *         partial and the element, and both are silent bugs otherwise.
 */
export const fillPrototype = <TName extends PrototypeName>(
  source: ParentNode,
  name: TName,
  values: PrototypeValues<TName>,
): PrototypeInstance<TName> => {
  const template = prototypeTemplate(source, name);
  const declared = keysOf(template);
  Object.keys(values).forEach((key): void => {
    if (!declared.has(key)) {
      throw new Error(
        `The profile editing prototype "${name}" declares no slot "${key}".`,
      );
    }
  });
  const read = (key: string): SlotValue =>
    (values as Record<string, SlotValue>)[key];
  const fragment = template.content.cloneNode(true) as DocumentFragment;

  // Conditions first: a removed subtree is not filled, so a slot that only
  // exists in one mode costs nothing in the other.
  Array.from(fragment.querySelectorAll(`[${whenAttribute}]`)).forEach(
    (node): void => {
      const key = node.getAttribute(whenAttribute);
      if (key !== null && !isTruthy(read(key))) {
        node.remove();
      }
    },
  );
  fragment.querySelectorAll(`[${slotAttribute}]`).forEach((node): void => {
    const key = node.getAttribute(slotAttribute);
    if (key !== null) {
      // "textContent", never "innerHTML": a display value that contains
      // markup is shown, not run.
      node.textContent = text(read(key));
    }
  });
  fragment.querySelectorAll(`[${attrAttribute}]`).forEach((node): void => {
    parseAttributeBindings(node.getAttribute(attrAttribute)).forEach(
      ([attribute, key]): void => {
        const value = read(key);
        if (value === undefined) {
          // The value the template wrote stands. The only attribute a
          // prototype writes literally is the `class` of a field wrapper,
          // which carries the column of that shape; every other binding
          // names an attribute the template does not carry, where leaving
          // it alone and removing it are the same thing.
          return;
        }
        if (value === null || value === false) {
          node.removeAttribute(attribute);

          return;
        }
        node.setAttribute(attribute, value === true ? "" : String(value));
      },
    );
  });

  const element = fragment.firstElementChild;
  if (!(element instanceof HTMLElement)) {
    throw new Error(
      `The profile editing prototype "${name}" renders no element.`,
    );
  }

  return {
    element,
    fragment,
    list(key: PrototypeLists[TName], nodes: readonly Node[]): void {
      if (!listsOf(template).has(String(key))) {
        throw new Error(
          `The profile editing prototype "${name}" declares no list "${String(key)}".`,
        );
      }
      const marker = fragment.querySelector(
        `[${listAttribute}="${String(key)}"]`,
      );
      if (marker === null) {
        // The region this list sits in was removed by a "data-pe-when", which
        // is the normal case for a mode that does not show it.
        return;
      }
      if (marker instanceof HTMLTemplateElement) {
        marker.replaceWith(...nodes);

        return;
      }
      marker.append(...nodes);
    },
    query<TElement extends Element = HTMLElement>(
      selector: string,
    ): TElement | null {
      return fragment.querySelector<TElement>(selector);
    },
  };
};
