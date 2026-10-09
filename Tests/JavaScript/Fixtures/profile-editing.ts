/**
 * The markup the profile editing modules are driven against.
 *
 * ## Where it comes from
 *
 * Every block below is the rendered shape of a Fluid partial of this extension,
 * named at the block. Nothing here is invented: an attribute that the templates
 * do not emit does not belong in a fixture, because then a test would pass on
 * markup no visitor ever receives. Two reductions are deliberate and are the
 * only ones:
 *
 * - `<f:translate>` is replaced by the English text it resolves to, and
 *   `<ab:icon>` by nothing at all. Neither is read by the JavaScript.
 * - What an element writes is not transcribed: the mount points are, and
 *   the element renders into them here exactly as it does in a browser. The
 *   partials they were transcribed from are named at each block.
 *
 * What is kept verbatim is everything a module queries: the `data-pe-*` hooks,
 * the element ids, the class names it toggles, the `data-*` configuration of
 * the root, and the structure the `closest()` calls walk. A template change
 * that drops one of those turns a test red here, which is the point - the PHP
 * functional tests assert the markup, and these assert what the JavaScript does
 * with it.
 *
 * See `docs/testing/academic-persons-edit-frontend-tests.md`.
 */

import { resetBody } from "../../../../../../Build/tests/dom.mjs";
import {
  readEditingContext,
  type EditingContext,
} from "@fgtclb/academic-persons-edit/frontend/profile/context.js";

/**
 * The endpoints of `Templates/Profile/Index.html:12-49`, absolute as
 * `f:uri.action(absolute: true)` renders them. The origin is the one
 * `Build/tests/dom.mjs` gives the window, so `credentials: "same-origin"`
 * means the same thing here as on a real page.
 */
export const endpoints = {
  update: "https://example.test/profile/update",
  skipSync: "https://example.test/profile/skip-sync",
  visibility: "https://example.test/profile/visibility",
  deleteImage: "https://example.test/profile/delete-image",
  documentForm: "https://example.test/profile/document-form",
  createDocument: "https://example.test/profile/create-document",
  updateDocument: "https://example.test/profile/update-document",
  deleteDocument: "https://example.test/profile/delete-document",
  sortDocument: "https://example.test/profile/sort-document",
  toggleDocumentVisibility: "https://example.test/profile/toggle-document-visibility",
  contractContactForm: "https://example.test/profile/contract-contact-form",
  createContractContact: "https://example.test/profile/create-contract-contact",
  updateContractContact: "https://example.test/profile/update-contract-contact",
  deleteContractContact: "https://example.test/profile/delete-contract-contact",
  sortContractContact: "https://example.test/profile/sort-contract-contact",
  toggleContractContactVisibility: "https://example.test/profile/toggle-contract-contact-visibility",
  uploadImage: "https://example.test/profile/upload-image",
} as const;

/** The placeholder image `Index.html:50-52` resolves through `f:uri.resource`. */
export const placeholderImageUrl =
  "/typo3conf/ext/academic_persons_edit/Resources/Public/Images/profile-placeholder.png";

/**
 * The translated strings the root carries as `data-message-*` and
 * `data-label-*` (`Index.html:89-115`). The values are the English labels of
 * `Resources/Private/Language/locallang.xlf`, shortened where the exact
 * wording is irrelevant - a test asserts that *this* message is shown, never
 * what it says.
 */
export const messages = {
  saving: "Saving …",
  successTitle: "Saved",
  successMessage: "The change was saved.",
  errorTitle: "Error",
  errorMessage: "The change could not be saved.",
  infoTitle: "Information",
  infoMessage: "Working …",
  warningTitle: "Please check your input",
  validation: "Please check the highlighted fields.",
  editorError: "The editor could not be started.",
  unchanged: "Nothing was changed.",
  saveInProgress: "Please wait until the change has been saved.",
  discarded: "Unsaved changes were discarded.",
  unsavedChangesTitle: "Unsaved changes",
  unsavedChangesMessage:
    "The editor that is open has changes that are not saved yet. Save them, discard them, or keep editing?",
  imageUploaded: "The image was uploaded.",
  imageUploadMissing: "The upload returned no image.",
  imageDeleted: "The image was deleted.",
  documentSaved: "The entry was saved.",
  documentDeleted: "The entry was deleted.",
  documentSorted: "The order was saved.",
  documentHidden: "The entry is now hidden in the frontend.",
  documentShown: "The entry is now visible in the frontend.",
  documentDeleteConfirm: "Delete this entry?",
  contractContactDeleteConfirm: "Delete this contact?",
  contractContactEmpty: "No contacts yet.",
  contractContactHidden: "The contact is now hidden in the frontend.",
  contractContactShown: "The contact is now visible in the frontend.",
  placeholderAlt: "No profile image",
  empty: "Not specified",
  formReverted: "All fields were restored to the saved values.",
} as const;

/**
 * The mode labels of `Index.html:109-114`, read by `getModeLabel()`, plus
 * `viewClose`.
 *
 * That one is not a root label and never travels as `data-label-*`: it is the
 * second string Fluid writes onto the eye of a row, as
 * `data-pe-label-expanded`, and `setExpanded()` puts it on `aria-label` and
 * `title` while the panel it opened is standing.
 */
export const labels = {
  add: "Add",
  view: "View",
  viewClose: "Close details",
  hide: "Hide in frontend",
  show: "Show in frontend",
  hidden: "Hidden",
  managed: "Synchronised",
  contactActions: "Contact actions",
  edit: "Edit",
  delete: "Delete",
  save: "Save",
  close: "Cancel",
  sortUp: "Move up",
  sortDown: "Move down",
} as const;

export interface RootOptions {
  /** The markup below the root, in the order `Index.html:116-172` renders it. */
  content?: string;
  /**
   * The markup inside the image editor target, which is the first child of the
   * root and is where `Index.html` renders the image editor.
   */
  target?: string;
  hasImage?: boolean;
  imageRenderType?: string;
  imageCropperRatio?: string;
  profileUid?: number;
}

/**
 * `Templates/Profile/Index.html:66-115` - the plugin root and the whole
 * `data-*` contract between Fluid and the JavaScript.
 */
