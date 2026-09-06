import assert from "node:assert/strict";
import { beforeEach, describe, it } from "node:test";
import { resetBody, settle } from "../../../../../Build/tests/dom.mjs";
import { installFetch, type FetchDouble } from "../../../../../Build/tests/fetch.mjs";
import {
  createDocumentEditing,
  initializeDocumentSections,
  type DocumentEditingController,
} from "@fgtclb/academic-persons-edit/frontend/profile/documents.js";
import { askUnsavedChanges } from "@fgtclb/academic-persons-edit/frontend/profile/editors.js";
import { initializeFieldEditing } from "@fgtclb/academic-persons-edit/frontend/profile/fields.js";
import {
  documentRow,
  documentSection,
  endpoints,
  fieldsForm,
  messages,
  profileEditingRoot,
  profileHeader,
  select,
  textField,
} from "./Fixtures/profile-editing.ts";

/**
 * One editor at a time, across the modules.
 *
 * The profile fields and the document sections are driven by two modules that
 * share no state, and `editors.ts` is where they meet: each registers what it
 * can do with the editor it has open, and each asks before it opens one. This
 * file drives both modules on one root, which is what neither of their own
 * suites can do, and asserts the transition from the outside: which editor is
 * open afterwards, what was sent, and what the dialog was told.
 */
