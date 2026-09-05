import Cropper from "cropperjs";
import {
  requestJson,
  showStatus,
} from "@fgtclb/academic-persons-edit/frontend/profile/common.js";
import {
  toEditingContext,
  type EditingTarget,
} from "@fgtclb/academic-persons-edit/frontend/profile/context.js";
/**
 * Everything the editor's markup is derived from.
 *
 * A plain object, wrapped in the proxy of `observeState()` so that the element
 * driving the markup is told when one of these fields has changed.
 */
export interface ImageState {
  closing: boolean;
  confirmingDelete: boolean;
  cropperEnabled: boolean;
  cropperReady: boolean;
  cropperRequested: boolean;
  editing: boolean;
  error: string;
  hasImage: boolean;
  hasSelection: boolean;
  pending: boolean;
  previewUrl: string;
  selectedName: string;
}

interface RequestError extends Error {
  result?: {
    error?: string;
    message?: string;
  };
}

/**
 * What `<academic-persons-edit-image-editor>` drives, and what the behavioural
 * suite drives directly.
 *
 * `image` is the render input: the element reads it after every change and
 * writes what it means onto the markup of the Fluid partial: what is shown,
 * what is disabled, which preview is visible. Nothing here renders markup - the
 * upload form is a server rendered `f:form` and has to stay one.
 */
export interface ImageEditingController {
  readonly image: ImageState;
  openImage(): Promise<void>;
  closeImage(): void;
  requestDeleteImage(): void;
  cancelDeleteImage(): void;
  finishImageClose(): void;
  selectImage(event: Event): Promise<void>;
  submitImage(event: Event): Promise<void>;
  deleteImage(): Promise<void>;
}

const maximumCroppedImageWidth = 2400;
// How much of the stage the crop covers when the editor opens it. CropperJS
// fits a box of this share of the displayed image to the configured ratio and
// centres it, which is what the editor used to compute for itself.
const initialCropCoverage = 0.85;
const imageClosingClass = "is-image-closing";
const imageEditorTargetSelector = "[data-pe-image-editor-target]";
const cropperStageSelector = "[data-pe-image-cropper-stage]";
const cropperSourceSelector = "[data-pe-image-cropper-source]";
const imagePreviewColumnSelector = "[data-pe-image-preview-column]";
const profileFieldsColumnSelector =
  ".academic-persons-profile-editing__profile-fields-column";
const supportedOutputMimeTypes = new Set([
  "image/jpeg",
  "image/png",
  "image/webp",
]);

/**
 * A state object that reports every change it accepts.
 *
 * The element that drives the markup updates the DOM itself, and all it needs
 * is to be told when to. A proxy keeps the change detection out of the
 * forty-odd assignments below,
 * which would otherwise each have to remember to call back - and it keeps
 * reading the state exactly as cheap as it was.
 *
 * A write of the value that is already there notifies nobody, so a `render()`
 * is a change and not a heartbeat.
 */
const observeState = <T extends object>(state: T, onChange: () => void): T =>
  new Proxy(state, {
    set(target: T, property: string | symbol, value: unknown): boolean {
      const previous: unknown = Reflect.get(target, property);
      const written = Reflect.set(target, property, value);
      if (written && !Object.is(previous, value)) {
        onChange();
      }

      return written;
    },
  });

const parseImageRatio = (value: unknown): number | null => {
  const normalized = String(value ?? "").trim();
  const ratioMatch = /^(\d+(?:\.\d+)?)\s*(?:x|:|\/)\s*(\d+(?:\.\d+)?)$/i.exec(
    normalized,
  );
  if (ratioMatch !== null) {
    const width = Number.parseFloat(ratioMatch[1] ?? "");
    const height = Number.parseFloat(ratioMatch[2] ?? "");
    const ratio = width / height;
    return Number.isFinite(ratio) && ratio > 0 ? ratio : null;
  }
  const ratio = Number(normalized);
  return Number.isFinite(ratio) && ratio > 0 ? ratio : null;
};

const getImagePreviews = (root: HTMLElement): HTMLElement[] =>
  Array.from(
    root.querySelectorAll<HTMLElement>(
      "[data-pe-image-preview], [data-pe-image-view-preview]",
    ),
  );

const getImagePreview = (
  root: HTMLElement,
  selector: string,
): HTMLElement | null => root.querySelector<HTMLElement>(selector);

