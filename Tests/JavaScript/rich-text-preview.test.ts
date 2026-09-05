import assert from "node:assert/strict";
import { beforeEach, describe, it } from "node:test";
import { resetBody } from "../../../../../Build/tests/dom.mjs";
import {
  ensureRichTextEditor,
  getPlainText,
  isAllowedRichTextLink,
  parseRichTextPreview,
  renderRichTextPreview,
  setRichTextEditorValue,
} from "@fgtclb/academic-persons-edit/frontend/profile/rich-text.js";
import {
  fieldsForm,
  messages,
  profileEditingRoot,
  richTextField,
  select,
} from "./Fixtures/profile-editing.ts";

/**
 * What comes back from the rich text endpoints is HTML, and it is written into
 * the page as HTML - in the field preview and in the description column of a
 * document row. The allow-list below is therefore the only thing between a
 * stored value and script execution, and it is an allow-list rather than a
 * block-list on purpose.
 *
 * The sanitiser is exercised through its own function rather than through the
 * DOM, because the shape of its output is the contract: `documents.ts` reads
 * `parseRichTextPreview(value).body.innerHTML` and hands the string to the
 * element that renders the preview.
 */
describe("the rich text preview sanitiser", () => {
  const sanitise = (value: string): string =>
    parseRichTextPreview(value).body.innerHTML;

  beforeEach(() => {
    resetBody("");
  });

  it("keeps the tags of the allow-list", () => {
    assert.equal(
      sanitise("<p>An <strong>important</strong> and <em>emphasised</em> note.</p>"),
      "<p>An <strong>important</strong> and <em>emphasised</em> note.</p>",
    );
    assert.equal(
      sanitise("<ul><li>one</li></ul><ol><li>two</li></ol><p>three<br>four</p>"),
      "<ul><li>one</li></ul><ol><li>two</li></ol><p>three<br>four</p>",
    );
  });

  it("removes a script element and everything in it", () => {
    assert.equal(
      sanitise("<p>before</p><script>alert(1)</script><p>after</p>"),
      "<p>before</p><p>after</p>",
    );
  });

  it("removes the other elements that can execute or load", () => {
    for (const tag of ["iframe", "object", "style", "svg", "math", "template"]) {
      assert.equal(
        sanitise(`<p>text</p><${tag}>payload</${tag}>`),
        "<p>text</p>",
        `a <${tag}> survived the sanitiser`,
      );
    }
  });

  /**
   * An element that is merely not allowed is unwrapped rather than dropped:
   * the text a visitor wrote inside a `<div>` or a `<span>` is content, and
   * losing it would be a data loss where the point is a safety measure.
   */
  it("unwraps an element that is not on the list and keeps its text", () => {
    assert.equal(
      sanitise("<div>kept <span>text</span></div>"),
      "kept text",
    );
  });

  it("removes every attribute, including the event handlers", () => {
    assert.equal(
      sanitise('<p class="x" style="color:red" onclick="alert(1)">text</p>'),
      "<p>text</p>",
    );
  });

  it("keeps a link and makes it safe to open", () => {
    assert.equal(
      sanitise('<a href="https://example.org/paper" title="t">Paper</a>'),
      '<a href="https://example.org/paper" rel="noopener noreferrer">Paper</a>',
    );
  });

  it("removes the href of a link that is not http, mailto or tel", () => {
    assert.equal(
      sanitise('<a href="javascript:alert(1)">Click</a>'),
      '<a rel="noopener noreferrer">Click</a>',
    );
    assert.equal(
      sanitise('<a href="data:text/html;base64,PHNjcmlwdD4=">Click</a>'),
      '<a rel="noopener noreferrer">Click</a>',
    );
  });

  it("accepts the schemes the editor itself allows", () => {
    for (const href of [
      "https://example.org",
      "http://example.org",
      "mailto:ada@example.org",
      "tel:+123456",
      "/relative/page",
      "#anchor",
    ]) {
      assert.equal(isAllowedRichTextLink(href), true, `${href} was refused`);
    }
  });

  it("refuses a protocol relative link and an empty one", () => {
    assert.equal(isAllowedRichTextLink("//evil.example"), false);
    assert.equal(isAllowedRichTextLink("   "), false);
    assert.equal(isAllowedRichTextLink("javascript:alert(1)"), false);
  });

  it("reads a value as the text a person sees", () => {
    assert.equal(
      getPlainText("<p>two words</p>\n<p>  and   more </p>"),
      "two words and more",
    );
    assert.equal(getPlainText("<p><br></p>"), "");
  });
});

