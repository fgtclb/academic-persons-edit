/**
 * The contract between `Templates/Profile/Index.html` and the profile editing
 * modules, read once.
 *
 * The template puts the whole configuration of an editor on its root element:
 * fourteen endpoint urls, seven state values, twenty-four translated messages
 * and six labels. Until now every module read them straight off
 * `root.dataset` at the moment it needed one - the same forty-odd attributes,
 * re-read and re-coerced on every status message, every request and every
 * editor that opens.
 *
 * `readEditingContext()` does that once, at start-up, and hands the frozen
 * result down. Three things follow from it:
 *
 * - A misspelled key is a `typecheckJs` failure. `HTMLElement.dataset` is a
 *   `DOMStringMap`, so `root.dataset.messageSavng` type checks and reads
 *   `undefined` forever; `ProfileEditingContract` below is the same closed-type
 *   mechanism `ProfileEditingHooks` uses for the per-element `data-pe-*` hooks,
 *   applied to the root's own vocabulary.
 * - The coercions live in one place. Whether `data-profile-uid` is an integer
 *   and whether `data-editor-language` is trimmed was decided per call site
 *   before; it is decided here now.
 * - An element can be handed the contract as a property. That is what the
 *   custom elements need: an element below the root neither knows nor asks
 *   which element the attributes came from.
 *
 * ## Why the strings are `string | undefined` and not defaulted here
 *
 * The fallback for a missing message is *not* uniform, and it is meaningful:
 * `showStatus(context, "warning", context.messages.validation ?? null)` falls
 * back to the severity's own default text, while
 * `image.error = context.messages.validation ?? ""` falls back to no error at
 * all. Defaulting to `""` here would silently turn the first into the second.
 * The reader therefore returns what the attribute holds and each call site
 * keeps the fallback it has today.
 *
 * Only the values with a single, unambiguous reading are coerced: the profile
 * uid, the editor language, the image render type and `data-has-image`.
 */
import { profileEditingElementName } from "@fgtclb/academic-persons-edit/frontend/profile/elements/names.js";


/**
 * The root's `data-*` contract, spelled as the `dataset` keys the attributes
 * become - `data-sort-contract-contact-url` is `sortContractContactUrl`.
 *
 * Deliberately separate from `ProfileEditingHooks` of `common.ts`, which
 * describes the `data-pe-*` hooks of *any* element below the root. The two
 * vocabularies are disjoint and are read of different elements: merging them
 * would make `hooks(button).messageSaving` type check on a button that can
 * never carry it.
 */
type ProfileEditingContract = {
  contractContactFormUrl?: string;
  createContractContactUrl?: string;
  createDocumentUrl?: string;
  deleteContractContactUrl?: string;
  deleteDocumentUrl?: string;
  deleteImageUrl?: string;
  documentFormUrl?: string;
  editorLanguage?: string;
  hasImage?: string;
  imageCropperRatio?: string;
  imageRenderType?: string;
  labelDocumentAdd?: string;
  labelDocumentDelete?: string;
  labelDocumentEdit?: string;
  labelDocumentEmpty?: string;
  labelDocumentSave?: string;
  labelDocumentView?: string;
  messageContractContactDeleteConfirm?: string;
  messageContractContactEmpty?: string;
  messageContractContactHidden?: string;
  messageContractContactShown?: string;
  messageDiscarded?: string;
  messageDocumentDeleteConfirm?: string;
  messageDocumentDeleted?: string;
  messageDocumentSaved?: string;
  messageDocumentSorted?: string;
  messageEditorError?: string;
  messageErrorMessage?: string;
  messageErrorTitle?: string;
  messageImageDeleted?: string;
  messageImageUploadMissing?: string;
  messageImageUploaded?: string;
  messageInfoMessage?: string;
  messageInfoTitle?: string;
  messageSaveInProgress?: string;
  messageSaving?: string;
  messageSuccessMessage?: string;
  messageSuccessTitle?: string;
  messageUnchanged?: string;
  messageValidation?: string;
  messageWarningTitle?: string;
  placeholderImageAlt?: string;
  placeholderImageUrl?: string;
  profileUid?: string;
  skipSyncUrl?: string;
  sortContractContactUrl?: string;
  sortDocumentUrl?: string;
  toggleContractContactVisibilityUrl?: string;
  updateContractContactUrl?: string;
  updateDocumentUrl?: string;
  updateUrl?: string;
};