export const profileEditingRoot = ({
  content = "",
  target = "",
  hasImage = true,
  imageRenderType = "",
  imageCropperRatio = "",
  profileUid = 1,
}: RootOptions = {}): string => `
<div class="academic-persons-profile-editing"
  data-academic-persons-profile-editing
  data-update-url="${endpoints.update}"
  data-skip-sync-url="${endpoints.skipSync}"
  data-visibility-url="${endpoints.visibility}"
  data-delete-image-url="${endpoints.deleteImage}"
  data-document-form-url="${endpoints.documentForm}"
  data-create-document-url="${endpoints.createDocument}"
  data-update-document-url="${endpoints.updateDocument}"
  data-delete-document-url="${endpoints.deleteDocument}"
  data-sort-document-url="${endpoints.sortDocument}"
  data-toggle-document-visibility-url="${endpoints.toggleDocumentVisibility}"
  data-contract-contact-form-url="${endpoints.contractContactForm}"
  data-create-contract-contact-url="${endpoints.createContractContact}"
  data-update-contract-contact-url="${endpoints.updateContractContact}"
  data-delete-contract-contact-url="${endpoints.deleteContractContact}"
  data-sort-contract-contact-url="${endpoints.sortContractContact}"
  data-toggle-contract-contact-visibility-url="${endpoints.toggleContractContactVisibility}"
  data-placeholder-image-url="${placeholderImageUrl}"
  data-placeholder-image-alt="${messages.placeholderAlt}"
  data-has-image="${hasImage ? "1" : "0"}"
  data-image-render-type="${imageRenderType}"
  data-image-cropper-ratio="${imageCropperRatio}"
  data-profile-uid="${profileUid}"
  data-editor-language="en"
  data-message-saving="${messages.saving}"
  data-message-success-message="${messages.successMessage}"
  data-message-error-message="${messages.errorMessage}"
  data-message-error-title="${messages.errorTitle}"
  data-message-success-title="${messages.successTitle}"
  data-message-info-title="${messages.infoTitle}"
  data-message-info-message="${messages.infoMessage}"
  data-message-warning-title="${messages.warningTitle}"
  data-message-validation="${messages.validation}"
  data-message-editor-error="${messages.editorError}"
  data-message-unchanged="${messages.unchanged}"
  data-message-save-in-progress="${messages.saveInProgress}"
  data-message-discarded="${messages.discarded}"
  data-message-image-uploaded="${messages.imageUploaded}"
  data-message-image-upload-missing="${messages.imageUploadMissing}"
  data-message-image-deleted="${messages.imageDeleted}"
  data-message-document-saved="${messages.documentSaved}"
  data-message-document-deleted="${messages.documentDeleted}"
  data-message-document-sorted="${messages.documentSorted}"
  data-message-document-hidden="${messages.documentHidden}"
  data-message-document-shown="${messages.documentShown}"
  data-message-document-delete-confirm="${messages.documentDeleteConfirm}"
  data-message-contract-contact-delete-confirm="${messages.contractContactDeleteConfirm}"
  data-message-contract-contact-empty="${messages.contractContactEmpty}"
  data-message-contract-contact-hidden="${messages.contractContactHidden}"
  data-message-contract-contact-shown="${messages.contractContactShown}"
  data-label-document-add="${labels.add}"
  data-label-document-view="${labels.view}"
  data-label-document-edit="${labels.edit}"
  data-label-document-delete="${labels.delete}"
  data-label-document-save="${labels.save}"
  data-label-document-empty="${messages.empty}">
  <div id="profile-editing-${profileUid}-image-editor-target" data-pe-image-editor-target>${target}</div>
  ${content}
  ${prototypes()}
  ${unsavedChangesDialog()}
  ${statusToast()}
</div>`;

/**
 * `Templates/Profile/Index.html:66-73` - the custom element the template wraps
 * the plugin root in.
 *
 * The wrapper is what a browser upgrades and what starts the editor. The
 * controllers below it neither know nor ask about it, which is why every other
 * fixture here stops at the root and the controller tests drive that: an
 * element around markup the module never queries would be arrangement without
 * a subject.
 */
export const profileEditingElement = (options: RootOptions = {}): string => `
<academic-persons-edit-profile-editing>${profileEditingRoot(options)}</academic-persons-edit-profile-editing>`;

/**
 * `Partials/Profile/Prototypes.html` and the four partials it renders - the
 * `<template data-pe-proto>` blocks the two editors clone.
 *
 * Transcribed rather than generated, exactly as every other block here is, and
 * with the same two reductions: `<f:translate>` becomes its English text and
 * `<ab:icon>` becomes a marker element. What the transcription has to get
 * right is the contract - the prototype names, the four verbs and their keys,
 * the hooks and the classes - and that is not left to care: the functional
 * test `AcademicPersonsEditProfileEditingPrototypesTest` asserts the same
 * inventory and the same slot keys against the rendered partial, so a drift
 * between this file and the Fluid tree fails there.
 */
const controlAttributes = {
  input:
    "id:controlId name:name type:inputType value:value required:required " +
    "readonly:readOnly disabled:disabled aria-describedby:describedBy " +
    "aria-invalid:invalid autocomplete:autocomplete placeholder:placeholder " +
    "min:min max:max step:step " +
    "data-pe-document-field:documentField " +
    "data-pe-contract-contact-field:contactField",
  textarea:
    "id:controlId name:name required:required readonly:readOnly " +
    "disabled:disabled aria-describedby:describedBy aria-invalid:invalid " +
    "data-pe-document-field:documentField " +
    "data-pe-contract-contact-field:contactField",
  richText:
    "id:controlId name:name required:required readonly:readOnly " +
    "disabled:disabled aria-describedby:describedBy aria-invalid:invalid " +
    "data-pe-character-limit:characterLimit " +
    "data-pe-document-field:documentField " +
    "data-pe-contract-contact-field:contactField",
  select:
    "id:controlId name:name required:required disabled:disabled " +
    "aria-describedby:describedBy aria-invalid:invalid " +
    "data-pe-document-field:documentField " +
    "data-pe-contract-contact-field:contactField",
  checkbox:
    "id:controlId name:name checked:checked disabled:disabled " +
    "aria-describedby:describedBy aria-invalid:invalid " +
    "data-pe-autosave-on-change:autosave data-pe-checked-label:checkedLabel " +
    "data-pe-unchecked-label:uncheckedLabel " +
    "data-pe-document-field:documentField " +
    "data-pe-contract-contact-field:contactField",
} as const;

/** `Partials/Profile/Field/PrototypeWrapper.html`, in its three shapes. */
const fieldWrapper = (columnClass: string, checkbox: boolean): string =>
  checkbox
    ? `<div class="${columnClass}" data-pe-attr="class:columnClass">
  <div class="ace-field" data-pe-field-wrapper data-pe-attr="data-pe-compact:compact">
    <div class="ace-switch" data-pe-field-control-group>
      <template data-pe-list="control"></template>
      <label class="ace-label" data-pe-attr="for:controlId" data-pe-slot="label"></label>
      <template data-pe-list="helptext"></template>
      <span class="ace-state" data-pe-when="managed">${labels.managed}</span>
      <div class="ace-message" role="alert" data-pe-attr="id:errorId hidden:errorHidden" data-pe-slot="error"></div>
    </div>
  </div>
</div>`
    : `<div class="${columnClass}" data-pe-attr="class:columnClass">
  <div class="ace-field" data-pe-field-wrapper data-pe-attr="data-pe-compact:compact">
    <div class="ace-header">
      <label class="ace-label" data-pe-attr="for:controlId">
        <span data-pe-slot="label"></span>
        <span class="ace-required" aria-hidden="true" data-pe-when="required">*</span>
      </label>
      <template data-pe-list="helptext"></template>
      <span class="ace-state" data-pe-when="managed">${labels.managed}</span>
    </div>
    <template data-pe-list="control"></template>
    <div class="ace-counter" aria-live="polite" data-pe-character-counter data-pe-when="hasCharacterLimit" data-pe-attr="data-pe-for:controlId">0 / <span data-pe-slot="characterLimit"></span></div>
    <div class="ace-message" role="alert" data-pe-attr="id:errorId hidden:errorHidden" data-pe-slot="error"></div>
  </div>
</div>`;

