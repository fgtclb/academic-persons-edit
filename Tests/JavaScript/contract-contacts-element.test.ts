import assert from "node:assert/strict";
import { beforeEach, describe, it } from "node:test";
import { settle } from "../../../../../Build/tests/dom.mjs";
import { installFetch, type FetchDouble } from "../../../../../Build/tests/fetch.mjs";
import type { EditingContext } from "@fgtclb/academic-persons-edit/frontend/profile/context.js";
import {
  createDocumentEditing,
  initializeDocumentSections,
  type ContractContactSection,
  type DocumentEditingController,
  type DocumentField,
} from "@fgtclb/academic-persons-edit/frontend/profile/documents.js";
import {
  emptyContractContactEditor,
  ProfileContractContactsElement,
  registerProfileContractContactsElement,
  type ProfileContractContactEditorState,
} from "@fgtclb/academic-persons-edit/frontend/profile/elements/contract-contacts.js";
import {
  profileContractContactsElementName,
  profileEditingElementName,
} from "@fgtclb/academic-persons-edit/frontend/profile/elements/names.js";
import {
  documentRow,
  documentSection,
  endpoints,
  labels,
  messages,
  editingHost,
  select,
  selectAll,
} from "./Fixtures/profile-editing.ts";

/**
 * The element that renders the contacts of a contract.
 *
 * ## Why this file drives properties and the second half drives the page
 *
 * `Partials/Profile/Documents/ContractContacts.html` and
 * `…/ContractContactEditor.html` render the prototypes of this list rather
 * than finished markup in them, and none of it could be asserted: the PHP tests see the
 * template text, and the behavioural suite saw a hand placed transcription of
 * what a framework made of it. The markup is a function of the sections
 * and of the open editor, and that pair is exactly the property set below.
 *
 * `contract-contacts.test.ts` keeps driving the controller and asserts what a
 * request does to the state; this file asserts what the element makes of that
 * state, and - in the second half - that the controls it renders reach the
 * controller at all, which is the one thing neither of the two can see alone.
 */
registerProfileContractContactsElement();

const field = (overrides: Partial<DocumentField> = {}): DocumentField => ({
  disabled: false,
  displayValue: "",
  label: "City",
  name: "city",
  readOnly: false,
  required: false,
  richText: false,
  type: "text",
  value: "",
  ...overrides,
});

const contact = (uid: number, city: string, hidden = false): {
  hidden: boolean;
  summary: { label: string; value: string }[];
  uid: number;
} => ({
  hidden,
  summary: [{ label: "City", value: city }],
  uid,
});

const addresses = (
  ...items: ReturnType<typeof contact>[]
): ContractContactSection[] => [
  {
    identifier: "addresses",
    items,
    label: "Addresses",
    singularLabel: "Address",
  },
];

const openEditor = (
  overrides: Partial<ProfileContractContactEditorState> = {},
): ProfileContractContactEditorState => ({
  ...emptyContractContactEditor,
  mode: "add",
  open: true,
  section: "addresses",
  title: "Add: Address",
  ...overrides,
});