/** The writing endpoints, named after the action rather than the attribute. */
export interface EditingUrls {
  readonly contractContactForm: string | undefined;
  readonly createContractContact: string | undefined;
  readonly createDocument: string | undefined;
  readonly deleteContractContact: string | undefined;
  readonly deleteDocument: string | undefined;
  readonly deleteImage: string | undefined;
  readonly documentForm: string | undefined;
  readonly sortContractContact: string | undefined;
  readonly sortDocument: string | undefined;
  readonly skipSync: string | undefined;
  readonly toggleContractContactVisibility: string | undefined;
  readonly update: string | undefined;
  readonly updateContractContact: string | undefined;
  readonly updateDocument: string | undefined;
}

/**
 * What the template knows about the profile image.
 *
 * `hasImage` is the state at start-up. The image controller writes
 * `data-has-image` back when an upload or a deletion changes it, because the
 * attribute is what the rendered markup exposes - the controller's own
 * reactive state is what it reads afterwards, exactly as before.
 *
 * `cropperRatio` stays the raw attribute: the cropper accepts `4:3`, `4/3`,
 * `4x3` and a plain number alike, and that parsing belongs to the module that
 * owns the cropper.
 */
export interface EditingImage {
  readonly cropperRatio: string | undefined;
  readonly hasImage: boolean;
  readonly placeholderAlt: string | undefined;
  readonly placeholderUrl: string | undefined;
  readonly renderType: string;
}

/** The twenty-four translated status messages, keyed by what they say. */
export interface EditingMessages {
  readonly contractContactDeleteConfirm: string | undefined;
  readonly contractContactEmpty: string | undefined;
  readonly contractContactHidden: string | undefined;
  readonly contractContactShown: string | undefined;
  readonly discarded: string | undefined;
  readonly documentDeleteConfirm: string | undefined;
  readonly documentDeleted: string | undefined;
  readonly documentSaved: string | undefined;
  readonly documentSorted: string | undefined;
  readonly editorError: string | undefined;
  readonly errorMessage: string | undefined;
  readonly errorTitle: string | undefined;
  readonly imageDeleted: string | undefined;
  readonly imageUploadMissing: string | undefined;
  readonly imageUploaded: string | undefined;
  readonly infoMessage: string | undefined;
  readonly infoTitle: string | undefined;
  readonly saveInProgress: string | undefined;
  readonly saving: string | undefined;
  readonly successMessage: string | undefined;
  readonly successTitle: string | undefined;
  readonly unchanged: string | undefined;
  readonly validation: string | undefined;
  readonly warningTitle: string | undefined;
}

/**
 * The action labels of the editors that are rendered in the browser.
 *
 * Only the ones a *value* is composed from: the heading of a contact editor is
 * `${documentAdd} ${section.singularLabel}`, and an empty display value shows
 * `documentEmpty`. Every label that is only ever *rendered* - the two sort
 * arrows, the close, save and delete buttons, the add control of a contact
 * section - is written by Fluid into the prototype that carries it, so it is
 * overridable with the markup around it and never travels as a `data-label-*`
 * attribute.
 */
export interface EditingLabels {
  readonly documentAdd: string | undefined;
  readonly documentDelete: string | undefined;
  readonly documentEdit: string | undefined;
  readonly documentEmpty: string | undefined;
  readonly documentSave: string | undefined;
  readonly documentView: string | undefined;
}

/**
 * The parsed contract of one profile editing root, frozen.
 *
 * `root` is part of it: every module that reads the contract also queries the
 * markup below the same element, and carrying the two together is what lets a
 * function take one argument instead of two that could disagree.
 */
export interface EditingContext {
  readonly editorLanguage: string;
  readonly image: EditingImage;
  readonly labels: EditingLabels;
  readonly messages: EditingMessages;
  readonly profileUid: number | null;
  /**
   * `data-profile-uid` verbatim, `""` when the attribute is absent.
   *
   * The profile fields carry element ids of the shape
   * `profile-editing-{profile.uid}-{name}`, written by Fluid from the same
   * value, and `fields.ts` rebuilds them to look one up. Normalising the
   * attribute here would build an id the markup does not contain whenever the
   * two disagree, so the raw value is kept next to the validated
   * `profileUid` the endpoints are called with. That the two can disagree at
   * all is a property of a malformed root and is left as it is.
   */
  readonly profileUidValue: string;
  readonly root: HTMLElement;
  readonly urls: EditingUrls;
}