export const prototypes = (): string => `
<template data-pe-proto="control-input"><input type="text" name="" value="" aria-invalid="false"
  class="ace-control ace-text" data-pe-field-control="true"
  data-pe-attr="${controlAttributes.input}" /></template>
<template data-pe-proto="control-textarea"><textarea rows="6" name="" aria-invalid="false"
  class="ace-control ace-textarea" data-pe-field-control="true"
  data-pe-attr="${controlAttributes.textarea}"></textarea></template>
<template data-pe-proto="control-rich-text"><academic-persons-edit-rich-text><textarea rows="6" name="" aria-invalid="false"
  class="ace-control ace-textarea ace-ckeditor" data-pe-field-control="true"
  data-pe-rich-text="true"
  data-pe-attr="${controlAttributes.richText}"></textarea></academic-persons-edit-rich-text></template>
<template data-pe-proto="control-select"><select name="" aria-invalid="false"
  class="ace-control ace-select" data-pe-field-control="true"
  data-pe-list="options"
  data-pe-attr="${controlAttributes.select}"><option value=""></option></select></template>
<template data-pe-proto="control-checkbox"><input type="checkbox" name="" value="1" aria-invalid="false"
  class="ace-control ace-checkbox" data-pe-field-control="true"
  data-pe-attr="${controlAttributes.checkbox}" /></template>
<template data-pe-proto="option"><option data-pe-slot="label" data-pe-attr="value:value"></option></template>
<template data-pe-proto="field-default">${fieldWrapper("col-12 col-md-6", false)}</template>
<template data-pe-proto="field-wide">${fieldWrapper("col-12", false)}</template>
<template data-pe-proto="field-checkbox">${fieldWrapper("col-12", true)}</template>
<template data-pe-proto="helptext-button"><button type="button"
  class="ace-control ace-help btn btn-link"
  data-pe-helptext data-bs-toggle="popover" data-bs-trigger="focus" data-bs-placement="right"
  data-bs-custom-class="custom-popover"
  data-pe-attr="data-bs-title:title data-bs-content:content aria-label:ariaLabel"><span data-test-icon="help"></span></button></template>
<template data-pe-proto="display-row"><dt class="col-sm-4"><span class="ace-label" data-pe-slot="label"></span></dt><dd class="col-sm-8"><div class="ace-value"><div class="ce-bodytext" data-pe-when="richText" data-pe-list="richValue"></div><span data-pe-when="plain" data-pe-slot="value"></span></div></dd></template>
<template data-pe-proto="document-panel"><section
  class="ace-document-editor"
  data-pe-document-view-container
  data-pe-attr="aria-busy:busy data-pe-document-kind:kind">
  <div class="ace-content">
    <form class="ace-form" data-pe-document-form>
      <h2 class="ace-title" tabindex="-1" data-pe-document-heading data-pe-slot="heading"></h2>
      <div class="ace-message ace-error" data-pe-document-error role="alert" data-pe-attr="hidden:errorHidden" data-pe-slot="error"></div>
      <p class="ace-copy" data-pe-when="isDelete" data-pe-slot="deleteConfirmation"></p>
      <dl class="row" data-pe-when="showDisplay"><template data-pe-list="displayRows"></template></dl>
      <div class="ace-contacts" data-pe-when="showContacts"><template data-pe-list="contacts"></template></div>
      <div class="row" data-pe-document-fields data-pe-when="showFields"><template data-pe-list="fields"></template></div>
      <div class="ace-actions" data-pe-when="showActions">
        <button type="button" class="ace-control ace-cancel btn btn-outline-secondary" data-pe-document-cancel data-pe-attr="disabled:pending">${labels.close}</button>
        <button type="submit" class="ace-control ace-save btn btn-primary" data-pe-document-save data-pe-when="isSave" data-pe-attr="disabled:pending"><span class="ace-spinner" data-pe-document-spinner aria-hidden="true" data-pe-attr="hidden:spinnerHidden"></span><span>${labels.save}</span></button>
        <button type="submit" class="ace-control ace-delete btn btn-danger" data-pe-document-save data-pe-when="isDelete" data-pe-attr="disabled:pending"><span class="ace-spinner" data-pe-document-spinner aria-hidden="true" data-pe-attr="hidden:spinnerHidden"></span><span>${labels.delete}</span></button>
      </div>
    </form>
  </div>
</section></template>
<template data-pe-proto="contact-section"><section class="ace-section" data-pe-attr="data-pe-contract-contact-section:identifier">
  <div class="ace-header">
    <h3 class="ace-title" data-pe-slot="label"></h3>
    <button type="button" class="ace-control ace-add btn btn-sm btn-link" data-pe-contract-contact-add
      data-pe-attr="aria-controls:editorId aria-expanded:addExpanded disabled:addDisabled"><span data-test-icon="add"></span><span class="visually-hidden">${labels.add}</span></button>
  </div>
  <div class="ace-editor" data-pe-list="addEditor" data-pe-attr="hidden:addEditorHidden"></div>
  <div class="ace-itemlist" data-pe-list="rows" data-pe-attr="hidden:rowsHidden"></div>
  <p class="ace-empty" role="status" data-pe-attr="hidden:emptyHidden" data-pe-slot="emptyMessage"></p>
</section></template>
<template data-pe-proto="contact-row"><article class="ace-item"
  data-pe-attr="data-pe-contract-contact-item:uid data-pe-contract-contact-hidden:hidden">
  <div class="row">
  <template data-pe-list="summary"></template>
  <div class="col-12 col-md-auto">
  <div class="ace-actions">
    <span class="ace-state" data-pe-when="hidden">${labels.hidden}</span>
    <span class="ace-state" data-pe-when="managed">${labels.managed}</span>
    <div class="ace-controls" role="group" aria-label="${labels.contactActions}" data-pe-contract-contact-actions>
    <button type="button" class="ace-control ace-visibility btn btn-sm btn-link" data-pe-contract-contact-hide title="${labels.hide}" aria-label="${labels.hide}" data-pe-label-visible="${labels.hide}" data-pe-label-hidden="${labels.show}"><span data-pe-visibility-icon="visible" data-test-icon="visible"></span><span data-pe-visibility-icon="hidden" data-test-icon="hidden" hidden="hidden"></span></button>
    <button type="button" class="ace-control ace-view btn btn-sm btn-link" data-pe-contract-contact-view title="${labels.view}" aria-label="${labels.view}" data-pe-label-collapsed="${labels.view}" data-pe-label-expanded="${labels.viewClose}" data-pe-attr="aria-controls:editorId aria-expanded:viewExpanded"><span data-pe-view-icon="collapsed" data-test-icon="view"></span><span data-pe-view-icon="expanded" data-test-icon="view-close" hidden="hidden"></span></button>
    <button type="button" class="ace-control ace-sort-down btn btn-sm btn-link" data-pe-contract-contact-sort="down" title="${labels.sortDown}" aria-label="${labels.sortDown}"><span data-test-icon="move-down"></span></button>
    <button type="button" class="ace-control ace-sort-up btn btn-sm btn-link" data-pe-contract-contact-sort="up" title="${labels.sortUp}" aria-label="${labels.sortUp}"><span data-test-icon="move-up"></span></button>
    <button type="button" class="ace-control ace-delete btn btn-sm btn-link" data-pe-when="deletable" data-pe-contract-contact-delete title="${labels.delete}" aria-label="${labels.delete}" data-pe-attr="aria-controls:editorId aria-expanded:deleteExpanded"><span data-test-icon="delete"></span></button>
    <button type="button" class="ace-control ace-edit btn btn-sm btn-link" data-pe-when="editable" data-pe-contract-contact-edit title="${labels.edit}" aria-label="${labels.edit}" data-pe-attr="aria-controls:editorId aria-expanded:editExpanded"><span data-test-icon="edit"></span></button>
    </div>
  </div>
  </div>
  <div class="col-12">
    <div class="ace-editor" data-pe-list="editor" data-pe-attr="hidden:editorHidden"></div>
  </div>
  </div>
</article></template>
<template data-pe-proto="contact-summary-cell"><div class="col-12 col-md">
  <div class="ace-cell ace-value">
    <div class="ace-label" data-pe-slot="label"></div>
    <span data-pe-when="hasValue" data-pe-slot="value"></span>
    <span data-pe-when="isEmpty">—</span>
  </div>
</div></template>
<template data-pe-proto="contact-editor-panel"><section class="ace-item"
  data-pe-contract-contact-editor data-pe-contract-contact-form
  data-pe-attr="id:editorId aria-busy:busy">
  <h4 class="ace-title" tabindex="-1" data-pe-contract-contact-heading data-pe-slot="title"></h4>
  <div class="ace-message ace-error" data-pe-contract-contact-error role="alert" data-pe-attr="hidden:errorHidden" data-pe-slot="error"></div>
  <p class="ace-copy" data-pe-when="isDelete" data-pe-slot="deleteConfirmation"></p>
  <dl class="row" data-pe-when="showDisplay"><template data-pe-list="displayRows"></template></dl>
  <div class="row" data-pe-contract-contact-fields data-pe-when="showFields"><template data-pe-list="fields"></template></div>
  <div class="ace-actions" data-pe-when="showActions">
    <button type="button" class="ace-control ace-cancel btn btn-outline-secondary" data-pe-contract-contact-cancel data-pe-attr="disabled:pending">${labels.close}</button>
    <button type="button" class="ace-control ace-save btn btn-primary" data-pe-contract-contact-save data-pe-when="isSave" data-pe-attr="disabled:pending"><span class="ace-spinner" data-pe-contract-contact-spinner aria-hidden="true" data-pe-attr="hidden:spinnerHidden"></span><span>${labels.save}</span></button>
    <button type="button" class="ace-control ace-delete btn btn-danger" data-pe-contract-contact-save data-pe-when="isDelete" data-pe-attr="disabled:pending"><span class="ace-spinner" data-pe-contract-contact-spinner aria-hidden="true" data-pe-attr="hidden:spinnerHidden"></span><span>${labels.delete}</span></button>
  </div>
</section></template>`;

