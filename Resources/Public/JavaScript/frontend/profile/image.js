/* Generated from Resources/Private/TypeScript — do not edit. */
import Cropper from "cropperjs";
import {
  requestJson,
  showStatus
} from "@fgtclb/academic-persons-edit/frontend/profile/common.js";
import {
  toEditingContext
} from "@fgtclb/academic-persons-edit/frontend/profile/context.js";
const maximumCroppedImageWidth = 2400;
const initialCropCoverage = 0.85;
const imageClosingClass = "is-image-closing";
const imageEditorTargetSelector = "[data-pe-image-editor-target]";
const cropperStageSelector = "[data-pe-image-cropper-stage]";
const cropperSourceSelector = "[data-pe-image-cropper-source]";
const imagePreviewColumnSelector = "[data-pe-image-preview-column]";
const profileFieldsColumnSelector = ".academic-persons-profile-editing__profile-fields-column";
const supportedOutputMimeTypes = /* @__PURE__ */ new Set([
  "image/jpeg",
  "image/png",
  "image/webp"
]);
const observeState = (state, onChange) => new Proxy(state, {
  set(target, property, value) {
    const previous = Reflect.get(target, property);
    const written = Reflect.set(target, property, value);
    if (written && !Object.is(previous, value)) {
      onChange();
    }
    return written;
  }
});
const parseImageRatio = (value) => {
  const normalized = String(value ?? "").trim();
  const ratioMatch = /^(\d+(?:\.\d+)?)\s*(?:x|:|\/)\s*(\d+(?:\.\d+)?)$/i.exec(
    normalized
  );
  if (ratioMatch !== null) {
    const width = Number.parseFloat(ratioMatch[1] ?? "");
    const height = Number.parseFloat(ratioMatch[2] ?? "");
    const ratio2 = width / height;
    return Number.isFinite(ratio2) && ratio2 > 0 ? ratio2 : null;
  }
  const ratio = Number(normalized);
  return Number.isFinite(ratio) && ratio > 0 ? ratio : null;
};
const getImagePreviews = (root) => Array.from(
  root.querySelectorAll(
    "[data-pe-image-preview], [data-pe-image-view-preview]"
  )
);
const getImagePreview = (root, selector) => root.querySelector(selector);
const setImagePreviewUrl = (preview, url, alt = "", title = "") => {
  const images = preview.querySelectorAll("img");
  if (images.length === 0) {
    return;
  }
  preview.querySelectorAll("source").forEach((source) => source.removeAttribute("srcset"));
  images.forEach((image) => {
    image.removeAttribute("srcset");
    image.src = url;
    image.alt = alt;
    image.title = title;
  });
};
const setImageState = (root, hasImage) => {
  root.dataset.hasImage = hasImage ? "1" : "0";
  const input = root.querySelector('input[type="file"]');
  if (input !== null) {
    input.required = !hasImage;
  }
};
const getCroppedImageWidth = (cropper) => {
  const sourceWidth = Math.round(cropper.getData(true).width);
  if (!Number.isFinite(sourceWidth)) {
    return 1;
  }
  return Math.min(maximumCroppedImageWidth, Math.max(1, sourceWidth));
};
const canvasToBlob = (canvas, mimeType) => new Promise((resolve, reject) => {
  canvas.toBlob(
    (blob) => {
      if (blob !== null) {
        resolve(blob);
        return;
      }
      reject(new Error("The cropped image could not be encoded."));
    },
    mimeType,
    mimeType === "image/png" ? void 0 : 0.92
  );
});
const getCroppedImageFileName = (file, mimeType) => {
  if (mimeType === file.type) {
    return file.name;
  }
  const extension = mimeType === "image/jpeg" ? "jpg" : mimeType.split("/")[1];
  const basename = file.name.replace(/\.[^.]+$/, "");
  return `${basename || "profile-image"}.${extension || "png"}`;
};
const createCroppedImageFile = async (cropper, file) => {
  if (cropper === null) {
    throw new Error("The image crop is unavailable.");
  }
  const canvas = cropper.getCroppedCanvas({
    width: getCroppedImageWidth(cropper)
  });
  if (canvas === null || canvas.width <= 0 || canvas.height <= 0) {
    throw new Error("The image crop is empty.");
  }
  const requestedMimeType = supportedOutputMimeTypes.has(file.type) ? file.type : "image/png";
  const blob = await canvasToBlob(canvas, requestedMimeType);
  return new File(
    [blob],
    getCroppedImageFileName(file, blob.type || requestedMimeType),
    {
      type: blob.type || requestedMimeType,
      lastModified: Date.now()
    }
  );
};
const createImageEditing = (editingTarget, onChange = () => void 0) => {
  const context = toEditingContext(editingTarget);
  const root = context.root;
  const cropperRequested = context.image.renderType === "cropper";
  const cropperRatio = parseImageRatio(context.image.cropperRatio);
  const image = observeState({
    closing: false,
    confirmingDelete: false,
    cropperEnabled: cropperRequested && cropperRatio !== null,
    cropperReady: false,
    cropperRequested,
    editing: false,
    error: "",
    hasImage: context.image.hasImage,
    hasSelection: false,
    pending: false,
    previewUrl: "",
    selectedName: ""
  }, () => onChange());
  let cropper = null;
  let selectedFile = null;
  let selectedPreviewUrl = null;
  let persistedPreviewUrl = null;
  let persistedAlternative = "";
  let persistedTitle = "";
  const getFileInput = () => root.querySelector(
    '[data-pe-image-view-container] input[type="file"]'
  );
  const getCropperStage = () => root.querySelector(cropperStageSelector);
  const getCropperSource = () => root.querySelector(cropperSourceSelector);
  const releaseUrl = (url) => {
    if (url !== null && url.startsWith("blob:")) {
      URL.revokeObjectURL(url);
    }
  };
  const destroyCropper = () => {
    cropper == null ? void 0 : cropper.destroy();
    cropper = null;
    image.cropperReady = false;
  };
  const initializeCropper = async () => {
    destroyCropper();
    if (!image.cropperRequested || !image.hasSelection || image.previewUrl === "") {
      return;
    }
    const cropperSource = getCropperSource();
    const cropperStage = getCropperStage();
    if (!image.cropperEnabled || cropperRatio === null || cropperSource === null || cropperStage === null) {
      image.error = context.messages.errorMessage ?? "";
      return;
    }
    try {
      let reportReady = () => void 0;
      let reportBroken = () => void 0;
      const ready = new Promise((resolve, reject) => {
        reportReady = resolve;
        reportBroken = reject;
      });
      const instance = new Cropper(cropperSource, {
        aspectRatio: cropperRatio,
        autoCropArea: initialCropCoverage,
        // Nothing here ships the chequerboard the backdrop is drawn with, and
        // the stage brings its own surface colour.
        background: false,
        checkCrossOrigin: false,
        dragMode: "crop",
        // The crop box may not leave the image, and the image may not be zoomed
        // out of the box: a crop of nothing is not an upload anybody wants.
        viewMode: 1,
        zoomable: false,
        responsive: true,
        ready: () => reportReady(),
        // A file the browser cannot decode never reaches `ready`: CropperJS
        // stops on the image's own error, and without this the promise below
        // would never settle and the editor would stay disabled with nothing
        // said. The rejection is reported like a refused request.
        error: () => reportBroken(new Error("The image could not be read."))
      });
      cropper = instance;
      await ready;
      if (cropper !== instance) {
        return;
      }
      const cropBox = instance.getCropBoxData();
      image.cropperReady = (cropBox.width ?? 0) > 0 && (cropBox.height ?? 0) > 0;
      if (!image.cropperReady) {
        image.error = context.messages.errorMessage ?? "";
      }
    } catch {
      destroyCropper();
      image.error = context.messages.errorMessage ?? "";
    }
  };
  const readPersistedPreview = () => {
    const preview = getImagePreview(root, "[data-pe-image-preview]");
    const previewImage = preview == null ? void 0 : preview.querySelector("img");
    persistedPreviewUrl = (previewImage == null ? void 0 : previewImage.currentSrc) || (previewImage == null ? void 0 : previewImage.src) || null;
    persistedAlternative = (previewImage == null ? void 0 : previewImage.alt) ?? "";
    persistedTitle = (previewImage == null ? void 0 : previewImage.title) ?? "";
    image.previewUrl = persistedPreviewUrl ?? "";
  };
  const resetSelection = () => {
    releaseUrl(selectedPreviewUrl);
    selectedPreviewUrl = null;
    selectedFile = null;
    image.hasSelection = false;
    image.selectedName = "";
    image.previewUrl = persistedPreviewUrl ?? "";
    const input = getFileInput();
    if (input !== null) {
      input.value = "";
    }
  };
  const getImageCloseScrollTop = () => {
    const target = root.querySelector(imageEditorTargetSelector);
    const preview = root.querySelector(imagePreviewColumnSelector);
    const fields = root.querySelector(profileFieldsColumnSelector);
    if (target === null || preview === null || fields === null) {
      return null;
    }
    const scrollMarginTop = Number.parseFloat(
      globalThis.getComputedStyle(preview).scrollMarginTop
    );
    return Math.max(
      0,
      globalThis.scrollY + fields.getBoundingClientRect().top - target.getBoundingClientRect().height - (Number.isFinite(scrollMarginTop) ? scrollMarginTop : 0)
    );
  };
  const openImage = async () => {
    var _a, _b;
    image.error = "";
    readPersistedPreview();
    root.classList.remove(imageClosingClass);
    image.closing = false;
    image.editing = true;
    (_a = root.querySelector(imageEditorTargetSelector)) == null ? void 0 : _a.scrollIntoView({
      behavior: globalThis.matchMedia("(prefers-reduced-motion: reduce)").matches ? "auto" : "smooth",
      block: "start"
    });
    const input = getFileInput();
    if (input !== null) {
      input.required = !image.hasImage;
    }
    await initializeCropper();
    (_b = getFileInput()) == null ? void 0 : _b.focus({ preventScroll: true });
  };
  const closeImage = () => {
    image.confirmingDelete = false;
    if (image.pending || image.closing) {
      return;
    }
    const scrollTop = getImageCloseScrollTop();
    destroyCropper();
    resetSelection();
    root.classList.add(imageClosingClass);
    image.closing = true;
    image.editing = false;
    if (scrollTop !== null) {
      globalThis.scrollTo({
        top: scrollTop,
        behavior: globalThis.matchMedia("(prefers-reduced-motion: reduce)").matches ? "auto" : "smooth"
      });
    }
  };
  const finishImageClose = () => {
    if (image.editing) {
      return;
    }
    image.closing = false;
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        var _a;
        root.classList.remove(imageClosingClass);
        if (image.editing) {
          return;
        }
        (_a = root.querySelector("[data-pe-open-image-view]")) == null ? void 0 : _a.focus({ preventScroll: true });
      });
    });
  };
  const selectImage = async (event) => {
    var _a;
    const input = event.target;
    if (!(input instanceof HTMLInputElement)) {
      return;
    }
    image.error = "";
    destroyCropper();
    releaseUrl(selectedPreviewUrl);
    selectedPreviewUrl = null;
    selectedFile = ((_a = input.files) == null ? void 0 : _a[0]) ?? null;
    image.hasSelection = selectedFile !== null;
    image.selectedName = (selectedFile == null ? void 0 : selectedFile.name) ?? "";
    if (selectedFile === null) {
      image.previewUrl = persistedPreviewUrl ?? "";
    } else {
      selectedPreviewUrl = URL.createObjectURL(selectedFile);
      image.previewUrl = selectedPreviewUrl;
    }
    await initializeCropper();
  };
  const commitUploadedPreview = (file, previewUrl, alternative, title) => {
    if (persistedPreviewUrl !== previewUrl) {
      releaseUrl(persistedPreviewUrl);
    }
    if (selectedPreviewUrl === previewUrl) {
      selectedPreviewUrl = null;
    } else {
      releaseUrl(selectedPreviewUrl);
      selectedPreviewUrl = null;
    }
    persistedPreviewUrl = previewUrl;
    persistedAlternative = String(alternative ?? file.name);
    persistedTitle = String(title ?? file.name);
    image.previewUrl = previewUrl;
    getImagePreviews(root).forEach((preview) => {
      setImagePreviewUrl(
        preview,
        previewUrl,
        persistedAlternative,
        persistedTitle
      );
    });
  };
  const submitImage = async (event) => {
    const form = event.currentTarget instanceof HTMLFormElement ? event.currentTarget : event.target instanceof HTMLFormElement ? event.target : null;
    if (form === null || image.pending) {
      return;
    }
    const file = selectedFile;
    if (!image.hasSelection || file === null) {
      image.error = context.messages.validation ?? "";
      return;
    }
    if (!form.reportValidity()) {
      image.error = context.messages.validation ?? "";
      return;
    }
    image.pending = true;
    image.error = "";
    showStatus(context, "info", context.messages.saving ?? null);
    let uploadPreviewUrl = selectedPreviewUrl;
    try {
      const uploadFile = image.cropperRequested ? await createCroppedImageFile(image.cropperEnabled ? cropper : null, file) : file;
      if (image.cropperRequested) {
        uploadPreviewUrl = URL.createObjectURL(uploadFile);
      }
      const input = getFileInput();
      if (input === null || input.name === "") {
        throw new Error("The image upload field has no name.");
      }
      const formData = new FormData(form);
      formData.set(input.name, uploadFile, uploadFile.name);
      const result = await requestJson(form.action, {
        method: "POST",
        body: formData
      });
      if (result.hasImage !== true || uploadPreviewUrl === null) {
        const error = new Error("The upload returned no profile image.");
        error.result = { message: context.messages.imageUploadMissing ?? "" };
        throw error;
      }
      commitUploadedPreview(
        uploadFile,
        uploadPreviewUrl,
        result.imageAlternative,
        result.imageTitle
      );
      uploadPreviewUrl = null;
      image.hasImage = true;
      setImageState(root, true);
      image.pending = false;
      closeImage();
      showStatus(context, "success", context.messages.imageUploaded ?? null);
    } catch (error) {
      if (uploadPreviewUrl !== null && uploadPreviewUrl !== selectedPreviewUrl) {
        releaseUrl(uploadPreviewUrl);
      }
      const result = error.result;
      image.error = (result == null ? void 0 : result.error) === "image_upload_missing" ? context.messages.imageUploadMissing ?? "" : (result == null ? void 0 : result.message) ?? context.messages.errorMessage ?? "";
    } finally {
      image.pending = false;
    }
  };
  const requestDeleteImage = () => {
    if (image.pending || !image.hasImage) {
      return;
    }
    image.error = "";
    image.confirmingDelete = true;
  };
  const cancelDeleteImage = () => {
    image.confirmingDelete = false;
  };
  const deleteImage = async () => {
    var _a;
    const profile = context.profileUid;
    const endpoint = context.urls.deleteImage;
    if (image.pending || !image.hasImage || !image.confirmingDelete || profile === null || endpoint === void 0) {
      return;
    }
    image.confirmingDelete = false;
    image.pending = true;
    image.error = "";
    showStatus(context, "info", context.messages.saving ?? null);
    try {
      await requestJson(endpoint, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ profile, data: {} })
      });
      const placeholderUrl = context.image.placeholderUrl;
      if (placeholderUrl !== void 0) {
        releaseUrl(selectedPreviewUrl);
        releaseUrl(persistedPreviewUrl);
        selectedPreviewUrl = null;
        persistedPreviewUrl = placeholderUrl;
        image.previewUrl = placeholderUrl;
        getImagePreviews(root).forEach((preview) => {
          setImagePreviewUrl(
            preview,
            placeholderUrl,
            context.image.placeholderAlt ?? ""
          );
        });
      }
      image.hasImage = false;
      setImageState(root, false);
      image.pending = false;
      closeImage();
      showStatus(context, "success", context.messages.imageDeleted ?? null);
    } catch (error) {
      image.error = ((_a = error.result) == null ? void 0 : _a.message) ?? context.messages.errorMessage ?? "";
    } finally {
      image.pending = false;
    }
  };
  globalThis.addEventListener(
    "pagehide",
    () => {
      destroyCropper();
      releaseUrl(selectedPreviewUrl);
      releaseUrl(persistedPreviewUrl);
    },
    { once: true }
  );
  return {
    image,
    openImage,
    closeImage,
    requestDeleteImage,
    cancelDeleteImage,
    finishImageClose,
    selectImage,
    submitImage,
    deleteImage
  };
};
export {
  createImageEditing
};
