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
  documentEditorView,
  documentRow,
  documentSection,
  endpoints,
  labels,
  messages,
  profileEditingRoot,
  select,
  selectAll,
} from "./Fixtures/profile-editing.ts";

/**
 * Opening, filling and closing a document editor.
 *
 * ## What can and cannot be observed here
 *
 * This file drives the controller without a custom element registry, which is
 * what it was written to do: the DOM the controller *reads* is put in place
 * by the test, and what is asserted is everything the controller does around
 * it - the request it sends, the trigger it marks, the collapse target it
 * addresses, the editors it creates, the field it focuses, the row it inserts,
 * updates or removes, and the focus it returns.
 *
 * Where a value has no observable effect of its own - the title of the editor,
 * the per-field error messages - the state handed to the element is asserted
 * instead, and said so at the assertion. What renders that state is
 * `<academic-persons-edit-document-editor>` and is covered by
 * `document-editor-element.test.ts`.
 */
const fieldResponse = (
  overrides: Record<string, unknown> = {},
): Record<string, unknown> => ({
  name: "title",
  label: "Title",
  type: "text",
  value: "Sample paper",
  disabled: false,
  readOnly: false,
  required: true,
  richText: false,
  ...overrides,
});

describe("opening a document editor", () => {
  let fetch: FetchDouble;
  let root: HTMLElement;
  let controller: DocumentEditingController;

  const render = ({ rows = [7] }: { rows?: number[] } = {}): void => {
    const body = resetBody(
      profileEditingRoot({
        content: documentSection({
          identifier: "publications",
          rows: rows
            .map((uid, index): string =>
              documentRow({ uid, sorting: (index + 1) * 10, position: index, title: `Paper ${uid}` }),
            )
            .join(""),
        }),
      }),
    );
    root = select(body, "[data-academic-persons-profile-editing]", HTMLElement);
    controller = createDocumentEditing(root);
    initializeDocumentSections(root);
  };

  /**
   * Stands in for what `<academic-persons-edit-document-editor>` renders into
   * the collapse target once the state says the editor is open - this file does
   * not register the element, so the controller's `updateComplete` resolves at
   * once and it looks for the view in the same turn. It is placed before the
   * click for that reason.
   */
  const placeEditorView = (target: string, view: string): void => {
    select(root, target, HTMLElement).innerHTML = view;
  };

  const addButton = (): HTMLButtonElement =>
    select(root, "[data-pe-document-add]", HTMLButtonElement);
  const viewButton = (uid: number): HTMLButtonElement =>
    select(root, `[data-item-uid="${uid}"] [data-pe-document-view]`, HTMLButtonElement);

  beforeEach(() => {
    fetch = installFetch();
    render();
  });

  it("asks the form endpoint for the fields of that section, record and mode", async () => {
    fetch.respond({ success: true, fields: [fieldResponse()], record: null });

    addButton().click();
    await settle(20);

    const call = fetch.calls[0];
    assert.equal(call?.url, endpoints.documentForm);
    assert.equal(call?.method, "POST");
    assert.equal(call?.headers["X-Requested-With"], "XMLHttpRequest");
    assert.deepEqual(call?.body, {
      profile: 1,
      data: { section: "publications", record: 0, mode: "add" },
    });
  });

  it("sends the record of the row the button belongs to", async () => {
    fetch.respond({ success: true, fields: [fieldResponse()], record: 7 });

    viewButton(7).click();
    await settle(20);

    assert.deepEqual(fetch.calls[0]?.body, {
      profile: 1,
      data: { section: "publications", record: 7, mode: "view" },
    });
  });

  /**
   * The editor is rendered into the collapse target of the row or of the
   * section, so the target needs an id to be addressable - and the button that
   * opened it has to say which region it controls.
   */
  it("gives the collapse target an id and points the trigger and the editor at it", async () => {
    fetch.respond({ success: true, fields: [fieldResponse()], record: null });

    addButton().click();
    await settle(20);

    const target = select(root, "[data-pe-document-add-collapse-target]", HTMLElement);
    assert.notEqual(target.id, "");
    assert.equal(addButton().getAttribute("aria-controls"), target.id);
    assert.equal(addButton().getAttribute("aria-expanded"), "true");
    // The selector the editor is rendered through - a property of the state the
    // element renders from.
    assert.equal(controller.document.target, `#${target.id}`);
    assert.equal(controller.document.open, true);
  });

  it("addresses the collapse target of the row a row action was pressed in", async () => {
    fetch.respond({ success: true, fields: [fieldResponse()], record: 7 });

    viewButton(7).click();
    await settle(20);

    const target = select(
      root,
      '[data-item-uid="7"] [data-pe-document-item-collapse-target]',
      HTMLElement,
    );
    assert.equal(viewButton(7).getAttribute("aria-controls"), target.id);
    assert.equal(controller.document.target, `#${target.id}`);
  });

  it("titles the editor with the mode and the subject", async () => {
    fetch.respond({ success: true, fields: [fieldResponse()], record: null });

    addButton().click();
    await settle(20);

    assert.equal(controller.document.title, "Add: Sample paper");
  });

  it("falls back to the section heading where the fields carry no title", async () => {
    fetch.respond({
      success: true,
      fields: [fieldResponse({ name: "note", value: "" })],
      record: null,
    });

    addButton().click();
    await settle(20);

    assert.equal(controller.document.title, "Add: publications");
  });

  it("starts the values from the fields the server answered with", async () => {
    fetch.respond({
      success: true,
      fields: [fieldResponse(), fieldResponse({ name: "year", value: 1843 })],
      record: null,
    });

    addButton().click();
    await settle(20);

    assert.deepEqual(controller.document.values, {
      title: "Sample paper",
      year: 1843,
    });
  });

  /**
   * Pressing the button that opened an editor closes it again, so a row's own
   * action is a toggle rather than a one-way door.
   */
  it("closes again when the same trigger is pressed a second time", async () => {
    fetch.respond({ success: true, fields: [fieldResponse()], record: 7 });
    viewButton(7).click();
    await settle(20);

    viewButton(7).click();
    await settle(20);

    assert.equal(controller.document.open, false);
    assert.equal(viewButton(7).getAttribute("aria-expanded"), "false");
    assert.equal(fetch.calls.length, 1);
  });

  /**
   * What the eye of a row says while the panel it opened is standing.
   *
   * `aria-expanded` is the whole answer for a screen reader and was the whole
   * answer for everybody else too, which left an eye reading "show" above an
   * open panel. Fluid renders both icons and both strings; the controller only
   * flips `hidden` on the two wrappers and rewrites `aria-label` and `title`
   * from the two data attributes, so the swap is asserted through the button
   * rather than through any markup this file writes.
   */
  it("shows the closing eye and its label while the panel is open", async () => {
    const iconOf = (state: string): HTMLElement =>
      select(
        viewButton(7),
        `[data-pe-view-icon="${state}"]`,
        HTMLElement,
      );
    assert.equal(iconOf("collapsed").hidden, false);
    assert.equal(iconOf("expanded").hidden, true);
    fetch.respond({ success: true, fields: [fieldResponse()], record: 7 });

    viewButton(7).click();
    await settle(20);

    assert.equal(viewButton(7).getAttribute("aria-expanded"), "true");
    assert.equal(iconOf("collapsed").hidden, true);
    assert.equal(iconOf("expanded").hidden, false);
    assert.equal(viewButton(7).getAttribute("aria-label"), labels.viewClose);
    assert.equal(viewButton(7).getAttribute("title"), labels.viewClose);

    viewButton(7).click();
    await settle(20);

    assert.equal(viewButton(7).getAttribute("aria-expanded"), "false");
    assert.equal(iconOf("collapsed").hidden, false);
    assert.equal(iconOf("expanded").hidden, true);
    assert.equal(viewButton(7).getAttribute("aria-label"), labels.view);
    assert.equal(viewButton(7).getAttribute("title"), labels.view);
  });

  /**
   * The edit and delete controls carry `aria-expanded` as well and have one
   * icon and one label each, so the same code path has to leave them alone
   * instead of hiding the only glyph they own.
   */
  it("leaves a control that has only one icon and one label as it is", async () => {
    const edit = select(
      root,
      '[data-item-uid="7"] [data-pe-document-edit]',
      HTMLButtonElement,
    );
    fetch.respond({ success: true, fields: [fieldResponse()], record: 7 });

    edit.click();
    await settle(20);

    assert.equal(edit.getAttribute("aria-expanded"), "true");
    assert.equal(edit.getAttribute("aria-label"), labels.edit);
    assert.equal(edit.querySelector("[data-pe-view-icon]"), null);
  });

  it("reports a refused form request and opens nothing", async () => {
    fetch.respondWithError({ success: false, message: "Not your profile." }, 403);

    addButton().click();
    await settle(20);

    assert.equal(controller.document.open, false);
    assert.equal(addButton().getAttribute("aria-expanded"), "false");
    assert.equal(
      select(root, '[data-pe-status-toast="alert"] .status-message', HTMLElement).textContent,
      "Not your profile.",
    );
  });

  it("puts the caret in the first field that can be edited", async () => {
    fetch.respond({
      success: true,
      fields: [
        fieldResponse({ name: "kind", disabled: true }),
        fieldResponse({ name: "title" }),
      ],
      record: null,
    });
    placeEditorView(
      "[data-pe-document-add-collapse-target]",
      documentEditorView({
        fields: [
          { name: "kind", disabled: true },
          { name: "title" },
        ],
      }),
    );

    addButton().click();
    await settle(20);

    assert.equal(
      document.activeElement,
      select(root, '[data-pe-document-field="title"]', HTMLInputElement),
    );
  });

  it("puts the caret on the heading when there is nothing to edit", async () => {
    fetch.respond({ success: true, fields: [fieldResponse()], record: 7 });
    placeEditorView(
      '[data-item-uid="7"] [data-pe-document-item-collapse-target]',
      documentEditorView({ fields: [], heading: "View: Paper 7" }),
    );

    viewButton(7).click();
    await settle(20);

    assert.equal(
      document.activeElement,
      select(root, "[data-pe-document-heading]", HTMLElement),
    );
  });

  /**
   * A rich text field of the editor gets a CKEditor of its own, and it is the
   * one that receives the caret rather than the textarea behind it.
   */
  it("creates a rich text editor for every rich text field of the form", async () => {
    fetch.respond({
      success: true,
      fields: [fieldResponse({ name: "bodytext", type: "textarea", richText: true, value: "" })],
      record: null,
    });
    placeEditorView(
      "[data-pe-document-add-collapse-target]",
      documentEditorView({
        fields: [{ name: "bodytext", type: "textarea", richText: true }],
      }),
    );

    addButton().click();
    await settle(20);

    assert.equal(
      select(root, '[data-pe-document-field="bodytext"]', HTMLTextAreaElement)
        .getAttribute("data-test-ckeditor"),
      "live",
    );
  });
});