/**
 * `Partials/Profile/StatusToast.html:185-218` - the two live regions
 * `showStatus()` picks between, the assertive one for a failure and the polite
 * one for everything else.
 */
/**
 * `Partials/Profile/UnsavedChanges.html` - the dialog `editors.ts` clones when
 * the editor being closed holds changes.
 */
export const unsavedChangesDialog = (profileUid = 1): string => `
<template data-pe-dialog="unsaved-changes">
  <dialog class="ace-dialog" data-pe-unsaved-changes
    aria-labelledby="profile-editing-${profileUid}-unsaved-changes-title">
    <div class="ace-content">
      <h2 id="profile-editing-${profileUid}-unsaved-changes-title" class="ace-title">${messages.unsavedChangesTitle}</h2>
      <p class="ace-copy">${messages.unsavedChangesMessage}</p>
      <div class="ace-actions">
        <button type="button" class="ace-control ace-cancel btn btn-outline-secondary" data-pe-unsaved-choice="cancel">Keep editing</button>
        <button type="button" class="ace-control ace-discard btn btn-outline-danger" data-pe-unsaved-choice="discard">Discard changes</button>
        <button type="button" class="ace-control ace-save btn btn-primary" data-pe-unsaved-choice="save">Save and continue</button>
      </div>
    </div>
  </dialog>
</template>`;

export const statusToast = (): string => `
<div class="ace-messages">
  <div data-pe-status-toast="status" class="ace-message" role="status" aria-live="polite" aria-atomic="true">
    <div class="ace-header"><strong class="ace-title" data-pe-status-title></strong><button type="button" class="ace-control ace-close btn-close" data-pe-status-close aria-label="Close notification"></button></div>
    <div class="ace-content" data-pe-status-message></div>
  </div>
  <div data-pe-status-toast="alert" class="ace-message" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="ace-header"><strong class="ace-title" data-pe-status-title></strong><button type="button" class="ace-control ace-close btn-close" data-pe-status-close aria-label="Close notification"></button></div>
    <div class="ace-content" data-pe-status-message></div>
  </div>
</div>`;

/**
 * `Partials/Profile/Header.html` - the profile name, the synchronisation
 * switch, the visibility switch and the "edit all" toggle. `visible` is the
 * state of the visibility switch, which is on while the profile is public.
 */
export const profileHeader = ({
  profileUid = 1,
  nameFieldIds = "firstName lastName",
  name = "Ada Lovelace",
  skipSync = false,
  visible = true,
}: {
  profileUid?: number;
  nameFieldIds?: string;
  name?: string;
  skipSync?: boolean;
  visible?: boolean;
} = {}): string => `
<header class="ace-header" data-pe-profile-header>
  <h1 id="profile-editing-${profileUid}-name-heading" class="ace-title"
    data-pe-profile-name data-pe-profile-name-field-ids="${nameFieldIds}">${name}</h1>
  <div class="ace-actions">
  <form class="ace-form" data-pe-sync-form>
    <div class="ace-switch">
      <input class="ace-control ace-checkbox" data-pe-sync-checkbox="true"
        type="checkbox" name="skipSync" id="profile-editing-${profileUid}-skipSync" value="1"
        aria-describedby="profile-editing-${profileUid}-skipSync-error" aria-invalid="false"${skipSync ? " checked" : ""} />
      <label class="ace-label" for="profile-editing-${profileUid}-skipSync">Do not synchronise</label>
      <div id="profile-editing-${profileUid}-skipSync-error" class="ace-message" role="alert"></div>
    </div>
  </form>
  <form class="ace-form" data-pe-visibility-form>
    <div class="ace-switch">
      <input class="ace-control ace-checkbox" data-pe-visibility-checkbox="true"
        type="checkbox" name="visibility" id="profile-editing-${profileUid}-visibility" value="1"
        aria-describedby="profile-editing-${profileUid}-visibility-error" aria-invalid="false"${visible ? " checked" : ""} />
      <label class="ace-label" for="profile-editing-${profileUid}-visibility">Show my profile publicly</label>
      <div id="profile-editing-${profileUid}-visibility-error" class="ace-message" role="alert"></div>
    </div>
  </form>
  <button class="ace-control ace-edit btn btn-outline-secondary btn-sm" type="button"
    data-academic-persons-profile-editing-edit-all-btn
    data-pe-edit-all-label="Edit all"
    data-pe-close-all-label="Close all"
    aria-controls="profile-editing-${profileUid}-personal-form"
    aria-pressed="false">
    <span data-pe-edit-all-button-label>Edit all</span>
  </button>
  </div>
</header>`;

