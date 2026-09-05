import assert from "node:assert/strict";
import { beforeEach, describe, it } from "node:test";
import { resetBody, settle } from "../../../../../Build/tests/dom.mjs";
import {
  readEditingContext,
  type EditingContext,
} from "@fgtclb/academic-persons-edit/frontend/profile/context.js";
import type { DocumentField } from "@fgtclb/academic-persons-edit/frontend/profile/documents.js";
import { cloneField } from "@fgtclb/academic-persons-edit/frontend/profile/elements/field-clone.js";
import { profileRichTextElementName } from "@fgtclb/academic-persons-edit/frontend/profile/elements/names.js";
import {
  ProfileRichTextElement,
  registerProfileRichTextElement,
} from "@fgtclb/academic-persons-edit/frontend/profile/elements/rich-text.js";
import { getRichTextEditorValue } from "@fgtclb/academic-persons-edit/frontend/profile/rich-text.js";
import { profileEditingElement, select } from "./Fixtures/profile-editing.ts";

/**
 * The element that owns one CKEditor.
 *
 * It creates no markup: the textarea is the `control-rich-text` prototype of
 * `Partials/Profile/Prototypes.html`, which is the same
 * `Profile/Field/Control.html` the permanent profile fields are rendered from.
 * So every case below builds the element the way the document editor does -
 * by cloning that prototype through `cloneField()` - and asserts what the
 * element does with the textarea it is handed, never what it produced.
 *
 * The rest is a lifetime: an editor is created when the element connects and
 * destroyed when it disconnects, and neither may happen twice. A
 * `ClassicEditor` that is dropped without being destroyed keeps its window and
 * document listeners until it is collected, and a second one created on a
 * textarea that already has one leaves two editors fighting over the field.
 *
 * The editor itself is the stub of `Build/tests/stubs/ckeditor.mjs`, which
 * reports what was asked of it on the textarea: `data-test-ckeditor` is `live`
 * or `destroyed`, and `data-test-ckeditor-destroys` counts the destroys.
 */
registerProfileRichTextElement();

const richTextField = (field: Partial<DocumentField> = {}): DocumentField => ({
  disabled: false,
  label: "Note",
  name: "bodytext",
  readOnly: false,
  required: false,
  richText: true,
  type: "textarea",
  value: null,
  ...field,
});

