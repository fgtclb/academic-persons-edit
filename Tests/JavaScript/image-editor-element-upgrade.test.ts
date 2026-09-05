import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { resetBody } from "../../../../../Build/tests/dom.mjs";
import { registerProfileEditingElement } from "@fgtclb/academic-persons-edit/frontend/profile/elements/root.js";
import {
  profileImageEditorElementName,
  ProfileImageEditorElement,
  registerProfileImageEditorElement,
} from "@fgtclb/academic-persons-edit/frontend/profile/elements/image-editor.js";
import {
  imageCard,
  imageEditor,
  profileEditingElement,
  select,
} from "./Fixtures/profile-editing.ts";

/**
 * Both orders in which the image editor element and its markup can meet, in a
 * process where the registry has not seen the element yet - which is why this
 * is a file and not a case of `image-editor-element.test.ts`: an element cannot
 * be undefined once it is defined. Nothing before the first test may register
 * it, so nothing does.
 *
 * The order that really happens is the first one. `<f:asset.module>` renders
 * `type="module"`, the document is parsed before the entry point runs, and both
 * elements are already in it when they are defined.
 *
 * Both elements are registered here, in the order the entry point registers
 * them. That order used to be a decision - the root mounted an application that
 * replaced the markup this element wraps, so an element upgraded before the
 * mount was upgraded on a copy about to be thrown away - and it stopped being
 * one when the runtime left. It is kept as the order the page really starts in.
 */
describe("upgrading the image editor element", () => {
  const editorMarkup = (profileUid = 1): string =>
    profileEditingElement({
      profileUid,
      content: imageCard({ profileUid }),
      target: imageEditor({ profileUid }),
    });

  it("starts an image editor that was in the document before the module loaded", () => {
    const body = resetBody(editorMarkup());
    const element = select(body, profileImageEditorElementName, HTMLElement);
    assert.equal(element instanceof ProfileImageEditorElement, false);

    registerProfileEditingElement();
    registerProfileImageEditorElement();

    assert.ok(element instanceof ProfileImageEditorElement);
    assert.equal(element.context?.profileUid, 1);
    assert.notEqual(element.controller, null);
  });

  it("starts an image editor that arrives after the module loaded", () => {
    const body = resetBody("");

    body.innerHTML = editorMarkup(4);

    const element = select(body, profileImageEditorElementName, HTMLElement);
    assert.ok(element instanceof ProfileImageEditorElement);
    assert.equal(element.context?.profileUid, 4);
    assert.notEqual(element.controller, null);
  });
});
