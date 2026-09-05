/* Generated from Resources/Private/TypeScript — do not edit. */
import {
  ownerEditingContext
} from "@fgtclb/academic-persons-edit/frontend/profile/context.js";
import { ProfileEditingElement } from "@fgtclb/academic-persons-edit/frontend/profile/elements/base.js";
import { profileRichTextElementName } from "@fgtclb/academic-persons-edit/frontend/profile/elements/names.js";
import {
  destroyRichTextEditors,
  ensureRichTextEditor
} from "@fgtclb/academic-persons-edit/frontend/profile/rich-text.js";
class ProfileRichTextElement extends ProfileEditingElement {
  #context = null;
  #field = null;
  #teardown = Promise.resolve();
  /**
   * The contract of `Templates/Profile/Index.html`.
   *
   * Assigned by whoever creates the element, and otherwise resolved from the
   * `<academic-persons-edit-profile-editing>` above it on connection. The
   * editor needs it for the interface language and for the failure message it
   * reports when the editor cannot be created.
   */
  get context() {
    return this.#context;
  }
  set context(context) {
    this.#context = context;
  }
  /** The textarea of the prototype, or `null` for an empty element. */
  get field() {
    this.#field ??= this.querySelector("textarea");
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
  get value() {
    var _a;
    return ((_a = this.field) == null ? void 0 : _a.value) ?? "";
  }
  set value(value) {
    const field = this.field;
    if (field !== null && field.value !== value) {
      field.value = value;
    }
  }
  connectedCallback() {
    super.connectedCallback();
    this.#context ??= ownerEditingContext(this);
    const context = this.#context;
    const field = this.field;
    if (context === null || field === null) {
      return;
    }
    void this.#teardown.then(() => {
      if (this.isConnected) {
        void ensureRichTextEditor(context, field);
      }
    });
  }
  disconnectedCallback() {
    super.disconnectedCallback();
    this.#teardown = destroyRichTextEditors(this);
  }
}
const registerProfileRichTextElement = () => {
  if (customElements.get(profileRichTextElementName) !== void 0) {
    return;
  }
  customElements.define(profileRichTextElementName, ProfileRichTextElement);
};
export {
  ProfileRichTextElement,
  profileRichTextElementName,
  registerProfileRichTextElement
};