describe("the contract contacts element", () => {
  let root: HTMLElement;
  let context: EditingContext;

  const mount = async (
    properties: Partial<ProfileContractContactsElement>,
  ): Promise<ProfileContractContactsElement> => {
    const element = document.createElement(
      profileContractContactsElementName,
    ) as ProfileContractContactsElement;
    Object.assign(element, { context, contract: 5, ...properties });
    root.append(element);
    await element.updateComplete;

    return element;
  };

  beforeEach(() => {
    ({ context, root } = editingHost());
  });

  it("is defined under its published name", () => {
    assert.equal(
      profileContractContactsElementName,
      "academic-persons-edit-contract-contacts",
    );
    assert.equal(
      customElements.get(profileContractContactsElementName),
      ProfileContractContactsElement,
    );
    // A second call is a no-op, not the "NotSupportedError" a repeated
    // definition of the same name raises - and it leaves the definition alone.
    const defined = customElements.get(profileContractContactsElementName);
    registerProfileContractContactsElement();
    assert.equal(customElements.get(profileContractContactsElementName), defined);
  });

  it("writes its own children and opens no shadow root", async () => {
    const element = await mount({ sections: addresses(contact(21, "London")) });

    // Nothing renders into this element but the element itself: there is no
    // template engine below it. What is here is what this element put here.
    assert.equal(element.shadowRoot, null);
    assert.equal(
      Array.from(element.childNodes).some(
        (node): boolean => node.nodeType === Node.COMMENT_NODE,
      ),
      false,
    );
    assert.ok(element.querySelector("[data-pe-contract-contact-section]") !== null);
  });

  it("takes its editing context from the profile editor it stands in", async () => {
    const owner = document.createElement(profileEditingElementName) as HTMLElement & {
      context?: EditingContext;
    };
    owner.context = context;
    root.append(owner);
    const element = document.createElement(
      profileContractContactsElementName,
    ) as ProfileContractContactsElement;
    element.sections = addresses(contact(21, "London"));
    owner.append(element);
    await element.updateComplete;

    assert.equal(element.context, context);
    // Proof that it is used: the labels of the controls come off the contract.
    assert.equal(
      select(element, "[data-pe-contract-contact-view]", HTMLButtonElement).title,
      labels.view,
    );
  });

  describe("the list", () => {
    it("renders one section per entry, with its heading and its rows", async () => {
      const element = await mount({
        sections: [
          ...addresses(contact(21, "London"), contact(22, "Turin")),
          {
            identifier: "phoneNumbers",
            items: [contact(31, "+44")],
            label: "Phone numbers",
            singularLabel: "Phone number",
          },
        ],
      });

      assert.deepEqual(
        selectAll(element, "[data-pe-contract-contact-section]", HTMLElement).map(
          (section): string | null =>
            section.getAttribute("data-pe-contract-contact-section"),
        ),
        ["addresses", "phoneNumbers"],
      );
      assert.deepEqual(
        selectAll(element, "h3", HTMLElement).map(
          (heading): string | undefined => heading.textContent?.trim(),
        ),
        ["Addresses", "Phone numbers"],
      );
      assert.deepEqual(
        selectAll(element, "[data-pe-contract-contact-item]", HTMLElement).map(
          (item): string | null => item.getAttribute("data-pe-contract-contact-item"),
        ),
        ["21", "22", "31"],
      );
    });

    it("renders the summary of a row as a label and a value", async () => {
      const element = await mount({ sections: addresses(contact(21, "London")) });

      const row = select(element, '[data-pe-contract-contact-item="21"]', HTMLElement);
      assert.equal(select(row, ".d-md-none", HTMLElement).textContent?.trim(), "City");
      assert.equal(select(row, "span", HTMLElement).textContent?.trim(), "London");
    });

    it("shows a dash for a summary value the record does not have", async () => {
      const element = await mount({ sections: addresses(contact(21, "")) });

      const row = select(element, '[data-pe-contract-contact-item="21"]', HTMLElement);
      assert.equal(select(row, "span", HTMLElement).textContent?.trim(), "—");
    });

    it("shows the empty message of a section that has no contacts", async () => {
      const element = await mount({
        sections: addresses(),
        emptyMessage: messages.contractContactEmpty,
      });

      const empty = select(element, "p[role='status']", HTMLElement);
      assert.equal(empty.textContent?.trim(), messages.contractContactEmpty);
      assert.equal(element.querySelector("[data-pe-contract-contact-item]"), null);
    });

    /**
     * Both used to be class names this module wrote. They are not any more:
     * the striping is a `:nth-child` rule of `profile-editing.scss` and a
     * hidden contact is a `data-pe-contract-contact-hidden` attribute the
     * stylesheet dims. No class name of the editor is spelled in TypeScript,
     * which is what makes the whole list overridable in Fluid.
     */
    it("marks a hidden contact and leaves the striping to the stylesheet", async () => {
      const element = await mount({
        sections: addresses(contact(21, "London"), contact(22, "Turin", true)),
      });

      const rows = selectAll(element, "[data-pe-contract-contact-item]", HTMLElement);
      assert.equal(rows[0]?.hasAttribute("data-pe-contract-contact-hidden"), false);
      assert.equal(rows[1]?.hasAttribute("data-pe-contract-contact-hidden"), true);
      assert.deepEqual(
        rows.map((row): string => row.className),
        [
          "row g-0 align-items-center border-bottom py-2 ps-3",
          "row g-0 align-items-center border-bottom py-2 ps-3",
        ],
      );
    });

    /**
     * The controls of the list are the seven icons `Templates/Profile/Index.html`
     * renders as `<template data-pe-icon="…">`. They are cloned rather than
     * written in TypeScript, because the icon registry knows the identifiers
     * and the site's overrides and a browser can ask neither.
     */
    it("clones the icon Fluid rendered for every control", async () => {
      const element = await mount({ sections: addresses(contact(21, "London")) });

      assert.deepEqual(
        selectAll(element, "[data-test-icon]", HTMLElement).map(
          (icon): string | null => icon.getAttribute("data-test-icon"),
        ),
        ["add", "visible", "hidden", "view", "view-close", "move-down", "move-up", "delete", "edit"],
      );
    });

    /**
     * The toggle names the press - hide or show - while the glyph shows the
     * state, and a hidden row carries the tag Fluid renders for it. There is
     * no `aria-pressed`: a label that changes with the state would announce
     * the opposite of what a pressed button does.
     */
    it("marks the toggle, the label and the badge of a hidden contact", async () => {
      const element = await mount({
        sections: addresses(contact(21, "London"), contact(22, "Torino", true)),
      });
      const toggleOf = (uid: number): HTMLButtonElement =>
        select(
          element,
          `[data-pe-contract-contact-item="${uid}"] [data-pe-contract-contact-hide]`,
          HTMLButtonElement,
        );
      const iconOf = (uid: number, state: string): HTMLElement =>
        select(toggleOf(uid), `[data-pe-visibility-icon="${state}"]`, HTMLElement);

      assert.equal(toggleOf(21).hasAttribute("aria-pressed"), false);
      assert.equal(toggleOf(21).getAttribute("aria-label"), labels.hide);
      assert.equal(iconOf(21, "visible").hidden, false);
      assert.equal(iconOf(21, "hidden").hidden, true);
      assert.equal(toggleOf(22).hasAttribute("aria-pressed"), false);
      assert.equal(toggleOf(22).getAttribute("aria-label"), labels.show);
      assert.equal(toggleOf(22).getAttribute("title"), labels.show);
      assert.equal(iconOf(22, "visible").hidden, true);
      assert.equal(iconOf(22, "hidden").hidden, false);
      assert.equal(
        element.querySelector('[data-pe-contract-contact-item="21"] .badge'),
        null,
      );
      assert.equal(
        select(element, '[data-pe-contract-contact-item="22"] .badge', HTMLElement).textContent,
        labels.hidden,
      );
    });

    /**
     * The eye is drawn twice - Fluid renders the open and the closed glyph,
     * one of them `hidden`, and so is the visibility toggle - so the icon
     * inventory above carries nine entries for seven controls. Which of the
     * two is showing is the element's answer,
     * and on a row that has nothing open it is the one that says "show".
     */
    it("hides the closing eye of a row that has nothing open", async () => {
      const element = await mount({ sections: addresses(contact(21, "London")) });

      const view = select(
        element,
        "[data-pe-contract-contact-view]",
        HTMLButtonElement,
      );
      assert.equal(view.getAttribute("aria-expanded"), "false");
      assert.equal(
        select(view, '[data-pe-view-icon="collapsed"]', HTMLElement).hidden,
        false,
      );
      assert.equal(
        select(view, '[data-pe-view-icon="expanded"]', HTMLElement).hidden,
        true,
      );
      assert.equal(view.getAttribute("aria-label"), labels.view);
    });

    it("labels every control from the editing contract", async () => {
      const element = await mount({ sections: addresses(contact(21, "London")) });

      assert.equal(
        select(element, "[data-pe-contract-contact-add] .visually-hidden", HTMLElement)
          .textContent,
        labels.add,
      );
      assert.deepEqual(
        selectAll(
          element,
          "[data-pe-contract-contact-actions] button",
          HTMLButtonElement,
        ).map((button): string | null => button.getAttribute("aria-label")),
        [labels.hide,
          labels.view, labels.sortDown, labels.sortUp, labels.delete, labels.edit],
      );
    });

    it("disables each sort control at its end of the list", async () => {
      const element = await mount({
        sections: addresses(contact(21, "London"), contact(22, "Turin")),
      });

      const rows = selectAll(element, "[data-pe-contract-contact-item]", HTMLElement);
      assert.deepEqual(
        selectAll(rows[0] ?? element, "[data-pe-contract-contact-sort]", HTMLButtonElement)
          .map((button): boolean => button.disabled),
        [false, true],
      );
      assert.deepEqual(
        selectAll(rows[1] ?? element, "[data-pe-contract-contact-sort]", HTMLButtonElement)
          .map((button): boolean => button.disabled),
        [true, false],
      );
    });

    it("renders no editor while none is open", async () => {
      const element = await mount({ sections: addresses(contact(21, "London")) });

      assert.equal(element.querySelector("[data-pe-contract-contact-editor]"), null);
    });
  });

  describe("the editor", () => {
    it("stands below the heading of its section for an addition", async () => {
      const element = await mount({
        sections: addresses(contact(21, "London")),
        editor: openEditor({ fields: [field()] }),
      });

      const editor = select(element, "[data-pe-contract-contact-editor]", HTMLElement);
      assert.equal(editor.id, "profile-editing-contract-contact-editor-addresses");
      assert.equal(editor.closest("[data-pe-contract-contact-item]"), null);
      assert.equal(
        select(element, "[data-pe-contract-contact-add]", HTMLButtonElement).getAttribute(
          "aria-expanded",
        ),
        "true",
      );
    });

    it("stands inside the row it belongs to for everything else", async () => {
      const element = await mount({
        sections: addresses(contact(21, "London"), contact(22, "Turin")),
        editor: openEditor({
          mode: "edit",
          record: 22,
          title: "Edit: Address",
          fields: [field()],
        }),
      });

      const editor = select(element, "[data-pe-contract-contact-editor]", HTMLElement);
      assert.equal(
        editor.closest("[data-pe-contract-contact-item]")?.getAttribute(
          "data-pe-contract-contact-item",
        ),
        "22",
      );
      assert.equal(
        select(
          element,
          '[data-pe-contract-contact-item="22"] [data-pe-contract-contact-edit]',
          HTMLButtonElement,
        ).getAttribute("aria-expanded"),
        "true",
      );
      assert.equal(
        select(
          element,
          '[data-pe-contract-contact-item="21"] [data-pe-contract-contact-edit]',
          HTMLButtonElement,
        ).getAttribute("aria-expanded"),
        "false",
      );
    });

    it("renders a control of the shape each field asks for", async () => {
      const element = await mount({
        sections: addresses(),
        editor: openEditor({
          fields: [
            field({ name: "city", type: "text" }),
            field({
              name: "country",
              label: "Country",
              type: "select",
              options: [
                { label: "Italy", value: "it" },
                { label: "United Kingdom", value: "uk" },
              ],
            }),
          ],
          values: { city: "Turin", country: "it" },
        }),
      });

      assert.equal(
        select(element, '[data-pe-contract-contact-field="city"]', HTMLInputElement).value,
        "Turin",
      );
      // The selectedness is written on the options rather than on the select,
      // because the option list is filled after the element itself is.
      assert.equal(
        select(element, '[data-pe-contract-contact-field="country"]', HTMLSelectElement)
          .value,
        "it",
      );
    });

    it("gives every control an id its label points at", async () => {
      const element = await mount({
        sections: addresses(),
        editor: openEditor({
          fields: [field({ name: "street", label: "Street" }), field()],
        }),
      });

      const control = select(
        element,
        '[data-pe-contract-contact-field="city"]',
        HTMLInputElement,
      );
      assert.equal(control.id, "profile-editing-contract-contact-field-1-city");
      assert.equal(
        selectAll(element, "label.form-label", HTMLLabelElement)[1]?.htmlFor,
        control.id,
      );
    });

    it("shows the message of the request and the message of each refused field", async () => {
      const element = await mount({
        sections: addresses(),
        editor: openEditor({
          fields: [field({ name: "street", label: "Street" }), field()],
          error: "Please check.",
          errors: { city: "Must not be empty." },
        }),
      });

      assert.equal(
        select(element, ".alert-danger", HTMLElement).textContent?.trim(),
        "Please check.",
      );
      const control = select(
        element,
        '[data-pe-contract-contact-field="city"]',
        HTMLInputElement,
      );
      assert.equal(control.getAttribute("aria-invalid"), "true");
      assert.equal(
        control.getAttribute("aria-describedby"),
        "profile-editing-contract-contact-field-error-1-city",
      );
      assert.equal(
        select(
          element,
          "#profile-editing-contract-contact-field-error-1-city",
          HTMLElement,
        ).textContent?.trim(),
        "Must not be empty.",
      );
    });

    it("locks every control and both buttons while a request is running", async () => {
      const element = await mount({
        sections: addresses(),
        editor: openEditor({ fields: [field()], pending: true }),
      });

      const editor = select(element, "[data-pe-contract-contact-editor]", HTMLElement);
      assert.equal(editor.getAttribute("aria-busy"), "true");
      assert.equal(
        select(editor, '[data-pe-contract-contact-field="city"]', HTMLInputElement)
          .disabled,
        true,
      );
      assert.deepEqual(
        selectAll(editor, "button", HTMLButtonElement).map(
          (button): boolean => button.disabled,
        ),
        [true, true],
      );
      assert.ok(editor.querySelector(".spinner-border") !== null);
    });

    it("lists every field as a term and a description in view mode", async () => {
      const element = await mount({
        sections: addresses(contact(21, "London")),
        editor: openEditor({
          mode: "view",
          record: 21,
          fields: [
            field({ name: "city", displayValue: "London" }),
            field({ name: "street", label: "Street", displayValue: "" }),
          ],
        }),
      });

      assert.deepEqual(
        selectAll(element, "dt", HTMLElement).map((term): string | null => term.textContent),
        ["City", "Street"],
      );
      assert.deepEqual(
        selectAll(element, "dd", HTMLElement).map(
          (description): string | undefined => description.textContent?.trim(),
        ),
        ["London", messages.empty],
      );
      assert.equal(element.querySelector("[data-pe-contract-contact-save]"), null);
    });

    /**
     * A read-only panel has no form, so it has nothing to cancel. The panel
     * header carried a "Cancel" of its own until ACE-520, which in this mode
     * was the only control there was and only closed the panel again - the
     * same thing the row's own view toggle does, under a label promising that
     * something would be discarded.
     */
    it("offers no control at all in view mode, because there is nothing to cancel", async () => {
      const element = await mount({
        sections: addresses(contact(21, "London")),
        editor: openEditor({
          mode: "view",
          record: 21,
          fields: [field({ displayValue: "London" })],
        }),
      });

      const editor = select(element, "[data-pe-contract-contact-editor]", HTMLElement);
      assert.deepEqual(selectAll(editor, "button", HTMLButtonElement), []);
      assert.equal(editor.querySelector("[data-pe-contract-contact-cancel]"), null);
    });

    /**
     * The counterpart: the visitor is offered the cancel once, in the action
     * bar below the form, and not a second time in the panel header where it
     * did exactly the same thing.
     */
    it("offers the cancel exactly once, in the action bar", async () => {
      const element = await mount({
        sections: addresses(contact(21, "London")),
        editor: openEditor({ mode: "edit", record: 21, fields: [field()] }),
      });

      const editor = select(element, "[data-pe-contract-contact-editor]", HTMLElement);
      const cancels = selectAll(
        editor,
        "[data-pe-contract-contact-cancel]",
        HTMLButtonElement,
      );
      const actions = select(editor, '[data-pe-when="showActions"]', HTMLElement);
      assert.equal(cancels.length, 1);
      assert.equal(cancels[0]?.parentElement, actions);
      assert.equal(
        select(editor, "[data-pe-contract-contact-save]", HTMLButtonElement)
          .parentElement,
        actions,
      );
      // Which container, and not merely "the same one as the submit": the two
      // stood together in the header before, so a shared parent is exactly what
      // the removed markup had. The bar is the last thing in the panel, below
      // the fields, and the heading is not in it.
      assert.equal(editor.lastElementChild, actions);
      assert.equal(
        actions.contains(
          select(editor, "[data-pe-contract-contact-heading]", HTMLElement),
        ),
        false,
      );
    });

    it("asks the question and offers the destructive action in delete mode", async () => {
      const element = await mount({
        sections: addresses(contact(21, "London")),
        editor: openEditor({
          mode: "delete",
          record: 21,
          title: "Delete: Address",
          deleteConfirmation: messages.contractContactDeleteConfirm,
          fields: [field()],
        }),
      });

      const editor = select(element, "[data-pe-contract-contact-editor]", HTMLElement);
      assert.equal(
        select(editor, "p", HTMLElement).textContent?.trim(),
        messages.contractContactDeleteConfirm,
      );
      // The action bar carries both, and it is the only place a control lives.
      assert.equal(selectAll(editor, "button", HTMLButtonElement).length, 2);
      const save = select(editor, "[data-pe-contract-contact-save]", HTMLButtonElement);
      assert.ok(save.classList.contains("btn-danger"));
      assert.equal(save.textContent?.trim(), labels.delete);
      assert.equal(editor.querySelector("[data-pe-contract-contact-fields]"), null);
    });

    /**
     * A save adds, replaces or removes a contact by handing over a *new*
     * sections array, so every one of them re-renders the whole list around an
     * editor that may still be open. `repeat()` keys the rows by uid and the
     * editor is a part of the template rather than a rebuild, so the control
     * the visitor stands in survives it.
     */
    it("leaves an open editor alone when the list around it is replaced", async () => {
      const element = await mount({
        sections: addresses(contact(21, "London"), contact(22, "Turin")),
        editor: openEditor({ fields: [field()], values: { city: "Nottingham" } }),
      });
      const control = select(
        element,
        '[data-pe-contract-contact-field="city"]',
        HTMLInputElement,
      );
      control.focus();

      element.sections = addresses(
        contact(21, "London"),
        contact(22, "Turin"),
        contact(23, "Nottingham"),
      );
      await element.updateComplete;

      assert.equal(
        selectAll(element, "[data-pe-contract-contact-item]", HTMLElement).length,
        3,
      );
      assert.equal(
        select(element, '[data-pe-contract-contact-field="city"]', HTMLInputElement),
        control,
      );
      assert.equal(control.value, "Nottingham");
      assert.equal(document.activeElement, control);
    });
  });
});

