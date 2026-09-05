import assert from "node:assert/strict";
import { beforeEach, describe, it } from "node:test";
import {
  createKeyboardEvent,
  resetBody,
  settle,
} from "../../../../../Build/tests/dom.mjs";
import { installFetch, type FetchDouble } from "../../../../../Build/tests/fetch.mjs";
import { initializeFieldEditing } from "@fgtclb/academic-persons-edit/frontend/profile/fields.js";
import { setRichTextEditorValue } from "@fgtclb/academic-persons-edit/frontend/profile/rich-text.js";
import {
  checkboxField,
  documentEditorView,
  endpoints,
  fieldGroup,
  fieldsForm,
  messages,
  profileEditingRoot,
  profileHeader,
  richTextField,
  select,
  selectAll,
  textField,
} from "./Fixtures/profile-editing.ts";

/**
 * Full form editing: the whole profile open at once, applied, undone or
 * discarded as one form.
 *
 * The difference to single-field editing is not that more fields are open. It
 * is that the form has controls of its own and the fields do not: the per-field
 * clear/undo/save groups are hidden for as long as the form is open, an
 * autosaving checkbox stops writing on change, and the three buttons of
 * `Field/FormActions.html` act on every editable field at once. Everything
 * single-field editing does is unchanged and is pinned in
 * `field-editing.test.ts`.
 *
 * Apply is one request. `updateAction()` validates the whole field map before
 * it persists anything, so a refusal leaves the profile untouched and nothing
 * on screen is reverted - what the visitor typed stays where it was typed.
 */
