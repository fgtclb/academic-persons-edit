import assert from "node:assert/strict";
import { beforeEach, describe, it } from "node:test";
import { resetBody, settle } from "../../../../../Build/tests/dom.mjs";
import { installFetch, type FetchDouble } from "../../../../../Build/tests/fetch.mjs";
import { createVisibility } from "@fgtclb/academic-persons-edit/frontend/profile/visibility.js";
import {
  endpoints,
  messages,
  profileEditingRoot,
  profileHeader,
  select,
} from "./Fixtures/profile-editing.ts";

/**
 * The owner's "Show my profile publicly" switch of `Partials/Profile/Header.html`.
 * It saves on change like the synchronisation switch, and what sets it apart is
 * the inversion: the switch is on while the profile is public, the endpoint takes
 * and answers the profile's `hidden` value. A switch that sends the value it shows
 * instead would hide a profile its owner just made public.
 */
describe("the visibility switch", () => {
  let fetch: FetchDouble;
  let root: HTMLElement;
  let checkbox: HTMLInputElement;
  let form: HTMLFormElement;
  let updateVisibility: (event: Event) => Promise<void>;

  beforeEach(() => {
    fetch = installFetch();
    const body = resetBody(
      profileEditingRoot({ content: profileHeader({ visible: true }) }),
    );
    root = select(body, "[data-academic-persons-profile-editing]", HTMLElement);
    checkbox = select(
      root,
      "[data-pe-visibility-checkbox]",
      HTMLInputElement,
    );
    form = select(root, "[data-pe-visibility-form]", HTMLFormElement);
    updateVisibility = createVisibility(root).updateVisibility;
  });

  const dispatch = (checked: boolean): Promise<void> => {
    checkbox.checked = checked;
    const event = new CustomEvent("change", { bubbles: true });
    Object.defineProperty(event, "target", { value: checkbox });

    return updateVisibility(event);
  };

  it("posts hidden for this profile when the owner switches it off", async () => {
    fetch.respond({ success: true, hidden: true });

    await dispatch(false);

    const call = fetch.calls[0];
    assert.equal(call?.url, endpoints.visibility);
    assert.equal(call?.method, "POST");
    assert.equal(call?.headers["X-Requested-With"], "XMLHttpRequest");
    assert.deepEqual(call?.body, { profile: 1, data: { hidden: true } });
    assert.equal(checkbox.checked, false);
  });

  it("posts not hidden when the owner switches it on", async () => {
    fetch.respond({ success: true, hidden: false });

    await dispatch(true);

    assert.deepEqual(fetch.calls[0]?.body, { profile: 1, data: { hidden: false } });
    assert.equal(checkbox.checked, true);
  });

  it("marks the form busy and the control unavailable while it saves", async () => {
    const slow = fetch.respondLater();

    const pending = dispatch(false);
    assert.equal(form.getAttribute("aria-busy"), "true");
    assert.equal(checkbox.disabled, true);

    slow.settle({ success: true, hidden: true });
    await pending;
    assert.equal(form.getAttribute("aria-busy"), "false");
    assert.equal(checkbox.disabled, false);
  });

  /**
   * The server has the last word: a profile the server keeps hidden shows the
   * switch off, whatever was clicked.
   */
  it("takes the stored value from the response", async () => {
    fetch.respond({ success: true, hidden: true });

    await dispatch(true);

    assert.equal(checkbox.checked, false);
    assert.equal(checkbox.classList.contains("is-invalid"), false);
    assert.equal(
      select(root, '[data-pe-status-toast="status"] [data-pe-status-title]', HTMLElement)
        .textContent,
      "Saved",
    );
  });

  it("puts the control back and marks it invalid when the request fails", async () => {
    fetch.respondWithError(
      { success: false, message: "The visibility of this profile cannot be changed here." },
      403,
    );

    await dispatch(false);

    assert.equal(checkbox.checked, true);
    assert.ok(checkbox.classList.contains("is-invalid"));
    assert.equal(
      select(root, '[data-pe-status-toast="alert"] [data-pe-status-message]', HTMLElement)
        .textContent,
      "The visibility of this profile cannot be changed here.",
    );
  });

  it("reverts to the last stored value, not to the previous click", async () => {
    fetch.respond({ success: true, hidden: true });
    await dispatch(false);

    fetch.respondWithError({ success: false }, 500);
    await dispatch(true);

    assert.equal(checkbox.checked, false);
  });

  it("saves when the control the visitor toggled reports a change", async () => {
    fetch.respond({ success: true, hidden: true });

    checkbox.checked = false;
    checkbox.dispatchEvent(new CustomEvent("change", { bubbles: true }));
    await settle();

    assert.equal(fetch.calls.length, 1);
    assert.deepEqual(fetch.calls[0]?.body, { profile: 1, data: { hidden: true } });
  });

  /**
   * The two switches share the header and both listen on the root. A change of
   * one must never reach the endpoint of the other.
   */
  it("leaves a change of the synchronisation switch alone", async () => {
    const syncCheckbox = select(
      root,
      "[data-pe-sync-checkbox]",
      HTMLInputElement,
    );

    syncCheckbox.checked = true;
    syncCheckbox.dispatchEvent(new CustomEvent("change", { bubbles: true }));
    await settle();

    assert.equal(fetch.calls.length, 0);
  });

  it("swallows a submission of the form it sits in", () => {
    const event = new CustomEvent("submit", { bubbles: true, cancelable: true });

    form.dispatchEvent(event);

    assert.equal(event.defaultPrevented, true);
    assert.equal(fetch.calls.length, 0);
  });

  it("refuses to send anything when the endpoint is not configured", async () => {
    root.removeAttribute("data-visibility-url");
    updateVisibility = createVisibility(root).updateVisibility;

    await dispatch(false);

    assert.equal(fetch.calls.length, 0);
    assert.equal(checkbox.checked, true);
    assert.equal(
      select(root, '[data-pe-status-toast="alert"] [data-pe-status-message]', HTMLElement)
        .textContent,
      messages.errorMessage,
    );
  });
});
