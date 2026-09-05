import assert from "node:assert/strict";
import { beforeEach, describe, it } from "node:test";
import { resetBody } from "../../../../../Build/tests/dom.mjs";
import {
  fillPrototype,
  parseAttributeBindings,
  prototypeLists,
  prototypeSlots,
} from "@fgtclb/academic-persons-edit/frontend/profile/prototypes.js";
import type {
  PrototypeInstance,
  PrototypeName,
  PrototypeValues,
  SlotValue,
} from "@fgtclb/academic-persons-edit/frontend/profile/prototypes.js";
import { editingHost, select } from "./Fixtures/profile-editing.ts";

/**
 * The filler, which is the JavaScript half of "Fluid owns the markup".
 *
 * Four verbs and no fifth, and the whole point of pinning them here rather
 * than only through the two editors is that they are the contract an override
 * has to keep: `Partials/Profile/Prototypes.html` may change every tag and
 * every class, and what it may not change is which slot carries which value.
 * The prototypes below are the ones the fixture transcribes from that partial;
 * the functional suite asserts the same inventory against the rendered
 * partial, so a drift between the two fails there.
 */
/**
 * `fillPrototype()` with the slots a case is not about left out.
 *
 * `PrototypeValues` is total on purpose - a declared slot a caller forgets
 * silently drops the attribute it is bound to - so the production callers write
 * every key, `undefined` included. A test that is about two of them should not
 * have to, and the table the filler declares is right here to complete the
 * record from.
 */
const fill = <TName extends PrototypeName>(
  source: ParentNode,
  name: TName,
  filled: Partial<PrototypeValues<TName>>,
): PrototypeInstance<TName> => {
  const complete: Record<string, SlotValue> = {};
  (prototypeSlots[name] as readonly string[]).forEach((slot): void => {
    complete[slot] = undefined;
  });

  return fillPrototype(source, name, {
    ...complete,
    ...filled,
  } as PrototypeValues<TName>);
};