/**
 * The preview beside the field, which is what the visitor reads while the
 * editor is closed.
 */
describe("rendering the rich text preview", () => {
  let root: HTMLElement;
  let field: HTMLTextAreaElement;

  beforeEach(() => {
    const body = resetBody(
      profileEditingRoot({
        content: fieldsForm(
          richTextField({ identifier: "description", value: "<p>Before</p>" }),
        ),
      }),
    );
    root = select(body, "[data-academic-persons-profile-editing]", HTMLElement);
    field = select(root, "#profile-editing-1-description", HTMLTextAreaElement);
  });

  const content = (): HTMLElement =>
    select(root, "[data-pe-rich-text-preview-content]", HTMLElement);

  it("writes the sanitised markup into the preview of that field", () => {
    renderRichTextPreview(root, field, '<p>After <a href="https://example.org">link</a></p>');

    assert.equal(
      content().innerHTML,
      '<p>After <a href="https://example.org" rel="noopener noreferrer">link</a></p>',
    );
  });

  it("shows the empty label when the value has no text", () => {
    renderRichTextPreview(root, field, "<p><br></p>");

    const placeholder = select(content(), "span", HTMLElement);
    assert.equal(placeholder.textContent, messages.empty);
    assert.ok(placeholder.classList.contains("text-body-secondary"));
  });
});

/**
 * The character limit. It is a limit on the *text*, not on the markup, and it
 * is enforced by refusing the change rather than by truncating it - CKEditor is
 * put back to the last value that fit, so a paste that is too long does not
 * silently lose its end.
 */
describe("the rich text character limit", () => {
  let root: HTMLElement;
  let field: HTMLTextAreaElement;

  /**
   * The stub editor's own hook for "a person typed": it sets the data and
   * fires `change:data`, which is what the production handler listens to. It
   * is not part of the CKEditor interface, hence the cast.
   */
  const type = (editor: unknown, value: string): void => {
    (editor as { typeData: (data: string) => void }).typeData(value);
  };

  beforeEach(() => {
    const body = resetBody(
      profileEditingRoot({
        content: fieldsForm(
          richTextField({
            identifier: "description",
            value: "<p>Short</p>",
            characterLimit: 10,
          }),
        ),
      }),
    );
    root = select(body, "[data-academic-persons-profile-editing]", HTMLElement);
    field = select(root, "#profile-editing-1-description", HTMLTextAreaElement);
  });

  const counter = (): HTMLElement =>
    select(root, "[data-pe-character-counter]", HTMLElement);

  it("counts the text of the initial value, not its markup", async () => {
    await ensureRichTextEditor(root, field);

    assert.equal(counter().textContent, "5 / 10");
  });

  it("counts what is typed and keeps the field in step", async () => {
    const editor = await ensureRichTextEditor(root, field);

    type(editor, "<p>Nine char</p>");

    assert.equal(counter().textContent, "9 / 10");
    assert.equal(field.value, "<p>Nine char</p>");
  });

  it("refuses a change that goes over the limit and restores the last value that fit", async () => {
    const editor = await ensureRichTextEditor(root, field);
    type(editor, "<p>Nine char</p>");

    type(editor, "<p>Nine characters and more</p>");

    assert.equal(field.value, "<p>Nine char</p>");
    assert.equal(counter().textContent, "9 / 10");
  });

  /**
   * A value that is already too long - stored before the limit was configured,
   * or written by an editor in the backend - can still be shortened: a change
   * that gets closer to the limit is accepted even while it is over it.
   */
  it("accepts a change that shortens a value that is already too long", async () => {
    setRichTextEditorValue(field, "<p>Far too many characters</p>");
    const editor = await ensureRichTextEditor(root, field);

    type(editor, "<p>Still too long</p>");

    assert.equal(field.value, "<p>Still too long</p>");
    assert.equal(counter().textContent, "14 / 10");
    assert.ok(counter().classList.contains("text-danger"));
  });

  it("marks the counter as exceeded and clears the mark again", async () => {
    setRichTextEditorValue(field, "<p>Far too many characters</p>");
    assert.ok(counter().classList.contains("text-danger"));

    setRichTextEditorValue(field, "<p>Short</p>");
    assert.equal(counter().classList.contains("text-danger"), false);
  });
});