/**
 * What an entry point accepts.
 *
 * `profile.ts` builds the context once and passes it to every controller. A
 * caller that has only the element - a test, or a second editor discovered
 * later - passes that instead and the entry point reads the contract itself.
 */
export type EditingTarget = HTMLElement | EditingContext;

const parseProfileUid = (value: string | undefined): number | null => {
  const profileUid = Number.parseInt(value ?? "", 10);
  return Number.isInteger(profileUid) && profileUid > 0 ? profileUid : null;
};

export const readEditingContext = (root: HTMLElement): EditingContext => {
  const contract = root.dataset as ProfileEditingContract;
  return Object.freeze({
    editorLanguage: (contract.editorLanguage ?? "").trim().toLowerCase(),
    image: Object.freeze({
      cropperRatio: contract.imageCropperRatio,
      hasImage: contract.hasImage === "1",
      placeholderAlt: contract.placeholderImageAlt,
      placeholderUrl: contract.placeholderImageUrl,
      renderType: (contract.imageRenderType ?? "").toLowerCase(),
    }),
    labels: Object.freeze({
      documentAdd: contract.labelDocumentAdd,
      documentDelete: contract.labelDocumentDelete,
      documentEdit: contract.labelDocumentEdit,
      documentEmpty: contract.labelDocumentEmpty,
      documentSave: contract.labelDocumentSave,
      documentView: contract.labelDocumentView,
    }),
    messages: Object.freeze({
      contractContactDeleteConfirm: contract.messageContractContactDeleteConfirm,
      contractContactEmpty: contract.messageContractContactEmpty,
      contractContactHidden: contract.messageContractContactHidden,
      contractContactShown: contract.messageContractContactShown,
      discarded: contract.messageDiscarded,
      documentDeleteConfirm: contract.messageDocumentDeleteConfirm,
      documentDeleted: contract.messageDocumentDeleted,
      documentSaved: contract.messageDocumentSaved,
      documentSorted: contract.messageDocumentSorted,
      editorError: contract.messageEditorError,
      errorMessage: contract.messageErrorMessage,
      errorTitle: contract.messageErrorTitle,
      imageDeleted: contract.messageImageDeleted,
      imageUploadMissing: contract.messageImageUploadMissing,
      imageUploaded: contract.messageImageUploaded,
      infoMessage: contract.messageInfoMessage,
      infoTitle: contract.messageInfoTitle,
      saveInProgress: contract.messageSaveInProgress,
      saving: contract.messageSaving,
      successMessage: contract.messageSuccessMessage,
      successTitle: contract.messageSuccessTitle,
      unchanged: contract.messageUnchanged,
      validation: contract.messageValidation,
      warningTitle: contract.messageWarningTitle,
    }),
    profileUid: parseProfileUid(contract.profileUid),
    profileUidValue: contract.profileUid ?? "",
    root,
    urls: Object.freeze({
      contractContactForm: contract.contractContactFormUrl,
      createContractContact: contract.createContractContactUrl,
      createDocument: contract.createDocumentUrl,
      deleteContractContact: contract.deleteContractContactUrl,
      deleteDocument: contract.deleteDocumentUrl,
      deleteImage: contract.deleteImageUrl,
      documentForm: contract.documentFormUrl,
      skipSync: contract.skipSyncUrl,
      sortContractContact: contract.sortContractContactUrl,
      sortDocument: contract.sortDocumentUrl,
      toggleContractContactVisibility: contract.toggleContractContactVisibilityUrl,
      update: contract.updateUrl,
      updateContractContact: contract.updateContractContactUrl,
      updateDocument: contract.updateDocumentUrl,
    }),
  });
};

export const toEditingContext = (target: EditingTarget): EditingContext =>
  target instanceof HTMLElement ? readEditingContext(target) : target;

/**
 * The contract of the profile editor an element stands in, or `null` for one
 * that stands in none.
 *
 * The context is read once, by the root element, and handed down as a property
 * - so this is only the fallback for an element that no caller assigned one to,
 * which is every element Fluid renders. It reads the owner's documented
 * `context` property through the tag name rather than through an `instanceof`
 * on purpose: the element modules would otherwise have to import the root
 * element module, which imports the document editing that creates two of them,
 * and the cycle buys nothing the tag name does not already guarantee.
 */
export const ownerEditingContext = (element: Element): EditingContext | null => {
  const owner = element.closest(profileEditingElementName) as
    | (Element & { context?: EditingContext | null })
    | null;

  return owner?.context ?? null;
};