interface FieldOptions {
  identifier: string;
  profileUid?: number;
  value?: string;
  /** `field.propertyName`, which is what the endpoint payload is keyed by. */
  propertyName?: string;
  required?: boolean;
  disabled?: boolean;
  readOnly?: boolean;
}

/**
 * `Partials/Profile/Field/Editable.html:8-108` with
 * `Partials/Profile/Field/Preview.html`, `Control.html` and `Actions.html`
 * rendered into it: one text field with its preview, its editor and the three
 * per-field buttons.
 */
export const textField = ({
  identifier,
  profileUid = 1,
  value = "",
  propertyName,
  required = false,
  disabled = false,
  readOnly = false,
}: FieldOptions): string => {
  const elementId = `profile-editing-${profileUid}-${identifier}`;
  const name = propertyName ?? identifier;

  return `
<div class="col-12">
  <div class="ace-field-group" data-pe-field-wrapper>
    <div class="ace-preview" data-form-field-button-area data-pe-field-preview
      data-pe-for="${elementId}" data-empty-label="${messages.empty}">
      <div class="ace-content">
        <div class="ace-label">${identifier}</div>
        <div class="ace-value" data-pe-field-preview-content>${value}</div>
      </div>
      ${
        disabled || readOnly
          ? ""
          : `<button class="ace-control ace-edit btn btn-sm"
        data-academic-persons-profile-editing-activate-btn data-pe-for="${elementId}" type="button"
        aria-controls="${elementId}-editor" aria-expanded="false" title="Edit" aria-label="Edit"></button>`
      }
    </div>
    <div id="${elementId}-editor" class="ace-editor" hidden="hidden" data-pe-field-editor data-pe-for="${elementId}">
      <div class="ace-header">
        <label class="ace-label" for="${elementId}">${identifier}</label>
      </div>
      <div class="ace-controls" data-pe-field-control-group>
        <input class="ace-control ace-text" data-pe-field-control="true"
          type="text" name="${name}" id="${elementId}" value="${value}"
          aria-describedby="${elementId}-error" aria-invalid="false"${required ? " required" : ""}${disabled ? " disabled" : ""}${readOnly ? " readonly" : ""} />
        ${
          disabled || readOnly
            ? ""
            : fieldActions(elementId)
        }
      </div>
      <div id="${elementId}-error" class="ace-message" role="alert"></div>
    </div>
  </div>
</div>`;
};

/**
 * `Partials/Profile/Field/Editable.html:23-61` in its rich text branch, with
 * `Preview.html:184-207` and `Control.html:7-32`: the textarea CKEditor is
 * created on, its character counter and its sanitised preview.
 */
export const richTextField = ({
  identifier,
  profileUid = 1,
  value = "",
  propertyName,
  characterLimit,
  editorValue,
}: {
  identifier: string;
  profileUid?: number;
  value?: string;
  propertyName?: string;
  characterLimit?: number;
  /** What the editor makes of `value`; see the CKEditor stub. */
  editorValue?: string;
}): string => {
  const elementId = `profile-editing-${profileUid}-${identifier}`;

  return `
<div class="col-12">
  <div class="ace-field-group" data-pe-field-wrapper>
    <div class="ace-preview" data-form-field-button-area data-pe-field-preview
      data-pe-for="${elementId}" data-empty-label="${messages.empty}">
      <div class="ace-content">
        <div class="ace-label">${identifier}</div>
        <div class="ace-value" data-pe-field-preview-content data-pe-rich-text-preview
          data-pe-for="${elementId}" data-empty-label="${messages.empty}">
          <div class="${value === "" ? "" : "ce-bodytext"}" data-pe-rich-text-preview-content>${value}</div>
        </div>
      </div>
      <button class="ace-control ace-edit btn btn-sm"
        data-academic-persons-profile-editing-activate-btn data-pe-for="${elementId}" type="button"
        aria-controls="${elementId}-editor" aria-expanded="false" title="Edit" aria-label="Edit"></button>
    </div>
    <div id="${elementId}-editor" class="ace-editor" hidden="hidden" data-pe-field-editor data-pe-for="${elementId}">
      <div class="ace-header" data-pe-rich-text-heading>
        <label class="ace-label" for="${elementId}">${identifier}</label>
        ${fieldActions(elementId)}
      </div>
      <div class="ace-control-wrap" data-pe-editor-container>
        <textarea name="${propertyName ?? identifier}" id="${elementId}" rows="5"
          class="ace-control ace-textarea ace-ckeditor" data-pe-field-control="true"
          aria-describedby="${elementId}-error" aria-invalid="false"
          data-pe-rich-text="true"${characterLimit === undefined ? "" : ` data-pe-character-limit="${characterLimit}"`}${editorValue === undefined ? "" : ` data-test-ckeditor-initial="${editorValue}"`}>${value}</textarea>
        ${
          characterLimit === undefined
            ? ""
            : `<div id="${elementId}-character-counter" class="ace-counter" aria-live="polite"
          data-pe-character-counter data-pe-for="${elementId}">0 / ${characterLimit}</div>`
        }
      </div>
      <div id="${elementId}-error" class="ace-message" role="alert"></div>
    </div>
  </div>
</div>`;
};

/** `Partials/Profile/Field/Actions.html:114-162` - clear, undo and save. */
export const fieldActions = (elementId: string): string => `
<div class="ace-actions btn-group btn-group-sm" data-pe-field-actions data-pe-for="${elementId}"
  role="group" hidden="hidden" aria-label="Actions">
  <button class="ace-control ace-clear btn btn-outline-danger" data-pe-dismiss data-pe-for="${elementId}" type="button" title="Clear" aria-label="Clear"></button>
  <button class="ace-control ace-undo btn btn-outline-secondary" data-pe-cancel data-pe-for="${elementId}" type="button" title="Undo" aria-label="Undo"></button>
  <button class="ace-control ace-save btn btn-success" data-pe-save data-pe-for="${elementId}" type="button" title="Save" aria-label="Save"></button>
</div>`;

/**
 * `Partials/Profile/Field/Checkbox.html:73-140` with
 * `Partials/Profile/Field/AutosaveUndo.html:147-161` - the visibility switch,
 * which saves on change and has no save button of its own.
 */
