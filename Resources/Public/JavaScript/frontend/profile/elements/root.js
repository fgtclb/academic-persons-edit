/* Generated from Resources/Private/TypeScript — do not edit. */
import { ProfileEditingElement } from "@fgtclb/academic-persons-edit/frontend/profile/elements/base.js";
import {
  initializePopover,
  rootSelector,
  showStatus
} from "@fgtclb/academic-persons-edit/frontend/profile/common.js";
import {
  readEditingContext
} from "@fgtclb/academic-persons-edit/frontend/profile/context.js";
import {
  createDocumentEditing,
  initializeDocumentSections
} from "@fgtclb/academic-persons-edit/frontend/profile/documents.js";
import {
  profileEditingElementName,
  profileEditingElementPrefix
} from "@fgtclb/academic-persons-edit/frontend/profile/elements/names.js";
import { initializeFieldEditing } from "@fgtclb/academic-persons-edit/frontend/profile/fields.js";
import { initializeStickyImageOffset } from "@fgtclb/academic-persons-edit/frontend/profile/sticky-image.js";
import { createSkipSync } from "@fgtclb/academic-persons-edit/frontend/profile/sync.js";
const profileEditingStatusEvent = "pe:status";
const statusTypes = ["danger", "info", "success", "warning"];
const isStatusType = (value) => typeof value === "string" && statusTypes.includes(value);
const startProfileEditing = (context) => {
  createDocumentEditing(context);
  createSkipSync(context);
  initializeStickyImageOffset(context.root);
  initializeFieldEditing(context);
  initializeDocumentSections(context);
  initializePopover(context.root);
};
class ProfileEditingRootElement extends ProfileEditingElement {
  #context = null;
  #handleStatus = (event) => {
    const detail = event.detail;
    const status = typeof detail === "object" && detail !== null ? detail : null;
    if (status === null || !isStatusType(status.type)) {
      return;
    }
    this.showStatus(status.type, status.message ?? null);
  };
  /**
   * The contract of `Templates/Profile/Index.html`, read once when the element
   * first connected, and `null` for an element that carries no editor root.
   */
  get context() {
    return this.#context;
  }
  connectedCallback() {
    super.connectedCallback();
    this.addEventListener(profileEditingStatusEvent, this.#handleStatus);
    if (this.#context !== null) {
      return;
    }
    const root = this.querySelector(rootSelector);
    if (root === null) {
      return;
    }
    this.#context = readEditingContext(root);
    startProfileEditing(this.#context);
  }
  disconnectedCallback() {
    super.disconnectedCallback();
    this.removeEventListener(profileEditingStatusEvent, this.#handleStatus);
  }
  /**
   * Writes one of the two live regions of `Partials/Profile/StatusToast.html`:
   * the assertive one for a failure, the polite one for everything else.
   *
   * The rendering itself stays in `profile/common.ts`, which the controllers
   * call directly and which the behavioural suite pins. What the element adds
   * is the address: a descendant reports a status by dispatching `pe:status`
   * and needs to know neither the root nor the module.
   */
  showStatus(type, message = null) {
    if (this.#context === null) {
      return;
    }
    showStatus(this.#context, type, message);
  }
}
const registerProfileEditingElement = () => {
  if (customElements.get(profileEditingElementName) !== void 0) {
    return;
  }
  customElements.define(profileEditingElementName, ProfileEditingRootElement);
};
export {
  ProfileEditingRootElement,
  profileEditingElementName,
  profileEditingElementPrefix,
  profileEditingStatusEvent,
  registerProfileEditingElement
};