const setImagePreviewUrl = (
  preview: HTMLElement,
  url: string,
  alt = "",
  title = "",
): void => {
  const images = preview.querySelectorAll<HTMLImageElement>("img");
  if (images.length === 0) {
    return;
  }
  preview
    .querySelectorAll<HTMLSourceElement>("source")
    .forEach((source): void => source.removeAttribute("srcset"));
  images.forEach((image): void => {
    image.removeAttribute("srcset");
    image.src = url;
    image.alt = alt;
    image.title = title;
  });
};

const setImageState = (root: HTMLElement, hasImage: boolean): void => {
  root.dataset.hasImage = hasImage ? "1" : "0";
  const input = root.querySelector<HTMLInputElement>('input[type="file"]');
  if (input !== null) {
    input.required = !hasImage;
  }
};

/**
 * The width the crop is rasterised at: the crop rectangle measured in the
 * natural pixels of the file the visitor chose, capped.
 *
 * `getData()` reports exactly that - CropperJS divides the crop box by the
 * scale the image is displayed at - so the upload keeps the resolution the
 * source has instead of the resolution the stage happened to be laid out at.
 * A visitor who uploads a 6000 pixel photograph and crops most of it away would
 * otherwise post a 4000 pixel image the site never renders, which is what the
 * cap is for.
 */
const getCroppedImageWidth = (cropper: Cropper): number => {
  const sourceWidth = Math.round(cropper.getData(true).width);
  if (!Number.isFinite(sourceWidth)) {
    return 1;
  }

  return Math.min(maximumCroppedImageWidth, Math.max(1, sourceWidth));
};

const canvasToBlob = (
  canvas: HTMLCanvasElement,
  mimeType: string,
): Promise<Blob> =>
  new Promise((resolve, reject): void => {
    canvas.toBlob(
      (blob): void => {
        if (blob !== null) {
          resolve(blob);
          return;
        }
        reject(new Error("The cropped image could not be encoded."));
      },
      mimeType,
      mimeType === "image/png" ? undefined : 0.92,
    );
  });

const getCroppedImageFileName = (file: File, mimeType: string): string => {
  if (mimeType === file.type) {
    return file.name;
  }
  const extension = mimeType === "image/jpeg" ? "jpg" : mimeType.split("/")[1];
  const basename = file.name.replace(/\.[^.]+$/, "");
  return `${basename || "profile-image"}.${extension || "png"}`;
};

const createCroppedImageFile = async (
  cropper: Cropper | null,
  file: File,
): Promise<File> => {
  if (cropper === null) {
    throw new Error("The image crop is unavailable.");
  }
  const canvas = cropper.getCroppedCanvas({
    width: getCroppedImageWidth(cropper),
  });
  if (canvas === null || canvas.width <= 0 || canvas.height <= 0) {
    throw new Error("The image crop is empty.");
  }
  const requestedMimeType = supportedOutputMimeTypes.has(file.type)
    ? file.type
    : "image/png";
  const blob = await canvasToBlob(canvas, requestedMimeType);
  return new File(
    [blob],
    getCroppedImageFileName(file, blob.type || requestedMimeType),
    {
      type: blob.type || requestedMimeType,
      lastModified: Date.now(),
    },
  );
};

/**
 * @param onChange runs after every accepted change of the state, so that the
 *   element around the server rendered markup can update what it derives from
 *   it. A caller that only drives the controller - the behavioural suite - hands
 *   nothing over and no rendering happens.
 */
