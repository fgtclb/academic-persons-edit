import assert from "node:assert/strict";
import { afterEach, beforeEach, describe, it, mock } from "node:test";
import { nextFrame, settle } from "../../../../../Build/tests/dom.mjs";
import { installFetch, type FetchDouble } from "../../../../../Build/tests/fetch.mjs";
import type { EditingContext } from "@fgtclb/academic-persons-edit/frontend/profile/context.js";
import {
  createDocumentEditing,
  initializeDocumentSections,
  type DocumentEditingController,
  type DocumentField,
} from "@fgtclb/academic-persons-edit/frontend/profile/documents.js";
import {
  documentEditorClosedEvent,
  documentEditorCloseEvent,
  documentEditorInputEvent,
  documentEditorSubmitEvent,
  ProfileDocumentEditorElement,
  registerProfileDocumentEditorElement,
  type ProfileDocumentEditorInputDetail,
} from "@fgtclb/academic-persons-edit/frontend/profile/elements/document-editor.js";
import {
  profileDocumentEditorElementName,
  profileEditingElementName,
  profileRichTextElementName,
} from "@fgtclb/academic-persons-edit/frontend/profile/elements/names.js";
import { registerProfileRichTextElement } from "@fgtclb/academic-persons-edit/frontend/profile/elements/rich-text.js";
import {
  documentRow,
  documentSection,
  labels,
  messages,
  editingHost,
  select,
  selectAll,
} from "./Fixtures/profile-editing.ts";

/**
 * The element that renders one open document or contract editor.
 *
 * ## Why this file drives properties and not a controller
 *
 * `Partials/Profile/Documents/Editor.html` used to be 266 lines of Fluid with
 * directives in them, and none of it could be asserted: the PHP tests
 * see the template text, and the behavioural suite saw a hand placed
 * transcription of what a framework made of it. The markup is a function of the
 * `documentForm` response now, so it can be - and the response's shape is
 * exactly the property set below.
 *
 * `document-editor.test.ts` keeps driving the controller and asserts what it
 * does around the element; this file asserts what the element does with what it
 * is given. The last block joins the two, because the collapse target, the
 * `aria-controls` and the focus restore are only observable together.
 */
registerProfileDocumentEditorElement();
registerProfileRichTextElement();

const field = (overrides: Partial<DocumentField> = {}): DocumentField => ({
  disabled: false,
  displayValue: "",
  label: "Title",
  name: "title",
  readOnly: false,
  required: false,
  richText: false,
  type: "text",
  value: "",
  ...overrides,
});

