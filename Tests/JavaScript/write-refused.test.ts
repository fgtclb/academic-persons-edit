import assert from "node:assert/strict";
import { beforeEach, describe, it } from "node:test";
import { resetBody, settle } from "../../../../../Build/tests/dom.mjs";
import { installFetch, type FetchDouble } from "../../../../../Build/tests/fetch.mjs";
import {
  createDocumentEditing,
  initializeDocumentSections,
  type DocumentEditingController,
} from "@fgtclb/academic-persons-edit/frontend/profile/documents.js";
import { initializeFieldEditing } from "@fgtclb/academic-persons-edit/frontend/profile/fields.js";
import {
  documentRow,
  documentSection,
  fieldsForm,
  profileEditingRoot,
  profileHeader,
  select,
  textField,
} from "./Fixtures/profile-editing.ts";

/**
 * A write a listener of `BeforeProfileEditingWriteEvent` refused.
 *
 * The endpoint answers 422 with the error `write_refused`, the listener's reason
 * as `message`, and no `errors`, and the reason is written for the person who
 * pressed the button. It has to reach them, as text: a reason is a project's
 * string, and markup in it must not become markup of the page. The three editors
 * a person types into are covered here - the fields of the profile, a document
 * and a contact of a contract - because each has a refusal of its own that keeps
 * the input and shows a message.
 */
const reason = "Ask the <b>office</b> to change this.";

const refusal = { success: false, error: "write_refused", message: reason };

describe("a refused write of a profile field", () => {
  let fetch: FetchDouble;
  let root: HTMLElement;

  beforeEach(() => {
    fetch = installFetch();
    const body = resetBody(
      profileEditingRoot({
        content: profileHeader() + fieldsForm(textField({ identifier: "firstName", value: "Ada" })),
      }),
    );
    root = select(body, "[data-academic-persons-profile-editing]", HTMLElement);
    initializeFieldEditing(root);
  });

  it("shows the reason as text and keeps the editor open with the input", async () => {
    fetch.respondWithError(refusal, 422);

    select(
      root,
      '[data-academic-persons-profile-editing-activate-btn][data-pe-for="profile-editing-1-firstName"]',
      HTMLButtonElement,
    ).click();
    const field = select(root, "#profile-editing-1-firstName", HTMLInputElement);
    field.value = "Augusta";
    select(root, '[data-pe-save][data-pe-for="profile-editing-1-firstName"]', HTMLButtonElement).click();
    await settle(20);

    const message = select(root, '[data-pe-status-toast="alert"] [data-pe-status-message]', HTMLElement);
    assert.equal(message.textContent, reason);
    assert.equal(message.querySelector("b"), null);
    assert.equal(field.value, "Augusta");
    assert.equal(
      select(root, "#profile-editing-1-firstName-editor", HTMLElement).hidden,
      false,
    );
  });
});

describe("a refused write of a document", () => {
  let fetch: FetchDouble;
  let controller: DocumentEditingController;

  beforeEach(() => {
    fetch = installFetch();
    const body = resetBody(
      profileEditingRoot({
        content: documentSection({
          identifier: "publications",
          rows: documentRow({ uid: 7, sorting: 10, position: 0, title: "Paper 7" }),
        }),
      }),
    );
    const root = select(body, "[data-academic-persons-profile-editing]", HTMLElement);
    controller = createDocumentEditing(root);
    initializeDocumentSections(root);
  });

  /**
   * The editor state is what `<academic-persons-edit-document-editor>` renders
   * its alert from, and it renders it through `textContent`, see
   * `document-editor-element.test.ts`.
   */
  it("keeps the document editor open with the reason", async () => {
    fetch.respond({
      success: true,
      fields: [{ name: "title", label: "Title", type: "text", value: "New paper", required: false }],
      record: null,
    });
    select(document.body, "[data-pe-document-add]", HTMLButtonElement).click();
    await settle(20);
    fetch.respondWithError(refusal, 422);

    await controller.submitDocument();
    await settle(20);

    assert.equal(controller.document.open, true);
    assert.equal(controller.document.error, reason);
    assert.deepEqual(controller.document.errors, {});
  });
});

describe("a refused write of a contact", () => {
  let fetch: FetchDouble;
  let root: HTMLElement;
  let controller: DocumentEditingController;

  beforeEach(() => {
    fetch = installFetch();
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
  });

  const openContract = async (): Promise<void> => {
    fetch.respond({
      success: true,
      fields: [{ name: "position", label: "Position", type: "text", value: "Analyst" }],
      record: 5,
      contactSections: [
        { identifier: "addresses", label: "Addresses", singularLabel: "Address", items: [] },
      ],
    });
    select(root, '[data-item-uid="5"] [data-pe-document-view]', HTMLButtonElement).click();
    await settle(20);
  };

  it("keeps the contact editor open with the reason", async () => {
    await openContract();
    fetch.respond({
      success: true,
      fields: [{ name: "city", label: "City", type: "text", value: "" }],
      record: null,
      title: "Address",
    });
    const add = select(root, "[data-pe-contract-contact-add]", HTMLButtonElement);
    const event = new CustomEvent("click", { bubbles: true });
    Object.defineProperty(event, "target", { value: add });
    await controller.openContractContact("add", "addresses", event);
    await settle(20);
    fetch.respondWithError(refusal, 422);

    await controller.submitContractContact();
    await settle(20);

    assert.equal(controller.contractContact.open, true);
    assert.equal(controller.contractContact.error, reason);
    assert.deepEqual(controller.contractContact.errors, {});
  });
});