export const createImageEditing = (
  editingTarget: EditingTarget,
  onChange: () => void = (): void => undefined,
): ImageEditingController => {
  const context = toEditingContext(editingTarget);
  const root = context.root;
  const cropperRequested = context.image.renderType === "cropper";
  const cropperRatio = parseImageRatio(context.image.cropperRatio);
  const image = observeState<ImageState>({
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
    selectedName: "",
  }, (): void => onChange());
  let cropper: Cropper | null = null;
  let selectedFile: File | null = null;
  let selectedPreviewUrl: string | null = null;
  let persistedPreviewUrl: string | null = null;
  let persistedAlternative = "";
  let persistedTitle = "";

  const getFileInput = (): HTMLInputElement | null =>
    root.querySelector<HTMLInputElement>(
      '[data-pe-image-view-container] input[type="file"]',
    );

  // The cropper's stage and the image it crops are queried, like every other
  // element this module reaches for, so the cropping path is reachable without
  // holding a reference to markup this module does not own. CropperJS mounts
  // into the parent of the image it is given rather than into a container it is
  // told about, so the stage is what it measures itself against - a source
  // without one is a partial that cannot crop.
  const getCropperStage = (): HTMLElement | null =>
    root.querySelector<HTMLElement>(cropperStageSelector);

  const getCropperSource = (): HTMLImageElement | null =>
    root.querySelector<HTMLImageElement>(cropperSourceSelector);

  const releaseUrl = (url: string | null): void => {
    if (url !== null && url.startsWith("blob:")) {
      URL.revokeObjectURL(url);
    }
  };

  const destroyCropper = (): void => {
    cropper?.destroy();
    cropper = null;
    image.cropperReady = false;
  };

  const initializeCropper = async (): Promise<void> => {
    destroyCropper();
    if (
      !image.cropperRequested ||
      !image.hasSelection ||
      image.previewUrl === ""
    ) {
      return;
    }
    const cropperSource = getCropperSource();
    const cropperStage = getCropperStage();
    if (
      !image.cropperEnabled ||
      cropperRatio === null ||
      cropperSource === null ||
      cropperStage === null
    ) {
      image.error = context.messages.errorMessage ?? "";
      return;
    }
    try {
      // CropperJS reports that it has measured the image through a callback and
      // not through a promise, and the module has to wait for it: until then
      // there is no crop box, and the save button stays disabled. The resolver
      // is captured before the instance is built because a cropper over an
      // image the browser has already decoded reports back inside the
      // constructor.
      let reportReady: () => void = (): void => undefined;
      let reportBroken: (reason: Error) => void = (): void => undefined;
      const ready = new Promise<void>((resolve, reject): void => {
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
        ready: (): void => reportReady(),
        // A file the browser cannot decode never reaches `ready`: CropperJS
        // stops on the image's own error, and without this the promise below
        // would never settle and the editor would stay disabled with nothing
        // said. The rejection is reported like a refused request.
        error: (): void =>
          reportBroken(new Error("The image could not be read.")),
      });
      cropper = instance;
      await ready;
      if (cropper !== instance) {
        return;
      }
      const cropBox = instance.getCropBoxData();
      image.cropperReady =
        (cropBox.width ?? 0) > 0 && (cropBox.height ?? 0) > 0;
      if (!image.cropperReady) {
        image.error = context.messages.errorMessage ?? "";
      }
    } catch {
      destroyCropper();
      image.error = context.messages.errorMessage ?? "";
    }
  };

  const readPersistedPreview = (): void => {
    const preview = getImagePreview(root, "[data-pe-image-preview]");
    const previewImage = preview?.querySelector<HTMLImageElement>("img");
    persistedPreviewUrl = previewImage?.currentSrc || previewImage?.src || null;
    persistedAlternative = previewImage?.alt ?? "";
    persistedTitle = previewImage?.title ?? "";
    image.previewUrl = persistedPreviewUrl ?? "";
  };

  const resetSelection = (): void => {
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

  const getImageCloseScrollTop = (): number | null => {
    const target = root.querySelector<HTMLElement>(imageEditorTargetSelector);
    const preview = root.querySelector<HTMLElement>(imagePreviewColumnSelector);
    const fields = root.querySelector<HTMLElement>(profileFieldsColumnSelector);
    if (target === null || preview === null || fields === null) {
      return null;
    }
    const scrollMarginTop = Number.parseFloat(
      globalThis.getComputedStyle(preview).scrollMarginTop,
    );
    return Math.max(
      0,
      globalThis.scrollY +
        fields.getBoundingClientRect().top -
        target.getBoundingClientRect().height -
        (Number.isFinite(scrollMarginTop) ? scrollMarginTop : 0),
    );
  };

  const openImage = async (): Promise<void> => {
    image.error = "";
    readPersistedPreview();
    root.classList.remove(imageClosingClass);
    image.closing = false;
    image.editing = true;
    root.querySelector<HTMLElement>(imageEditorTargetSelector)?.scrollIntoView({
      behavior: globalThis.matchMedia("(prefers-reduced-motion: reduce)").matches
        ? "auto"
        : "smooth",
      block: "start",
    });
    const input = getFileInput();
    if (input !== null) {
      input.required = !image.hasImage;
    }
    await initializeCropper();
    getFileInput()?.focus({ preventScroll: true });
  };

  const closeImage = (): void => {
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
        behavior: globalThis.matchMedia("(prefers-reduced-motion: reduce)")
          .matches
          ? "auto"
          : "smooth",
      });
    }
  };

  const finishImageClose = (): void => {
    if (image.editing) {
      return;
    }
    image.closing = false;
    // The element applies the state change synchronously, so only the two
    // frames remain - and they are what the class is really waiting for. It
    // suppresses the browser's scroll anchoring while the editor collapses, and
    // dropping it before the collapsed layout has been painted lets the page
    // jump by exactly the height that was removed.
    requestAnimationFrame((): void => {
      requestAnimationFrame((): void => {
        root.classList.remove(imageClosingClass);
        if (image.editing) {
          return;
        }
        root
          .querySelector<HTMLButtonElement>("[data-pe-open-image-view]")
          ?.focus({ preventScroll: true });
      });
    });
  };

  const selectImage = async (event: Event): Promise<void> => {
    const input = event.target;
    if (!(input instanceof HTMLInputElement)) {
      return;
    }
    image.error = "";
    destroyCropper();
    releaseUrl(selectedPreviewUrl);
    selectedPreviewUrl = null;
    selectedFile = input.files?.[0] ?? null;
    image.hasSelection = selectedFile !== null;
    image.selectedName = selectedFile?.name ?? "";
    if (selectedFile === null) {
      image.previewUrl = persistedPreviewUrl ?? "";
    } else {
      selectedPreviewUrl = URL.createObjectURL(selectedFile);
      image.previewUrl = selectedPreviewUrl;
    }
    await initializeCropper();
  };

  const commitUploadedPreview = (
    file: File,
    previewUrl: string,
    alternative: unknown,
    title: unknown,
  ): void => {
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
    getImagePreviews(root).forEach((preview): void => {
      setImagePreviewUrl(
        preview,
        previewUrl,
        persistedAlternative,
        persistedTitle,
      );
    });
  };

  const submitImage = async (event: Event): Promise<void> => {
    const form = event.currentTarget instanceof HTMLFormElement
      ? event.currentTarget
      : event.target instanceof HTMLFormElement
        ? event.target
        : null;
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
    let uploadPreviewUrl: string | null = selectedPreviewUrl;
    try {
      const uploadFile = image.cropperRequested
        ? await createCroppedImageFile(image.cropperEnabled ? cropper : null, file)
        : file;
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
        body: formData,
      });
      if (result.hasImage !== true || uploadPreviewUrl === null) {
        const error = new Error("The upload returned no profile image.") as RequestError;
        error.result = { message: context.messages.imageUploadMissing ?? "" };
        throw error;
      }
      commitUploadedPreview(
        uploadFile,
        uploadPreviewUrl,
        result.imageAlternative,
        result.imageTitle,
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
      const result = (error as RequestError).result;
      image.error =
        result?.error === "image_upload_missing"
          ? (context.messages.imageUploadMissing ?? "")
          : (result?.message ?? context.messages.errorMessage ?? "");
    } finally {
      image.pending = false;
    }
  };

  // Deleting the image drops the FAL relation and, with the last reference, the
  // file. The documents and contacts ask before they delete; this asks too.
  const requestDeleteImage = (): void => {
    if (image.pending || !image.hasImage) {
      return;
    }
    image.error = "";
    image.confirmingDelete = true;
  };

  const cancelDeleteImage = (): void => {
    image.confirmingDelete = false;
  };

  const deleteImage = async (): Promise<void> => {
    const profile = context.profileUid;
    const endpoint = context.urls.deleteImage;
    if (
      image.pending
      || !image.hasImage
      || !image.confirmingDelete
      || profile === null
      || endpoint === undefined
    ) {
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
        body: JSON.stringify({ profile, data: {} }),
      });
      const placeholderUrl = context.image.placeholderUrl;
      if (placeholderUrl !== undefined) {
        releaseUrl(selectedPreviewUrl);
        releaseUrl(persistedPreviewUrl);
        selectedPreviewUrl = null;
        persistedPreviewUrl = placeholderUrl;
        image.previewUrl = placeholderUrl;
        getImagePreviews(root).forEach((preview): void => {
          setImagePreviewUrl(
            preview,
            placeholderUrl,
            context.image.placeholderAlt ?? "",
          );
        });
      }
      image.hasImage = false;
      setImageState(root, false);
      image.pending = false;
      closeImage();
      showStatus(context, "success", context.messages.imageDeleted ?? null);
    } catch (error) {
      image.error =
        (error as RequestError).result?.message ??
        context.messages.errorMessage ??
        "";
    } finally {
      image.pending = false;
    }
  };

  globalThis.addEventListener(
    "pagehide",
    (): void => {
      destroyCropper();
      releaseUrl(selectedPreviewUrl);
      releaseUrl(persistedPreviewUrl);
    },
    { once: true },
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
    deleteImage,
  };
};
