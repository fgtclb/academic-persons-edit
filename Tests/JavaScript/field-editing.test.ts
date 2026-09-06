import assert from "node:assert/strict";
import { beforeEach, describe, it } from "node:test";
import { resetBody, settle } from "../../../../../Build/tests/dom.mjs";
import { installFetch, type FetchDouble } from "../../../../../Build/tests/fetch.mjs";
import { initializeFieldEditing } from "@fgtclb/academic-persons-edit/frontend/profile/fields.js";
import {
  checkboxField,
  endpoints,
  fieldGroup,
  fieldsForm,
  messages,
  profileEditingRoot,
  profileHeader,
  richTextField,
  select,
  textField,
} from "./Fixtures/profile-editing.ts";

/**
 * The per-field controls of the profile form: the pencil that opens one field,
 * the three buttons beside it, the group that opens several at once, and the
 * checkbox that saves without being asked.
 *
 * This is the part of the editor a defect hid in longest. The templates emit
 * their hooks as `data-pe-*` and the TypeScript read them as `dataset.ie…` for
 * a whole release: every one of these controls was inert, and no gate could see
 * it, because the PHP tests assert the markup and never execute the module
 * while `typecheckJs` is happy with any key of a `DOMStringMap`. Every
 * assertion below fails if a hook is read under a name the templates do not
 * emit - see `docs/testing/academic-persons-edit-frontend-tests.md`.
 */