export const checkboxField = ({
  identifier,
  profileUid = 1,
  checked = false,
  propertyName,
}: {
  identifier: string;
  profileUid?: number;
  checked?: boolean;
  propertyName?: string;
}): string => {
  const elementId = `profile-editing-${profileUid}-${identifier}`;

  return `
<div class="col-12">
  <div class="ace-field-group" data-pe-field-wrapper>
    <div class="ace-preview" data-form-field-button-area data-pe-field-preview
      data-pe-for="${elementId}" data-empty-label="${messages.empty}">
      <div class="ace-content">
        <div class="ace-label">${identifier}</div>
        <div class="ace-value" data-pe-field-preview-content></div>
      </div>
      <button class="ace-control ace-edit btn btn-sm"
        data-academic-persons-profile-editing-activate-btn data-pe-for="${elementId}" type="button"
        aria-controls="${elementId}-editor" aria-expanded="false" title="Edit" aria-label="Edit"></button>
    </div>
    <div id="${elementId}-editor" class="ace-editor" hidden="hidden" data-pe-field-editor data-pe-for="${elementId}">
      <div class="ace-switch" data-pe-field-control-group>
        <div class="ace-controls">
          <input class="ace-control ace-checkbox" data-pe-field-control="true" type="checkbox"
            name="${propertyName ?? identifier}" id="${elementId}" value="1"${checked ? " checked" : ""}
            aria-describedby="${elementId}-error" aria-invalid="false"
            data-pe-autosave-on-change="true"
            data-pe-checked-label="Public"
            data-pe-unchecked-label="Private" />
          <label class="ace-label" for="${elementId}">${identifier}</label>
          <button class="ace-control ace-undo btn btn-sm btn-outline-secondary" data-pe-autosave-undo
            data-pe-cancel data-pe-for="${elementId}" type="button" title="Undo" aria-label="Undo"></button>
        </div>
        <div id="${elementId}-error" class="ace-message" role="alert"></div>
      </div>
    </div>
  </div>
</div>`;
};

/**
 * `Partials/Profile/Field/Group.html:9-140` - several fields behind one
 * preview, with the group's own edit, clear, undo and save buttons.
 */
export const fieldGroup = ({
  identifier,
  profileUid = 1,
  fields,
  displayFieldIds,
  displayMode = "join",
}: {
  identifier: string;
  profileUid?: number;
  fields: { identifier: string; value?: string; propertyName?: string }[];
  displayFieldIds?: string;
  displayMode?: "join" | "first";
}): string => {
  const groupId = `profile-editing-${profileUid}-${identifier}-group`;
  const fieldIds = fields
    .map((field): string => `profile-editing-${profileUid}-${field.identifier}`)
    .join(" ");
  const controls = fields
    .map((field): string => {
      const elementId = `profile-editing-${profileUid}-${field.identifier}`;

      return `
        <div class="col-12">
          <div class="ace-field" data-pe-group-control>
            <div class="ace-header">
              <label class="ace-label" for="${elementId}">${field.identifier}</label>
            </div>
            <input class="ace-control ace-text" data-pe-field-control="true" type="text"
              name="${field.propertyName ?? field.identifier}" id="${elementId}" value="${field.value ?? ""}"
              aria-describedby="${elementId}-error" aria-invalid="false" />
            <div id="${elementId}-error" class="ace-message" role="alert"></div>
          </div>
        </div>`;
    })
    .join("");

  return `
<div class="col-12">
  <div class="ace-field-group" data-pe-field-group data-pe-field-ids="${fieldIds}"
    data-pe-display-field-ids="${displayFieldIds ?? fieldIds}" data-pe-display-mode="${displayMode}">
    <div class="ace-preview" data-pe-group-preview>
      <div class="ace-content">
        <div class="ace-label">${identifier}</div>
        <div class="ace-value" data-pe-group-preview-content data-empty-label="${messages.empty}"></div>
      </div>
      <button class="ace-control ace-edit btn btn-sm" data-pe-group-edit type="button"
        aria-controls="${groupId}-editor" aria-expanded="false" title="Edit" aria-label="Edit"></button>
    </div>
    <div id="${groupId}-editor" class="ace-editor" hidden="hidden" data-pe-group-editor>
      <div class="row">${controls}</div>
      <div class="ace-actions" data-pe-group-actions>
        <div class="btn-group btn-group-sm" role="group" aria-label="Actions">
          <button class="ace-control ace-clear btn btn-outline-danger" data-pe-group-dismiss type="button" title="Clear" aria-label="Clear"></button>
          <button class="ace-control ace-undo btn btn-outline-secondary" data-pe-group-cancel type="button" title="Undo" aria-label="Undo"></button>
          <button class="ace-control ace-save btn btn-success" data-pe-group-save type="button" title="Save" aria-label="Save"></button>
        </div>
      </div>
    </div>
  </div>
</div>`;
};

/**
 * `Partials/Profile/Field/FormActions.html` - the controls of full form
 * editing, rendered at the end of every fields form and delivered `hidden`.
 * The three buttons stand in tab order: apply, undo, discard.
 */
export const formActions = (sectionLabel = "Personal data"): string => `
<div class="col-12">
<div class="ace-actions"
  data-pe-form-actions data-pe-form-reverted-message="${messages.formReverted}"
  role="group" aria-label="Form actions: ${sectionLabel}" hidden>
  <button class="ace-control ace-apply btn btn-success" data-pe-form-apply type="button" title="Save every changed field of this profile">Apply</button>
  <button class="ace-control ace-undo btn btn-outline-secondary" data-pe-form-undo type="button" title="Restore every field to the saved value and continue editing">Undo</button>
  <button class="ace-control ace-discard btn btn-outline-danger" data-pe-form-discard type="button" title="Restore every field to the saved value and close the form">Discard</button>
</div>
</div>`;

/**
 * `Partials/Profile/Profile/Personal.html:15-31` with
 * `Profile/Fields.html` rendered into it - the form the fields sit in, and the
 * form action bar the field partial renders at its end.
 */
export const fieldsForm = (
  fields: string,
  formId = "personal",
  sectionLabel = "Personal data",
): string => `
<form id="profile-editing-1-${formId}-form" class="ace-form" data-pe-fields-form>
  <fieldset class="ace-fieldset">
    <div class="row">${fields}${formActions(sectionLabel)}</div>
  </fieldset>
</form>`;

interface DocumentRowOptions {
  uid: number;
  sorting?: number;
  position?: number;
  title?: string;
  link?: string;
  yearStart?: string;
  bodytext?: string;
  actions?: string[];
  sortable?: boolean;
  hidden?: boolean;
}

/**
 * `Partials/Profile/Documents/ProfileInformationRow.html:187-254` with
 * `Actions.html:85-181` - one list row with its value cells, its action group
 * and the collapse target the editor is teleported into.
 */
export const documentRow = ({
  uid,
  sorting = 0,
  position = 0,
  title = "",
  link = "",
  yearStart = "",
  bodytext = "",
  actions = ["view", "down", "up", "delete", "edit"],
  sortable = true,
  hidden = false,
}: DocumentRowOptions): string => `
<article class="ace-item" data-pe-document-item
  data-item-uid="${uid}" data-item-sorting="${sorting}" data-item-position="${position}"${hidden ? ' data-item-hidden="1"' : ""}>
  <div class="row">
  <div class="col-12 col-md-2">
    <div class="ace-cell"><div class="ace-value" data-pe-document-value="yearStart">${yearStart}</div></div>
  </div>
  <div class="col-12 col-md">
    <div class="ace-cell"><div class="ace-value" data-pe-document-title>${link === "" ? `<span>${title}</span>` : `<a href="${link}" target="_blank" rel="noopener noreferrer">${title}</a>`}</div></div>
  </div>
  <div class="col-12">
    <div class="ace-cell"><div class="ace-value"${bodytext === "" ? ' hidden="hidden"' : ""} data-pe-document-value="bodytext"><div class="${bodytext === "" ? "" : "ce-bodytext"}" data-pe-document-rich-text>${bodytext}</div></div></div>
  </div>
  ${documentActions(actions, sortable, hidden)}
  <div class="col-12"><div class="ace-collapse" data-pe-document-item-collapse-target></div></div>
  </div>
</article>`;

