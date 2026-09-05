import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { resetBody, settle } from "../../../../../Build/tests/dom.mjs";
import {
  ProfileEditingElement,
  type ChangedProperties,
} from "@fgtclb/academic-persons-edit/frontend/profile/elements/base.js";

/**
 * The base class of every element of the editor, and the whole of what it
 * does.
 *
 * Every element here controls markup Fluid rendered and produces none, so
 * nothing below one may ever be written by something other than the element
 * itself - which is what the first two cases assert. The remaining five are
 * the update cycle `elements/base.ts` hand-writes in place of a framework: an
 * assignment schedules one update, several in a turn become one, the update is
 * deferred until the element is connected, an assignment that changes nothing
 * schedules nothing at all, and `updateComplete` settles whatever the update
 * did - `false` when the update queued the next one, and a rejection when a
 * callback threw. The document editor depends on every one of those, and
 * `documents.ts` awaits that promise inside the `try` of `openDocument()`:
 * one that never settles takes the error path and the teardown with it.
 */
const elementName = "academic-persons-edit-test-lifecycle";

class LifecycleProbe extends ProfileEditingElement<LifecycleProbe> {
  #heading = "";

  changes: string[][] = [];
  firstUpdates = 0;
  updates = 0;
  /** Thrown by the next `updated()`, once, and then forgotten. */
  failWith: Error | null = null;
  /** How many further `updated()` calls assign a property to themselves. */
  reentrant = 0;

  get heading(): string {
    return this.#heading;
  }

  set heading(value: string) {
    const previous = this.#heading;
    this.#heading = value;
    this.requestUpdate("heading", previous);
  }

  override firstUpdated(): void {
    this.firstUpdates += 1;
  }

  override updated(changed: ChangedProperties<LifecycleProbe>): void {
    this.updates += 1;
    this.changes.push(Array.from(changed.keys(), String));
    if (this.reentrant > 0) {
      this.reentrant -= 1;
      this.heading = `${this.#heading}!`;
    }
    const failure = this.failWith;
    this.failWith = null;
    if (failure !== null) {
      throw failure;
    }
  }
}

customElements.define(elementName, LifecycleProbe);

const mount = async (name: string): Promise<HTMLElement> => {
  const body = resetBody(
    `<${name}><span id="fluid">from Fluid</span></${name}>`,
  );
  const element = body.querySelector(name);
  if (!(element instanceof HTMLElement)) {
    throw new Error(`The test markup has no "${name}".`);
  }
  await settle(0);

  return element;
};

describe("the profile editing element base", () => {
  it("leaves the children it was handed exactly where they were", async () => {
    const element = (await mount(elementName)) as LifecycleProbe;
    const before = Array.from(element.childNodes);

    element.heading = "one";
    await element.updateComplete;
    element.heading = "two";
    await element.updateComplete;

    assert.deepEqual(Array.from(element.childNodes), before);
    // And nothing else is there either: no marker, no wrapper, no node an
    // update put there on its way past.
    assert.deepEqual(
      Array.from(element.childNodes, (node): string => node.nodeName),
      ["SPAN"],
    );
    assert.equal(element.querySelector("#fluid")?.textContent, "from Fluid");
  });

  it("opens no shadow root", async () => {
    const element = await mount(elementName);

    assert.equal(element.shadowRoot, null);
  });

  /**
   * What the base class is for. The document editor is the element that needs
   * all of it: several property writes of one `openDocument()` have to become
   * one DOM pass, and the changed-property map is what separates a structural
   * change from a value change.
   */
  it("collects several assignments of one turn into a single update", async () => {
    const element = (await mount(elementName)) as LifecycleProbe;
    const updatesAfterMount = element.updates;

    element.heading = "one";
    element.heading = "two";
    await element.updateComplete;

    assert.equal(element.firstUpdates, 1);
    assert.equal(element.updates, updatesAfterMount + 1);
    assert.deepEqual(element.changes.at(-1), ["heading"]);
    assert.equal(element.heading, "two");
    assert.equal(element.hasUpdated, true);
  });

  /**
   * The order every element of this extension is used in: created, assigned,
   * and only then put in the document. An update per assignment would write
   * markup an owner is still configuring.
   */
  it("defers the first update until the element is connected", async () => {
    const body = resetBody();
    const element = document.createElement(elementName) as LifecycleProbe;

    element.heading = "one";
    await settle(3);

    assert.equal(element.hasUpdated, false);
    assert.equal(element.updates, 0);

    body.append(element);
    await element.updateComplete;

    assert.equal(element.updates, 1);
    assert.deepEqual(element.changes.at(-1), ["heading"]);
  });

  it("schedules nothing for an assignment that changes nothing", async () => {
    const element = (await mount(elementName)) as LifecycleProbe;

    element.heading = "one";
    await element.updateComplete;
    const updates = element.updates;

    element.heading = "one";
    await element.updateComplete;

    assert.equal(element.updates, updates);
  });

  /**
   * The promise settles whatever the update did, and this is the case that
   * costs something when it does not: `documents.ts` awaits `updateComplete`
   * inside the `try` of `openDocument()`, so a promise that stays pending
   * takes the `catch` that shows the failure and the `finally` that clears
   * `pending` with it - the panel would sit there busy, empty and silent. An
   * override of `Partials/Profile/Prototypes.html` that drops a prototype
   * makes the element throw exactly here.
   */
  it("rejects updateComplete when a callback throws, and updates again after", async () => {
    const element = (await mount(elementName)) as LifecycleProbe;
    const failure = new Error("from updated()");
    element.failWith = failure;

    element.heading = "one";
    const outcome = await Promise.race([
      element.updateComplete.then(
        (): string => "resolved",
        (error: unknown): string => (error === failure ? "rejected" : "other"),
      ),
      settle(20).then((): string => "pending for ever"),
    ]);

    assert.equal(outcome, "rejected");

    // And the element is not stuck: the next assignment is a new promise of
    // its own, and it settles.
    element.heading = "two";

    assert.equal(await element.updateComplete, true);
    assert.equal(element.heading, "two");
  });

  /**
   * `true` means "the markup is what the properties say"; a callback that
   * assigns a property makes that false for one more pass, and a caller that
   * reads the DOM has to await again. Nothing in the editor assigns its own
   * property in `updated()` today - the document editor writes to the
   * *contacts* element - so this pins the answer before something does.
   */
  it("reports false when the update it settles queued the next one", async () => {
    const element = (await mount(elementName)) as LifecycleProbe;
    element.reentrant = 1;
    const updates = element.updates;

    element.heading = "one";

    assert.equal(await element.updateComplete, false);

    // The next pass has already run by the time this line does - a microtask
    // queued from inside the first one runs before the continuation waiting on
    // it - so what `false` buys is the honest answer about what the promise
    // covered, and a caller that has to see the second pass awaits again.
    assert.equal(await element.updateComplete, true);
    assert.equal(element.updates, updates + 2);
    assert.equal(element.heading, "one!");
  });
});