describe("editing the whole profile as one form", () => {
  let fetch: FetchDouble;
  let root: HTMLElement;
  let announcements: string[];

  const render = (): HTMLElement => {
    const body = resetBody(
      profileEditingRoot({
        content:
          profileHeader() +
          fieldsForm(
            textField({ identifier: "firstName", value: "Ada" }) +
              textField({ identifier: "lastName", value: "Lovelace" }) +
              textField({
                identifier: "position",
                value: "Countess",
                readOnly: true,
              }) +
              checkboxField({
                identifier: "publishedToWebsite",
                checked: false,
              }) +
              fieldGroup({
                identifier: "link",
                displayMode: "first",
                fields: [
                  { identifier: "linkTitle", value: "Homepage" },
                  { identifier: "linkUrl", value: "https://example.test" },
                ],
              }),
          ) +
          // Outside every fields form, where the document editor element puts
          // it: a panel may well be open while the profile form is.
          documentEditorView({ fields: [{ name: "title", value: "Notes" }] }),
      }),
    );
    const rendered = select(
      body,
      "[data-academic-persons-profile-editing]",
      HTMLElement,
    );
    initializeFieldEditing(rendered);

    return rendered;
  };

  const field = (identifier: string): HTMLInputElement =>
    select(root, `#profile-editing-1-${identifier}`, HTMLInputElement);
  const editor = (identifier: string): HTMLElement =>
    select(root, `#profile-editing-1-${identifier}-editor`, HTMLElement);
  const preview = (identifier: string): HTMLElement =>
    select(
      root,
      `[data-pe-field-preview][data-pe-for="profile-editing-1-${identifier}"] [data-pe-field-preview-content]`,
      HTMLElement,
    );
  const bar = (): HTMLElement =>
    select(root, "[data-pe-form-actions]", HTMLElement);
  const barButton = (attribute: string): HTMLButtonElement =>
    select(root, `[${attribute}]`, HTMLButtonElement);
  const toggle = (): HTMLButtonElement =>
    select(
      root,
      "[data-academic-persons-profile-editing-edit-all-btn]",
      HTMLButtonElement,
    );
  const statusMessage = (region: "status" | "alert"): string =>
    select(
      root,
      `[data-pe-status-toast="${region}"] .status-message`,
      HTMLElement,
    ).textContent ?? "";

  beforeEach(() => {
    fetch = installFetch();
    // `showStatus()` shows a Bootstrap toast where the page has Bootstrap. The
    // double is what makes "announced once" countable: the text of a region can
    // only be read after the last write, never how often it was written.
    announcements = [];
    (globalThis as unknown as { bootstrap?: unknown }).bootstrap = {
      Toast: {
        getOrCreateInstance: (element: Element): { show: () => void } => ({
          show: (): void => {
            announcements.push(
              select(element, ".status-message", HTMLElement).textContent ?? "",
            );
          },
        }),
      },
    };
    root = render();
  });

  it("delivers the form actions hidden", () => {
    assert.equal(bar().hidden, true);
    assert.equal(bar().getAttribute("role"), "group");
    assert.equal(barButton("data-pe-form-apply").type, "button");
    assert.equal(barButton("data-pe-form-undo").type, "button");
    assert.equal(barButton("data-pe-form-discard").type, "button");
  });

  it("shows the form actions and hides every per-field and per-group group", () => {
    toggle().click();

    assert.equal(bar().hidden, false);
    for (const group of selectAll(
      root,
      "[data-pe-field-actions], [data-pe-group-actions], [data-pe-autosave-undo]",
      HTMLElement,
    )) {
      assert.equal(group.hidden, true, group.outerHTML);
    }
    assert.equal(toggle().getAttribute("aria-pressed"), "true");
    assert.equal(
      select(root, "[data-pe-edit-all-button-label]", HTMLElement).textContent,
      "Close all",
    );
  });

  it("opens the editable fields and leaves a read-only one closed", () => {
    toggle().click();

    assert.equal(editor("firstName").classList.contains("d-none"), false);
    assert.equal(editor("lastName").classList.contains("d-none"), false);
    assert.equal(
      select(root, "[data-pe-group-editor]", HTMLElement).classList.contains("d-none"),
      false,
    );
    assert.equal(editor("position").classList.contains("d-none"), true);
  });

  it("puts the caret in the first editable field rather than in the last", () => {
    toggle().click();

    assert.equal(document.activeElement, field("firstName"));
  });

  it("takes over a single field that was already open", () => {
    select(
      root,
      '[data-academic-persons-profile-editing-activate-btn][data-pe-for="profile-editing-1-lastName"]',
      HTMLButtonElement,
    ).click();
    assert.equal(editor("lastName").classList.contains("d-none"), false);

    toggle().click();

    assert.equal(editor("lastName").classList.contains("d-none"), false);
    assert.equal(
      select(
        root,
        '[data-pe-field-actions][data-pe-for="profile-editing-1-lastName"]',
        HTMLElement,
      ).hidden,
      true,
    );
    assert.equal(document.activeElement, field("firstName"));
  });

  it("posts every changed field of the form in one request", async () => {
    fetch.respond({ success: true, data: {} });
    toggle().click();
    field("firstName").value = "Augusta";
    select(root, "#profile-editing-1-linkTitle", HTMLInputElement).value = "Notes";
    field("publishedToWebsite").checked = true;

    barButton("data-pe-form-apply").click();
    await settle(20);

    assert.equal(fetch.calls.length, 1);
    const call = fetch.calls[0];
    assert.equal(call?.url, endpoints.update);
    assert.equal(call?.method, "POST");
    assert.deepEqual(call?.body, {
      profile: 1,
      data: {
        firstName: "Augusta",
        linkTitle: "Notes",
        publishedToWebsite: true,
      },
    });
  });

  it("sends nothing at all when no field was touched", async () => {
    toggle().click();

    barButton("data-pe-form-apply").click();
    await settle(20);

    assert.equal(fetch.calls.length, 0);
    assert.equal(statusMessage("status"), messages.unchanged);
    assert.equal(bar().hidden, true);
  });

  it("closes the form and returns the focus to the toggle when the profile was saved", async () => {
    fetch.respond({ success: true, data: {} });
    toggle().click();
    field("firstName").value = "Augusta";

    barButton("data-pe-form-apply").click();
    await settle(20);

    assert.equal(bar().hidden, true);
    assert.equal(editor("firstName").classList.contains("d-none"), true);
    assert.equal(toggle().getAttribute("aria-pressed"), "false");
    assert.equal(
      select(root, "[data-pe-edit-all-button-label]", HTMLElement).textContent,
      "Edit all",
    );
    assert.equal(document.activeElement, toggle());
    for (const group of selectAll(
      root,
      "[data-pe-field-actions], [data-pe-group-actions], [data-pe-autosave-undo]",
      HTMLElement,
    )) {
      assert.equal(group.hidden, false);
    }
  });

  /**
   * `saveFields()` closes what it saved and would focus each activate button on
   * the way; in form editing the apply path owns the focus and takes it to the
   * toggle, so a screen reader must not be walked past the per-field buttons
   * first.
   */
  it("does not touch the per-field buttons on the way back to the toggle", async () => {
    fetch.respond({ success: true, data: {} });
    toggle().click();
    field("firstName").value = "Augusta";
    const focused: Element[] = [];
    root.addEventListener("focusin", (event): void => {
      if (event.target instanceof Element) {
        focused.push(event.target);
      }
    });

    barButton("data-pe-form-apply").click();
    await settle(20);

    assert.deepEqual(
      focused.filter((element): boolean =>
        element.matches("[data-academic-persons-profile-editing-activate-btn]"),
      ),
      [],
    );
    assert.equal(focused.at(-1), toggle());
  });

  it("writes the values the server stored back into the fields and previews", async () => {
    fetch.respond({
      success: true,
      data: { firstName: "Augusta", lastName: "King" },
    });
    toggle().click();
    field("firstName").value = "  augusta  ";
    field("lastName").value = "king";

    barButton("data-pe-form-apply").click();
    await settle(20);

    assert.equal(field("firstName").value, "Augusta");
    assert.equal(field("lastName").value, "King");
    assert.equal(preview("firstName").textContent, "Augusta");
    assert.equal(
      select(root, "[data-pe-profile-name]", HTMLElement).textContent,
      "Augusta King",
    );
  });

  it("keeps every typed value when the server refuses the form", async () => {
    fetch.respondWithError(
      {
        success: false,
        errors: { "profile.firstName": ["Must not be empty."] },
      },
      422,
    );
    toggle().click();
    field("firstName").value = "";
    field("lastName").value = "King";

    barButton("data-pe-form-apply").click();
    await settle(20);

    assert.equal(field("firstName").value, "");
    assert.equal(field("lastName").value, "King");
    assert.equal(bar().hidden, false);
    assert.equal(toggle().getAttribute("aria-pressed"), "true");
  });

  it("marks each refused field and puts the caret in the first of them", async () => {
    fetch.respondWithError(
      {
        success: false,
        errors: {
          "profile.firstName": ["Must not be empty."],
          "profile.lastName": ["Must not be empty."],
        },
      },
      422,
    );
    toggle().click();
    field("firstName").value = "";
    field("lastName").value = "";

    barButton("data-pe-form-apply").click();
    await settle(20);

    assert.equal(field("firstName").getAttribute("aria-invalid"), "true");
    assert.equal(field("lastName").getAttribute("aria-invalid"), "true");
    assert.equal(
      select(root, "#profile-editing-1-firstName-error", HTMLElement).textContent,
      "Must not be empty.",
    );
    assert.equal(
      select(root, "#profile-editing-1-lastName-error", HTMLElement).textContent,
      "Must not be empty.",
    );
    assert.equal(document.activeElement, field("firstName"));
  });

  it("announces the refusal once and not once per refused field", async () => {
    fetch.respondWithError(
      {
        success: false,
        errors: {
          "profile.firstName": ["Must not be empty."],
          "profile.lastName": ["Must not be empty."],
        },
      },
      422,
    );
    toggle().click();
    field("firstName").value = "";
    field("lastName").value = "";
    announcements = [];

    barButton("data-pe-form-apply").click();
    await settle(20);

    assert.deepEqual(announcements, [messages.saving, messages.validation]);
    // The caret has just moved to the first refused field, and a polite region
    // queued behind that focus change is routinely dropped.
    assert.equal(statusMessage("alert"), messages.validation);
    assert.equal(statusMessage("status"), messages.saving);
  });

  it("clears the messages of the previous attempt before it sends the next", async () => {
    fetch.respondWithError(
      { success: false, errors: { "profile.firstName": ["Must not be empty."] } },
      422,
    );
    toggle().click();
    field("firstName").value = "";
    barButton("data-pe-form-apply").click();
    await settle(20);

    fetch.respond({ success: true, data: { firstName: "Augusta" } });
    field("firstName").value = "Augusta";
    barButton("data-pe-form-apply").click();
    await settle(20);

    assert.equal(field("firstName").getAttribute("aria-invalid"), "false");
    assert.equal(field("firstName").classList.contains("is-invalid"), false);
    assert.equal(
      select(root, "#profile-editing-1-firstName-error", HTMLElement).textContent,
      "",
    );
  });

  it("sends one request even when apply is pressed twice in the same turn", async () => {
    fetch.respond({ success: true, data: {} });
    toggle().click();
    field("firstName").value = "Augusta";

    barButton("data-pe-form-apply").click();
    barButton("data-pe-form-apply").click();
    await settle(20);

    assert.equal(fetch.calls.length, 1);
  });

  it("restores every field on undo and keeps the form open", () => {
    toggle().click();
    field("firstName").value = "Augusta";
    field("publishedToWebsite").checked = true;
    select(root, "#profile-editing-1-linkTitle", HTMLInputElement).value = "Notes";

    barButton("data-pe-form-undo").click();

    assert.equal(field("firstName").value, "Ada");
    assert.equal(field("publishedToWebsite").checked, false);
    assert.equal(
      select(root, "#profile-editing-1-linkTitle", HTMLInputElement).value,
      "Homepage",
    );
    assert.equal(bar().hidden, false);
    assert.equal(editor("firstName").classList.contains("d-none"), false);
    assert.equal(statusMessage("status"), messages.formReverted);
  });

  it("rewrites every preview and the name heading on undo", () => {
    toggle().click();
    field("firstName").value = "Augusta";
    select(root, "#profile-editing-1-linkTitle", HTMLInputElement).value = "Notes";
    select(root, "[data-pe-profile-name]", HTMLElement).textContent = "stale";

    barButton("data-pe-form-undo").click();

    assert.equal(preview("firstName").textContent, "Ada");
    assert.equal(
      select(root, "[data-pe-group-preview-content]", HTMLElement).textContent,
      "Homepage",
    );
    assert.equal(
      select(root, "[data-pe-profile-name]", HTMLElement).textContent,
      "Ada Lovelace",
    );
  });

  it("restores every field on discard, closes the form and returns the focus", () => {
    toggle().click();
    field("firstName").value = "Augusta";

    barButton("data-pe-form-discard").click();

    assert.equal(field("firstName").value, "Ada");
    assert.equal(bar().hidden, true);
    assert.equal(editor("firstName").classList.contains("d-none"), true);
    assert.equal(toggle().getAttribute("aria-pressed"), "false");
    assert.equal(document.activeElement, toggle());
  });

  it("discards the form when the toggle closes it", () => {
    toggle().click();
    field("firstName").value = "Augusta";

    toggle().click();

    assert.equal(field("firstName").value, "Ada");
    assert.equal(bar().hidden, true);
    assert.equal(
      select(root, "[data-pe-edit-all-button-label]", HTMLElement).textContent,
      "Edit all",
    );
  });

  it("discards the form on Escape", () => {
    toggle().click();
    field("firstName").value = "Augusta";

    field("firstName").dispatchEvent(
      createKeyboardEvent("keydown", { key: "Escape" }),
    );

    assert.equal(field("firstName").value, "Ada");
    assert.equal(bar().hidden, true);
  });

  it("leaves Escape to the rich text editor the caret is in", () => {
    toggle().click();
    field("firstName").value = "Augusta";
    const editorSurface = document.createElement("div");
    editorSurface.className = "ck";
    select(root, "[data-pe-fields-form]", HTMLFormElement).append(editorSurface);

    editorSurface.dispatchEvent(
      createKeyboardEvent("keydown", { key: "Escape" }),
    );

    assert.equal(field("firstName").value, "Augusta");
    assert.equal(bar().hidden, false);
  });

  it("applies the form on Ctrl and Enter", async () => {
    fetch.respond({ success: true, data: {} });
    toggle().click();
    field("firstName").value = "Augusta";

    field("firstName").dispatchEvent(
      createKeyboardEvent("keydown", { key: "Enter", ctrlKey: true }),
    );
    await settle(20);

    assert.deepEqual(fetch.calls[0]?.body, {
      profile: 1,
      data: { firstName: "Augusta" },
    });
  });

  it("applies the form when it is submitted, and never lets the submission through", async () => {
    fetch.respond({ success: true, data: {} });
    toggle().click();
    field("firstName").value = "Augusta";
    const form = select(root, "[data-pe-fields-form]", HTMLFormElement);
    const submission = new Event("submit", { bubbles: true, cancelable: true });

    form.dispatchEvent(submission);
    await settle(20);

    assert.equal(submission.defaultPrevented, true);
    assert.equal(fetch.calls.length, 1);
    assert.deepEqual(fetch.calls[0]?.body, {
      profile: 1,
      data: { firstName: "Augusta" },
    });
  });

  /**
   * The one behaviour change single-field editing keeps and the form does not.
   * A checkbox that writes on change would reach the database while the visitor
   * is still deciding, and discard could not take it back.
   */
  it("does not let an autosaving checkbox write while the form is open", async () => {
    toggle().click();

    field("publishedToWebsite").checked = true;
    field("publishedToWebsite").dispatchEvent(
      new CustomEvent("change", { bubbles: true }),
    );
    await settle(20);

    assert.equal(fetch.calls.length, 0);
    assert.equal(field("publishedToWebsite").checked, true);
  });

  it("lets the same checkbox write again once the form is closed", async () => {
    fetch.respond({ success: true, data: { publishedToWebsite: true } });
    toggle().click();
    barButton("data-pe-form-discard").click();

    field("publishedToWebsite").checked = true;
    field("publishedToWebsite").dispatchEvent(
      new CustomEvent("change", { bubbles: true }),
    );
    await settle(20);

    assert.deepEqual(fetch.calls[0]?.body, {
      profile: 1,
      data: { publishedToWebsite: true },
    });
  });

  it("applies the checkbox with the rest of the form instead", async () => {
    fetch.respond({ success: true, data: { publishedToWebsite: true } });
    toggle().click();
    field("publishedToWebsite").checked = true;
    field("publishedToWebsite").dispatchEvent(
      new CustomEvent("change", { bubbles: true }),
    );
    await settle(20);
    assert.equal(fetch.calls.length, 0);

    barButton("data-pe-form-apply").click();
    await settle(20);

    assert.equal(fetch.calls.length, 1);
    assert.deepEqual(fetch.calls[0]?.body, {
      profile: 1,
      data: { publishedToWebsite: true },
    });
  });

  /**
   * The request is on its way and cannot be un-persisted, so the transition is
   * refused rather than the request abandoned. Reverting under it would let the
   * response write the reverted values into the baseline for every property the
   * endpoint does not echo, and the next apply would then not resend them: the
   * discarded value would stay in the database with nothing saying so.
   */
  it("refuses discard while an apply is on its way to the server", async () => {
    const pending = fetch.respondLater();
    toggle().click();
    field("firstName").value = "Augusta";
    barButton("data-pe-form-apply").click();
    await settle(20);

    barButton("data-pe-form-discard").click();

    assert.equal(field("firstName").value, "Augusta");
    assert.equal(bar().hidden, false);
    assert.equal(toggle().getAttribute("aria-pressed"), "true");

    pending.settle({ success: true, data: {} });
    await settle(20);

    assert.equal(field("firstName").value, "Augusta");
    assert.equal(bar().hidden, true);
    assert.deepEqual(fetch.calls[0]?.body, {
      profile: 1,
      data: { firstName: "Augusta" },
    });
  });

  it("refuses undo, the toggle and Escape while an apply is on its way", async () => {
    const pending = fetch.respondLater();
    toggle().click();
    field("firstName").value = "Augusta";
    barButton("data-pe-form-apply").click();
    await settle(20);

    barButton("data-pe-form-undo").click();
    toggle().click();
    field("firstName").dispatchEvent(
      createKeyboardEvent("keydown", { key: "Escape" }),
    );

    assert.equal(field("firstName").value, "Augusta");
    assert.equal(bar().hidden, false);

    pending.settle({ success: true, data: {} });
    await settle(20);

    assert.equal(fetch.calls.length, 1);
    assert.equal(bar().hidden, true);
  });

  /**
   * A document, contact or image editor may be open while the profile form is -
   * full form editing deliberately does not touch those panels - so neither key
   * may be taken from them.
   */
  it("leaves Escape and Ctrl + Enter to a document panel that is open beside the form", async () => {
    toggle().click();
    field("firstName").value = "Augusta";
    const documentField = select(
      root,
      "#profile-editing-document-field-0-title",
      HTMLInputElement,
    );

    documentField.dispatchEvent(createKeyboardEvent("keydown", { key: "Escape" }));
    documentField.dispatchEvent(
      createKeyboardEvent("keydown", { key: "Enter", ctrlKey: true }),
    );
    await settle(20);

    assert.equal(bar().hidden, false);
    assert.equal(field("firstName").value, "Augusta");
    assert.equal(fetch.calls.length, 0);
  });
});

