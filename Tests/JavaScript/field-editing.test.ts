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
