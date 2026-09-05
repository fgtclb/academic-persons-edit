/**
 * `<academic-persons-edit-image-editor>` - the element that owns the profile
 * image editor.
 *
 * ## Why this one renders nothing
 *
 * The editor is an Extbase `<f:form>`. It carries `__referrer[…]` and the
 * `__trustedProperties` HMAC that the property mapper validates the upload
 * against, and nothing in a browser can recompute that signature. The form
 * therefore stays server rendered, and this element is a controller over Fluid's
 * markup rather than an element with a template: it takes the partial's output
 * as its light DOM children, binds the four events the panel reports, and
 * writes onto that markup what the state means.
 *
 * That is not a preference. A template that re-rendered this subtree would have
 * to reproduce the hidden fields, and a subtree CropperJS fills with its own
 * custom elements is one no template may own.
 *
 * ## What it does not own
 *
 * The state and every request stay in `profile/image.ts`, which the behavioural
 * suite drives directly and pins. This element creates one controller, hands it
 * a callback, and turns each state change into DOM. Reading the two apart is
 * the point: the controller is testable without a registry and the element is
 * testable without a server.
 *
 * ## No decorators, no shadow root
 *
 * Both for the reasons `elements/root.ts` gives: the suite runs the sources
 * under node's type stripping, and the theme's Bootstrap stylesheet has to
 * reach the controls.
 */
import {
  createImageEditing,
  type ImageEditingController,
  type ImageState,
} from "@fgtclb/academic-persons-edit/frontend/profile/image.js";
import { ProfileEditingElement } from "@fgtclb/academic-persons-edit/frontend/profile/elements/base.js";
import { profileImageEditorElementName } from "@fgtclb/academic-persons-edit/frontend/profile/elements/names.js";
import { createElementTransition } from "@fgtclb/academic-persons-edit/frontend/profile/elements/transition.js";
import {
  ownerEditingContext,
  type EditingContext,
} from "@fgtclb/academic-persons-edit/frontend/profile/context.js";

/** The tag name of this element. Public API from the moment it ships. */
export { profileImageEditorElementName };

/**
 * The transition the editor opens and closes with, and the class name prefix
 * its classes derive from. The mechanism is shared
 * with the document editor and lives in `elements/transition.ts`; only the
 * prefix is this editor's own.
 */
export const runElementTransition = createElementTransition(
  "academic-persons-profile-editing-image-editor",
);

const setHidden = (element: Element | null, hidden: boolean): void => {
  if (element instanceof HTMLElement) {
    element.hidden = hidden;
  }
};

const setDisabled = (
  element: Element | null,
  disabled: boolean,
): void => {
  if (
    element instanceof HTMLButtonElement ||
    element instanceof HTMLFieldSetElement
  ) {
    element.disabled = disabled;
  }
};

const setImageSource = (
  element: Element | null,
  url: string,
  alternative: string,
): void => {
  if (!(element instanceof HTMLImageElement)) {
    return;
  }
  // Assigning "src" resolves it against the document, so an unset preview would
  // read back as the page url rather than as nothing.
  if (url === "") {
    element.removeAttribute("src");
  } else {
    element.src = url;
  }
  element.alt = alternative;
};

const setClass = (
  element: Element | null,
  className: string,
  present: boolean,
): void => {
  element?.classList.toggle(className, present);
};

/**
 * The element.
 *
 * Public surface: the tag name, the writable `context` property, the read only
 * `controller` property and the `applyState()` method. It observes no attributes
 * and dispatches no events of its own - every status the image editing reports
 * is reported by the controller, which holds the context and writes the live
 * region through `profile/common.ts` directly.
 */
export class ProfileImageEditorElement extends ProfileEditingElement {
  #context: EditingContext | null = null;
  #controller: ImageEditingController | null = null;
  #cancelTransition: (() => void) | null = null;
  #open = false;

  #handleClick = (event: Event): void => {
    const target = event.target instanceof Element ? event.target : null;
    const button = target?.closest<HTMLButtonElement>(
      "[data-pe-open-image-view], [data-pe-close-image-view], [data-pe-delete-image], " +
        "[data-pe-cancel-delete-image], [data-pe-confirm-delete-image]",
    );
    const controller = this.#controller;
    if (button === null || button === undefined || button.disabled || controller === null) {
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

  #handleChange = (event: Event): void => {
    if (
      event.target instanceof HTMLInputElement &&
      event.target.type === "file"
    ) {
      void this.#controller?.selectImage(event);
    }
  };

  #handleSubmit = (event: Event): void => {
    // The form posts through "fetch()", never through the browser: the response
    // is JSON and the page it was rendered on stays.
    event.preventDefault();
    void this.#controller?.submitImage(event);
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
  get context(): EditingContext | null {
    return this.#context;
  }

  set context(context: EditingContext | null) {
    this.#context = context;
  }

  /** The image editing this element drives, or `null` until it is connected. */
  get controller(): ImageEditingController | null {
    return this.#controller;
  }