/**
 * A rich text field in the form.
 *
 * Two things about it are specific to full form editing. Opening the form
 * creates every CKEditor without moving the caret into any of them, so the
 * first field of the form keeps it. And the editor normalises what the template
 * rendered - bare text becomes a paragraph - so the baseline the module compares
 * against has to be taken from the editor and not from the markup, on the undo
 * path as well as on the save path. The stub reports that difference where a
 * fixture asks for it, through `data-test-ckeditor-initial`.
 */
describe("a rich text field in the whole-profile form", () => {
  let fetch: FetchDouble;
  let root: HTMLElement;

  const render = (): HTMLElement => {
    const body = resetBody(
      profileEditingRoot({
        content:
          profileHeader() +
          fieldsForm(
            textField({ identifier: "firstName", value: "Ada" }) +
              richTextField({
                identifier: "description",
                value: "Ada wrote notes",
                editorValue: "<p>Ada wrote notes</p>",
              }),
          ),
      }),
    );
    const rendered = select(
      body,
      "[data-academic-persons-profile-editing]",
      HTMLElement,
    );
    initializeFieldEditing(rendered);

    return rendered;
  };

  const description = (): HTMLTextAreaElement =>
    select(root, "#profile-editing-1-description", HTMLTextAreaElement);
  const toggle = (): HTMLButtonElement =>
    select(
      root,
      "[data-academic-persons-profile-editing-edit-all-btn]",
      HTMLButtonElement,
    );
  const barButton = (attribute: string): HTMLButtonElement =>
    select(root, `[${attribute}]`, HTMLButtonElement);

  beforeEach(() => {
    fetch = installFetch();
    root = render();
  });

  it("creates the editor of every rich text field and still focuses the first field", async () => {
    toggle().click();
    await settle(20);

    assert.equal(description().getAttribute("data-test-ckeditor"), "live");
    assert.equal(
      document.activeElement,
      select(root, "#profile-editing-1-firstName", HTMLInputElement),
    );
  });

  it("takes the baseline from the editor, so undo leaves nothing for apply to send", async () => {
    toggle().click();
    await settle(20);

    barButton("data-pe-form-undo").click();
    barButton("data-pe-form-apply").click();
    await settle(20);

    assert.equal(description().value, "<p>Ada wrote notes</p>");
    assert.equal(fetch.calls.length, 0);
  });

  it("posts what the editor holds and writes the stored value back into it", async () => {
    fetch.respond({
      success: true,
      data: { description: "<p>Ada wrote a lot</p>" },
    });
    toggle().click();
    await settle(20);
    setRichTextEditorValue(description(), "<p>Ada wrote more</p>");

    barButton("data-pe-form-apply").click();
    await settle(20);

    assert.deepEqual(fetch.calls[0]?.body, {
      profile: 1,
      data: { description: "<p>Ada wrote more</p>" },
    });
    assert.equal(description().value, "<p>Ada wrote a lot</p>");
  });

  it("puts the caret in the first refused field even when a later one is rich text", async () => {
    fetch.respondWithError(
      {
        success: false,
        errors: {
          "profile.firstName": ["Must not be empty."],
          "profile.description": ["Too long."],
        },
      },
      422,
    );
    toggle().click();
    await settle(20);
    select(root, "#profile-editing-1-firstName", HTMLInputElement).value = "";
    setRichTextEditorValue(description(), "<p>Ada wrote far too much</p>");

    barButton("data-pe-form-apply").click();
    await settle(20);

    assert.equal(
      document.activeElement,
      select(root, "#profile-editing-1-firstName", HTMLInputElement),
    );
    assert.equal(description().getAttribute("aria-invalid"), "true");
  });
});