describe("saving a document", () => {
  let fetch: FetchDouble;
  let root: HTMLElement;
  let controller: DocumentEditingController;

  const render = (): void => {
    const body = resetBody(
      profileEditingRoot({
        content: documentSection({
          identifier: "publications",
          rows: documentRow({ uid: 7, sorting: 10, position: 0, title: "Paper 7" }),
        }),
      }),
    );
    root = select(body, "[data-academic-persons-profile-editing]", HTMLElement);
    controller = createDocumentEditing(root);
    initializeDocumentSections(root);
  };

  const open = async (
    button: HTMLButtonElement,
    fields: Record<string, unknown>[],
    record: number | null,
  ): Promise<void> => {
    fetch.respond({ success: true, fields, record });
    button.click();
    await settle(20);
  };

  const rows = (): HTMLElement[] =>
    selectAll(root, "[data-pe-document-items] > [data-pe-document-item]", HTMLElement);

  beforeEach(() => {
    fetch = installFetch();
    render();
  });

  it("creates the record from the values of the form and appends the row", async () => {
    await open(
      select(root, "[data-pe-document-add]", HTMLButtonElement),
      [fieldResponse({ value: "New paper" })],
      null,
    );
    fetch.respond({
      success: true,
      item: {
        uid: 12,
        sorting: 20,
        display: { title: "New paper", yearStart: "1843" },
        values: {},
      },
    });

    await controller.submitDocument();
    await settle(20);

    const call = fetch.calls[1];
    assert.equal(call?.url, endpoints.createDocument);
    assert.deepEqual(call?.body, {
      profile: 1,
      data: { section: "publications", fields: { title: "New paper" } },
    });
    assert.deepEqual(
      rows().map((row): string | undefined => row.dataset.itemUid),
      ["7", "12"],
    );
    assert.equal(
      select(root, '[data-item-uid="12"] [data-pe-document-title]', HTMLElement).textContent,
      "New paper",
    );
    assert.equal(
      select(root, '[data-pe-status-toast="status"] .status-message', HTMLElement).textContent,
      messages.documentSaved,
    );
    assert.equal(controller.document.open, false);
  });

  it("updates the record and rewrites the row it belongs to", async () => {
    await open(
      select(root, '[data-item-uid="7"] [data-pe-document-edit]', HTMLButtonElement),
      [fieldResponse({ value: "Paper 7, revised" })],
      7,
    );
    fetch.respond({
      success: true,
      item: { uid: 7, sorting: 10, display: { title: "Paper 7, revised" }, values: {} },
    });

    await controller.submitDocument();
    await settle(20);

    const call = fetch.calls[1];
    assert.equal(call?.url, endpoints.updateDocument);
    assert.deepEqual(call?.body, {
      profile: 1,
      data: {
        section: "publications",
        record: 7,
        fields: { title: "Paper 7, revised" },
      },
    });
    assert.equal(rows().length, 1);
    assert.equal(
      select(root, '[data-item-uid="7"] [data-pe-document-title]', HTMLElement).textContent,
      "Paper 7, revised",
    );
  });

  /**
   * A deletion sends no field values, and the row survives until the editor
   * has finished closing - it is the editor's own row, and removing it while
   * it is still on screen takes the editor with it mid-transition.
   */
  it("deletes the record and removes the row only once the editor has closed", async () => {
    await open(
      select(root, '[data-item-uid="7"] [data-pe-document-delete]', HTMLButtonElement),
      [fieldResponse()],
      7,
    );
    fetch.respond({ success: true });

    await controller.submitDocument();
    await settle(20);

    assert.deepEqual(fetch.calls[1]?.body, {
      profile: 1,
      data: { section: "publications", record: 7 },
    });
    assert.equal(rows().length, 1);

    controller.finishDocumentClose(
      select(root, "[data-pe-document-section]", HTMLElement),
    );
    await settle(20);

    assert.equal(rows().length, 0);
    assert.equal(
      select(root, "[data-pe-document-empty-state]", HTMLElement).classList.contains("d-none"),
      false,
    );
  });

  /**
   * The field is deliberately not `required`: the editor renders the
   * constraint the response declares, so a required control left empty is
   * refused by the browser and never reaches the server at all. What is under
   * test here is what the editor does with a refusal that only the server can
   * make, and that needs a form the browser lets through.
   */
  it("keeps the editor open and keeps the messages of the refused fields", async () => {
    await open(
      select(root, "[data-pe-document-add]", HTMLButtonElement),
      [fieldResponse({ value: "", required: false })],
      null,
    );
    fetch.respondWithError(
      { success: false, message: "Please check.", errors: { title: ["Must not be empty."] } },
      422,
    );

    await controller.submitDocument();
    await settle(20);

    assert.equal(controller.document.open, true);
    // Rendered by the template from the state; a property of the component
    // element renders from.
    assert.equal(controller.document.error, "Please check.");
    assert.deepEqual(controller.document.errors, { title: "Must not be empty." });
    assert.equal(
      selectAll(root, "[data-pe-document-items] > [data-pe-document-item]", HTMLElement).length,
      1,
    );
  });

  /**
   * The trigger is where the visitor was before the editor opened, and it is
   * where the caret goes back to - but only if it is still on the page, which
   * a deleted row's own buttons are not.
   */
  it("returns the caret to the trigger when the editor has closed", async () => {
    const trigger = select(
      root,
      '[data-item-uid="7"] [data-pe-document-view]',
      HTMLButtonElement,
    );
    await open(trigger, [fieldResponse()], 7);

    controller.closeDocument();
    controller.finishDocumentClose(
      select(root, '[data-item-uid="7"] [data-pe-document-item-collapse-target]', HTMLElement),
    );
    await settle(20);

    assert.equal(document.activeElement, trigger);
    assert.equal(controller.document.target, "");
    assert.deepEqual(controller.document.values, {});
  });
});