  override connectedCallback(): void {
    super.connectedCallback();
    this.#context ??= ownerEditingContext(this);
    const context = this.#context;
    if (context === null) {
      // Not an error: an editor whose owner has not read its contract yet is
      // one a later connection resolves.
      return;
    }
    // Delegated on the root and not on this element, because the button that
    // opens the editor belongs to the image card and stands outside it.
    context.root.addEventListener("click", this.#handleClick);
    this.addEventListener("change", this.#handleChange);
    this.addEventListener("submit", this.#handleSubmit);
    if (this.#controller === null) {
      this.#controller = createImageEditing(context, (): void => this.applyState());
    }
    this.applyState();
  }

  override disconnectedCallback(): void {
    super.disconnectedCallback();
    this.#context?.root.removeEventListener("click", this.#handleClick);
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
  applyState(): void {
    const context = this.#context;
    const state = this.#controller?.image;
    if (context === null || state === undefined) {
      return;
    }
    this.#renderEditor(state);
    this.#renderDeleteActions(state);
    this.#renderPreview(state);
    this.#renderFooter(state);
    this.#renderLayout(context, state);
  }

  /** The editor as a whole: its visibility, its transition and its busy state. */
  #renderEditor(state: ImageState): void {
    const section = this.querySelector<HTMLElement>(
      "[data-pe-image-view-container]",
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
      this.#startTransition(section, "enter", (): void => undefined);
    } else if (!state.editing && this.#open) {
      this.#open = false;
      if (state.closing) {
        this.#startTransition(section, "leave", (): void =>
          this.#controller?.finishImageClose(),
        );
      }
    }
    if (!state.editing && !state.closing) {
      this.#cancelTransition?.();
      this.#cancelTransition = null;
      section.hidden = true;
    }
  }

  /** Delete, and the question it asks before it deletes. */
  #renderDeleteActions(state: ImageState): void {
    setHidden(
      this.querySelector("[data-pe-image-delete-actions]"),
      !state.hasImage,
    );
    setHidden(
      this.querySelector("[data-pe-delete-image]"),
      state.confirmingDelete,
    );
    for (const selector of [
      "[data-pe-delete-image-confirm-question]",
      "[data-pe-cancel-delete-image]",
      "[data-pe-confirm-delete-image]",
    ]) {
      setHidden(this.querySelector(selector), !state.confirmingDelete);
    }
    for (const selector of [
      "[data-pe-delete-image]",
      "[data-pe-cancel-delete-image]",
      "[data-pe-confirm-delete-image]",
    ]) {
      setDisabled(this.querySelector(selector), state.pending);
    }
  }

  /** The cropper stage, the plain preview, and which of the two is shown. */
  #renderPreview(state: ImageState): void {
    const cropping =
      state.cropperRequested && state.hasSelection && state.previewUrl !== "";
    setHidden(this.querySelector("[data-pe-image-cropper-stage]"), !cropping);
    setImageSource(
      this.querySelector("[data-pe-image-cropper-source]"),
      state.previewUrl,
      state.selectedName,
    );
    const preview = this.querySelector("[data-pe-image-selected-preview]");
    setHidden(preview, cropping || state.previewUrl === "");
    setImageSource(preview, state.previewUrl, state.selectedName);
  }

  /** The error the upload reports, and the two buttons below it. */
  #renderFooter(state: ImageState): void {
    const error = this.querySelector<HTMLElement>("[data-pe-image-error]");
    if (error !== null) {
      error.textContent = state.error;
      error.hidden = state.error === "";
    }
    this.querySelector('input[type="file"]')?.setAttribute(
      "aria-invalid",
      state.error === "" ? "false" : "true",
    );
    setDisabled(this.querySelector("[data-pe-close-image-view]"), state.pending);
    setDisabled(
      this.querySelector("[data-pe-upload-image]"),
      state.pending ||
        !state.hasSelection ||
        state.previewUrl === "" ||
        (state.cropperRequested && !state.cropperReady),
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
  #renderLayout(context: EditingContext, state: ImageState): void {
    const collapsed = state.editing || state.closing;
    const preview = context.root.querySelector("[data-pe-image-preview-column]");
    setClass(preview, "col-lg-4", !collapsed);
    setHidden(preview, collapsed);
    const fields = context.root.querySelector(
      ".academic-persons-profile-editing__profile-fields-column",
    );
    setClass(fields, "col-lg-12", collapsed);
    setClass(fields, "col-lg-8", !collapsed);
    context.root
      .querySelector("[data-pe-open-image-view]")
      ?.setAttribute("aria-expanded", state.editing ? "true" : "false");
  }

  #startTransition(
    section: HTMLElement,
    kind: "enter" | "leave",
    done: () => void,
  ): void {
    this.#cancelTransition?.();
    this.#cancelTransition = null;
    // "settled" rather than an unconditional assignment: a transition that has
    // nothing to animate finishes inside the call, and storing the cancellation
    // it hands back afterwards would leave a finished transition looking live.
    let settled = false;
    const cancel = runElementTransition(section, kind, (): void => {
      settled = true;
      this.#cancelTransition = null;
      done();
    });
    if (!settled) {
      this.#cancelTransition = cancel;
    }
  }
}

/**
 * Defines the element, idempotently.
 *
 * Called by the entry point, after the root element. The order no longer
 * decides anything - it did while the root still mounted an application that
 * replaced the markup this element wraps - but it is the one the page starts
 * in: the owner, then what it owns.
 */
export const registerProfileImageEditorElement = (): void => {
  if (customElements.get(profileImageEditorElementName) !== undefined) {
    return;
  }
  customElements.define(profileImageEditorElementName, ProfileImageEditorElement);
};
