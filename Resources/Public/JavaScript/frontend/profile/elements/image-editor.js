/* Generated from Resources/Private/TypeScript — do not edit. */
import {
  createImageEditing
} from "@fgtclb/academic-persons-edit/frontend/profile/image.js";
import { ProfileEditingElement } from "@fgtclb/academic-persons-edit/frontend/profile/elements/base.js";
import { profileImageEditorElementName } from "@fgtclb/academic-persons-edit/frontend/profile/elements/names.js";
import { createElementTransition } from "@fgtclb/academic-persons-edit/frontend/profile/elements/transition.js";
import {
  ownerEditingContext
} from "@fgtclb/academic-persons-edit/frontend/profile/context.js";
const runElementTransition = createElementTransition(
  "academic-persons-profile-editing-image-editor"
);
const setHidden = (element, hidden) => {
  if (element instanceof HTMLElement) {
    element.hidden = hidden;
  }
};
const setDisabled = (element, disabled) => {
  if (element instanceof HTMLButtonElement || element instanceof HTMLFieldSetElement) {
    element.disabled = disabled;
  }
};
const setImageSource = (element, url, alternative) => {
  if (!(element instanceof HTMLImageElement)) {
    return;
  }
  if (url === "") {
    element.removeAttribute("src");
  } else {
    element.src = url;
  }
  element.alt = alternative;
};
const setClass = (element, className, present) => {
  element == null ? void 0 : element.classList.toggle(className, present);
};
class ProfileImageEditorElement extends ProfileEditingElement {
  #context = null;
  #controller = null;
  #cancelTransition = null;
  #open = false;
  #handleClick = (event) => {
    const target = event.target instanceof Element ? event.target : null;
    const button = target == null ? void 0 : target.closest(
      "[data-pe-open-image-view], [data-pe-close-image-view], [data-pe-delete-image], [data-pe-cancel-delete-image], [data-pe-confirm-delete-image]"
    );
    const controller = this.#controller;
    if (button === null || button === void 0 || button.disabled || controller === null) {
      return;
    }
    if (button.matches("[data-pe-open-image-view]")) {
      void controller.openImage();
    } else if (button.matches("[data-pe-close-image-view]")) {
      controller.closeImage();
    } else if (button.matches("[data-pe-delete-image]")) {
      controller.requestDeleteImage();
    } else if (button.matches("[data-pe-cancel-delete-image]")) {
      controller.cancelDeleteImage();
    } else {
      void controller.deleteImage();
    }
  };
  #handleChange = (event) => {
    var _a;
    if (event.target instanceof HTMLInputElement && event.target.type === "file") {
      void ((_a = this.#controller) == null ? void 0 : _a.selectImage(event));
    }
  };
  #handleSubmit = (event) => {
    var _a;
    event.preventDefault();
    void ((_a = this.#controller) == null ? void 0 : _a.submitImage(event));
  };
  /**
   * The contract of `Templates/Profile/Index.html`.
   *
   * Assigned by whoever creates the element, and otherwise resolved from the
   * `<academic-persons-edit-profile-editing>` above it on connection - the
   * markup of this element is rendered by Fluid, so there is no creating caller
   * to assign it. Either way the element never reads the root's attributes
   * itself: the contract is read once, by the owner, and handed down.
   */
  get context() {
    return this.#context;
  }
  set context(context) {
    this.#context = context;
  }
  /** The image editing this element drives, or `null` until it is connected. */
  get controller() {
    return this.#controller;
  }
  connectedCallback() {
    super.connectedCallback();
    this.#context ??= ownerEditingContext(this);
    const context = this.#context;
    if (context === null) {
      return;
    }
    context.root.addEventListener("click", this.#handleClick);
    this.addEventListener("change", this.#handleChange);
    this.addEventListener("submit", this.#handleSubmit);
    if (this.#controller === null) {
      this.#controller = createImageEditing(context, () => this.applyState());
    }
    this.applyState();
  }
  disconnectedCallback() {
    var _a;
    super.disconnectedCallback();
    (_a = this.#context) == null ? void 0 : _a.root.removeEventListener("click", this.#handleClick);
    this.removeEventListener("change", this.#handleChange);
    this.removeEventListener("submit", this.#handleSubmit);
  }
  /**
   * Writes everything the state means for the partial - what is shown, what is
   * disabled, which preview is visible - plus the two column widths of
   * `Templates/Profile/Index.html`.
   *
   * Called after every change the controller accepts, and once on connection so
   * that the markup agrees with the state it was rendered before.
   *
   * Named `applyState()` rather than `render()`: this element renders
   * nothing - it writes attributes, classes and the `hidden` flag onto markup
   * Fluid produced.
   */
  applyState() {
    var _a;
    const context = this.#context;
    const state = (_a = this.#controller) == null ? void 0 : _a.image;
    if (context === null || state === void 0) {
      return;
    }
    this.#renderEditor(state);
    this.#renderDeleteActions(state);
    this.#renderPreview(state);
    this.#renderFooter(state);
    this.#renderLayout(context, state);
  }
  /** The editor as a whole: its visibility, its transition and its busy state. */
  #renderEditor(state) {
    var _a;
    const section = this.querySelector(
      "[data-pe-image-view-container]"
    );
    if (section === null) {
      return;
    }
    section.setAttribute("aria-busy", state.pending ? "true" : "false");
    section.style.cursor = state.pending ? "wait" : "";
    setDisabled(this.querySelector("[data-pe-image-fieldset]"), state.pending);
    if (state.editing && !this.#open) {
      this.#open = true;
      section.hidden = false;
      this.#startTransition(section, "enter", () => void 0);
    } else if (!state.editing && this.#open) {
      this.#open = false;
      if (state.closing) {
        this.#startTransition(
          section,
          "leave",
          () => {
            var _a2;
            return (_a2 = this.#controller) == null ? void 0 : _a2.finishImageClose();
          }
        );
      }
    }
    if (!state.editing && !state.closing) {
      (_a = this.#cancelTransition) == null ? void 0 : _a.call(this);
      this.#cancelTransition = null;
      section.hidden = true;
    }
  }
  /** Delete, and the question it asks before it deletes. */
  #renderDeleteActions(state) {
    setHidden(
      this.querySelector("[data-pe-image-delete-actions]"),
      !state.hasImage
    );
    setHidden(
      this.querySelector("[data-pe-delete-image]"),
      state.confirmingDelete
    );
    for (const selector of [
      "[data-pe-delete-image-confirm-question]",
      "[data-pe-cancel-delete-image]",
      "[data-pe-confirm-delete-image]"
    ]) {
      setHidden(this.querySelector(selector), !state.confirmingDelete);
    }
    for (const selector of [
      "[data-pe-delete-image]",
      "[data-pe-cancel-delete-image]",
      "[data-pe-confirm-delete-image]"
    ]) {
      setDisabled(this.querySelector(selector), state.pending);
    }
  }
  /** The cropper stage, the plain preview, and which of the two is shown. */
  #renderPreview(state) {
    const cropping = state.cropperRequested && state.hasSelection && state.previewUrl !== "";
    setHidden(this.querySelector("[data-pe-image-cropper-stage]"), !cropping);
    setImageSource(
      this.querySelector("[data-pe-image-cropper-source]"),
      state.previewUrl,
      state.selectedName
    );
    const preview = this.querySelector("[data-pe-image-selected-preview]");
    setHidden(preview, cropping || state.previewUrl === "");
    setImageSource(preview, state.previewUrl, state.selectedName);
  }
  /** The error the upload reports, and the two buttons below it. */
  #renderFooter(state) {
    var _a;
    const error = this.querySelector("[data-pe-image-error]");
    if (error !== null) {
      error.textContent = state.error;
      error.hidden = state.error === "";
    }
    (_a = this.querySelector('input[type="file"]')) == null ? void 0 : _a.setAttribute(
      "aria-invalid",
      state.error === "" ? "false" : "true"
    );
    setDisabled(this.querySelector("[data-pe-close-image-view]"), state.pending);
    setDisabled(
      this.querySelector("[data-pe-upload-image]"),
      state.pending || !state.hasSelection || state.previewUrl === "" || state.cropperRequested && !state.cropperReady
    );
    setHidden(this.querySelector("[data-pe-image-upload-spinner]"), !state.pending);
  }
  /**
   * The two columns of `Templates/Profile/Index.html`: the image moves out of
   * the way while the editor is open and the fields take the full width.
   *
   * They stand outside this element and are written from it, because the state
   * they are derived from is this one - the same reason the module below has
   * always written the previews of the image card.
   */
  #renderLayout(context, state) {
    var _a;
    const collapsed = state.editing || state.closing;
    const preview = context.root.querySelector("[data-pe-image-preview-column]");
    setClass(preview, "col-lg-4", !collapsed);
    setHidden(preview, collapsed);
    const fields = context.root.querySelector(
      ".academic-persons-profile-editing__profile-fields-column"
    );
    setClass(fields, "col-lg-12", collapsed);
    setClass(fields, "col-lg-8", !collapsed);
    (_a = context.root.querySelector("[data-pe-open-image-view]")) == null ? void 0 : _a.setAttribute("aria-expanded", state.editing ? "true" : "false");
  }
  #startTransition(section, kind, done) {
    var _a;
    (_a = this.#cancelTransition) == null ? void 0 : _a.call(this);
    this.#cancelTransition = null;
    let settled = false;
    const cancel = runElementTransition(section, kind, () => {
      settled = true;
      this.#cancelTransition = null;
      done();
    });
    if (!settled) {
      this.#cancelTransition = cancel;
    }
  }
}
const registerProfileImageEditorElement = () => {
  if (customElements.get(profileImageEditorElementName) !== void 0) {
    return;
  }
  customElements.define(profileImageEditorElementName, ProfileImageEditorElement);
};
export {
  ProfileImageEditorElement,
  profileImageEditorElementName,
  registerProfileImageEditorElement,
  runElementTransition
};
