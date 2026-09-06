/* Generated from Resources/Private/TypeScript — do not edit. */
import { profileEditingElementName } from "@fgtclb/academic-persons-edit/frontend/profile/elements/names.js";
const parseProfileUid = (value) => {
  const profileUid = Number.parseInt(value ?? "", 10);
  return Number.isInteger(profileUid) && profileUid > 0 ? profileUid : null;
};
const readEditingContext = (root) => {
  const contract = root.dataset;
  return Object.freeze({
    editorLanguage: (contract.editorLanguage ?? "").trim().toLowerCase(),
    image: Object.freeze({
      cropperRatio: contract.imageCropperRatio,
      hasImage: contract.hasImage === "1",
      placeholderAlt: contract.placeholderImageAlt,
      placeholderUrl: contract.placeholderImageUrl,
      renderType: (contract.imageRenderType ?? "").toLowerCase()
    }),
    labels: Object.freeze({
      documentAdd: contract.labelDocumentAdd,
      documentDelete: contract.labelDocumentDelete,
      documentEdit: contract.labelDocumentEdit,
      documentEmpty: contract.labelDocumentEmpty,
      documentSave: contract.labelDocumentSave,
      documentView: contract.labelDocumentView
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
      warningTitle: contract.messageWarningTitle
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
      updateDocument: contract.updateDocumentUrl
    })
  });
};
const toEditingContext = (target) => target instanceof HTMLElement ? readEditingContext(target) : target;
const ownerEditingContext = (element) => {
  const owner = element.closest(profileEditingElementName);
  return (owner == null ? void 0 : owner.context) ?? null;
};
export {
  ownerEditingContext,
  readEditingContext,
  toEditingContext
};