/**
 * The element and the controller together.
 *
 * The controls the element renders carry `data-pe-contract-contact-*` and are
 * delegated on the plugin root, because the document editor creates this
 * element inside its own panel and the controller never holds it. That the two
 * halves meet is what nothing else in the suite can see: the property tests
 * above render buttons nobody presses, and `contract-contacts.test.ts` presses
 * methods no button reaches.
 */
describe("editing the contacts of a contract in the page", () => {
  let fetch: FetchDouble;
  let root: HTMLElement;
  let controller: DocumentEditingController;

  const contactItem = (
    uid: number,
    city: string,
    sorting: number,
  ): Record<string, unknown> => ({
    uid,
    sorting,
    hidden: false,
    summary: [{ label: "City", value: city }],
  });

  /** Opens the contract, which is what renders the contacts at all. */
  const openContract = async (): Promise<void> => {
    fetch.respond({
      success: true,
      record: 5,
      fields: [{ name: "position", label: "Position", type: "text", value: "Analyst" }],
      contactSections: [
        {
          identifier: "addresses",
          label: "Addresses",
          singularLabel: "Address",
          items: [contactItem(21, "London", 10), contactItem(22, "Turin", 20)],
        },
      ],
    });
    select(root, '[data-item-uid="5"] [data-pe-document-view]', HTMLButtonElement).click();
    await settle(20);
  };

  /** Presses one of the controls the contacts element renders. */
  const press = async (selector: string): Promise<HTMLButtonElement> => {
    const button = select(root, selector, HTMLButtonElement);
    button.click();
    await settle(20);

    return button;
  };

  const rows = (): (string | null)[] =>
    selectAll(root, "[data-pe-contract-contact-item]", HTMLElement).map(
      (item): string | null => item.getAttribute("data-pe-contract-contact-item"),
    );

  const type = (name: string, value: string): void => {
    const control = select(
      root,
      `[data-pe-contract-contact-field="${name}"]`,
      HTMLInputElement,
    );
    control.value = value;
    control.dispatchEvent(new Event("input", { bubbles: true }));
  };

  beforeEach(() => {
    fetch = installFetch();
    ({ root } = editingHost({
        content: documentSection({
          identifier: "contracts",
          kind: "contract",
          rows: documentRow({ uid: 5, sorting: 10, position: 0, title: "Contract 5" }),
        }),
      }));
    controller = createDocumentEditing(root);
    initializeDocumentSections(root);
  });

  it("renders the contacts the contract was answered with", async () => {
    await openContract();

    assert.deepEqual(rows(), ["21", "22"]);
    assert.ok(root.querySelector("academic-persons-edit-contract-contacts") !== null);
  });

  it("creates a contact from the add control of its section", async () => {
    await openContract();
    fetch.respond({
      success: true,
      record: null,
      title: "Address",
      fields: [{ name: "city", label: "City", type: "text", value: "" }],
    });

    const add = await press("[data-pe-contract-contact-add]");

    const form = fetch.calls[1];
    assert.equal(form?.url, endpoints.contractContactForm);
    assert.equal(form?.method, "POST");
    assert.equal(form?.headers["X-Requested-With"], "XMLHttpRequest");
    assert.deepEqual(form?.body, {
      profile: 1,
      data: { contract: 5, section: "addresses", record: 0, mode: "add" },
    });
    assert.equal(add.getAttribute("aria-expanded"), "true");

    type("city", "Nottingham");
    fetch.respond({ success: true, item: contactItem(23, "Nottingham", 30) });
    await press("[data-pe-contract-contact-save]");

    const save = fetch.calls[2];
    assert.equal(save?.url, endpoints.createContractContact);
    assert.equal(save?.headers["X-Requested-With"], "XMLHttpRequest");
    assert.deepEqual(save?.body, {
      profile: 1,
      data: { contract: 5, section: "addresses", fields: { city: "Nottingham" } },
    });
    assert.deepEqual(rows(), ["21", "22", "23"]);
    assert.equal(root.querySelector("[data-pe-contract-contact-editor]"), null);
  });

  it("changes a contact from the edit control of its row", async () => {
    await openContract();
    fetch.respond({
      success: true,
      record: 22,
      title: "Address",
      fields: [{ name: "city", label: "City", type: "text", value: "Turin" }],
    });

    await press('[data-pe-contract-contact-item="22"] [data-pe-contract-contact-edit]');

    assert.deepEqual(fetch.calls[1]?.body, {
      profile: 1,
      data: { contract: 5, section: "addresses", record: 22, mode: "edit" },
    });

    type("city", "Torino");
    fetch.respond({ success: true, item: contactItem(22, "Torino", 20) });
    await press("[data-pe-contract-contact-save]");

    assert.equal(fetch.calls[2]?.url, endpoints.updateContractContact);
    assert.deepEqual(fetch.calls[2]?.body, {
      profile: 1,
      data: {
        contract: 5,
        section: "addresses",
        record: 22,
        fields: { city: "Torino" },
      },
    });
    assert.deepEqual(rows(), ["21", "22"]);
    assert.equal(
      select(root, '[data-pe-contract-contact-item="22"] span', HTMLElement)
        .textContent?.trim(),
      "Torino",
    );
  });

  it("deletes a contact from the delete control of its row", async () => {
    await openContract();
    fetch.respond({ success: true, record: 21, title: "Address", fields: [] });

    await press('[data-pe-contract-contact-item="21"] [data-pe-contract-contact-delete]');
    fetch.respond({ success: true });
    await press("[data-pe-contract-contact-save]");

    assert.equal(fetch.calls[2]?.url, endpoints.deleteContractContact);
    assert.deepEqual(fetch.calls[2]?.body, {
      profile: 1,
      data: { contract: 5, section: "addresses", record: 21 },
    });
    assert.deepEqual(rows(), ["22"]);
  });

  it("shows a contact from the view control of its row", async () => {
    await openContract();
    fetch.respond({
      success: true,
      record: 21,
      title: "Address",
      fields: [
        { name: "city", label: "City", type: "text", value: "London", displayValue: "London" },
      ],
    });

    await press('[data-pe-contract-contact-item="21"] [data-pe-contract-contact-view]');

    const editor = select(root, "[data-pe-contract-contact-editor]", HTMLElement);
    assert.equal(select(editor, "dt", HTMLElement).textContent, "City");
    assert.equal(select(editor, "dd", HTMLElement).textContent?.trim(), "London");
    assert.equal(editor.querySelector("[data-pe-contract-contact-fields]"), null);
  });

  /**
   * The one close path a read view has since ACE-520: the panel renders no
   * control of its own, so the view control of the row carries both
   * directions.
   *
   * It is worth a case of its own because this toggle is not the document
   * one. The document list compares the *button* that is pressed with the
   * button that opened the editor; this one compares the mode, the section and
   * the record - by value, and against a record that
   * `openContractContact()` takes from the response rather than from the row.
   * A response that answers a different record therefore leaves a panel that
   * cannot be closed at all, and the case that used to cover the toggle drives
   * "add", where that record is `null` and the panel still had a cancel of its
   * own in the action bar.
   */
  it("closes a read view again when the view control is pressed twice", async () => {
    await openContract();
    fetch.respond({
      success: true,
      record: 21,
      title: "Address",
      fields: [
        { name: "city", label: "City", type: "text", value: "London", displayValue: "London" },
      ],
    });
    const view =
      '[data-pe-contract-contact-item="21"] [data-pe-contract-contact-view]';

    await press(view);
    assert.ok(root.querySelector("[data-pe-contract-contact-editor]") !== null);
    assert.equal(
      select(root, view, HTMLButtonElement).getAttribute("aria-expanded"),
      "true",
    );
    // The row a re-render rebuilt says the same thing the button says: the eye
    // that closes the panel, and the label that names the close.
    const openView = select(root, view, HTMLButtonElement);
    assert.equal(
      select(openView, '[data-pe-view-icon="collapsed"]', HTMLElement).hidden,
      true,
    );
    assert.equal(
      select(openView, '[data-pe-view-icon="expanded"]', HTMLElement).hidden,
      false,
    );
    assert.equal(openView.getAttribute("aria-label"), labels.viewClose);
    assert.equal(openView.getAttribute("title"), labels.viewClose);

    await press(view);

    assert.equal(root.querySelector("[data-pe-contract-contact-editor]"), null);
    assert.equal(controller.contractContact.open, false);
    // The second press is a close, not a second open: nothing is asked of
    // "contractContactForm" again, and the caret is back on the control that
    // is now the only thing the visitor can reach the panel from.
    assert.equal(fetch.calls.length, 2);
    const toggle = select(root, view, HTMLButtonElement);
    assert.equal(toggle.getAttribute("aria-expanded"), "false");
    assert.equal(
      select(toggle, '[data-pe-view-icon="collapsed"]', HTMLElement).hidden,
      false,
    );
    assert.equal(
      select(toggle, '[data-pe-view-icon="expanded"]', HTMLElement).hidden,
      true,
    );
    assert.equal(toggle.getAttribute("aria-label"), labels.view);
    assert.equal(document.activeElement, toggle);
  });

  it("closes the editor without a request when it is cancelled", async () => {
    await openContract();
    fetch.respond({
      success: true,
      record: null,
      title: "Address",
      fields: [{ name: "city", label: "City", type: "text", value: "" }],
    });
    const add = await press("[data-pe-contract-contact-add]");

    await press("[data-pe-contract-contact-cancel]");

    assert.equal(fetch.calls.length, 2);
    assert.equal(root.querySelector("[data-pe-contract-contact-editor]"), null);
    assert.equal(add.getAttribute("aria-expanded"), "false");
    assert.equal(document.activeElement, add);
  });

  it("sorts a contact from the control of its row", async () => {
    await openContract();
    fetch.respond({ success: true, order: [22, 21] });

    await press(
      '[data-pe-contract-contact-item="22"] [data-pe-contract-contact-sort="up"]',
    );

    assert.equal(fetch.calls[1]?.url, endpoints.sortContractContact);
    assert.deepEqual(fetch.calls[1]?.body, {
      profile: 1,
      data: { contract: 5, section: "addresses", record: 22, direction: "up" },
    });
    assert.deepEqual(rows(), ["22", "21"]);
  });

  it("ignores a sort control that is disabled at its end of the list", async () => {
    await openContract();

    select(
      root,
      '[data-pe-contract-contact-item="21"] [data-pe-contract-contact-sort="up"]',
      HTMLButtonElement,
    ).click();
    await settle(20);

    assert.equal(fetch.calls.length, 1);
  });

  it("keeps the list and the editor when the save is refused", async () => {
    await openContract();
    fetch.respond({
      success: true,
      record: null,
      title: "Address",
      fields: [{ name: "city", label: "City", type: "text", value: "" }],
    });
    await press("[data-pe-contract-contact-add]");
    fetch.respondWithError(
      { success: false, message: "Please check.", errors: { city: ["Must not be empty."] } },
      422,
    );

    await press("[data-pe-contract-contact-save]");

    assert.deepEqual(rows(), ["21", "22"]);
    const editor = select(root, "[data-pe-contract-contact-editor]", HTMLElement);
    assert.equal(select(editor, ".alert-danger", HTMLElement).textContent?.trim(), "Please check.");
    assert.equal(
      select(editor, ".invalid-feedback", HTMLElement).textContent?.trim(),
      "Must not be empty.",
    );
    assert.equal(controller.contractContact.open, true);
    // Never left busy: the controls are usable again for a second attempt.
    assert.equal(
      select(editor, '[data-pe-contract-contact-field="city"]', HTMLInputElement).disabled,
      false,
    );
  });
});
