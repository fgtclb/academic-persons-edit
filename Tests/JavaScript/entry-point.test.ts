import assert from "node:assert/strict";
import { describe, it } from "node:test";
import "@fgtclb/academic-persons-edit/frontend/profile.js";
import {
  profileContractContactsElementName,
  profileDocumentEditorElementName,
  profileEditingElementName,
  profileImageEditorElementName,
  profileRichTextElementName,
} from "@fgtclb/academic-persons-edit/frontend/profile/elements/names.js";

/**
 * `frontend/profile.js` is what `<f:asset.module>` loads, and all it does is
 * define the elements. A file of its own, because importing it *is* the subject:
 * the import registers, so no other test file may import it and this one must
 * do nothing before it.
 *
 * The point is not that the definitions work - each element has a file for that.
 * It is that an element that was added to this editor is also reachable from the
 * page, which is a step nothing else takes: every other test registers what it
 * is about itself.
 */
describe("the profile editing entry point", () => {
  it("defines every element of the editor", () => {
    assert.notEqual(customElements.get(profileEditingElementName), undefined);
    assert.notEqual(customElements.get(profileImageEditorElementName), undefined);
    assert.notEqual(customElements.get(profileDocumentEditorElementName), undefined);
    assert.notEqual(customElements.get(profileRichTextElementName), undefined);
    assert.notEqual(customElements.get(profileContractContactsElementName), undefined);
  });
});