/**
 * The shipped template renders two fields forms - personal data and about me -
 * in different rows of the grid, so `Profile/Fields.html` renders two bars.
 * Each of them governs the whole profile: the two forms are one form as far as
 * this state is concerned.
 */
describe("a profile whose fields are spread over two forms", () => {
  let fetch: FetchDouble;
  let root: HTMLElement;

  const render = (): HTMLElement => {
    const body = resetBody(
      profileEditingRoot({
        content:
          profileHeader() +
          fieldsForm(textField({ identifier: "firstName", value: "Ada" })) +
          fieldsForm(
            textField({ identifier: "description", value: "Notes" }),
            "about",
            "About me",
          ),
      }),
    );
    const rendered = select(
      body,
      "[data-academic-persons-profile-editing]",
      HTMLElement,
    );
    initializeFieldEditing(rendered);

    return rendered;
  };

  const field = (identifier: string): HTMLInputElement =>
    select(root, `#profile-editing-1-${identifier}`, HTMLInputElement);
  const bars = (): HTMLElement[] =>
    selectAll(root, "[data-pe-form-actions]", HTMLElement);
  const secondBarButton = (attribute: string): HTMLButtonElement =>
    select(bars()[1] as HTMLElement, `[${attribute}]`, HTMLButtonElement);
  const toggle = (): HTMLButtonElement =>
    select(
      root,
      "[data-academic-persons-profile-editing-edit-all-btn]",
      HTMLButtonElement,
    );

  beforeEach(() => {
    fetch = installFetch();
    root = render();
  });

  it("shows and hides both bars together and names them apart", () => {
    assert.equal(bars().length, 2);
    assert.deepEqual(
      bars().map((element): string | null => element.getAttribute("aria-label")),
      ["Form actions: Personal data", "Form actions: About me"],
    );

    toggle().click();

    assert.deepEqual(bars().map((element): boolean => element.hidden), [false, false]);
  });

  it("applies every changed field of both forms from the second bar", async () => {
    fetch.respond({ success: true, data: {} });
    toggle().click();
    field("firstName").value = "Augusta";
    field("description").value = "More notes";

    secondBarButton("data-pe-form-apply").click();
    await settle(20);

    assert.equal(fetch.calls.length, 1);
    assert.deepEqual(fetch.calls[0]?.body, {
      profile: 1,
      data: { firstName: "Augusta", description: "More notes" },
    });
  });

  it("restores the fields of both forms from the second bar", () => {
    toggle().click();
    field("firstName").value = "Augusta";
    field("description").value = "More notes";

    secondBarButton("data-pe-form-undo").click();

    assert.equal(field("firstName").value, "Ada");
    assert.equal(field("description").value, "Notes");
  });
});
