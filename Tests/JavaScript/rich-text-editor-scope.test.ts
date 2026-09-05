import assert from "node:assert/strict";
import { beforeEach, describe, it } from "node:test";
import { resetBody, settle } from "../../../../../Build/tests/dom.mjs";
import { createDocumentEditing } from "@fgtclb/academic-persons-edit/frontend/profile/documents.js";
import {
  destroyRichTextEditors,
  ensureRichTextEditor,
  getRichTextEditorValue,
} from "@fgtclb/academic-persons-edit/frontend/profile/rich-text.js";

/**
 * The profile editing page renders two kinds of rich text field below one
 * plugin root, and the difference between them is the whole point of these
 * tests.
 *
 * - The profile field of `Partials/Profile/Field/Control.html` is permanent.
 *   It exists for as long as the page does, and a visitor may have it open.
 * - The document editor is created on demand into a collapse target and is
 *   removed from the document again when it closes.
 *
 * Both carry `data-pe-rich-text`, so a selector query that starts at the plugin
 * root cannot tell them apart.
 */
const profileEditingMarkup = `
<div data-academic-persons-profile-editing data-profile-uid="1">
  <div data-pe-editor-container>
    <textarea id="profile-editing-1-description" data-pe-rich-text="true">Profile description</textarea>
  </div>
  <div id="profile-editing-1-document-42" data-pe-document-item-collapse-target>
    <div data-pe-document-view-container>
      <textarea id="profile-editing-1-document-42-note" data-pe-rich-text="true">Document note</textarea>
    </div>
  </div>
</div>`;

const select = <T extends Element>(
  scope: ParentNode,
  selector: string,
  type: new () => T,
): T => {
  const element = scope.querySelector(selector);
  if (!(element instanceof type)) {
    throw new Error(`The test markup has no "${selector}".`);
  }
  return element;
};

interface ProfileEditingFixture {
  root: HTMLElement;
  documentEditor: HTMLElement;
  profileField: HTMLTextAreaElement;
  documentField: HTMLTextAreaElement;
}

const renderProfileEditing = async (): Promise<ProfileEditingFixture> => {
  const body = resetBody(profileEditingMarkup);
  const root = select(body, "[data-academic-persons-profile-editing]", HTMLElement);
  const fixture: ProfileEditingFixture = {
    root,
    documentEditor: select(root, "[data-pe-document-item-collapse-target]", HTMLElement),
    profileField: select(root, "#profile-editing-1-description", HTMLTextAreaElement),
    documentField: select(root, "#profile-editing-1-document-42-note", HTMLTextAreaElement),
  };

  // Both editors exist before anything closes, which is the situation the
  // scoping has to survive.
  await ensureRichTextEditor(root, fixture.profileField);
  await ensureRichTextEditor(root, fixture.documentField);
  assert.equal(fixture.profileField.getAttribute("data-test-ckeditor"), "live");
  assert.equal(fixture.documentField.getAttribute("data-test-ckeditor"), "live");

  return fixture;
};

describe("destroying the rich text editors of a closing subtree", () => {
  let fixture: ProfileEditingFixture;

  beforeEach(async () => {
    fixture = await renderProfileEditing();
  });

  it("destroys the editors below the given scope and no other", async () => {
    await destroyRichTextEditors(fixture.documentEditor);

    assert.equal(fixture.documentField.getAttribute("data-test-ckeditor"), "destroyed");
    assert.equal(fixture.profileField.getAttribute("data-test-ckeditor"), "live");
  });

  it("forgets the destroyed editor and keeps the surviving one registered", async () => {
    await destroyRichTextEditors(fixture.documentEditor);

    assert.equal(getRichTextEditorValue(fixture.documentField), null);
    assert.equal(getRichTextEditorValue(fixture.profileField), "Profile description");
  });

  /**
   * The regression this harness was built for.
   *
   * The document editor closes through a transition hook that runs *after* the
   * leaving element has been removed from the document, and is handed that
   * element. Destroying "the editors below the plugin root" there therefore
   * destroys none of the closing view — its textareas are no longer below the
   * root — and every permanently rendered profile field editor instead, taking
   * a field the visitor still has open off screen with it.
   *
   * Nothing but a real DOM and a live editor instance can observe that, which
   * is why it reached a release unnoticed.
   */
  it("destroys the editors of a detached document editor, not those of the profile fields", async () => {
    const controller = createDocumentEditing(fixture.root);
    fixture.documentEditor.remove();

    controller.finishDocumentClose(fixture.documentEditor);
    await settle();

    assert.equal(fixture.documentField.getAttribute("data-test-ckeditor"), "destroyed");
    assert.equal(fixture.profileField.getAttribute("data-test-ckeditor"), "live");
    assert.equal(getRichTextEditorValue(fixture.profileField), "Profile description");
  });

  it("destroys each editor exactly once when the close path runs twice", async () => {
    await destroyRichTextEditors(fixture.documentEditor);
    await destroyRichTextEditors(fixture.documentEditor);

    assert.equal(fixture.documentField.getAttribute("data-test-ckeditor-destroys"), "1");
    assert.equal(fixture.profileField.getAttribute("data-test-ckeditor-destroys"), null);
  });
});