/** `Partials/Profile/Documents/Actions.html:85-181`. */
export const documentActions = (
  actions: string[],
  sortable: boolean,
  hidden = false,
): string => {
  const buttons: Record<string, string> = {
    hide: `<button type="button" class="ace-control ace-visibility-toggle btn btn-sm btn-link" data-pe-document-hide title="${hidden ? labels.show : labels.hide}" aria-label="${hidden ? labels.show : labels.hide}" data-pe-label-visible="${labels.hide}" data-pe-label-hidden="${labels.show}"><span data-pe-visibility-icon="visible" data-test-icon="visible"${hidden ? ' hidden="hidden"' : ""}></span><span data-pe-visibility-icon="hidden" data-test-icon="hidden"${hidden ? "" : ' hidden="hidden"'}></span></button>`,
    view: `<button type="button" class="ace-control ace-view-toggle btn btn-sm btn-link" title="${labels.view}" aria-label="${labels.view}" aria-expanded="false" data-pe-label-collapsed="${labels.view}" data-pe-label-expanded="${labels.viewClose}" data-pe-document-view><span data-pe-view-icon="collapsed" data-test-icon="view"></span><span data-pe-view-icon="expanded" data-test-icon="view-close" hidden="hidden"></span></button>`,
    down: `<button type="button" class="ace-control ace-sort-down btn btn-sm btn-link" title="Move down" aria-label="Move down" data-pe-document-sort="down"></button>`,
    up: `<button type="button" class="ace-control ace-sort-up btn btn-sm btn-link" title="Move up" aria-label="Move up" data-pe-document-sort="up"></button>`,
    delete: `<button type="button" class="ace-control ace-delete btn btn-sm btn-link" title="Delete" aria-label="Delete" aria-expanded="false" data-pe-document-delete></button>`,
    edit: `<button type="button" class="ace-control ace-edit btn btn-sm btn-link" title="Edit" aria-label="Edit" aria-expanded="false" data-pe-document-edit></button>`,
  };

  return `
  <div class="col-12 col-md-auto">
  <div class="ace-actions">
    <span class="ace-state" data-pe-document-hidden-badge${hidden ? "" : ' hidden="hidden"'}>${labels.hidden}</span>
    <div class="ace-controls" role="group" aria-label="Actions" data-pe-document-actions>
    ${sortable ? `<button type="button" class="ace-control ace-sort ace-sort-handle btn btn-sm btn-link" title="Sort" aria-label="Sort" draggable="true" data-pe-document-drag></button>` : ""}
    ${actions.map((action): string => buttons[action] ?? "").join("")}
    </div>
  </div>
  </div>`;
};

/**
 * `Partials/Profile/Documents/Sections.html:14-77` with
 * `ProfileInformation.html:5-19` and `Header.html:45-85`: one section, its list,
 * its row template, its list header and its empty state.
 */
export const documentSection = ({
  identifier,
  profileUid = 1,
  kind = "profileInformation",
  sortable = true,
  rows = "",
  canCreate = true,
  actions = ["view", "down", "up", "delete", "edit"],
}: {
  identifier: string;
  profileUid?: number;
  kind?: string;
  sortable?: boolean;
  rows?: string;
  canCreate?: boolean;
  actions?: string[];
}): string => `
<section class="ace-section" aria-labelledby="profile-editing-${profileUid}-document-section-${identifier}-heading"
  data-pe-document-section
  data-section-key="${identifier}"
  data-section-readonly="0"
  data-section-sortable="${sortable ? "1" : "0"}"
  data-section-kind="${kind}">
  <div class="ace-header" data-pe-document-section-header>
    <h2 id="profile-editing-${profileUid}-document-section-${identifier}-heading" class="ace-title">${identifier}</h2>
    ${canCreate ? `<button type="button" class="ace-control ace-add btn btn-sm btn-link" aria-expanded="false" data-pe-document-add></button>` : ""}
  </div>
  <div data-pe-document-add-collapse-target></div>
  <div class="ace-header ace-label" data-pe-document-list-header${rows === "" ? ' hidden="hidden"' : ""}><div class="row"></div></div>
  <div class="ace-itemlist" data-pe-document-items>${rows}</div>
  <div hidden="hidden" aria-hidden="true" data-pe-document-item-template>
    ${documentRow({ uid: 0, actions, sortable })}
  </div>
  <div class="ace-empty" role="status"${rows === "" ? "" : ' hidden="hidden"'} data-pe-document-empty-state>No entries yet.</div>
</section>`;

/**
 * What `<academic-persons-edit-document-editor>` renders for `mode: "add"` or
 * `"edit"`: the view container the controller queries for its rich text fields
 * and for the field to focus.
 *
 * It is a fixture rather than the element itself because the files that use it
 * drive the controller without a registry. The controls carry the ids and the
 * `data-pe-document-field` hooks the element builds from the response's
 * `fields`, so the same response drives the markup here and the markup the
 * element renders in `document-editor-element.test.ts`.
 */
export const documentEditorView = ({
  fields,
  heading = "Add: Publications",
}: {
  fields: {
    name: string;
    type?: "text" | "textarea" | "checkbox";
    richText?: boolean;
    disabled?: boolean;
    value?: string;
  }[];
  heading?: string;
}): string => {
  const controls = fields
    .map((field, index): string => {
      const id = `profile-editing-document-field-${index}-${field.name}`;
      if (field.type === "textarea") {
        return `<textarea class="ace-control ace-textarea" data-pe-field-control="true" rows="6" id="${id}" name="${field.name}"
          data-pe-document-field="${field.name}"${field.richText === true ? ' data-pe-rich-text=""' : ""}${field.disabled === true ? " disabled" : ""}>${field.value ?? ""}</textarea>`;
      }
      if (field.type === "checkbox") {
        return `<input class="ace-control ace-checkbox" data-pe-field-control="true" type="checkbox" id="${id}" name="${field.name}"
          data-pe-document-field="${field.name}"${field.disabled === true ? " disabled" : ""} />`;
      }

      return `<input class="ace-control ace-text" data-pe-field-control="true" type="text" id="${id}" name="${field.name}" value="${field.value ?? ""}"
        data-pe-document-field="${field.name}"${field.disabled === true ? " disabled" : ""} />`;
    })
    .join("\n");

  return `
<section class="ace-document-editor" data-pe-document-view-container>
  <form class="ace-form" data-pe-document-form>
    <h2 class="ace-title" tabindex="-1" data-pe-document-heading>${heading}</h2>
    <div class="row" data-pe-document-fields>
      ${controls}
    </div>
  </form>
</section>`;
};

/**
 * `Partials/Profile/Image/Card.html:15-102` - the preview the upload and the
 * deletion write into, and the button focus returns to when the editor closes.
 */
