/**
 * `<academic-persons-edit-rich-text>` - one rich text field, and the one
 * CKEditor 5 instance on it.
 *
 * ## What it owns, and what Fluid owns
 *
 * The textarea is **not** created here. It is the `control-rich-text`
 * prototype of `Partials/Profile/Prototypes.html` - the same
 * `Profile/Field/Control.html` that renders the permanent profile fields,
 * wrapped in this element - so an integrator changes the control by
 * overriding a partial, and there is exactly one spelling of it. The document
 * editor clones that prototype, the filler writes the id, the name and the
 * validation attributes into it, and this element takes the textarea it finds
 * below itself.
 *
 * ## Why an element at all
 *
 * `ClassicEditor.create(textarea)` takes the textarea out of the layout and
 * puts its own container, toolbar and editable in its place. Everything below
 * that point belongs to CKEditor: it mutates it on every keystroke, its
 * balloon and its selection tracking work against `document`, and its UI
 * writes document level `<style>` elements. Nothing else may touch that
 * subtree, and an element is the unit that owns one.
 *
 * The lifetime is the whole point. `connectedCallback()` creates the editor
 * and `disconnectedCallback()` destroys it, so teardown is **structural**: the
 * document editor replaces its panel with `replaceChildren()`, the platform
 * disconnects what it removed, and every editor of the leaving subtree - and
 * only those - is destroyed. Scoping the teardown by hand is what shipped a
 * defect that destroyed the *profile* field editors instead (`c09` F1).
 *
 * A close immediately followed by a reopen creates a new element with a new
 * textarea, so the create-while-destroying race a shared textarea would have
 * has no node to happen on. The teardown promise below is kept for the one
 * case that does share a node: an element that is moved in the document and
 * is therefore disconnected and reconnected.
 *
 * ## No shadow root, no decorators
 *
 * Both for the reasons `elements/base.ts` gives, and one more that is specific
 * to this element: CKEditor 5 does not support a classic editor inside a
 * shadow root at all.
 */
import {
  ownerEditingContext,
  type EditingContext,
} from "@fgtclb/academic-persons-edit/frontend/profile/context.js";
import { ProfileEditingElement } from "@fgtclb/academic-persons-edit/frontend/profile/elements/base.js";
import { profileRichTextElementName } from "@fgtclb/academic-persons-edit/frontend/profile/elements/names.js";
import {
  destroyRichTextEditors,
  ensureRichTextEditor,
} from "@fgtclb/academic-persons-edit/frontend/profile/rich-text.js";

/** The tag name of this element. Public API from the moment it ships. */
export { profileRichTextElementName };

/**
 * The element.
 *
 * Public surface: the tag name, the `context` property, the `value` property,
 * the read only `field` property, and the `input` event the textarea below it
 * bubbles. It observes no attributes.
 */
export class ProfileRichTextElement extends ProfileEditingElement {
  #context: EditingContext | null = null;
  #field: HTMLTextAreaElement | null = null;
  #teardown: Promise<void> = Promise.resolve();

  /**
   * The contract of `Templates/Profile/Index.html`.
   *
   * Assigned by whoever creates the element, and otherwise resolved from the
   * `<academic-persons-edit-profile-editing>` above it on connection. The
   * editor needs it for the interface language and for the failure message it
   * reports when the editor cannot be created.
   */
  get context(): EditingContext | null {
    return this.#context;
  }

  set context(context: EditingContext | null) {
    this.#context = context;
  }

  /** The textarea of the prototype, or `null` for an empty element. */
  get field(): HTMLTextAreaElement | null {
    this.#field ??= this.querySelector<HTMLTextAreaElement>("textarea");

    return this.#field;
  }

  /**
   * The value of the textarea.
   *
   * The textarea, not the editor: CKEditor mirrors its data into the field on
   * every `change:data`, and the controller reads the editor itself through
   * `getRichTextEditorValue()` when it collects a payload. Written only into
   * the textarea, and only meaningfully before an editor exists - which is the
   * one moment the document editor writes it, right after it cloned the
   * prototype.
   */
  get value(): string {
    return this.field?.value ?? "";
  }

  set value(value: string) {
    const field = this.field;
    if (field !== null && field.value !== value) {
      field.value = value;
    }
  }

  override connectedCallback(): void {
    super.connectedCallback();
    this.#context ??= ownerEditingContext(this);
    const context = this.#context;
    const field = this.field;
    if (context === null || field === null) {
      // Neither is an error: an element whose owner has not read its contract
      // yet is one a later connection resolves, and an element without its
      // prototype content is one nothing has filled yet.
      return;
    }
    // Chained behind the teardown of the previous connection rather than
    // started outright: a disconnect that is still destroying the editor of
    // this very textarea would otherwise be raced by the new creation.
    void this.#teardown.then((): void => {
      if (this.isConnected) {
        void ensureRichTextEditor(context, field);
      }
    });
  }

  override disconnectedCallback(): void {
    super.disconnectedCallback();
    this.#teardown = destroyRichTextEditors(this);
  }
}

/**
 * Defines the element, idempotently.
 *
 * Called by the entry point. A second call is a no-op rather than the
 * `NotSupportedError` a repeated `customElements.define()` raises.
 */
export const registerProfileRichTextElement = (): void => {
  if (customElements.get(profileRichTextElementName) !== undefined) {
    return;
  }
  customElements.define(profileRichTextElementName, ProfileRichTextElement);
};
