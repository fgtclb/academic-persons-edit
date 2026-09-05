import assert from "node:assert/strict";
import { beforeEach, describe, it } from "node:test";
import {
  createDragEvent,
  installDom,
  isObjectUrlAlive,
  nextFrame,
  resetBody,
  setBoundingRect,
  setClientSize,
  settle,
} from "../../../../../../Build/tests/dom.mjs";

/**
 * The harness's own tests: that the window "register.mjs" installs is the one
 * the shipped modules are written against.
 *
 * They live below an extension because "node --test" is pointed at
 * "packages/<vendor>/<package>/Tests/JavaScript/", and "Build/tests/" is not
 * an extension.
 * "academic-persons-edit" is the extension the harness was built for.
 *
 * Every assertion here stands for a browser behaviour a shipped module relies
 * on without importing anything. Where jsdom does not provide it, "dom.mjs"
 * models it - and a model that is wrong is worse than an absent one, because it
 * turns a defect into a green test.
 */
describe("the installed DOM", () => {
  beforeEach(() => {
    resetBody();
  });

  it("gives a module a document while it is being evaluated", () => {
    // The proof is the import above: "register.mjs" installs the window before
    // node loads the first test file, and a module that reached for "document"
    // at evaluation time would already have failed.
    assert.equal(typeof document, "object");
    assert.equal(document.body.tagName, "BODY");
  });

  it("gives the window an origin, which a same-origin request needs", () => {
    assert.equal(window.location.href, "https://example.test/profile");
  });

  it("puts the browser constructors a module reaches for on globalThis", () => {
    for (const name of [
      "CustomEvent",
      "DocumentFragment",
      "Element",
      "File",
      "FormData",
      "HTMLButtonElement",
      "HTMLInputElement",
      "HTMLTemplateElement",
      "HTMLTextAreaElement",
      "Node",
      "customElements",
    ]) {
      assert.notEqual(
        (globalThis as unknown as Record<string, unknown>)[name],
        undefined,
        `globalThis.${name} is missing`,
      );
    }
  });

  it("keeps the window as the receiver of the methods it copies", () => {
    // A method copied off the window loses its receiver and throws "Illegal
    // invocation" when it is called bare - which is how a module that registers
    // a "pagehide" listener on "globalThis" fails.
    let seen = 0;
    const listener = (): void => {
      seen += 1;
    };
    globalThis.addEventListener("harness-probe", listener);
    globalThis.dispatchEvent(new CustomEvent("harness-probe"));
    globalThis.removeEventListener("harness-probe", listener);
    globalThis.dispatchEvent(new CustomEvent("harness-probe"));

    assert.equal(seen, 1);
    assert.doesNotThrow(() => getComputedStyle(document.body));
  });

  it("installs one window per process, not one per call", () => {
    resetBody('<p id="kept"></p>');
    installDom();

    assert.notEqual(document.getElementById("kept"), null);
  });

  it("replaces the body on every reset, so no test inherits markup", () => {
    const body = resetBody('<p id="first"></p>');
    assert.notEqual(body.querySelector("#first"), null);

    resetBody('<p id="second"></p>');
    assert.equal(document.getElementById("first"), null);
    assert.notEqual(document.getElementById("second"), null);
  });
});

describe("CSS.escape", () => {
  /**
   * The CSSOM algorithm, not an approximation: the ids this repository renders
   * are "profile-editing-{uid}-{property}", and a property path carries dots.
   * An escape that is merely good enough for those would pass a naive test and
   * fail the browser on the next id shape.
   *
   * https://drafts.csswg.org/cssom/#the-css.escape()-method
   */
  it("follows the specification, case by case", () => {
    assert.equal(CSS.escape("-"), "\\-");
    assert.equal(CSS.escape("\u0000"), "\uFFFD");
    assert.equal(CSS.escape("\u0001"), "\\1 ");
    assert.equal(CSS.escape("\u007F"), "\\7f ");
    assert.equal(CSS.escape("0a"), "\\30 a");
    assert.equal(CSS.escape("-0a"), "-\\30 a");
    assert.equal(CSS.escape("a.b"), "a\\.b");
    assert.equal(CSS.escape("a b"), "a\\ b");
    assert.equal(CSS.escape("-a"), "-a");
    assert.equal(CSS.escape("héllo"), "héllo");
  });

  it("produces a selector that finds the element it was built from", () => {
    const identifier = "profile-editing-1-about.me";
    resetBody(`<textarea id="${identifier}"></textarea>`);

    assert.notEqual(document.querySelector(`#${CSS.escape(identifier)}`), null);
  });
});