describe("editing a single field", () => {
  let fetch: FetchDouble;
  let root: HTMLElement;

  const fields = (): string =>
    fieldsForm(
      textField({ identifier: "firstName", value: "Ada" }) +
        textField({ identifier: "lastName", value: "Lovelace" }) +
        textField({ identifier: "position", value: "", propertyName: "position" }),
    );

  const render = (): HTMLElement => {
    const body = resetBody(
      profileEditingRoot({ content: profileHeader() + fields() }),
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
      `[data-pe-field-preview][data-pe-for="profile-editing-1-${identifier}"]`,
      HTMLElement,
    );
  const button = (attribute: string, identifier: string): HTMLButtonElement =>
    select(
      root,
      `[${attribute}][data-pe-for="profile-editing-1-${identifier}"]`,
      HTMLButtonElement,
    );
  const activate = (identifier: string): HTMLButtonElement =>
    button("data-academic-persons-profile-editing-activate-btn", identifier);

  beforeEach(() => {
    fetch = installFetch();
    root = render();
  });

  it("swaps the preview for the editor and puts the caret in the field", () => {
    activate("firstName").click();

    assert.equal(editor("firstName").classList.contains("d-none"), false);
    assert.ok(preview("firstName").classList.contains("d-none"));
    assert.equal(activate("firstName").getAttribute("aria-expanded"), "true");
    assert.equal(document.activeElement, field("firstName"));
  });

  it("shows the three field actions with the editor and hides them again on close", () => {
    const actions = select(
      root,
      '[data-pe-field-actions][data-pe-for="profile-editing-1-firstName"]',
      HTMLElement,
    );
    assert.ok(actions.classList.contains("d-none"));

    activate("firstName").click();
    assert.equal(actions.classList.contains("d-none"), false);

    button("data-pe-cancel", "firstName").click();
    assert.ok(actions.classList.contains("d-none"));
  });

  it("undoes the typed value, closes the field and returns the focus to the button", () => {
    activate("firstName").click();
    field("firstName").value = "Augusta";

    button("data-pe-cancel", "firstName").click();

    assert.equal(field("firstName").value, "Ada");
    assert.ok(editor("firstName").classList.contains("d-none"));
    assert.equal(activate("firstName").getAttribute("aria-expanded"), "false");
    assert.equal(document.activeElement, activate("firstName"));
  });

  /**
   * Clear is not undo: it empties the field and leaves the editor open, so the
   * visitor can type a replacement or undo the clearing.
   */
  it("clears the field and keeps the editor open", () => {
    activate("firstName").click();

    button("data-pe-dismiss", "firstName").click();

    assert.equal(field("firstName").value, "");
    assert.equal(editor("firstName").classList.contains("d-none"), false);
  });

  it("posts only the fields that changed", async () => {
    activate("firstName").click();
    field("firstName").value = "Augusta";

    button("data-pe-save", "firstName").click();
    await settle(20);

    const call = fetch.calls[0];
    assert.equal(call?.url, endpoints.update);
    assert.equal(call?.method, "POST");
    assert.equal(call?.headers["X-Requested-With"], "XMLHttpRequest");
    assert.deepEqual(call?.body, {
      profile: 1,
      data: { firstName: "Augusta" },
    });
  });

  it("sends nothing at all when the value was not touched", async () => {
    activate("firstName").click();

    button("data-pe-save", "firstName").click();
    await settle(20);

    assert.equal(fetch.calls.length, 0);
    assert.ok(editor("firstName").classList.contains("d-none"));
    assert.equal(
      select(root, '[data-pe-status-toast="status"] .status-message', HTMLElement)
        .textContent,
      messages.unchanged,
    );
  });

  /**
   * The server normalises - it trims, it resolves, and it may answer with
   * something other than what was sent. What the visitor then sees, in the
   * field and in the preview, is the server's value.
   */
  it("takes the stored value from the response and shows it in the preview", async () => {
    fetch.respond({ success: true, data: { firstName: "Augusta" } });

    activate("firstName").click();
    field("firstName").value = "  augusta  ";
    button("data-pe-save", "firstName").click();
    await settle(20);

    assert.equal(field("firstName").value, "Augusta");
    assert.equal(
      select(preview("firstName"), "[data-pe-field-preview-content]", HTMLElement)
        .textContent,
      "Augusta",
    );
    assert.ok(editor("firstName").classList.contains("d-none"));
  });

  it("rewrites the profile name heading from the fields it is built of", async () => {
    fetch.respond({ success: true, data: { firstName: "Augusta" } });

    activate("firstName").click();
    field("firstName").value = "Augusta";
    button("data-pe-save", "firstName").click();
    await settle(20);

    assert.equal(
      select(root, "[data-pe-profile-name]", HTMLElement).textContent,
      "Augusta Lovelace",
    );
  });

  it("shows the empty label when the stored value is empty", async () => {
    fetch.respond({ success: true, data: { firstName: "" } });

    activate("firstName").click();
    field("firstName").value = "x";
    button("data-pe-save", "firstName").click();
    await settle(20);

    const content = select(
      preview("firstName"),
      "[data-pe-field-preview-content]",
      HTMLElement,
    );
    assert.equal(content.textContent, messages.empty);
    assert.ok(content.classList.contains("text-body-secondary"));
  });

  /**
   * A refused value is shown where it was entered: the field is marked
   * invalid, described by its own feedback element, and the editor is opened
   * again so the visitor can see what is being complained about.
   */
  it("marks the refused field and shows the server's message beside it", async () => {
    fetch.respondWithError(
      {
        success: false,
        errors: { "profile.firstName": ["Must not be empty."] },
      },
      422,
    );

    activate("firstName").click();
    field("firstName").value = "";
    button("data-pe-save", "firstName").click();
    await settle(20);

    assert.equal(field("firstName").getAttribute("aria-invalid"), "true");
    assert.ok(field("firstName").classList.contains("is-invalid"));
    assert.equal(
      field("firstName").getAttribute("aria-describedby"),
      "profile-editing-1-firstName-error",
    );
    assert.equal(
      select(root, "#profile-editing-1-firstName-error", HTMLElement).textContent,
      "Must not be empty.",
    );
    assert.equal(editor("firstName").classList.contains("d-none"), false);
    assert.equal(
      select(root, '[data-pe-status-toast="status"] .status-message', HTMLElement)
        .textContent,
      messages.validation,
    );
  });

  it("clears an earlier validation message on the next save", async () => {
    fetch.respondWithError(
      { success: false, errors: { "profile.firstName": ["Must not be empty."] } },
      422,
    );
    activate("firstName").click();
    field("firstName").value = "";
    button("data-pe-save", "firstName").click();
    await settle(20);

    fetch.respond({ success: true, data: { firstName: "Ada" } });
    field("firstName").value = "Ada Augusta";
    button("data-pe-save", "firstName").click();
    await settle(20);

    assert.equal(field("firstName").getAttribute("aria-invalid"), "false");
    assert.equal(field("firstName").classList.contains("is-invalid"), false);
    assert.equal(
      select(root, "#profile-editing-1-firstName-error", HTMLElement).textContent,
      "",
    );
  });

  it("reports a refusal that names no field in the assertive region", async () => {
    fetch.respondWithError({ success: false, message: "Not your profile." }, 403);

    activate("firstName").click();
    field("firstName").value = "Augusta";
    button("data-pe-save", "firstName").click();
    await settle(20);

    assert.equal(
      select(root, '[data-pe-status-toast="alert"] .status-message', HTMLElement)
        .textContent,
      "Not your profile.",
    );
    assert.equal(root.getAttribute("aria-busy"), "false");
  });
});

/**
 * The visibility switch. It has no save button - it saves on change - so the
 * only thing that can put it back when the server refuses is the module, and
 * that is what is pinned here.
 */
describe("a field that saves on change", () => {
  let fetch: FetchDouble;
  let root: HTMLElement;
  let checkbox: HTMLInputElement;

  beforeEach(() => {
    fetch = installFetch();
    const body = resetBody(
      profileEditingRoot({
        content:
          profileHeader() +
          fieldsForm(
            checkboxField({ identifier: "publishedToWebsite", checked: false }),
          ),
      }),
    );
    root = select(body, "[data-academic-persons-profile-editing]", HTMLElement);
    initializeFieldEditing(root);
    checkbox = select(
      root,
      "#profile-editing-1-publishedToWebsite",
      HTMLInputElement,
    );
  });

  it("posts the new state as a boolean as soon as it is switched", async () => {
    fetch.respond({ success: true, data: { publishedToWebsite: true } });

    checkbox.checked = true;
    checkbox.dispatchEvent(new CustomEvent("change", { bubbles: true }));
    await settle(20);

    assert.deepEqual(fetch.calls[0]?.body, {
      profile: 1,
      data: { publishedToWebsite: true },
    });
  });

  it("shows the state as a word in the preview", async () => {
    fetch.respond({ success: true, data: { publishedToWebsite: true } });

    checkbox.checked = true;
    checkbox.dispatchEvent(new CustomEvent("change", { bubbles: true }));
    await settle(20);

    assert.equal(
      select(
        root,
        '[data-pe-field-preview][data-pe-for="profile-editing-1-publishedToWebsite"] [data-pe-field-preview-content]',
        HTMLElement,
      ).textContent,
      "Public",
    );
  });

  /**
   * Without this the switch shows "public" while the profile is not, and there
   * is no button to press that would reveal the difference.
   */
  it("switches back when the request fails", async () => {
    fetch.respondWithError({ success: false, message: "Refused." }, 403);

    checkbox.checked = true;
    checkbox.dispatchEvent(new CustomEvent("change", { bubbles: true }));
    await settle(20);

    assert.equal(checkbox.checked, false);
    assert.equal(
      select(root, '[data-pe-status-toast="alert"] .status-message', HTMLElement)
        .textContent,
      "Refused.",
    );
  });
});

/**
 * A field group - the name, the combined link - is several controls behind one
 * preview and one set of buttons. The preview is computed from the fields the
 * group names, in the mode it names.
 */
describe("editing a group of fields", () => {
  let fetch: FetchDouble;
  let root: HTMLElement;

  const render = (displayMode: "join" | "first", values: string[]): HTMLElement => {
    const body = resetBody(
      profileEditingRoot({
        content:
          profileHeader() +
          fieldsForm(
            fieldGroup({
              identifier: "name",
              displayMode,
              fields: [
                { identifier: "title", value: values[0] },
                { identifier: "middleName", value: values[1] },
              ],
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

  const groupPreview = (): HTMLElement =>
    select(root, "[data-pe-group-preview-content]", HTMLElement);
  const groupEditor = (): HTMLElement =>
    select(root, "[data-pe-group-editor]", HTMLElement);

  beforeEach(() => {
    fetch = installFetch();
  });

  it("joins the values of its display fields into the preview", () => {
    root = render("join", ["Dr.", "Augusta"]);

    assert.equal(groupPreview().textContent, "Dr. Augusta");
  });

  it("shows only the first non-empty value in the first mode", () => {
    root = render("first", ["", "Augusta"]);

    assert.equal(groupPreview().textContent, "Augusta");
  });

  it("falls back to the empty label when no field has a value", () => {
    root = render("join", ["", ""]);

    assert.equal(groupPreview().textContent, messages.empty);
    assert.ok(groupPreview().classList.contains("text-body-secondary"));
  });

  it("opens every control of the group and focuses the first one", () => {
    root = render("join", ["Dr.", "Augusta"]);

    select(root, "[data-pe-group-edit]", HTMLButtonElement).click();

    assert.equal(groupEditor().classList.contains("d-none"), false);
    assert.ok(select(root, "[data-pe-group-preview]", HTMLElement).classList.contains("d-none"));
    assert.equal(
      select(root, "[data-pe-group-edit]", HTMLButtonElement).getAttribute("aria-expanded"),
      "true",
    );
    assert.equal(document.activeElement, select(root, "#profile-editing-1-title", HTMLInputElement));
  });

  it("posts every changed control of the group in one request", async () => {
    root = render("join", ["Dr.", "Augusta"]);
    fetch.respond({ success: true, data: {} });

    select(root, "[data-pe-group-edit]", HTMLButtonElement).click();
    select(root, "#profile-editing-1-title", HTMLInputElement).value = "Prof.";
    select(root, "#profile-editing-1-middleName", HTMLInputElement).value = "Ada";
    select(root, "[data-pe-group-save]", HTMLButtonElement).click();
    await settle(20);

    assert.deepEqual(fetch.calls[0]?.body, {
      profile: 1,
      data: { title: "Prof.", middleName: "Ada" },
    });
  });

  /**
   * Only what changed travels: the group is saved as a whole, but a field the
   * visitor did not touch must not be written back - it would overwrite what
   * somebody else stored in the meantime.
   */
  it("leaves the untouched controls of the group out of the request", async () => {
    root = render("join", ["Dr.", "Augusta"]);
    fetch.respond({ success: true, data: {} });

    select(root, "[data-pe-group-edit]", HTMLButtonElement).click();
    select(root, "#profile-editing-1-middleName", HTMLInputElement).value = "Ada";
    select(root, "[data-pe-group-save]", HTMLButtonElement).click();
    await settle(20);

    assert.deepEqual(fetch.calls[0]?.body, {
      profile: 1,
      data: { middleName: "Ada" },
    });
  });

  it("undoes every control of the group, closes it and updates the preview", () => {
    root = render("join", ["Dr.", "Augusta"]);

    select(root, "[data-pe-group-edit]", HTMLButtonElement).click();
    select(root, "#profile-editing-1-title", HTMLInputElement).value = "Prof.";
    select(root, "[data-pe-group-cancel]", HTMLButtonElement).click();

    assert.equal(
      select(root, "#profile-editing-1-title", HTMLInputElement).value,
      "Dr.",
    );
    assert.ok(groupEditor().classList.contains("d-none"));
    assert.equal(groupPreview().textContent, "Dr. Augusta");
  });

  it("clears every control of the group and keeps it open", () => {
    root = render("join", ["Dr.", "Augusta"]);

    select(root, "[data-pe-group-edit]", HTMLButtonElement).click();
    select(root, "[data-pe-group-dismiss]", HTMLButtonElement).click();

    assert.equal(select(root, "#profile-editing-1-title", HTMLInputElement).value, "");
    assert.equal(select(root, "#profile-editing-1-middleName", HTMLInputElement).value, "");
    assert.equal(groupEditor().classList.contains("d-none"), false);
  });
});

/**
 * At most one field editor is open at a time.
 *
 * A second open editor is not a second draft: it is a value the visitor cannot
 * see beside the preview it contradicts, and the next save of a group or of the
 * whole form would post it as a change nobody looked at again. Opening any
 * editor therefore discards the one that is open - the value goes back to the
 * stored one, its validation message is cleared and it closes - and so does
 * entering full form editing, which `full-form-editing.test.ts` pins.
 */
describe("only one field editor at a time", () => {
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
              fieldGroup({
                identifier: "name",
                fields: [
                  { identifier: "title", value: "Dr." },
                  { identifier: "middleName", value: "Augusta" },
                ],
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

  const field = (identifier: string): HTMLInputElement =>
    select(root, `#profile-editing-1-${identifier}`, HTMLInputElement);
  const editor = (identifier: string): HTMLElement =>
    select(root, `#profile-editing-1-${identifier}-editor`, HTMLElement);
  const activate = (identifier: string): HTMLButtonElement =>
    select(
      root,
      `[data-academic-persons-profile-editing-activate-btn][data-pe-for="profile-editing-1-${identifier}"]`,
      HTMLButtonElement,
    );
  const save = (identifier: string): HTMLButtonElement =>
    select(
      root,
      `[data-pe-save][data-pe-for="profile-editing-1-${identifier}"]`,
      HTMLButtonElement,
    );
  const button = (attribute: string, identifier: string): HTMLButtonElement =>
    select(
      root,
      `[${attribute}][data-pe-for="profile-editing-1-${identifier}"]`,
      HTMLButtonElement,
    );
  const groupButton = (attribute: string): HTMLButtonElement =>
    select(root, `[${attribute}]`, HTMLButtonElement);
  const groupEdit = (): HTMLButtonElement => groupButton("data-pe-group-edit");
  const groupEditor = (): HTMLElement =>
    select(root, "[data-pe-group-editor]", HTMLElement);
  const groupPreview = (): HTMLElement =>
    select(root, "[data-pe-group-preview-content]", HTMLElement);
  const statusMessage = (): string =>
    select(
      root,
      '[data-pe-status-toast="status"] .status-message',
      HTMLElement,
    ).textContent ?? "";
  const dialog = (): HTMLDialogElement | null =>
    root.querySelector<HTMLDialogElement>("[data-pe-unsaved-changes]");
  /** Answers the dialog and waits for the transition it gates. */
  const choose = async (choice: "save" | "discard" | "cancel"): Promise<void> => {
    select(root, `[data-pe-unsaved-choice="${choice}"]`, HTMLButtonElement).click();
    await settle(20);
  };

  beforeEach(() => {
    fetch = installFetch();
    // The Bootstrap double of `full-form-editing.test.ts`, for the same
    // reason: the text of a region can be read after the last write, never how
    // often it was written, and "said nothing at all" is exactly what half of
    // these cases are about.
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

  /**
   * A field that was opened and left alone is closed on the spot, silently,
   * as its own undo would close it: there is nothing in it to ask about.
   */
  it("closes the untouched field that was open when another pencil is pressed", () => {
    activate("firstName").click();

    activate("lastName").click();

    assert.equal(dialog(), null);
    assert.ok(editor("firstName").classList.contains("d-none"));
    assert.equal(activate("firstName").getAttribute("aria-expanded"), "false");
    assert.equal(editor("lastName").classList.contains("d-none"), false);
    assert.equal(document.activeElement, field("lastName"));
  });

  /**
   * A field with a changed value is not thrown away because a pencil was
   * pressed somewhere else. The visitor is asked, in the editor's own dialog
   * rather than a native prompt, and nothing moves until they answer: the
   * changed field stays open with its value, the other one stays closed.
   */
  it("asks before it closes a field that was changed", () => {
    activate("firstName").click();
    field("firstName").value = "Augusta";

    activate("lastName").click();

    const asked = dialog();
    assert.ok(asked !== null);
    assert.ok(asked.hasAttribute("open"));
    assert.equal(field("firstName").value, "Augusta");
    assert.equal(editor("firstName").classList.contains("d-none"), false);
    assert.ok(editor("lastName").classList.contains("d-none"));
    assert.equal(fetch.calls.length, 0);
  });

  it("discards the changed field and opens the other one when told to", async () => {
    activate("firstName").click();
    field("firstName").value = "Augusta";
    activate("lastName").click();

    await choose("discard");

    assert.equal(dialog(), null);
    assert.equal(field("firstName").value, "Ada");
    assert.ok(editor("firstName").classList.contains("d-none"));
    assert.equal(activate("firstName").getAttribute("aria-expanded"), "false");
    assert.equal(editor("lastName").classList.contains("d-none"), false);
    assert.equal(document.activeElement, field("lastName"));
  });

  it("keeps the changed field open and opens nothing when the dialog is cancelled", async () => {
    activate("firstName").click();
    field("firstName").value = "Augusta";
    activate("lastName").click();

    await choose("cancel");

    assert.equal(dialog(), null);
    assert.equal(field("firstName").value, "Augusta");
    assert.equal(editor("firstName").classList.contains("d-none"), false);
    assert.ok(editor("lastName").classList.contains("d-none"));
    assert.equal(fetch.calls.length, 0);
  });

  /**
   * "Save" stores exactly what the field's own save button would have stored,
   * and only then opens the other editor - the switch waits for the answer.
   */
  it("saves the changed field first and then opens the other one when told to", async () => {
    fetch.respond({ success: true, data: { firstName: "Augusta" } });
    activate("firstName").click();
    field("firstName").value = "Augusta";
    activate("lastName").click();

    await choose("save");

    assert.equal(fetch.calls.length, 1);
    assert.deepEqual(fetch.calls[0]?.body, {
      profile: 1,
      data: { firstName: "Augusta" },
    });
    assert.equal(field("firstName").value, "Augusta");
    assert.ok(editor("firstName").classList.contains("d-none"));
    assert.equal(editor("lastName").classList.contains("d-none"), false);
  });

  /**
   * A save the server refuses keeps the visitor where the refusal is: the
   * field stays open, marked, with its message, and the other editor does not
   * open - opening it would put the refused value out of sight.
   */
  it("stays in the refused field when the save it chose is refused", async () => {
    fetch.respondWithError(
      { success: false, errors: { "profile.firstName": ["Must not be empty."] } },
      422,
    );
    activate("firstName").click();
    field("firstName").value = "";
    activate("lastName").click();

    await choose("save");

    assert.equal(fetch.calls.length, 1);
    assert.equal(editor("firstName").classList.contains("d-none"), false);
    assert.ok(field("firstName").classList.contains("is-invalid"));
    assert.ok(editor("lastName").classList.contains("d-none"));
  });

  /**
   * The second press of the same pencil is not a transition. Treating it as one
   * would throw away everything typed since the first press, which is the one
   * thing the visitor certainly did not ask for.
   */
  it("keeps what was typed when the same pencil is pressed again", () => {
    activate("firstName").click();
    field("firstName").value = "Augusta";

    activate("firstName").click();

    assert.equal(field("firstName").value, "Augusta");
    assert.equal(editor("firstName").classList.contains("d-none"), false);
  });

  it("discards the changed group that was open when a pencil is pressed and it is told to", async () => {
    groupEdit().click();
    field("title").value = "Prof.";

    activate("firstName").click();
    await choose("discard");

    assert.equal(field("title").value, "Dr.");
    assert.ok(groupEditor().classList.contains("d-none"));
    assert.equal(groupPreview().textContent, "Dr. Augusta");
    assert.equal(editor("firstName").classList.contains("d-none"), false);
  });

  it("discards the changed field that was open when a group is opened and it is told to", async () => {
    activate("firstName").click();
    field("firstName").value = "Augusta";

    groupEdit().click();
    await choose("discard");

    assert.equal(field("firstName").value, "Ada");
    assert.ok(editor("firstName").classList.contains("d-none"));
    assert.equal(groupEditor().classList.contains("d-none"), false);
    assert.equal(document.activeElement, field("title"));
  });

  it("keeps what was typed when the group's own pencil is pressed again", () => {
    groupEdit().click();
    field("title").value = "Prof.";

    groupEdit().click();

    assert.equal(field("title").value, "Prof.");
    assert.equal(groupEditor().classList.contains("d-none"), false);
  });

  /**
   * A refused field is left open, marked and described by its own message. It
   * is closed here without being saved, so the mark has to go with it: it would
   * otherwise describe a value that is no longer in the control.
   */
  it("clears the validation message of the field it discards", async () => {
    fetch.respondWithError(
      { success: false, errors: { "profile.firstName": ["Must not be empty."] } },
      422,
    );
    activate("firstName").click();
    field("firstName").value = "";
    save("firstName").click();
    await settle(20);
    assert.ok(field("firstName").classList.contains("is-invalid"));

    activate("lastName").click();
    await choose("discard");

    assert.equal(field("firstName").value, "Ada");
    assert.equal(field("firstName").classList.contains("is-invalid"), false);
    assert.equal(field("firstName").getAttribute("aria-invalid"), "false");
    assert.equal(
      select(root, "#profile-editing-1-firstName-error", HTMLElement).textContent,
      "",
    );
  });

  /**
   * The response of the save is about to write the stored value and the
   * baseline back for the field it saved, so a discard under it is undone
   * again a moment later: the control moves, and moves back, with nothing
   * saying why. That is the whole harm as the endpoint stands -
   * `getNormalizedData()` echoes every property it was sent, so the baseline
   * that is written back is the stored one. The refusal is also what keeps it
   * to that if an endpoint ever stops echoing, because the response would then
   * write the discarded value into the baseline and the next save would not
   * resend it. Either way the save is allowed to finish and the pencil does
   * nothing - not even open the other field, so that nothing on screen
   * suggests it did.
   */
  it("does nothing while a single field is on its way to the server", async () => {
    const pending = fetch.respondLater();
    activate("firstName").click();
    field("firstName").value = "Augusta";
    save("firstName").click();
    await settle(20);

    activate("lastName").click();

    assert.equal(field("firstName").value, "Augusta");
    assert.equal(editor("firstName").classList.contains("d-none"), false);
    assert.ok(editor("lastName").classList.contains("d-none"));

    pending.settle({ success: true, data: { firstName: "Augusta" } });
    await settle(20);

    assert.equal(fetch.calls.length, 1);
    assert.equal(field("firstName").value, "Augusta");
    assert.ok(editor("firstName").classList.contains("d-none"));
  });

  /**
   * The visitor chose the discard in the dialog, but the row that collapses
   * is not the one they are looking at: the polite region says what happened
   * to the value, for anyone who is not looking at it either.
   */
  it("announces the value it threw away", async () => {
    activate("firstName").click();
    field("firstName").value = "Augusta";
    announcements = [];

    activate("lastName").click();
    await choose("discard");

    assert.deepEqual(announcements, [messages.discarded]);
    assert.equal(statusMessage(), messages.discarded);
  });

  /**
   * And only then. An editor that is closed with the value it was opened with
   * has thrown nothing away, and announcing a discard there would teach the
   * visitor to ignore the message that matters.
   */
  it("says nothing when the editor it closed had nothing typed in it", () => {
    activate("firstName").click();
    announcements = [];

    activate("lastName").click();

    assert.deepEqual(announcements, []);
    assert.ok(editor("firstName").classList.contains("d-none"));
    assert.equal(editor("lastName").classList.contains("d-none"), false);
  });

  /**
   * A refused pencil is a control that does nothing, which is what a broken
   * control looks like. `aria-busy` is no answer to that: it is written inside
   * the save, after the rich text editors have been awaited and only for a
   * save that reaches the endpoint, so the refusal starts before it is set -
   * and it asks a screen reader to keep quiet rather than telling anyone
   * anything.
   */
  it("says why the pencil did nothing while a save is on its way", async () => {
    const pending = fetch.respondLater();
    activate("firstName").click();
    field("firstName").value = "Augusta";
    save("firstName").click();
    await settle(20);
    announcements = [];

    activate("lastName").click();

    assert.deepEqual(announcements, [messages.saveInProgress]);
    assert.equal(statusMessage(), messages.saveInProgress);

    pending.settle({ success: true, data: { firstName: "Augusta" } });
    await settle(20);
  });

  /**
   * Clear and undo move the control the response is about to write into, so
   * pressed under a save they are taken back a moment later and the visitor is
   * left with a control that ignored them. They wait for the same answer the
   * pencil waits for.
   */
  it("refuses the clear and the undo beside a field while its save is on its way", async () => {
    const pending = fetch.respondLater();
    activate("firstName").click();
    field("firstName").value = "Augusta";
    save("firstName").click();
    await settle(20);
    announcements = [];

    button("data-pe-dismiss", "firstName").click();
    button("data-pe-cancel", "firstName").click();

    assert.equal(field("firstName").value, "Augusta");
    assert.equal(editor("firstName").classList.contains("d-none"), false);
    assert.deepEqual(announcements, [
      messages.saveInProgress,
      messages.saveInProgress,
    ]);

    pending.settle({ success: true, data: { firstName: "Augusta" } });
    await settle(20);

    assert.equal(field("firstName").value, "Augusta");
    assert.ok(editor("firstName").classList.contains("d-none"));
  });

  it("refuses the clear and the undo of a group while its save is on its way", async () => {
    const pending = fetch.respondLater();
    groupEdit().click();
    field("title").value = "Prof.";
    groupButton("data-pe-group-save").click();
    await settle(20);
    announcements = [];

    groupButton("data-pe-group-dismiss").click();
    groupButton("data-pe-group-cancel").click();

    assert.equal(field("title").value, "Prof.");
    assert.equal(field("middleName").value, "Augusta");
    assert.equal(groupEditor().classList.contains("d-none"), false);
    assert.deepEqual(announcements, [
      messages.saveInProgress,
      messages.saveInProgress,
    ]);

    pending.settle({ success: true, data: { title: "Prof." } });
    await settle(20);

    assert.equal(field("title").value, "Prof.");
    assert.ok(groupEditor().classList.contains("d-none"));
  });
});

/**
 * The rich text field on the discard path.
 *
 * CKEditor normalises what it is handed - bare text becomes a paragraph - so
 * the value the template rendered and the value the editor gives back differ
 * for the same content. The baseline therefore has to be taken from the editor
 * before it is written into one, or the discard puts the rendered source into
 * a live editor and the next save posts it as a change nobody made. `undo`
 * corrects it for that reason; discarding writes the baseline into an editor
 * exactly as undo does, and it now happens whenever another editor is opened.
 */
describe("discarding a rich text field", () => {
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
  const activate = (identifier: string): HTMLButtonElement =>
    select(
      root,
      `[data-academic-persons-profile-editing-activate-btn][data-pe-for="profile-editing-1-${identifier}"]`,
      HTMLButtonElement,
    );

  beforeEach(() => {
    fetch = installFetch();
    root = render();
  });

  it("hands the editor the normalised value, so the next save has nothing to send", async () => {
    activate("description").click();
    await settle(20);

    activate("firstName").click();
    activate("description").click();
    await settle(20);
    select(
      root,
      '[data-pe-save][data-pe-for="profile-editing-1-description"]',
      HTMLButtonElement,
    ).click();
    await settle(20);

    assert.equal(description().value, "<p>Ada wrote notes</p>");
    assert.equal(fetch.calls.length, 0);
  });
});
