import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { resetBody } from "../../../../../Build/tests/dom.mjs";
import {
  profileEditingElementName,
  ProfileEditingRootElement,
  registerProfileEditingElement,
} from "@fgtclb/academic-persons-edit/frontend/profile/elements/root.js";
import { profileEditingElement, select } from "./Fixtures/profile-editing.ts";

/**
 * Both orders in which a custom element and its markup can meet.
 *
 * The first is the one where the document has been parsed before this module
 * is evaluated, so the editor's markup is already there. It is the one an
 * element that only listened for its own construction would silently miss -
 * the registry upgrades what is already there, and it does so synchronously
 * inside `customElements.define()`.
 *
 * The other order is the one a test file naturally has, and the one a page that
 * renders an editor over ajax has: the element is defined and the markup
 * arrives afterwards.
 *
 * `<f:asset.module>` renders `<script type="module" async>`, which is not
 * deferred: the module may also run while the document is still being parsed.
 * That third order is `profile-editing-element-parsing.test.ts`.
 *
 * A file of its own, and not a case in
 * `profile-editing-element.test.ts`: an element cannot be undefined once it is
 * defined, and node runs every test file in a process of its own - so a
 * registry that has not yet seen the element is a file, not a `beforeEach`.
 * Nothing before the first test may register it, which is why there is none.
 *
 * ## What this file cannot prove, and what follows from it
 *
 * jsdom upgrades a custom element when it is inserted into the document, in
 * *both* orders - it does not construct one while it parses, the way a browser's
 * streaming parser does. So the children of the element exist by the time its
 * constructor runs here, and reading the editor root in the constructor instead
 * of in `connectedCallback()` keeps this file green while it would find nothing
 * in a browser. That the root is never read in the constructor is a
 * requirement of the custom element specification - a constructor must not
 * inspect its children - and not something a jsdom test discriminates. Do not
 * move it on the strength of a green run.
 */
describe("upgrading the profile editing element", () => {
  it("starts an editor that was in the document before the module loaded", () => {
    const body = resetBody(profileEditingElement());
    const element = select(body, profileEditingElementName, HTMLElement);
    assert.equal(element instanceof ProfileEditingRootElement, false);
    assert.equal((element as { context?: unknown }).context, undefined);

    registerProfileEditingElement();

    assert.ok(element instanceof ProfileEditingRootElement);
    assert.equal(element.context?.profileUid, 1);
  });

  it("starts an editor that arrives after the module loaded", () => {
    const body = resetBody("");

    body.innerHTML = profileEditingElement({ profileUid: 4 });

    const element = select(body, profileEditingElementName, HTMLElement);
    assert.ok(element instanceof ProfileEditingRootElement);
    assert.equal(element.context?.profileUid, 4);
  });
});