export const imageCard = ({
  profileUid = 1,
  src = "/fileadmin/_processed_/profile.jpg",
  alt = "Ada Lovelace",
  title = "Ada Lovelace",
}: {
  profileUid?: number;
  src?: string;
  alt?: string;
  title?: string;
} = {}): string => `
<div class="col-12 col-lg-4" data-pe-image-preview-column>
  <div class="ace-image-column">
  <div class="ace-sticky-image" data-pe-sticky-image>
    <section class="ace-section" aria-labelledby="profile-editing-${profileUid}-image-heading">
      <div data-pe-image-preview>
        <figure class="ace-figure">
          <picture class="ace-picture">
            <source srcset="${src}.webp" type="image/webp" media="(min-width: 992px)" />
            <img src="${src}" alt="${alt}" class="ace-image" title="${title}" loading="lazy" />
          </picture>
        </figure>
      </div>
      <button class="ace-control ace-edit btn btn-outline-secondary btn-sm" type="button" data-pe-open-image-view
        aria-expanded="false" aria-controls="profile-editing-${profileUid}-image-view"
        title="Edit image" aria-label="Edit image"></button>
    </section>
  </div>
  </div>
</div>`;

/**
 * `Templates/Profile/Index.html:146-154` - the column beside the image card,
 * which widens to the full row while the editor is open.
 */
export const profileFieldsColumn = (content = ""): string => `
<div class="col-12 col-lg-8" data-pe-profile-fields-column><div class="ace-fields-column">${content}</div></div>`;

/**
 * `Partials/Profile/Image/Editor.html` - the upload form, its preview, the
 * cropper stage and the delete actions, in the state a page is delivered in:
 * closed, idle, without an error.
 *
 * The `<f:form>` is transcribed with its hidden fields. They are not decoration:
 * the property mapper validates the upload against the `__trustedProperties`
 * signature, nothing in a browser can recompute it, and it is the reason the
 * editor stays server rendered. A test asserts that they leave with the request.
 */
export const imageEditorView = ({
  profileUid = 1,
  action = endpoints.uploadImage,
}: { profileUid?: number; action?: string } = {}): string => `
<section id="profile-editing-${profileUid}-image-view"
  class="ace-image-editor" aria-busy="false" hidden="hidden"
  data-pe-image-view-container>
  <div class="ace-content">
    <form action="${action}" method="post" enctype="multipart/form-data"
      class="ace-form" data-pe-image-form="true">
      <div>
        <input type="hidden" name="tx_academicpersonsedit_profile[__referrer][@extension]" value="AcademicPersonsEdit" />
        <input type="hidden" name="tx_academicpersonsedit_profile[__referrer][@controller]" value="Profile" />
        <input type="hidden" name="tx_academicpersonsedit_profile[__referrer][@action]" value="edit" />
        <input type="hidden" name="tx_academicpersonsedit_profile[__referrer][arguments]" value="YTowOnt9ecf6f1" />
        <input type="hidden" name="tx_academicpersonsedit_profile[__trustedProperties]"
          value="a:1:{s:7:&quot;profile&quot;;a:1:{s:5:&quot;image&quot;;i:1;}}5f3c2a" />
      </div>
      <div class="ace-header">
        <h2 class="ace-title" tabindex="-1" data-pe-image-editor-heading>Profile image</h2>
        <div class="ace-actions" data-pe-image-delete-actions>
          <button class="ace-control ace-delete btn btn-danger" type="button" data-pe-delete-image title="Delete the image">Delete</button>
          <span class="ace-message" hidden="hidden" data-pe-delete-image-confirm-question>Delete this image?</span>
          <button class="ace-control ace-cancel btn btn-outline-secondary" type="button" hidden="hidden"
            data-pe-cancel-delete-image>Cancel</button>
          <button class="ace-control ace-delete btn btn-danger" type="button" hidden="hidden" data-pe-confirm-delete-image
            title="Delete the image">Delete</button>
        </div>
      </div>
      <fieldset data-pe-image-fieldset>
        <div class="ace-preview" data-pe-image-view-preview>
          <div class="ace-image-cropper" hidden="hidden" data-pe-image-cropper-stage>
            <img alt="" data-pe-image-cropper-source />
          </div>
          <img class="ace-image" alt="" hidden="hidden" data-pe-image-selected-preview />
        </div>
        <label class="ace-label" for="profile-editing-${profileUid}-image">Image</label>
        <div>
          <input class="ace-control ace-upload" data-pe-image-upload-control type="file" name="tx_academicpersonsedit_profile[profile][image]"
            id="profile-editing-${profileUid}-image" accept="image/jpeg,image/png"
            aria-describedby="profile-editing-${profileUid}-image-error" aria-invalid="false" required />
        </div>
      </fieldset>
      <div id="profile-editing-${profileUid}-image-error" class="ace-message ace-error" role="alert"
        hidden="hidden" data-pe-image-error></div>
      <div class="ace-actions">
        <button class="ace-control ace-cancel btn btn-outline-secondary" type="button" data-pe-close-image-view>Cancel</button>
        <button class="ace-control ace-save btn btn-primary" type="submit" disabled data-pe-upload-image>
          <span class="ace-spinner" aria-hidden="true" hidden="hidden"
            data-pe-image-upload-spinner></span>Save</button>
      </div>
    </form>
  </div>
</section>`;

/**
 * The same partial with the element that drives it, which is how
 * `Templates/Profile/Index.html` renders it: inside the image editor target,
 * so pass it as `target` rather than as `content`.
 */
export const imageEditor = (
  options: { profileUid?: number; action?: string } = {},
): string => `
<academic-persons-edit-image-editor>${imageEditorView(options)}</academic-persons-edit-image-editor>`;

/**
 * The one query helper every test file needs: a `querySelector` that fails with
 * the selector in the message rather than handing back a `null` that turns into
 * an assertion about `undefined` three lines later.
 */
export const select = <T extends Element>(
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

/** The same for a list, so a test can index into it without a cast. */
export const selectAll = <T extends Element>(
  scope: ParentNode,
  selector: string,
  type: new () => T,
): T[] =>
  Array.from(scope.querySelectorAll(selector)).map((element): T => {
    if (!(element instanceof type)) {
      throw new Error(`The test markup has a "${selector}" of the wrong kind.`);
    }

    return element;
  });

/**
 * The whole page one editor stands on: the owner element, the root below it,
 * and the contract the owner read when it started.
 *
 * Every element of the editor resolves its editing context by walking up to
 * `<academic-persons-edit-profile-editing>` and reading its `context`
 * property. A fixture that stopped at the root would therefore be a page no
 * visitor ever receives: a rich text field a document editor clones would find
 * no context and create no CKEditor. The owner is not registered here - the
 * property is the whole contract, and a test of one element should not have to
 * start the whole editor to get it.
 */
export const editingHost = (
  options: RootOptions = {},
): { context: EditingContext; owner: HTMLElement; root: HTMLElement } => {
  const body = resetBody(profileEditingElement(options));
  const owner = select(
    body,
    "academic-persons-edit-profile-editing",
    HTMLElement,
  ) as HTMLElement & { context?: EditingContext };
  const root = select(
    owner,
    "[data-academic-persons-profile-editing]",
    HTMLElement,
  );
  const context = readEditingContext(root);
  owner.context = context;

  return { context, owner, root };
};