describe("one editor at a time across the modules", () => {
  let fetch: FetchDouble;
  let root: HTMLElement;
  let controller: DocumentEditingController;

  const fieldResponse = (value = "Paper 7"): Record<string, unknown> => ({
    name: "title",
    label: "Title",
    type: "text",
    value,
    disabled: false,
    readOnly: false,
    required: true,
    richText: false,
  });

  const render = (): void => {
    const body = resetBody(
      profileEditingRoot({
        content:
          profileHeader() +
          fieldsForm(textField({ identifier: "firstName", value: "Ada" })) +
          documentSection({
            identifier: "publications",
            rows: [7, 8]
              .map((uid, index): string =>
                documentRow({ uid, sorting: (index + 1) * 10, position: index, title: `Paper ${uid}` }),
              )
              .join(""),
          }),
      }),
    );
    root = select(body, "[data-academic-persons-profile-editing]", HTMLElement);
    initializeFieldEditing(root);
    controller = createDocumentEditing(root);
    initializeDocumentSections(root);
  };

  const field = (): HTMLInputElement =>
    select(root, "#profile-editing-1-firstName", HTMLInputElement);
  const fieldEditor = (): HTMLElement =>
    select(root, "#profile-editing-1-firstName-editor", HTMLElement);
  const pencil = (): HTMLButtonElement =>
    select(
      root,
      '[data-academic-persons-profile-editing-activate-btn][data-pe-for="profile-editing-1-firstName"]',
      HTMLButtonElement,
    );
  const rowButton = (uid: number, action: string): HTMLButtonElement =>
    select(root, `[data-item-uid="${uid}"] [data-pe-document-${action}]`, HTMLButtonElement);
  const dialog = (): HTMLDialogElement | null =>
    root.querySelector<HTMLDialogElement>("[data-pe-unsaved-changes]");
  const choose = async (choice: "save" | "discard" | "cancel"): Promise<void> => {
    select(root, `[data-pe-unsaved-choice="${choice}"]`, HTMLButtonElement).click();
    await settle(20);
  };
  const openRow = async (uid: number, action: string, value?: string): Promise<void> => {
    fetch.respond({ success: true, fields: [fieldResponse(value)], record: uid });
    rowButton(uid, action).click();
    await settle(20);
  };

  beforeEach(() => {
    fetch = installFetch();
    render();
  });

  it("closes an untouched document editor when a field pencil is pressed", async () => {
    await openRow(7, "view");
    assert.equal(controller.document.open, true);

    pencil().click();

    assert.equal(dialog(), null);
    assert.equal(controller.document.open, false);
    assert.equal(rowButton(7, "view").getAttribute("aria-expanded"), "false");
    assert.equal(fieldEditor().classList.contains("d-none"), false);
  });

  /**
   * The document editor's values are what its controls report on input; the
   * suite writes them the way the input event does, and the editor is then a
   * record with a change in it - which a pencil elsewhere must not throw away.
   */
  it("asks before a field pencil replaces a document editor with changes", async () => {
    await openRow(7, "edit");
    controller.document.values = { title: "Paper 7, revised" };

    pencil().click();

    assert.ok(dialog() !== null);
    assert.equal(controller.document.open, true);
    assert.ok(fieldEditor().classList.contains("d-none"));
    assert.equal(fetch.calls.length, 1);
  });

  it("discards the document editor and opens the field when told to", async () => {
    await openRow(7, "edit");
    controller.document.values = { title: "Paper 7, revised" };
    pencil().click();

    await choose("discard");

    assert.equal(dialog(), null);
    assert.equal(controller.document.open, false);
    assert.equal(fieldEditor().classList.contains("d-none"), false);
    assert.equal(fetch.calls.length, 1);
  });

  it("saves the document editor first and opens the field when told to", async () => {
    await openRow(7, "edit");
    controller.document.values = { title: "Paper 7, revised" };
    pencil().click();
    fetch.respond({
      success: true,
      item: { uid: 7, sorting: 10, display: { title: "Paper 7, revised" }, values: {} },
    });

    await choose("save");

    assert.equal(fetch.calls[1]?.url, endpoints.updateDocument);
    assert.deepEqual(fetch.calls[1]?.body, {
      profile: 1,
      data: { section: "publications", record: 7, fields: { title: "Paper 7, revised" } },
    });
    assert.equal(controller.document.open, false);
    assert.equal(fieldEditor().classList.contains("d-none"), false);
  });

  it("keeps the document editor and opens nothing when the dialog is cancelled", async () => {
    await openRow(7, "edit");
    controller.document.values = { title: "Paper 7, revised" };
    pencil().click();

    await choose("cancel");

    assert.equal(dialog(), null);
    assert.equal(controller.document.open, true);
    assert.deepEqual(controller.document.values, { title: "Paper 7, revised" });
    assert.ok(fieldEditor().classList.contains("d-none"));
    assert.equal(fetch.calls.length, 1);
  });

  it("asks before a document row replaces a changed field, and discards it when told to", async () => {
    pencil().click();
    field().value = "Augusta";
    fetch.respond({ success: true, fields: [fieldResponse()], record: 7 });

    rowButton(7, "view").click();
    await settle(20);
    assert.ok(dialog() !== null);
    assert.equal(fetch.calls.length, 0);
    assert.equal(controller.document.open, false);

    await choose("discard");

    assert.equal(field().value, "Ada");
    assert.ok(fieldEditor().classList.contains("d-none"));
    assert.equal(fetch.calls[0]?.url, endpoints.documentForm);
    assert.equal(controller.document.open, true);
    assert.equal(
      select(root, '[data-pe-status-toast="status"] .status-message', HTMLElement).textContent,
      messages.discarded,
    );
  });

  it("opens no document row when the dialog about the changed field is cancelled", async () => {
    pencil().click();
    field().value = "Augusta";

    rowButton(7, "view").click();
    await settle(20);
    await choose("cancel");

    assert.equal(field().value, "Augusta");
    assert.equal(fieldEditor().classList.contains("d-none"), false);
    assert.equal(fetch.calls.length, 0);
    assert.equal(controller.document.open, false);
  });

  /**
   * Another row of the same or of another section is the case the report
   * named: the controller used to create a second panel and forget the first,
   * with its values still in it and its cancel closing the wrong one.
   */
  it("asks before another row replaces a document editor with changes", async () => {
    await openRow(7, "edit");
    controller.document.values = { title: "Paper 7, revised" };
    fetch.respond({ success: true, fields: [fieldResponse("Paper 8")], record: 8 });

    rowButton(8, "view").click();
    await settle(20);
    assert.ok(dialog() !== null);
    assert.equal(controller.document.record, 7);
    assert.equal(fetch.calls.length, 1);

    await choose("discard");

    assert.equal(controller.document.record, 8);
    assert.equal(controller.document.mode, "view");
    assert.equal(rowButton(7, "edit").getAttribute("aria-expanded"), "false");
    assert.equal(rowButton(8, "view").getAttribute("aria-expanded"), "true");
    assert.equal(fetch.calls.length, 2);
  });

  it("replaces an untouched document editor with another row's on the spot", async () => {
    await openRow(7, "view");
    fetch.respond({ success: true, fields: [fieldResponse("Paper 8")], record: 8 });

    rowButton(8, "view").click();
    await settle(20);

    assert.equal(dialog(), null);
    assert.equal(controller.document.record, 8);
    assert.equal(fetch.calls.length, 2);
  });

  describe("the dialog", () => {
    it("answers cancel when the markup carries no dialog template", async () => {
      select(root, 'template[data-pe-dialog="unsaved-changes"]', HTMLTemplateElement).remove();

      assert.equal(await askUnsavedChanges(root), "cancel");
      assert.equal(dialog(), null);
    });

    it("answers cancel when it is dismissed, and returns the focus", async () => {
      pencil().click();
      const asking = askUnsavedChanges(root);
      const shown = dialog();
      assert.ok(shown !== null);
      assert.ok(shown.hasAttribute("open"));
      assert.notEqual(document.activeElement, field());

      shown.dispatchEvent(new Event("cancel", { cancelable: true }));

      assert.equal(await asking, "cancel");
      assert.equal(dialog(), null);
      assert.equal(document.activeElement, field());
    });

    it("answers what the pressed button says", async () => {
      const asking = askUnsavedChanges(root);
      select(root, '[data-pe-unsaved-choice="save"]', HTMLButtonElement).click();

      assert.equal(await asking, "save");
      assert.equal(dialog(), null);
    });
  });
});