/**
 * What a row shows after it has been written by the JavaScript rather than by
 * Fluid. Each cell is addressed by the column name in `data-pe-document-value`
 * - one of the hooks that was read under the wrong name for a release, and one
 * that no PHP test can catch, because the markup it reads is correct and it is
 * the reader that was not.
 */
describe("the values of a row the JavaScript wrote", () => {
  let fetch: FetchDouble;
  let root: HTMLElement;
  let controller: DocumentEditingController;

  const insert = async (item: Record<string, unknown>): Promise<HTMLElement> => {
    const body = resetBody(
      profileEditingRoot({
        content: documentSection({ identifier: "publications", rows: "" }),
      }),
    );
    root = select(body, "[data-academic-persons-profile-editing]", HTMLElement);
    controller = createDocumentEditing(root);
    initializeDocumentSections(root);

    fetch.respond({ success: true, fields: [fieldResponse()], record: null });
    select(root, "[data-pe-document-add]", HTMLButtonElement).click();
    await settle(20);

    fetch.respond({ success: true, item });
    await controller.submitDocument();
    await settle(20);

    return select(root, "[data-pe-document-items] > [data-pe-document-item]", HTMLElement);
  };

  beforeEach(() => {
    fetch = installFetch();
  });

  it("writes each display value into the cell that carries its name", async () => {
    const row = await insert({
      uid: 3,
      sorting: 10,
      display: { title: "Paper", yearStart: "1843" },
      values: {},
    });

    assert.equal(row.dataset.itemUid, "3");
    assert.equal(row.dataset.itemSorting, "10");
    assert.equal(
      select(row, '[data-pe-document-value="yearStart"]', HTMLElement).textContent,
      "1843",
    );
  });

  it("shows a dash where a value is empty", async () => {
    const row = await insert({ uid: 3, sorting: 10, display: { title: "Paper" }, values: {} });

    assert.equal(
      select(row, '[data-pe-document-value="yearStart"]', HTMLElement).textContent,
      "—",
    );
  });

  /**
   * A section that shows one year and a section that shows a range are fed by
   * the same record, so the single year stands in for the missing start year
   * and the other way round.
   */
  it("stands the single year in for a missing start year", async () => {
    const row = await insert({
      uid: 3,
      sorting: 10,
      display: { title: "Paper", year: "1843" },
      values: {},
    });

    assert.equal(
      select(row, '[data-pe-document-value="yearStart"]', HTMLElement).textContent,
      "1843",
    );
  });

  it("links the title when the record has a link, and sanitises the target", async () => {
    const row = await insert({
      uid: 3,
      sorting: 10,
      display: { title: "Paper" },
      values: { link: "https://example.org/paper" },
    });

    const link = select(row, "[data-pe-document-title] a", HTMLElement);
    assert.equal(link.getAttribute("href"), "https://example.org/paper");
    assert.equal(link.getAttribute("rel"), "noopener noreferrer");
    assert.equal(link.getAttribute("target"), "_blank");
    assert.equal(link.textContent, "Paper");
  });

  it("refuses to link a title through a scheme that can execute", async () => {
    const row = await insert({
      uid: 3,
      sorting: 10,
      display: { title: "Paper" },
      values: { link: "javascript:alert(1)" },
    });

    assert.equal(row.querySelector("[data-pe-document-title] a"), null);
    assert.equal(
      select(row, "[data-pe-document-title] span", HTMLElement).textContent,
      "Paper",
    );
  });

  it("writes the description as sanitised markup and shows the cell only when there is one", async () => {
    const withText = await insert({
      uid: 3,
      sorting: 10,
      display: {
        title: "Paper",
        bodytext: '<p>A <strong>note</strong></p><script>alert(1)</script>',
      },
      values: {},
    });
    const cell = select(withText, '[data-pe-document-value="bodytext"]', HTMLElement);
    assert.equal(cell.innerHTML, "<p>A <strong>note</strong></p>");
    assert.equal(cell.classList.contains("d-none"), false);

    const withoutText = await insert({
      uid: 4,
      sorting: 10,
      display: { title: "Paper" },
      values: {},
    });
    assert.ok(
      select(withoutText, '[data-pe-document-value="bodytext"]', HTMLElement)
        .classList.contains("d-none"),
    );
  });
});