describe("the document editor element", () => {
  let root: HTMLElement;
  let context: EditingContext;

  const mount = async (
    properties: Partial<ProfileDocumentEditorElement>,
  ): Promise<ProfileDocumentEditorElement> => {
    const element = document.createElement(
      profileDocumentEditorElementName,
    ) as ProfileDocumentEditorElement;
    Object.assign(element, { context, open: true, ...properties });
    root.append(element);
    await element.updateComplete;

    return element;
  };

  beforeEach(() => {
    ({ context, root } = editingHost());
  });

  it("is defined under its published name", () => {
    assert.equal(
      profileDocumentEditorElementName,
      "academic-persons-edit-document-editor",
    );
    assert.equal(
      customElements.get(profileDocumentEditorElementName),
      ProfileDocumentEditorElement,
    );
    // A second call is a no-op, not the "NotSupportedError" a repeated
    // definition of the same name raises - and it leaves the definition alone.
    const defined = customElements.get(profileDocumentEditorElementName);
    registerProfileDocumentEditorElement();
    assert.equal(customElements.get(profileDocumentEditorElementName), defined);
  });

  /**
   * Light DOM, which is not a detail: the theme's Bootstrap stylesheet, the
   * popovers Bootstrap positions against `document` and CKEditor 5 all need to
   * reach the controls, and a shadow root would cut every one of them off.
   */
  it("writes its own children and opens no shadow root", async () => {
    const element = await mount({ mode: "view", heading: "View: Paper" });

    // Nothing renders into this element but the element itself: there is no
    // template engine below it. What is here is what this element put here.
    assert.equal(element.shadowRoot, null);
    assert.equal(
      Array.from(element.childNodes).some(
        (node): boolean => node.nodeType === Node.COMMENT_NODE,
      ),
      false,
    );
    assert.ok(element.querySelector("[data-pe-document-view-container]") !== null);
  });

  it("takes its editing context from the profile editor it stands in", async () => {
    // The lookup is by tag name and reads the owner's documented "context"
    // property, so a stand-in with that property is the whole contract - and
    // this test needs neither the root element module nor anything it does.
    const owner = document.createElement(profileEditingElementName) as HTMLElement & {
      context?: EditingContext;
    };
    owner.context = context;
    root.append(owner);
    const element = document.createElement(
      profileDocumentEditorElementName,
    ) as ProfileDocumentEditorElement;
    element.mode = "delete";
    element.open = true;
    owner.append(element);
    await element.updateComplete;

    assert.equal(element.context, context);
    // Proof that it is used: the button labels come off the contract.
    assert.equal(
      selectAll(element, "button", HTMLButtonElement)[1]?.textContent?.trim(),
      labels.delete,
    );
  });

  describe("in view mode", () => {
    it("lists every field as a term and a description", async () => {
      const element = await mount({
        mode: "view",
        heading: "View: Paper 7",
        fields: [
          field({ name: "title", label: "Title", displayValue: "Sample paper" }),
          field({ name: "year", label: "Year", displayValue: "1843" }),
        ],
      });

      assert.equal(
        select(element, "[data-pe-document-heading]", HTMLElement).textContent?.trim(),
        "View: Paper 7",
      );
      assert.deepEqual(
        selectAll(element, "dt", HTMLElement).map((term): string | null => term.textContent),
        ["Title", "Year"],
      );
      assert.deepEqual(
        selectAll(element, "dd", HTMLElement).map(
          (description): string | undefined => description.textContent?.trim(),
        ),
        ["Sample paper", "1843"],
      );
    });

    it("shows the label of the contract for a value that is empty", async () => {
      const element = await mount({
        mode: "view",
        fields: [field({ displayValue: "" })],
      });

      assert.equal(
        select(element, "dd", HTMLElement).textContent?.trim(),
        messages.empty,
      );
    });

    /**
     * The one value that is markup rather than text. It goes through the
     * allow-list of `parseRichTextPreview()` and reaches the page as nodes, so
     * nothing is parsed a second time on the way in.
     */
    it("renders a rich text value through the allow-list", async () => {
      const element = await mount({
        mode: "view",
        fields: [
          field({
            name: "bodytext",
            richText: true,
            displayValue: '<p>A <strong>note</strong></p><script>alert(1)</script>',
          }),
        ],
      });

      const rendered = select(element, "dd div", HTMLElement);
      assert.deepEqual(
        Array.from(rendered.children, (child): string => child.tagName),
        ["P"],
      );
      assert.equal(rendered.textContent?.trim(), "A note");
      assert.ok(rendered.querySelector("strong") !== null);
      assert.equal(rendered.querySelector("script"), null);
    });

    it("renders no form controls and no save button", async () => {
      const element = await mount({ mode: "view", fields: [field()] });

      assert.equal(element.querySelector("[data-pe-document-fields]"), null);
      assert.equal(element.querySelector("button[type='submit']"), null);
    });

    /**
     * A read-only panel has no form, so it has nothing to cancel. The panel
     * header carried a "Cancel" of its own until ACE-520, which in this mode
     * was the only control there was and only closed the panel again - the
     * same thing the row's own view toggle does, under a label promising that
     * something would be discarded.
     */
    it("offers no control at all, because there is nothing to cancel", async () => {
      const element = await mount({
        mode: "view",
        fields: [field({ displayValue: "Sample paper" })],
      });

      assert.deepEqual(selectAll(element, "button", HTMLButtonElement), []);
      assert.equal(element.querySelector("[data-pe-document-cancel]"), null);
    });
  });

  describe("in add and edit mode", () => {
    it("renders a control of the shape each field asks for", async () => {
      const element = await mount({
        mode: "add",
        fields: [
          field({ name: "title", type: "text" }),
          field({
            name: "kind",
            type: "select",
            options: [
              { label: "Article", value: "article" },
              { label: "Book", value: "book" },
            ],
          }),
          field({ name: "note", type: "textarea" }),
          field({ name: "hidden", type: "checkbox" }),
        ],
        values: { title: "Sample paper", kind: "book", note: "A note", hidden: true },
      });

      assert.equal(
        select(element, '[data-pe-document-field="title"]', HTMLInputElement).value,
        "Sample paper",
      );
      // The selectedness is written on the options rather than on the select,
      // because the option list is filled after the element itself is.
      assert.equal(
        select(element, '[data-pe-document-field="kind"]', HTMLSelectElement).value,
        "book",
      );
      assert.equal(
        select(element, '[data-pe-document-field="note"]', HTMLTextAreaElement).value,
        "A note",
      );
      assert.equal(
        select(element, '[data-pe-document-field="hidden"]', HTMLInputElement).checked,
        true,
      );
    });

    /**
     * The three timeline years are the only fields that declare bounds. They
     * reach the control as `min`, `max` and `step`, so the browser refuses out
     * of range input before the controller ever posts it - and a field that
     * declares none carries none of the three, rather than an empty attribute.
     */
    it("puts the declared bounds of a number field on its control", async () => {
      const element = await mount({
        mode: "add",
        fields: [
          field({ name: "year", type: "number", min: 0, max: 9999, step: 1 }),
          field({ name: "title", type: "text" }),
        ],
        values: { year: 2026, title: "Sample paper" },
      });

      const year = select(
        element,
        '[data-pe-document-field="year"]',
        HTMLInputElement,
      );
      assert.equal(year.type, "number");
      assert.equal(year.getAttribute("min"), "0");
      assert.equal(year.getAttribute("max"), "9999");
      assert.equal(year.getAttribute("step"), "1");
      assert.equal(year.value, "2026");

      const title = select(
        element,
        '[data-pe-document-field="title"]',
        HTMLInputElement,
      );
      assert.equal(title.hasAttribute("min"), false);
      assert.equal(title.hasAttribute("max"), false);
      assert.equal(title.hasAttribute("step"), false);
    });

    /**
     * The two contract dates are deliberately not an `<input type="date">`.
     * The native control forces the locale of the browser rather than the one
     * of the site and cannot be styled with the rest of the editor, and its
     * calendar is not the date picker this editor is meant to get - that is a
     * feature of its own and is not shipped yet. So the field type stays
     * `date`, which is what the endpoint serializes and validates by, and the
     * control it is rendered as is a text input showing the format as a hint.
     */
    it("renders a date field as a text control carrying its format hint", async () => {
      const element = await mount({
        mode: "add",
        fields: [
          field({
            name: "validFrom",
            type: "date",
            placeholder: "dd.mm.yyyy",
          }),
          field({ name: "title", type: "text" }),
        ],
        values: { validFrom: "15.01.2026", title: "Sample paper" },
      });

      const validFrom = select(
        element,
        '[data-pe-document-field="validFrom"]',
        HTMLInputElement,
      );
      assert.equal(validFrom.getAttribute("type"), "text");
      assert.equal(validFrom.getAttribute("placeholder"), "dd.mm.yyyy");
      assert.equal(validFrom.value, "15.01.2026");

      // A field that declares no hint carries no empty attribute either.
      const title = select(
        element,
        '[data-pe-document-field="title"]',
        HTMLInputElement,
      );
      assert.equal(title.getAttribute("type"), "text");
      assert.equal(title.hasAttribute("placeholder"), false);
    });

    it("gives every control an id its label points at", async () => {
      const element = await mount({
        mode: "add",
        fields: [field({ name: "kind" }), field({ name: "title" })],
      });

      const control = select(
        element,
        '[data-pe-document-field="title"]',
        HTMLInputElement,
      );
      assert.equal(control.id, "profile-editing-document-field-1-title");
      assert.equal(
        selectAll(element, "label.form-label", HTMLLabelElement)[1]?.htmlFor,
        control.id,
      );
    });

    it("marks a required field and disables the ones the server locked", async () => {
      const element = await mount({
        mode: "edit",
        fields: [
          field({ name: "title", required: true }),
          field({ name: "kind", disabled: true }),
        ],
      });

      assert.equal(
        select(element, '[data-pe-document-field="title"]', HTMLInputElement).required,
        true,
      );
      assert.equal(
        select(element, '[data-pe-document-field="kind"]', HTMLInputElement).disabled,
        true,
      );
      assert.equal(selectAll(element, ".text-danger", HTMLElement).length, 1);
    });

    /**
     * The help of a field is a popover, and its icon is cloned from the
     * `<template data-pe-icon>` Fluid rendered - the icon registry knows the
     * identifiers and the site's overrides, and a browser can ask neither.
     */
    it("clones the icon Fluid rendered for a field that has help", async () => {
      const element = await mount({
        mode: "add",
        fields: [field({ helptext: "The title of the work." })],
      });

      const help = select(element, "[data-pe-helptext]", HTMLButtonElement);
      assert.equal(help.getAttribute("data-bs-toggle"), "popover");
      assert.equal(help.getAttribute("data-bs-content"), "The title of the work.");
      assert.equal(
        help.getAttribute("aria-label"),
        "Title: The title of the work.",
      );
      assert.ok(help.querySelector('[data-test-icon="help"]') !== null);
    });

    it("locks every control and both buttons while a request is running", async () => {
      const element = await mount({
        mode: "add",
        fields: [field()],
        pending: true,
      });

      assert.equal(
        select(element, "[data-pe-document-view-container]", HTMLElement).getAttribute(
          "aria-busy",
        ),
        "true",
      );
      assert.equal(
        select(element, '[data-pe-document-field="title"]', HTMLInputElement).disabled,
        true,
      );
      assert.deepEqual(
        selectAll(element, "button", HTMLButtonElement).map(
          (button): boolean => button.disabled,
        ),
        [true, true],
      );
      assert.ok(element.querySelector(".spinner-border") !== null);
    });

    /**
     * The counterpart of the view mode case above: the visitor is offered the
     * cancel once, in the action bar below the form, and not a second time in
     * the panel header where it did exactly the same thing.
     */
    it("offers the cancel exactly once, in the action bar", async () => {
      const element = await mount({ mode: "edit", fields: [field()] });

      const cancels = selectAll(
        element,
        "[data-pe-document-cancel]",
        HTMLButtonElement,
      );
      const actions = select(element, '[data-pe-when="showActions"]', HTMLElement);
      assert.equal(cancels.length, 1);
      assert.equal(cancels[0]?.parentElement, actions);
      assert.equal(
        select(element, "[data-pe-document-save]", HTMLButtonElement).parentElement,
        actions,
      );
      // Which container, and not merely "the same one as the submit": the two
      // stood together in the header before, so a shared parent is exactly what
      // the removed markup had. The bar is the last thing in the form, below
      // the fields, and the heading is not in it.
      assert.equal(
        select(element, "[data-pe-document-form]", HTMLElement).lastElementChild,
        actions,
      );
      assert.equal(
        actions.contains(select(element, "[data-pe-document-heading]", HTMLElement)),
        false,
      );
    });

    it("shows the message of the request and the message of each refused field", async () => {
      const element = await mount({
        mode: "add",
        fields: [field({ name: "title" }), field({ name: "year" })],
        error: "Please check.",
        errors: { year: "Must be a year." },
      });

      assert.equal(
        select(element, ".alert-danger", HTMLElement).textContent?.trim(),
        "Please check.",
      );
      const control = select(
        element,
        '[data-pe-document-field="year"]',
        HTMLInputElement,
      );
      assert.equal(control.getAttribute("aria-invalid"), "true");
      assert.equal(
        control.getAttribute("aria-describedby"),
        "profile-editing-document-field-error-1-year",
      );
      assert.equal(
        select(element, "#profile-editing-document-field-error-1-year", HTMLElement)
          .textContent?.trim(),
        "Must be a year.",
      );
      assert.equal(
        select(element, '[data-pe-document-field="title"]', HTMLInputElement)
          .getAttribute("aria-invalid"),
        "false",
      );
    });

    it("counts the characters of a rich text field that has a limit", async () => {
      const element = await mount({
        mode: "add",
        fields: [
          field({ name: "bodytext", type: "textarea", richText: true, characterLimit: 40 }),
        ],
      });

      const counter = select(element, "[data-pe-character-counter]", HTMLElement);
      assert.equal(
        counter.getAttribute("data-pe-for"),
        "profile-editing-document-field-0-bodytext",
      );
      assert.equal(counter.textContent?.replace(/\s+/g, " ").trim(), "0 / 40");
    });
  });

  describe("in delete mode", () => {
    it("asks the question and offers the destructive action", async () => {
      const element = await mount({
        mode: "delete",
        heading: "Delete: Paper 7",
        deleteConfirmation: "Delete this entry?",
        fields: [field()],
      });

      assert.equal(select(element, "p", HTMLElement).textContent?.trim(), "Delete this entry?");
      const buttons = selectAll(element, "button", HTMLButtonElement);
      // The action bar carries both, and it is the only place a control lives.
      assert.equal(buttons.length, 2);
      assert.equal(buttons[1]?.type, "submit");
      assert.ok(buttons[1]?.classList.contains("btn-danger"));
      assert.equal(buttons[1]?.textContent?.trim(), labels.delete);
      assert.equal(element.querySelector("[data-pe-document-fields]"), null);
    });
  });

  describe("what it reports to its owner", () => {
    it("asks to be closed when the cancel button is pressed", async () => {
      const element = await mount({ mode: "add", fields: [field()] });
      let closes = 0;
      element.addEventListener(documentEditorCloseEvent, (): void => {
        closes += 1;
      });

      select(element, "[data-pe-document-cancel]", HTMLButtonElement).click();

      assert.equal(closes, 1);
    });

    it("asks to be saved when the form is submitted, and never posts it", async () => {
      const element = await mount({ mode: "add", fields: [field()] });
      let submits = 0;
      element.addEventListener(documentEditorSubmitEvent, (): void => {
        submits += 1;
      });

      const event = new Event("submit", { bubbles: true, cancelable: true });
      select(element, "[data-pe-document-form]", HTMLFormElement).dispatchEvent(event);

      assert.equal(submits, 1);
      assert.equal(event.defaultPrevented, true);
    });

    it("reports the name and the value of every control that changes", async () => {
      const element = await mount({
        mode: "add",
        fields: [
          field({ name: "title" }),
          field({ name: "hidden", type: "checkbox" }),
        ],
      });
      const reported: ProfileDocumentEditorInputDetail[] = [];
      element.addEventListener(documentEditorInputEvent, (event: Event): void => {
        reported.push(
          (event as CustomEvent<ProfileDocumentEditorInputDetail>).detail,
        );
      });

      const text = select(element, '[data-pe-document-field="title"]', HTMLInputElement);
      text.value = "Typed";
      text.dispatchEvent(new Event("input", { bubbles: true }));
      const checkbox = select(
        element,
        '[data-pe-document-field="hidden"]',
        HTMLInputElement,
      );
      checkbox.checked = true;
      checkbox.dispatchEvent(new Event("change", { bubbles: true }));

      assert.deepEqual(reported, [
        { name: "title", value: "Typed" },
        { name: "hidden", value: true },
      ]);
    });

    /**
     * The close is reported a frame after the leave transition, never inside
     * the update that started it: the owner removes the element when it hears
     * this, and tearing the tree out from inside `updated()` is how a reactive
     * element ends up patching detached nodes.
     */
    it("reports the finished close one frame after the transition", async () => {
      const element = await mount({ mode: "view", fields: [field()] });
      let closed = 0;
      element.addEventListener(documentEditorClosedEvent, (): void => {
        closed += 1;
      });

      element.open = false;
      await element.updateComplete;
      await settle(20);
      assert.equal(closed, 0);

      await nextFrame();

      assert.equal(closed, 1);
    });
  });

  describe("the collapse transition", () => {
    const computeStyle = globalThis.getComputedStyle;

    const withDuration = (duration: string): void => {
      globalThis.getComputedStyle = ((): CSSStyleDeclaration =>
        ({ transitionDuration: duration }) as CSSStyleDeclaration) as typeof computeStyle;
      mock.timers.enable({ apis: ["setTimeout"] });
    };

    afterEach(() => {
      globalThis.getComputedStyle = computeStyle;
      mock.timers.reset();
    });

    it("dresses the panel with the classes the stylesheet declares", async () => {
      withDuration("0.3s");

      const element = await mount({ mode: "view", fields: [field()] });

      assert.ok(
        select(element, "[data-pe-document-view-container]", HTMLElement).classList.contains(
          "academic-persons-profile-editing-document-collapse-enter-active",
        ),
      );
    });

    it("reports the close even when no transition ever ends", async () => {
      withDuration("0.3s");
      const element = await mount({ mode: "view", fields: [field()] });
      let closed = 0;
      element.addEventListener(documentEditorClosedEvent, (): void => {
        closed += 1;
      });

      element.open = false;
      await element.updateComplete;
      mock.timers.tick(400);
      await nextFrame();

      assert.equal(closed, 1);
    });
  });

  /**
   * The reason `<academic-persons-edit-rich-text>` is an element and not a
   * `<textarea>` in this template.
   *
   * CKEditor replaces the textarea in the document with its own container and
   * owns everything below it. The document editor creates and removes the
   * element and never writes inside it, so an update - which is what a
   * validation error causes - has to leave the live editor exactly where it
   * was.
   */
  describe("a live rich text editor", () => {
    it("survives a re-render of the editor around it", async () => {
      const element = await mount({
        mode: "add",
        fields: [field({ name: "bodytext", type: "textarea", richText: true })],
        values: { bodytext: "<p>Note</p>" },
      });
      await settle(20);
      const wrapper = select(element, profileRichTextElementName, HTMLElement);
      const textarea = select(wrapper, "textarea", HTMLTextAreaElement);
      assert.equal(textarea.getAttribute("data-test-ckeditor"), "live");

      element.errors = { bodytext: "Too long." };
      await element.updateComplete;

      // The re-render happened...
      assert.equal(
        select(element, ".invalid-feedback", HTMLElement).textContent?.trim(),
        "Too long.",
      );
      // ... and it went around the editor rather than through it.
      assert.equal(select(element, profileRichTextElementName, HTMLElement), wrapper);
      assert.equal(select(wrapper, "textarea", HTMLTextAreaElement), textarea);
      assert.equal(textarea.getAttribute("data-test-ckeditor"), "live");
      assert.equal(textarea.getAttribute("data-test-ckeditor-destroys"), null);
    });

    it("is destroyed exactly once when the editor is removed", async () => {
      const element = await mount({
        mode: "add",
        fields: [field({ name: "bodytext", type: "textarea", richText: true })],
      });
      await settle(20);
      const textarea = select(element, "textarea", HTMLTextAreaElement);

      element.remove();
      await settle(20);

      assert.equal(textarea.getAttribute("data-test-ckeditor"), "destroyed");
      assert.equal(textarea.getAttribute("data-test-ckeditor-destroys"), "1");
    });
  });

  /**
   * The contact list is an element of its own, driven by
   * `contract-contacts-element.test.ts`. What this case pins is where the
   * document editor puts it and what it is handed.
   */
  it("hands a contract's contacts to the element that will render them", async () => {
    const sections = [
      { identifier: "addresses", label: "Addresses", singularLabel: "Address", items: [] },
    ];
    const element = await mount({
      mode: "view",
      kind: "contract",
      record: 5,
      fields: [field()],
      contactSections: sections,
      contactEmptyMessage: "No addresses yet.",
    });

    const contacts = select(
      element,
      "academic-persons-edit-contract-contacts",
      HTMLElement,
    ) as HTMLElement & Record<string, unknown>;
    assert.equal(contacts.context, element.context);
    assert.equal(contacts.contract, 5);
    assert.equal(contacts.sections, sections);
    assert.equal(contacts.emptyMessage, "No addresses yet.");
  });

  it("renders no contact list for a document, and none while it is edited", async () => {
    const document_ = await mount({ mode: "view", kind: "document", fields: [field()] });
    assert.equal(
      document_.querySelector("academic-persons-edit-contract-contacts"),
      null,
    );

    const edited = await mount({ mode: "edit", kind: "contract", fields: [field()] });
    assert.equal(edited.querySelector("academic-persons-edit-contract-contacts"), null);
  });
});

