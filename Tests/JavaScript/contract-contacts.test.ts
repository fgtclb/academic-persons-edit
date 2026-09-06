import assert from "node:assert/strict";
import { beforeEach, describe, it } from "node:test";
import { resetBody, settle } from "../../../../../Build/tests/dom.mjs";
import { installFetch, type FetchDouble } from "../../../../../Build/tests/fetch.mjs";
import {
  createDocumentEditing,
  initializeDocumentSections,
  type DocumentEditingController,
} from "@fgtclb/academic-persons-edit/frontend/profile/documents.js";
import {
  documentRow,
  documentSection,
  endpoints,
  messages,
  profileEditingRoot,
  select,
} from "./Fixtures/profile-editing.ts";

/**
 * The addresses, phone numbers and mail addresses of a contract are edited
 * inside the open contract editor, one level below the document editor, and
 * against endpoints of their own. Everything here therefore depends on a
 * contract being open: the requests carry its record, and without one there is
 * nothing to attach a contact to.
 *
 * The list is rendered by `<academic-persons-edit-contract-contacts>` from
 * `document.contactSections`, which is the property it is handed - so what a
 * save does to that list is still asserted on the state, and what the element
 * makes of it is asserted in `contract-contacts-element.test.ts`.
 */
describe("the contacts of a contract", () => {
  let fetch: FetchDouble;
  let root: HTMLElement;
  let controller: DocumentEditingController;

  const contactItem = (uid: number, city: string, sorting: number): Record<string, unknown> => ({
    uid,
    sorting,
    hidden: false,
    summary: [{ label: "City", value: city }],
    display: { city },
    values: { city },
  });

  const contactSections = (): Record<string, unknown>[] => [
    {
      identifier: "addresses",
      label: "Addresses",
      singularLabel: "Address",
      items: [contactItem(21, "London", 10), contactItem(22, "Turin", 20)],
    },
  ];

  const render = (): void => {
    const body = resetBody(
      profileEditingRoot({
        content: documentSection({
          identifier: "contracts",
          kind: "contract",
          rows: documentRow({ uid: 5, sorting: 10, position: 0, title: "Contract 5" }),
        }),
      }),
    );
    root = select(body, "[data-academic-persons-profile-editing]", HTMLElement);
    controller = createDocumentEditing(root);
    initializeDocumentSections(root);
  };

  /** Opens the contract the contacts belong to, with the contact list of its response. */
  const openContract = async (): Promise<void> => {
    fetch.respond({
      success: true,
      fields: [{ name: "position", label: "Position", type: "text", value: "Analyst" }],
      record: 5,
      contactSections: contactSections(),
    });
    select(root, '[data-item-uid="5"] [data-pe-document-view]', HTMLButtonElement).click();
    await settle(20);
  };

  /**
   * The add control of the section, which the contacts element renders inside
   * the open contract.
   *
   * It used to be a hand placed transcription of what a rendering framework
   * made of
   * `Partials/Profile/Documents/ContractContacts.html`; that partial is gone
   * and the element renders the real control, so the arrangement is a query.
   * Nothing this file asserts changed with it.
   */
  const addButton = (): HTMLButtonElement =>
    select(root, "[data-pe-contract-contact-add]", HTMLButtonElement);

  /** The event shape the template hands the handler: the button as its target. */
  const eventFrom = (button: HTMLElement): Event => {
    const event = new CustomEvent("click", { bubbles: true });
    Object.defineProperty(event, "target", { value: button });

    return event;
  };

  const items = (): Record<string, unknown>[] =>
    (controller.document.contactSections[0]?.items ?? []) as unknown as Record<string, unknown>[];

  beforeEach(() => {
    fetch = installFetch();
    render();
  });

  it("does nothing at all while no contract is open", async () => {
    await controller.openContractContact("add", "addresses", new CustomEvent("click"));

    assert.equal(fetch.calls.length, 0);
    assert.equal(controller.contractContact.open, false);
  });

  it("takes its sections from the contract's own response", async () => {
    await openContract();

    assert.deepEqual(
      controller.document.contactSections.map((section): string => section.identifier),
      ["addresses"],
    );
    assert.deepEqual(items().map((item): unknown => item.uid), [21, 22]);
  });

  it("asks the contact form endpoint for the contract, section and mode", async () => {
    await openContract();
    const add = addButton();
    fetch.respond({ success: true, fields: [], record: null, title: "Address" });

    await controller.openContractContact("add", "addresses", eventFrom(add));
    await settle(20);

    const call = fetch.calls[1];
    assert.equal(call?.url, endpoints.contractContactForm);
    assert.equal(call?.headers["X-Requested-With"], "XMLHttpRequest");
    assert.deepEqual(call?.body, {
      profile: 1,
      data: { contract: 5, section: "addresses", record: 0, mode: "add" },
    });
  });

  it("brings the editor into view and puts the caret in its first control", async () => {
    await openContract();
    const add = addButton();
    fetch.respond({
      success: true,
      fields: [{ name: "city", label: "City", type: "text", value: "" }],
      record: null,
      title: "Address",
    });

    await controller.openContractContact("add", "addresses", eventFrom(add));
    await settle(20);

    const editor = select(root, "[data-pe-contract-contact-editor]", HTMLElement);
    assert.equal(editor.getAttribute("data-test-scrolled-into-view"), "nearest");
    assert.equal(
      document.activeElement,
      select(root, "#profile-editing-contract-contact-field-0-city", HTMLInputElement),
    );
    assert.equal(controller.contractContact.open, true);
    assert.equal(controller.contractContact.title, "Add: Address");
  });

  it("closes again when the same control is pressed a second time", async () => {
    await openContract();
    const add = addButton();
    fetch.respond({ success: true, fields: [], record: null, title: "Address" });
    await controller.openContractContact("add", "addresses", eventFrom(add));
    await settle(20);

    await controller.openContractContact("add", "addresses", eventFrom(add));
    await settle(20);

    assert.equal(controller.contractContact.open, false);
    assert.equal(fetch.calls.length, 2);
  });

  it("returns the caret to the control that opened it", async () => {
    await openContract();
    const add = addButton();
    fetch.respond({ success: true, fields: [], record: null, title: "Address" });
    await controller.openContractContact("add", "addresses", eventFrom(add));
    await settle(20);

    controller.closeContractContact();
    await settle(20);

    assert.equal(document.activeElement, add);
  });

  it("creates a contact and adds it to the list of its section", async () => {
    await openContract();
    const add = addButton();
    fetch.respond({
      success: true,
      fields: [{ name: "city", label: "City", type: "text", value: "Nottingham" }],
      record: null,
      title: "Address",
    });
    await controller.openContractContact("add", "addresses", eventFrom(add));
    await settle(20);
    fetch.respond({ success: true, item: contactItem(23, "Nottingham", 30) });

    await controller.submitContractContact();
    await settle(20);

    const call = fetch.calls[2];
    assert.equal(call?.url, endpoints.createContractContact);
    assert.deepEqual(call?.body, {
      profile: 1,
      data: {
        contract: 5,
        section: "addresses",
        fields: { city: "Nottingham" },
      },
    });
    assert.deepEqual(items().map((item): unknown => item.uid), [21, 22, 23]);
    assert.equal(controller.contractContact.open, false);
    assert.equal(
      select(root, '[data-pe-status-toast="status"] .status-message', HTMLElement).textContent,
      messages.documentSaved,
    );
  });

  it("updates a contact and replaces it in the list", async () => {
    await openContract();
    const add = addButton();
    fetch.respond({
      success: true,
      fields: [{ name: "city", label: "City", type: "text", value: "Torino" }],
      record: 22,
      title: "Address",
    });
    await controller.openContractContact("edit", "addresses", eventFrom(add), 22);
    await settle(20);
    fetch.respond({ success: true, item: contactItem(22, "Torino", 20) });

    await controller.submitContractContact();
    await settle(20);

    const call = fetch.calls[2];
    assert.equal(call?.url, endpoints.updateContractContact);
    assert.deepEqual(call?.body, {
      profile: 1,
      data: {
        contract: 5,
        section: "addresses",
        record: 22,
        fields: { city: "Torino" },
      },
    });
    assert.deepEqual(items().map((item): unknown => item.uid), [21, 22]);
    assert.deepEqual(
      items().map((item): unknown => (item.display as Record<string, unknown>).city),
      ["London", "Torino"],
    );
    // An edit stays open with what it stored, exactly as a document's does.
    assert.equal(controller.contractContact.open, true);
    assert.deepEqual(controller.contractContact.initialValues, { city: "Torino" });
  });

  it("deletes a contact, sends no values and drops it from the list", async () => {
    await openContract();
    const add = addButton();
    fetch.respond({ success: true, fields: [], record: 21, title: "Address" });
    await controller.openContractContact("delete", "addresses", eventFrom(add), 21);
    await settle(20);
    fetch.respond({ success: true });

    await controller.submitContractContact();
    await settle(20);

    assert.deepEqual(fetch.calls[2]?.body, {
      profile: 1,
      data: { contract: 5, section: "addresses", record: 21 },
    });
    assert.deepEqual(items().map((item): unknown => item.uid), [22]);
    assert.equal(
      select(root, '[data-pe-status-toast="status"] .status-message', HTMLElement).textContent,
      messages.documentDeleted,
    );
  });

  it("keeps the editor open and the messages when a contact is refused", async () => {
    await openContract();
    const add = addButton();
    fetch.respond({
      success: true,
      fields: [{ name: "city", label: "City", type: "text", value: "" }],
      record: null,
      title: "Address",
    });
    await controller.openContractContact("add", "addresses", eventFrom(add));
    await settle(20);
    fetch.respondWithError(
      { success: false, message: "Please check.", errors: { city: ["Must not be empty."] } },
      422,
    );

    await controller.submitContractContact();
    await settle(20);

    assert.equal(controller.contractContact.open, true);
    assert.equal(controller.contractContact.error, "Please check.");
    assert.deepEqual(controller.contractContact.errors, { city: "Must not be empty." });
    assert.deepEqual(items().map((item): unknown => item.uid), [21, 22]);
  });

  it("sorts a contact and renumbers the list from the order it is answered with", async () => {
    await openContract();
    fetch.respond({ success: true, order: [22, 21] });

    await controller.sortContractContact("up", "addresses", 22);
    await settle(20);

    const call = fetch.calls[1];
    assert.equal(call?.url, endpoints.sortContractContact);
    assert.deepEqual(call?.body, {
      profile: 1,
      data: { contract: 5, section: "addresses", record: 22, direction: "up" },
    });
    assert.deepEqual(items().map((item): unknown => item.uid), [22, 21]);
    assert.deepEqual(items().map((item): unknown => item.sorting), [10, 20]);
    assert.equal(
      select(root, '[data-pe-status-toast="status"] .status-message', HTMLElement).textContent,
      messages.documentSorted,
    );
  });

  /**
   * The toggle writes the record's `hidden` column and nothing else. The row
   * is replaced with what the server answers, so the list shows the stored
   * state rather than a flag flipped in the browser.
   */
  it("hides a contact and replaces it with what the server stored", async () => {
    await openContract();
    fetch.respond({
      success: true,
      item: { ...contactItem(21, "London", 10), hidden: true },
    });

    await controller.toggleContractContactVisibility("addresses", 21);
    await settle(20);

    const call = fetch.calls[1];
    assert.equal(call?.url, endpoints.toggleContractContactVisibility);
    assert.deepEqual(call?.body, {
      profile: 1,
      data: { contract: 5, section: "addresses", record: 21, hidden: true },
    });
    assert.deepEqual(
      items().map((item): unknown => item.hidden),
      [true, false],
    );
    assert.equal(controller.contractContact.pending, false);
    assert.equal(
      select(root, '[data-pe-status-toast="status"] .status-message', HTMLElement).textContent,
      messages.contractContactHidden,
    );
  });

  it("shows a hidden contact again and says so", async () => {
    await openContract();
    fetch.respond({
      success: true,
      item: { ...contactItem(21, "London", 10), hidden: true },
    });
    await controller.toggleContractContactVisibility("addresses", 21);
    await settle(20);
    fetch.respond({ success: true, item: contactItem(21, "London", 10) });

    await controller.toggleContractContactVisibility("addresses", 21);
    await settle(20);

    assert.deepEqual(fetch.calls[2]?.body, {
      profile: 1,
      data: { contract: 5, section: "addresses", record: 21, hidden: false },
    });
    assert.equal(items()[0]?.hidden, false);
    assert.equal(
      select(root, '[data-pe-status-toast="status"] .status-message', HTMLElement).textContent,
      messages.contractContactShown,
    );
  });

  it("leaves the list alone when the visibility change is refused", async () => {
    await openContract();
    fetch.respondWithError({ success: false, message: "Not yours." }, 403);

    await controller.toggleContractContactVisibility("addresses", 21);
    await settle(20);

    assert.equal(items()[0]?.hidden, false);
    assert.equal(
      select(root, '[data-pe-status-toast="alert"] .status-message', HTMLElement).textContent,
      "Not yours.",
    );
  });

  it("leaves the list alone when the sorting is refused", async () => {
    await openContract();
    fetch.respondWithError({ success: false, message: "Refused." }, 400);

    await controller.sortContractContact("up", "addresses", 22);
    await settle(20);

    assert.deepEqual(items().map((item): unknown => item.uid), [21, 22]);
    assert.equal(
      select(root, '[data-pe-status-toast="alert"] .status-message', HTMLElement).textContent,
      "Refused.",
    );
  });

  it("ignores a direction that is neither up nor down", async () => {
    await openContract();

    await controller.sortContractContact("sideways", "addresses", 22);

    assert.equal(fetch.calls.length, 1);
  });
});
