import assert from "node:assert/strict";
import { beforeEach, describe, it } from "node:test";
import { resetBody } from "../../../../../Build/tests/dom.mjs";
import { installFetch, type FetchDouble } from "../../../../../Build/tests/fetch.mjs";
import {
  requestJson,
  showStatus,
} from "@fgtclb/academic-persons-edit/frontend/profile/common.js";
import { profileEditingRoot, select } from "./Fixtures/profile-editing.ts";

/**
 * Every writing endpoint of the profile editor goes through `requestJson()`,
 * and everything below is a promise the editor makes to the server or to the
 * visitor:
 *
 * - the request identifies itself as an XMLHttpRequest, which is what the
 *   controller checks before it writes anything - a custom header cannot be set
 *   cross origin without a preflight, so it is the guard that keeps a foreign
 *   page from posting with the visitor's session;
 * - it carries the session (`credentials: "same-origin"`) and asks for JSON;
 * - a response is a failure unless it is 2xx *and* decodes to `success: true`,
 *   and the decoded body travels on the rejection so a caller can show the
 *   server's message and mark the fields the server refused;
 * - the wait cursor is reference counted, so two overlapping requests do not
 *   leave the page pointing at a request that finished.
 *
 * The status regions are here for the same reason: a failed request is the
 * only thing that writes the assertive one.
 */
describe("the request layer", () => {
  let fetch: FetchDouble;

  beforeEach(() => {
    resetBody("");
    fetch = installFetch();
  });

  it("identifies itself, asks for JSON and sends the session", async () => {
    fetch.respond({ success: true });

    await requestJson("https://example.test/profile/update");

    const call = fetch.calls[0];
    assert.equal(call?.url, "https://example.test/profile/update");
    assert.equal(call?.credentials, "same-origin");
    assert.equal(call?.headers.Accept, "application/json");
    assert.equal(call?.headers["X-Requested-With"], "XMLHttpRequest");
  });

  it("keeps the caller's method, body and content type", async () => {
    fetch.respond({ success: true });

    await requestJson("https://example.test/profile/update", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ profile: 1 }),
    });

    const call = fetch.calls[0];
    assert.equal(call?.method, "POST");
    assert.equal(call?.headers["Content-Type"], "application/json");
    assert.deepEqual(call?.body, { profile: 1 });
    // Still guarded: a caller supplying headers must not lose the guard.
    assert.equal(call?.headers["X-Requested-With"], "XMLHttpRequest");
  });

  it("resolves with the decoded result", async () => {
    fetch.respond({ success: true, data: { firstName: "Ada" } });

    const result = await requestJson("https://example.test/profile/update");

    assert.deepEqual(result, { success: true, data: { firstName: "Ada" } });
  });

  it("rejects a failing status and carries the decoded body on the error", async () => {
    fetch.respondWithError(
      { success: false, errors: { firstName: ["Too short."] } },
      422,
    );

    const error = await requestJson("https://example.test/profile/update").then(
      (): Error | null => null,
      (reason: Error): Error => reason,
    );

    assert.ok(error instanceof Error);
    assert.deepEqual((error as Error & { result: unknown }).result, {
      success: false,
      errors: { firstName: ["Too short."] },
    });
  });

  it("rejects a 200 that does not say it succeeded", async () => {
    fetch.respond({ success: false, message: "Not allowed." });

    const error = await requestJson("https://example.test/profile/update").then(
      (): Error | null => null,
      (reason: Error): Error => reason,
    );

    assert.ok(error instanceof Error);
    assert.deepEqual((error as Error & { result: unknown }).result, {
      success: false,
      message: "Not allowed.",
    });
  });

  /**
   * A login that expired answers the request with an HTML page, and a 200 at
   * that. Nothing may be read out of it - the rejection carries `null`.
   */
  it("rejects a response that is not JSON at all", async () => {
    fetch.respondWithText("<!doctype html><title>Login</title>");

    const error = await requestJson("https://example.test/profile/update").then(
      (): Error | null => null,
      (reason: Error): Error => reason,
    );

    assert.ok(error instanceof Error);
    assert.equal((error as Error & { result: unknown }).result, null);
  });

  it("shows the wait cursor while a request is open and restores it after", async () => {
    document.body.style.cursor = "auto";
    fetch.respond({ success: true });

    const pending = requestJson("https://example.test/profile/update");
    assert.equal(document.body.style.cursor, "wait");

    await pending;
    assert.equal(document.body.style.cursor, "auto");
  });

  /**
   * The reference count. Without it the first response clears the cursor while
   * the second request is still open, and the page stops saying it is busy.
   */
  it("keeps the wait cursor until the last of two overlapping requests is done", async () => {
    document.body.style.cursor = "auto";
    const slow = fetch.respondLater();
    fetch.respond({ success: true });

    const first = requestJson("https://example.test/profile/update");
    const second = requestJson("https://example.test/profile/skip-sync");

    await second;
    assert.equal(document.body.style.cursor, "wait");

    slow.settle({ success: true });
    await first;
    assert.equal(document.body.style.cursor, "auto");
  });

  it("restores the cursor when the request fails", async () => {
    document.body.style.cursor = "auto";
    fetch.respondWithError({ success: false }, 500);

    await requestJson("https://example.test/profile/update").catch(
      (): null => null,
    );

    assert.equal(document.body.style.cursor, "auto");
  });
});