/**
 * The element and the controller together: where the editor is put, what the
 * trigger says about it, and what is left behind when it closes.
 */
describe("opening a document editor in the page", () => {
  let fetch: FetchDouble;
  let root: HTMLElement;
  let controller: DocumentEditingController;

  beforeEach(() => {
    fetch = installFetch();
    ({ root } = editingHost({
        content: documentSection({
          identifier: "publications",
          rows: documentRow({ uid: 7, sorting: 10, position: 0, title: "Paper 7" }),
        }),
      }));
    controller = createDocumentEditing(root);
    initializeDocumentSections(root);
  });

  const open = async (selector: string): Promise<HTMLButtonElement> => {
    const button = select(root, selector, HTMLButtonElement);
    fetch.respond({
      success: true,
      record: 7,
      fields: [
        {
          name: "title",
          label: "Title",
          type: "text",
          value: "Paper 7",
          displayValue: "Paper 7",
        },
      ],
    });
    button.click();
    await settle(20);

    return button;
  };

  it("creates the editor inside the collapse target of the row", async () => {
    const button = await open('[data-item-uid="7"] [data-pe-document-view]');

    const target = select(
      root,
      '[data-item-uid="7"] [data-pe-document-item-collapse-target]',
      HTMLElement,
    );
    const element = select(target, profileDocumentEditorElementName, HTMLElement);
    assert.equal(element.parentElement, target);
    assert.equal(button.getAttribute("aria-controls"), target.id);
    assert.equal(button.getAttribute("aria-expanded"), "true");
    assert.equal(
      select(element, "[data-pe-document-heading]", HTMLElement).textContent?.trim(),
      "View: Paper 7",
    );
  });

  it("creates the editor inside the collapse target of the section for a new record", async () => {
    fetch.respond({ success: true, record: null, fields: [] });
    select(root, "[data-pe-document-add]", HTMLButtonElement).click();
    await settle(20);

    const target = select(root, "[data-pe-document-add-collapse-target]", HTMLElement);
    assert.ok(target.querySelector(profileDocumentEditorElementName) !== null);
  });

  it("removes the editor and returns the caret to the trigger when it closes", async () => {
    const button = await open('[data-item-uid="7"] [data-pe-document-view]');

    controller.closeDocument();
    await settle(20);
    await nextFrame();
    await settle(20);

    assert.equal(root.querySelector(profileDocumentEditorElementName), null);
    assert.equal(document.activeElement, button);
    assert.equal(controller.document.open, false);
  });

  /**
   * A close does not set `pending`, so the next open is accepted while the
   * previous panel is still animating out - the leave transition is 0.3s and
   * one `documentForm` request fits into it comfortably.
   *
   * The close of the first editor is reported one frame after its transition
   * ends. If that report is acted on through the controller's *current*
   * editor rather than through the element that fired it, the panel the
   * visitor just opened is torn down: its CKEditor instances are destroyed and
   * the element is taken out of the document, with `document.open` still true
   * and nothing left to render into. `settle()` never reaches a frame, so the
   * window is reproducible here exactly as it is in a browser.
   */
  it("survives a reopen while the previous editor is still closing", async () => {
    await open('[data-item-uid="7"] [data-pe-document-view]');
    const first = select(root, profileDocumentEditorElementName, HTMLElement);

    controller.closeDocument();
    await settle(20);

    // The close is now waiting for its frame. Open the next editor inside that
    // window, with a rich text field so that a teardown of the wrong element
    // is visible rather than merely structural.
    fetch.respond({
      success: true,
      record: 7,
      fields: [
        {
          name: "bodytext",
          label: "Abstract",
          type: "textarea",
          richText: true,
          value: "<p>Note</p>",
        },
      ],
    });
    select(root, '[data-item-uid="7"] [data-pe-document-edit]', HTMLButtonElement).click();
    await settle(20);
    const second = select(root, profileDocumentEditorElementName, HTMLElement);
    assert.notEqual(second, first);
    const textarea = select(second, "textarea", HTMLTextAreaElement);
    assert.equal(textarea.getAttribute("data-test-ckeditor"), "live");

    await nextFrame();
    await settle(20);

    assert.equal(select(root, profileDocumentEditorElementName, HTMLElement), second);
    assert.equal(second.isConnected, true);
    assert.equal(textarea.getAttribute("data-test-ckeditor"), "live");
    assert.equal(controller.document.open, true);
    assert.equal(first.isConnected, false);
  });

  /**
   * `Partials/Profile/Prototypes.html` is an override point, and an override
   * that drops a prototype makes `fillPrototype()` throw inside the element's
   * update - the update `openDocument()` awaits before it initialises the
   * editors of the panel. What has to happen then is what happens for a
   * refused request: the failure is announced and the section goes back to
   * where it was. What must not happen is a busy, empty panel with an
   * uncaught error behind it and nothing said.
   */
  it("takes the editor back out and reports when a prototype is missing", async () => {
    select(
      root,
      'template[data-pe-proto="document-panel"]',
      HTMLTemplateElement,
    ).remove();

    const button = await open('[data-item-uid="7"] [data-pe-document-view]');

    assert.equal(root.querySelector(profileDocumentEditorElementName), null);
    assert.equal(button.getAttribute("aria-expanded"), "false");
    assert.equal(controller.document.open, false);
    assert.equal(controller.document.pending, false);
    assert.equal(document.activeElement, button);
    assert.equal(
      select(root, '[data-pe-status-toast="alert"] .status-message', HTMLElement)
        .textContent,
      messages.errorMessage,
    );
  });

  /**
   * The editor is created per open and never moved: a move disconnects the
   * element, and a disconnect destroys the CKEditor instances below it.
   */
  it("replaces the editor rather than moving it when another row is opened", async () => {
    await open('[data-item-uid="7"] [data-pe-document-view]');
    const first = select(root, profileDocumentEditorElementName, HTMLElement);

    fetch.respond({ success: true, record: 7, fields: [] });
    select(root, '[data-item-uid="7"] [data-pe-document-edit]', HTMLButtonElement).click();
    await settle(20);

    const second = selectAll(root, profileDocumentEditorElementName, HTMLElement);
    assert.equal(second.length, 1);
    assert.notEqual(second[0], first);
  });
});
