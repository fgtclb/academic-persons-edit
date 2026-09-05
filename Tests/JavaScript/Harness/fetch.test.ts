import assert from "node:assert/strict";
import { afterEach, describe, it } from "node:test";
import { settle } from "../../../../../../Build/tests/dom.mjs";
import { installFetch } from "../../../../../../Build/tests/fetch.mjs";
import type { FetchDouble } from "../../../../../../Build/tests/fetch.mjs";

/**
 * The recording request double, exercised on its own so that a test which
 * *uses* it is asserting about the module under test rather than about the
 * harness underneath it.
 *
 * Node has a real "fetch", and every one of these would otherwise go to the
 * network.
 */
let double: FetchDouble | null = null;

const install = (): FetchDouble => {
  double = installFetch();

  return double;
};

afterEach(() => {
  double?.restore();
  double = null;
});

describe("the request double", () => {
  it("records the method, the url, the headers and the decoded body", async () => {
    const requests = install();
    requests.respond({ errors: {}, data: { title: "Doctor" } });

    const response = await fetch("/profile/1/update", {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/json", "X-Requested-With": "XMLHttpRequest" },
      body: JSON.stringify({ data: { title: "Doctor" } }),
    });

    assert.equal(response.ok, true);
    assert.deepEqual(await response.json(), { errors: {}, data: { title: "Doctor" } });

    const call = requests.lastCall();
    assert.equal(requests.calls.length, 1);
    assert.equal(call?.method, "POST");
    assert.equal(call?.url, "/profile/1/update");
    assert.equal(call?.credentials, "same-origin");
    // The guard every writing endpoint checks: a custom header cannot be set
    // cross origin without a preflight, so it is asserted and never assumed.
    assert.equal(call?.headers["X-Requested-With"], "XMLHttpRequest");
    assert.deepEqual(call?.body, { data: { title: "Doctor" } });
  });

  it("keeps a failing status a real response rather than a rejection", async () => {
    const requests = install();
    requests.respondWithError({ errors: { title: "Too long" } }, 422);

    const response = await fetch("/profile/1/update", { method: "POST" });

    assert.equal(response.ok, false);
    assert.equal(response.status, 422);
    assert.deepEqual(await response.json(), { errors: { title: "Too long" } });
  });

  it("rejects the decoding of a body that is not JSON, as a browser does", async () => {
    const requests = install();
    requests.respondWithText("<!doctype html><p>Session expired</p>");

    const response = await fetch("/profile/1/update", { method: "POST" });

    assert.equal(response.ok, true);
    await assert.rejects(() => response.json());
  });

  it("names the url of a request nobody prepared a response for", async () => {
    install();

    await assert.rejects(
      () => fetch("/profile/1/documents"),
      /No response was queued for the request to "\/profile\/1\/documents"/,
    );
  });

  it("holds a response open until the test settles it", async () => {
    const requests = install();
    const pending = requests.respondLater();

    let status = 0;
    const inFlight = fetch("/profile/1/update", { method: "POST" }).then((response) => {
      status = response.status;
    });

    // A queued response resolves in the same microtask as the call that takes
    // it, so this is what makes an overlap a real overlap.
    await settle();
    assert.equal(status, 0);

    pending.settle({ errors: {} });
    await inFlight;

    assert.equal(status, 200);
  });

  it("puts node's own fetch back", () => {
    const requests = install();
    const stubbed = globalThis.fetch;

    requests.restore();
    double = null;

    assert.notEqual(globalThis.fetch, stubbed);
  });
});