/**
 * `showStatus()` picks the region by severity: a failure interrupts a screen
 * reader through `role="alert"`, everything else waits for a pause in
 * `role="status"`. The two exist side by side in the markup because a region's
 * politeness cannot be changed once it is in the accessibility tree.
 */
describe("the status regions", () => {
  const render = (): HTMLElement =>
    select(
      resetBody(profileEditingRoot()),
      "[data-academic-persons-profile-editing]",
      HTMLElement,
    );

  it("writes a failure into the assertive region and leaves the polite one empty", () => {
    const root = render();

    showStatus(root, "danger");

    // The two regions and their politeness are rendered by Fluid and asserted
    // where they are written; what is asserted here is what the module puts
    // into them.
    const alert = select(root, '[data-pe-status-toast="alert"]', HTMLElement);
    const status = select(root, '[data-pe-status-toast="status"]', HTMLElement);
    assert.equal(
      select(alert, ".status-title", HTMLElement).textContent,
      "Error",
    );
    assert.equal(
      select(alert, ".status-message", HTMLElement).textContent,
      "The change could not be saved.",
    );
    assert.ok(alert.classList.contains("bg-danger"));
    assert.equal(select(status, ".status-message", HTMLElement).textContent, "");
  });

  it("writes everything else into the polite region", () => {
    const root = render();

    showStatus(root, "success");

    const status = select(root, '[data-pe-status-toast="status"]', HTMLElement);
    assert.equal(
      select(status, ".status-title", HTMLElement).textContent,
      "Saved",
    );
    assert.ok(status.classList.contains("bg-success"));
    assert.equal(
      select(root, '[data-pe-status-toast="alert"] .status-message', HTMLElement)
        .textContent,
      "",
    );
  });

  it("shows the server's message in place of the generic one", () => {
    const root = render();

    showStatus(root, "danger", "The record is locked.");

    assert.equal(
      select(root, '[data-pe-status-toast="alert"] .status-message', HTMLElement)
        .textContent,
      "The record is locked.",
    );
  });

  /**
   * The severity classes are exclusive: a success after a failure must not
   * leave the region red.
   */
  it("replaces the severity of a region it writes twice", () => {
    const root = render();

    showStatus(root, "info");
    showStatus(root, "warning");

    const status = select(root, '[data-pe-status-toast="status"]', HTMLElement);
    assert.ok(status.classList.contains("bg-warning"));
    assert.equal(status.classList.contains("bg-info"), false);
  });
});