describe("the rich text field element", () => {
  let root: HTMLElement;
  let host: HTMLElement;
  let context: EditingContext;

  const mount = async (
    field: Partial<DocumentField> = {},
    value: string | null = null,
  ): Promise<ProfileRichTextElement> => {
    const fragment = cloneField({
      error: undefined,
      field: richTextField(field),
      hook: "documentField",
      idPrefix: "profile-editing-document-field",
      index: 0,
      pending: false,
      source: root,
      value,
    });
    // Appended first, then read back: an element inside a "<template>" is not
    // upgraded until it is inserted into the document, which is exactly what
    // happens in the browser. It resolves its context from the profile editing
    // element above it, the way it does below a document editor.
    host.append(fragment);
    const element = select(
      host,
      profileRichTextElementName,
      ProfileRichTextElement,
    );
    await settle(20);

    return element;
  };

  beforeEach(() => {
    const body = resetBody(
      profileEditingElement({ content: "<div id='host'></div>" }),
    );
    root = select(body, "[data-academic-persons-profile-editing]", HTMLElement);
    host = select(root, "#host", HTMLElement);
    context = readEditingContext(root);
    // What "<academic-persons-edit-profile-editing>" does on connection. The
    // root element is not registered in this file: the subject is the rich
    // text element, and its owner is only the address of the context.
    (
      select(
        body,
        "academic-persons-edit-profile-editing",
        HTMLElement,
      ) as HTMLElement & { context?: EditingContext }
    ).context = context;
  });

  it("is defined under its published name", () => {
    assert.equal(profileRichTextElementName, "academic-persons-edit-rich-text");
    assert.equal(
      customElements.get(profileRichTextElementName),
      ProfileRichTextElement,
    );
    // A second call is a no-op, not the "NotSupportedError" a repeated
    // definition of the same name raises - and it leaves the definition alone.
    const defined = customElements.get(profileRichTextElementName);
    registerProfileRichTextElement();
    assert.equal(customElements.get(profileRichTextElementName), defined);
  });

  it("wraps the one textarea its prototype carries, with the filled hooks", async () => {
    const element = await mount(
      { characterLimit: 40, required: true },
      "<p>Note</p>",
    );

    assert.equal(element.querySelectorAll("textarea").length, 1);
    const field = select(element, "textarea", HTMLTextAreaElement);
    assert.equal(element.field, field);
    assert.equal(field.id, "profile-editing-document-field-0-bodytext");
    assert.equal(field.name, "bodytext");
    assert.equal(field.required, true);
    assert.equal(field.value, "<p>Note</p>");
    // The three hooks: one makes it a rich text field, one is how the
    // controller collects the value, one is how the counter finds its limit.
    assert.equal(field.getAttribute("data-pe-rich-text"), "true");
    assert.equal(field.dataset.peDocumentField, "bodytext");
    assert.equal(field.dataset.peCharacterLimit, "40");
    assert.equal(
      field.getAttribute("aria-describedby"),
      "profile-editing-document-field-error-0-bodytext",
    );
  });

  it("carries the classes of the shared control partial, not its own", async () => {
    const element = await mount();

    assert.deepEqual(
      Array.from(select(element, "textarea", HTMLTextAreaElement).classList).sort(),
      [
        "academic-persons-profile-editing__field",
        "form-control",
        "form-control-sm",
      ],
    );
  });

  it("creates exactly one editor when it connects", async () => {
    const element = await mount();

    const field = select(element, "textarea", HTMLTextAreaElement);
    assert.equal(field.getAttribute("data-test-ckeditor"), "live");
    assert.equal(getRichTextEditorValue(field), "");
  });

  it("destroys exactly one editor when it is removed", async () => {
    const element = await mount();
    const field = select(element, "textarea", HTMLTextAreaElement);

    element.remove();
    await settle(20);

    assert.equal(field.getAttribute("data-test-ckeditor"), "destroyed");
    assert.equal(field.getAttribute("data-test-ckeditor-destroys"), "1");
    assert.equal(getRichTextEditorValue(field), null);
  });

  /**
   * A move in the document is a disconnect and a reconnect of the same element
   * with the same textarea, and the destroy that the disconnect starts is a
   * promise. The creation is chained behind it, so the two cannot race for the
   * one node they share - which is the only case where they can, because every
   * other reopen builds a new element with a new textarea.
   */
  it("creates a new editor after a move, and only one", async () => {
    const element = await mount();
    const field = select(element, "textarea", HTMLTextAreaElement);

    element.remove();
    host.append(element);
    await settle(20);

    assert.equal(select(element, "textarea", HTMLTextAreaElement), field);
    assert.equal(field.getAttribute("data-test-ckeditor"), "live");
    assert.equal(field.getAttribute("data-test-ckeditor-destroys"), "1");
  });

  it("reads its value back through the field the editor owns", async () => {
    const element = await mount({}, "<p>A</p>");
    const field = select(element, "textarea", HTMLTextAreaElement);

    field.value = "<p>B</p>";

    assert.equal(element.value, "<p>B</p>");
  });

  it("creates no editor for a field the server locked", async () => {
    const element = await mount({ disabled: true });

    assert.equal(
      select(element, "textarea", HTMLTextAreaElement).getAttribute(
        "data-test-ckeditor",
      ),
      null,
    );
  });

  it("creates no editor when nothing filled its prototype", async () => {
    const element = document.createElement(
      profileRichTextElementName,
    ) as ProfileRichTextElement;
    element.context = context;
    host.append(element);
    await settle(20);

    assert.equal(element.field, null);
    assert.equal(element.value, "");
  });

  it("takes its editing context from the profile editor it stands in", async () => {
    const owner = document.createElement(
      "academic-persons-edit-profile-editing",
    ) as HTMLElement & { context?: EditingContext };
    owner.context = context;
    host.append(owner);
    owner.append(
      cloneField({
        error: undefined,
        field: richTextField(),
        hook: "documentField",
        idPrefix: "profile-editing-document-field",
        index: 0,
        pending: false,
        source: root,
        value: null,
      }),
    );
    const element = select(
      owner,
      profileRichTextElementName,
      ProfileRichTextElement,
    );
    await settle(20);

    assert.equal(element.context, context);
    assert.equal(
      select(element, "textarea", HTMLTextAreaElement).getAttribute(
        "data-test-ckeditor",
      ),
      "live",
    );
  });
});
