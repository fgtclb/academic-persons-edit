import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { settle } from "../../../../../Build/tests/dom.mjs";
import { profileDocumentEditorElementName } from "@fgtclb/academic-persons-edit/frontend/profile/elements/names.js";
import {
  ProfileDocumentEditorElement,
  registerProfileDocumentEditorElement,
} from "@fgtclb/academic-persons-edit/frontend/profile/elements/document-editor.js";
import { editingHost, select } from "./Fixtures/profile-editing.ts";

/**
 * Both orders in which a custom element and its markup can meet, for the one
 * element of this extension that is never in the markup to begin with.
 *
 * `<academic-persons-edit-document-editor>` is created by
 * `profile/documents.ts` when an editor opens, so the order that matters here
 * is the reverse of the root element's: the tag is in the document before the
 * definition only if a page was cached with an editor open, and the order the
 * editor really starts in is "defined first, created afterwards". Both are
 * asserted, because the element carries state that is assigned *before* it is
 * connected - and an element that is upgraded rather than constructed receives
 * those assignments as plain own properties that shadow the accessors on the
 * prototype unless the base class takes them over on upgrade.
 *
 * A file of its own for the same reason as the root element's: an element
 * cannot be undefined once it is defined, and node runs every test file in a
 * process of its own, so a registry that has not yet seen the element is a
 * file and not a `beforeEach`. Nothing before the first test may register it.
 */
describe("upgrading the document editor element", () => {
  it("adopts the properties an owner assigned before it was defined", async () => {
    const { root } = editingHost();
    const element = document.createElement(profileDocumentEditorElementName);
    // Exactly what "createDocumentEditor()" does, and the definition has not
    // run: these are own properties on a plain HTMLElement at this point.
    Object.assign(element, {
      mode: "view",
      heading: "View: Paper 7",
      fields: [
        {
          disabled: false,
          displayValue: "Sample paper",
          label: "Title",
          name: "title",
          readOnly: false,
          required: true,
          richText: false,
          type: "text",
          value: "Sample paper",
        },
      ],
      open: true,
    });
    root.append(element);
    assert.equal(element instanceof ProfileDocumentEditorElement, false);

    registerProfileDocumentEditorElement();
    await settle(20);

    assert.ok(element instanceof ProfileDocumentEditorElement);
    assert.equal(
      select(root, "[data-pe-document-heading]", HTMLElement).textContent?.trim(),
      "View: Paper 7",
    );
    assert.equal(select(root, "dt", HTMLElement).textContent, "Title");
  });

  it("renders an editor that is created after the module loaded", async () => {
    const { root } = editingHost();

    const element = document.createElement(
      profileDocumentEditorElementName,
    ) as ProfileDocumentEditorElement;
    element.mode = "view";
    element.heading = "View: Paper 8";
    element.open = true;
    root.append(element);
    await element.updateComplete;

    assert.ok(element instanceof ProfileDocumentEditorElement);
    assert.equal(
      select(root, "[data-pe-document-heading]", HTMLElement).textContent?.trim(),
      "View: Paper 8",
    );
  });
});