describe("the prototype filler", () => {
  let root: HTMLElement;

  beforeEach(() => {
    ({ root } = editingHost());
  });

  /**
   * The fixture is a hand transcription of `Partials/Profile/Prototypes.html`
   * and of the four partials it renders, so something has to check it. This is
   * one half of that: the blocks it carries and the keys their four verbs name
   * are the ones `prototypeSlots` and `prototypeLists` declare, which is what
   * the elements fill. The other half is the functional
   * `AcademicPersonsEditProfileEditingPrototypesTest`, which holds the same
   * table against the *rendered* partial - so a slot renamed in Fluid has to be
   * renamed here as well or one of the two goes red.
   */
  describe("the transcription in the fixture", () => {
    const keysOf = (template: HTMLTemplateElement, attribute: string): string[] =>
      Array.from(
        template.content.querySelectorAll(`[${attribute}]`),
        (node): string => node.getAttribute(attribute) ?? "",
      );

    it("carries exactly the prototypes the filler declares", () => {
      const rendered = Array.from(
        root.querySelectorAll<HTMLTemplateElement>("template[data-pe-proto]"),
        (template): string => template.getAttribute("data-pe-proto") ?? "",
      );

      assert.deepEqual(rendered.slice().sort(), Object.keys(prototypeSlots).sort());
      assert.equal(new Set(rendered).size, rendered.length, "A prototype is transcribed twice.");
    });

    Object.keys(prototypeSlots).forEach((name): void => {
      it(`declares the slots and lists of "${name}"`, () => {
        const template = root.querySelector<HTMLTemplateElement>(
          `template[data-pe-proto="${name}"]`,
        );
        assert.ok(template !== null, `The fixture has no "${name}" prototype.`);
        const declared = prototypeSlots[name as keyof typeof prototypeSlots];
        const lists = prototypeLists[name as keyof typeof prototypeLists];

        const found = new Set([
          ...keysOf(template, "data-pe-slot"),
          ...keysOf(template, "data-pe-when"),
          ...keysOf(template, "data-pe-attr").flatMap((value): string[] =>
            parseAttributeBindings(value).map(([, key]): string => key),
          ),
        ]);

        assert.deepEqual(
          Array.from(found).sort(),
          Array.from(new Set<string>(declared)).sort(),
        );
        assert.deepEqual(
          Array.from(new Set(keysOf(template, "data-pe-list"))).sort(),
          Array.from(new Set<string>(lists)).sort(),
        );
      });
    });
  });

  it("writes a slot value as the text of its node", () => {
    const clone = fill(root, "contact-summary-cell", {
      hasValue: true,
      isEmpty: false,
      label: "City",
      value: "Nottingham",
    });

    assert.equal(
      select(clone.fragment, ".d-md-none", HTMLElement).textContent,
      "City",
    );
    assert.equal(
      select(clone.fragment, "span[data-pe-slot='value']", HTMLElement)
        .textContent,
      "Nottingham",
    );
  });

  /**
   * The value reaches the page as text, never as markup. A display value is
   * whatever a visitor typed into a field the server did not sanitise as rich
   * text, so this is the difference between showing a script and running one.
   */
  it("renders a value that contains markup as text", () => {
    const clone = fill(root, "contact-summary-cell", {
      hasValue: true,
      isEmpty: false,
      label: "City",
      value: "<script>alert(1)</script>",
    });

    const cell = select(clone.fragment, ".col-12", HTMLElement);
    assert.equal(cell.querySelector("script"), null);
    assert.equal(
      select(cell, "span[data-pe-slot='value']", HTMLElement).textContent,
      "<script>alert(1)</script>",
    );
  });

  it("writes the attributes a binding names, and removes the rest", () => {
    const clone = fill(root, "control-input", {
      autocomplete: undefined,
      controlId: "profile-editing-document-field-0-title",
      disabled: undefined,
      inputType: "text",
      invalid: "false",
      name: "title",
      value: "Paper 7",
    });

    const control = select(clone.fragment, "input", HTMLInputElement);
    assert.equal(control.id, "profile-editing-document-field-0-title");
    assert.equal(control.getAttribute("value"), "Paper 7");
    assert.equal(control.getAttribute("aria-invalid"), "false");
    // Absent, undefined and false all mean "take the attribute off", which is
    // what keeps one prototype usable for a control that has the attribute and
    // for one that must not have it.
    assert.equal(control.hasAttribute("autocomplete"), false);
    assert.equal(control.hasAttribute("disabled"), false);
    assert.equal(control.hasAttribute("aria-describedby"), false);
    assert.equal(control.hasAttribute("data-pe-document-field"), false);
  });

  it("sets a boolean attribute empty rather than to the word true", () => {
    const clone = fill(root, "control-input", {
      controlId: "id",
      disabled: true,
      name: "title",
      required: true,
    });

    const control = select(clone.fragment, "input", HTMLInputElement);
    assert.equal(control.getAttribute("disabled"), "");
    assert.equal(control.getAttribute("required"), "");
    assert.equal(control.disabled, true);
    assert.equal(control.required, true);
  });

  it("removes the node of a condition that is falsy, and keeps a truthy one", () => {
    const shown = fill(root, "field-default", {
      characterLimit: 40,
      controlId: "id",
      errorHidden: true,
      hasCharacterLimit: true,
      label: "Note",
      required: true,
    });
    const hidden = fill(root, "field-default", {
      controlId: "id",
      errorHidden: true,
      hasCharacterLimit: false,
      label: "Note",
      required: false,
    });

    assert.ok(shown.query("[data-pe-character-counter]") !== null);
    assert.equal(
      shown.query("span.text-danger")?.textContent?.trim(),
      "*",
    );
    assert.equal(hidden.query("[data-pe-character-counter]"), null);
    assert.equal(hidden.query("span.text-danger"), null);
  });

  it("puts clones into a list element and in place of a template marker", () => {
    const control = fill(root, "control-select", {
      controlId: "id",
      name: "country",
    });
    control.list("options", [
      fill(root, "option", { label: "Italy", value: "1" }).fragment,
      fill(root, "option", { label: "France", value: "2" }).fragment,
    ]);
    const wrapper = fill(root, "field-default", {
      controlId: "id",
      errorHidden: true,
      label: "Country",
    });
    wrapper.list("control", [control.fragment]);
    wrapper.list("helptext", []);

    const select_ = select(wrapper.fragment, "select", HTMLSelectElement);
    // Into the element for a list that has a container of its own ...
    assert.deepEqual(
      Array.from(select_.options).map((option): string => option.value),
      ["", "1", "2"],
    );
    // ... and in place of the marker where the clone is the node itself, so
    // that no wrapper the partial did not ask for ends up in the page.
    assert.equal(select_.parentElement, wrapper.element);
    assert.equal(wrapper.query("template"), null);
  });

  it("skips a list whose region a condition removed", () => {
    const panel = fill(root, "document-panel", {
      busy: "false",
      errorHidden: true,
      heading: "View: Paper",
      isSave: true,
      showActions: false,
      showClose: true,
      showDisplay: false,
      showFields: false,
      spinnerHidden: true,
    });

    panel.list("displayRows", []);
    panel.list("fields", []);
    assert.equal(panel.query("dl"), null);
    assert.equal(panel.query("[data-pe-document-fields]"), null);
  });

  it("refuses a slot the prototype does not declare", () => {
    assert.throws(
      // The cast is the point: the type refuses this at compile time, and the
      // runtime has to refuse it as well, because a partial is not a type.
      (): unknown =>
        fill(root, "option", {
          label: "Italy",
          nonsense: "1",
        } as unknown as { label: string }),
      /declares no slot "nonsense"/,
    );
  });

  it("refuses a list the prototype does not declare", () => {
    const clone = fill(root, "option", { label: "Italy" });

    assert.throws(
      (): unknown => clone.list("nonsense" as never, []),
      /declares no list "nonsense"/,
    );
  });

  it("refuses a prototype the page does not render", () => {
    const body = resetBody("<div></div>");

    assert.throws(
      (): unknown => fill(body, "option", { label: "Italy" }),
      /prototype "option" is missing/,
    );
  });

  it("reads a binding list as pairs of attribute and key", () => {
    assert.deepEqual(parseAttributeBindings("id:controlId name:name"), [
      ["id", "controlId"],
      ["name", "name"],
    ]);
    assert.deepEqual(parseAttributeBindings(null), []);
    assert.deepEqual(parseAttributeBindings("   "), []);
    assert.throws(
      (): unknown => parseAttributeBindings("id"),
      /is not "attribute:key"/,
    );
  });
});