describe("what jsdom does not provide", () => {
  beforeEach(() => {
    resetBody();
  });

  it("answers a reduced-motion query the way a browser does by default", () => {
    const query = matchMedia("(prefers-reduced-motion: reduce)");

    assert.equal(query.matches, false);
    assert.equal(query.media, "(prefers-reduced-motion: reduce)");
  });

  it("records the alignment scrollIntoView was called with", () => {
    const body = resetBody('<div id="target"></div><div id="other"></div>');
    const target = body.querySelector("#target");
    const other = body.querySelector("#other");
    assert.ok(target !== null && other !== null);

    target.scrollIntoView({ block: "center" });
    other.scrollIntoView();

    assert.equal(target.getAttribute("data-test-scrolled-into-view"), "center");
    assert.equal(other.getAttribute("data-test-scrolled-into-view"), "start");
  });

  it("registers an object url until it is revoked", () => {
    const url = URL.createObjectURL(new Blob(["x"], { type: "image/png" }));

    assert.match(url, /^blob:/);
    assert.equal(isObjectUrlAlive(url), true);

    URL.revokeObjectURL(url);

    assert.equal(isObjectUrlAlive(url), false);
  });

  it("hands over a drag event with a recording data transfer", () => {
    const event = createDragEvent("dragover", { clientX: 12, clientY: 34 });
    event.dataTransfer.setData("text/plain", "42");

    assert.equal(event.type, "dragover");
    assert.equal(event.clientX, 12);
    assert.equal(event.clientY, 34);
    assert.equal(event.dataTransfer.getData("text/plain"), "42");
    assert.equal(event.dataTransfer.getData("text/html"), "");
  });

  it("places a rectangle on an element that would otherwise report zero", () => {
    const body = resetBody('<div id="box"></div>');
    const box = body.querySelector("#box");
    assert.ok(box !== null);
    assert.equal(box.getBoundingClientRect().height, 0);

    setBoundingRect(box, { top: 10, left: 20, width: 100, height: 50 });

    assert.deepEqual(
      {
        top: box.getBoundingClientRect().top,
        bottom: box.getBoundingClientRect().bottom,
        right: box.getBoundingClientRect().right,
      },
      { top: 10, bottom: 60, right: 120 },
    );
  });

  it("shadows the client size, which is a getter on the prototype", () => {
    const body = resetBody('<div id="stage"></div>');
    const stage = body.querySelector("#stage");
    assert.ok(stage !== null);
    assert.equal(stage.clientWidth, 0);

    setClientSize(stage, { width: 640, height: 480 });

    assert.equal(stage.clientWidth, 640);
    assert.equal(stage.clientHeight, 480);
  });
});

describe("waiting", () => {
  it("settles a promise chain that was never handed back", async () => {
    let reached = false;
    void Promise.resolve()
      .then(() => Promise.resolve())
      .then(() => {
        reached = true;
      });

    assert.equal(reached, false);

    await settle();

    assert.equal(reached, true);
  });

  it("waits for an animation frame, which settle() never reaches", async () => {
    let frames = 0;
    requestAnimationFrame(() => {
      frames += 1;
    });

    await settle();
    assert.equal(frames, 0);

    await nextFrame();
    assert.equal(frames, 1);
  });
});
