import assert from "node:assert/strict";
import { beforeEach, describe, it } from "node:test";
import {
  isObjectUrlAlive,
  resetBody,
  settle,
} from "../../../../../Build/tests/dom.mjs";
import { installFetch, type FetchDouble } from "../../../../../Build/tests/fetch.mjs";
import { createImageEditing } from "@fgtclb/academic-persons-edit/frontend/profile/image.js";
import {
  endpoints,
  imageCard,
  imageEditorView,
  messages,
  placeholderImageUrl,
  profileEditingRoot,
  select,
  selectAll,
} from "./Fixtures/profile-editing.ts";

/**
 * The profile image: choosing a file, uploading it, deleting the stored one,
 * and the two previews that have to agree with the database afterwards.
 *
 * ## What is and is not covered
 *
 * This file drives the controller without a custom element registry - the same
 * situation as the document editor, see `document-editor.test.ts`. The upload
 * form is server rendered (`f:form` inside
 * `Partials/Profile/Image/Editor.html`), so it is real markup here, and the
 * request that goes out of it is asserted in full.
 *
 * Two things are deliberately not covered and are named rather than papered
 * over:
 *
 * - The cropping path. It reaches its own error branch here rather than a crop:
 *   the stage and the source it needs are queried out of the editor's markup,
 *   and this file renders the root without the editor element that carries it.
 *   `image-editor-element.test.ts` renders that element and covers the cropper
 *   there, against the stub of `Build/tests/stubs/cropper.mjs` - CropperJS
 *   measures a layout and rasterises through a canvas, and jsdom has neither.
 * - The states that only exist to be rendered - `confirmingDelete`, `error`,
 *   `hasSelection` - are asserted as state, and said so. They are what the
 *   element reads on every change, and what it makes of them is asserted
 *   there.
 */
describe("the profile image", () => {
  let fetch: FetchDouble;
  let root: HTMLElement;
  let controller: ReturnType<typeof createImageEditing>;

  const render = ({ hasImage = true }: { hasImage?: boolean } = {}): void => {
    const body = resetBody(
      profileEditingRoot({
        hasImage,
        content: imageCard() + imageEditorView(),
      }),
    );
    root = select(body, "[data-academic-persons-profile-editing]", HTMLElement);
    controller = createImageEditing(root);
  };

  const fileInput = (): HTMLInputElement =>
    select(root, '[data-pe-image-view-container] input[type="file"]', HTMLInputElement);
  const previews = (): HTMLElement[] =>
    selectAll(root, "[data-pe-image-preview], [data-pe-image-view-preview]", HTMLElement);
  const cardImage = (): HTMLImageElement =>
    select(root, "[data-pe-image-preview] img", HTMLImageElement);

  /**
   * A chosen file, handed to the controller the way the DOM hands it over: as
   * the `change` event of the file input.
   */
  const choose = (name = "portrait.png"): File => {
    const file = new File(["binary"], name, { type: "image/png" });
    Object.defineProperty(fileInput(), "files", { value: [file], configurable: true });
    const event = new CustomEvent("change", { bubbles: true });
    Object.defineProperty(event, "target", { value: fileInput() });
    void controller.selectImage(event);

    return file;
  };

  beforeEach(() => {
    fetch = installFetch();
    render();
  });

  it("brings the editor into view and puts the caret on the file control", async () => {
    await controller.openImage();
    await settle(20);

    assert.equal(
      select(root, "[data-pe-image-editor-target]", HTMLElement)
        .getAttribute("data-test-scrolled-into-view"),
      "start",
    );
    assert.equal(document.activeElement, fileInput());
    assert.equal(controller.image.editing, true);
  });

  /**
   * A profile that has no image yet cannot be saved without choosing one; one
   * that has an image can be saved unchanged, so the control is not required.
   */
  it("requires a file only while the profile has no image", async () => {
    await controller.openImage();
    await settle(20);
    assert.equal(fileInput().required, false);

    render({ hasImage: false });
    await controller.openImage();
    await settle(20);
    assert.equal(fileInput().required, true);
  });

  it("marks the page as closing, forgets the choice and returns the caret to the button", async () => {
    await controller.openImage();
    await settle(20);
    choose();
    await settle(20);

    controller.closeImage();
    assert.ok(root.classList.contains("is-image-closing"));
    assert.equal(controller.image.editing, false);
    assert.equal(fileInput().value, "");

    controller.finishImageClose();
    await settle(20);
    // The class is removed after two animation frames, so the assertion has to
    // wait for both.
    await new Promise((resolve): void => {
      requestAnimationFrame((): void => {
        requestAnimationFrame((): void => {
          resolve(null);
        });
      });
    });

    assert.equal(root.classList.contains("is-image-closing"), false);
    assert.equal(
      document.activeElement,
      select(root, "[data-pe-open-image-view]", HTMLButtonElement),
    );
  });

  it("keeps a preview of the chosen file and releases it again on close", async () => {
    await controller.openImage();
    await settle(20);

    choose();
    await settle(20);
    const previewUrl = controller.image.previewUrl;
    assert.ok(previewUrl.startsWith("blob:"));
    assert.equal(controller.image.hasSelection, true);
    assert.equal(controller.image.selectedName, "portrait.png");
    assert.ok(isObjectUrlAlive(previewUrl));

    controller.closeImage();
    assert.equal(isObjectUrlAlive(previewUrl), false);
    assert.equal(controller.image.hasSelection, false);
  });

  it("uploads the chosen file through the form it belongs to", async () => {
    await controller.openImage();
    await settle(20);
    const file = choose();
    await settle(20);
    fetch.respond({
      success: true,
      hasImage: true,
      imageAlternative: "A portrait",
      imageTitle: "Ada",
    });

    const form = select(root, "form", HTMLFormElement);
    const event = new CustomEvent("submit", { bubbles: true });
    Object.defineProperty(event, "currentTarget", { value: form });
    await controller.submitImage(event);
    await settle(20);

    const call = fetch.calls[0];
    assert.equal(call?.url, endpoints.uploadImage);
    assert.equal(call?.method, "POST");
    assert.equal(call?.headers["X-Requested-With"], "XMLHttpRequest");
    // A multipart body, not JSON: the file travels under the name the server
    // rendered on the control.
    const body = call?.rawBody as FormData;
    assert.ok(body instanceof FormData);
    const uploaded = body.get("tx_academicpersonsedit_profile[profile][image]");
    assert.ok(uploaded instanceof File);
    assert.equal((uploaded as File).name, file.name);
  });

  it("shows the uploaded image in every preview and remembers that there is one", async () => {
    await controller.openImage();
    await settle(20);
    choose();
    await settle(20);
    fetch.respond({
      success: true,
      hasImage: true,
      imageAlternative: "A portrait",
      imageTitle: "Ada",
    });

    const form = select(root, "form", HTMLFormElement);
    const event = new CustomEvent("submit", { bubbles: true });
    Object.defineProperty(event, "currentTarget", { value: form });
    await controller.submitImage(event);
    await settle(20);

    assert.equal(cardImage().getAttribute("src"), controller.image.previewUrl);
    assert.equal(cardImage().alt, "A portrait");
    assert.equal(cardImage().title, "Ada");
    // The responsive sources still point at the previous image and would win
    // over the src, so they are removed with it.
    assert.equal(root.querySelector("source[srcset]"), null);
    assert.equal(root.dataset.hasImage, "1");
    assert.equal(controller.image.hasImage, true);
    assert.equal(
      select(root, '[data-pe-status-toast="status"] .status-message', HTMLElement).textContent,
      messages.imageUploaded,
    );
    assert.equal(previews().length, 2);
  });

  it("keeps the editor open and says why when the upload is refused", async () => {
    await controller.openImage();
    await settle(20);
    choose();
    await settle(20);
    fetch.respondWithError({ success: false, message: "The file is too large." }, 413);

    const form = select(root, "form", HTMLFormElement);
    const event = new CustomEvent("submit", { bubbles: true });
    Object.defineProperty(event, "currentTarget", { value: form });
    await controller.submitImage(event);
    await settle(20);

    // Rendered into `[data-pe-image-error]` by the template; a property of the
    // element that drives the markup.
    assert.equal(controller.image.error, "The file is too large.");
    assert.equal(controller.image.editing, true);
    assert.equal(cardImage().getAttribute("src"), "/fileadmin/_processed_/profile.jpg");
  });

  it("refuses to upload nothing", async () => {
    await controller.openImage();
    await settle(20);

    const form = select(root, "form", HTMLFormElement);
    const event = new CustomEvent("submit", { bubbles: true });
    Object.defineProperty(event, "currentTarget", { value: form });
    await controller.submitImage(event);
    await settle(20);

    assert.equal(fetch.calls.length, 0);
    assert.equal(controller.image.error, messages.validation);
  });

  it("names the missing image when the server stored no file", async () => {
    await controller.openImage();
    await settle(20);
    choose();
    await settle(20);
    fetch.respond({ success: true, hasImage: false });

    const form = select(root, "form", HTMLFormElement);
    const event = new CustomEvent("submit", { bubbles: true });
    Object.defineProperty(event, "currentTarget", { value: form });
    await controller.submitImage(event);
    await settle(20);

    assert.equal(controller.image.error, messages.imageUploadMissing);
    assert.equal(root.dataset.hasImage, "1");
  });
});

/**
 * Deleting the image drops the file reference and, with the last one, the file
 * itself - so it is asked about first, exactly as the documents are.
 */
describe("deleting the profile image", () => {
  let fetch: FetchDouble;
  let root: HTMLElement;
  let controller: ReturnType<typeof createImageEditing>;

  beforeEach(async () => {
    fetch = installFetch();
    const body = resetBody(
      profileEditingRoot({ content: imageCard() + imageEditorView() }),
    );
    root = select(body, "[data-academic-persons-profile-editing]", HTMLElement);
    controller = createImageEditing(root);
    await controller.openImage();
    await settle(20);
  });

  const cardImage = (): HTMLImageElement =>
    select(root, "[data-pe-image-preview] img", HTMLImageElement);

  it("asks before it deletes", async () => {
    await controller.deleteImage();

    assert.equal(fetch.calls.length, 0);
    // The question the template renders; a property of the component after the
    // port.
    assert.equal(controller.image.confirmingDelete, false);

    controller.requestDeleteImage();
    assert.equal(controller.image.confirmingDelete, true);

    controller.cancelDeleteImage();
    assert.equal(controller.image.confirmingDelete, false);
  });

  it("deletes the image for this profile and shows the placeholder everywhere", async () => {
    fetch.respond({ success: true });
    controller.requestDeleteImage();

    await controller.deleteImage();
    await settle(20);

    const call = fetch.calls[0];
    assert.equal(call?.url, endpoints.deleteImage);
    assert.equal(call?.method, "POST");
    assert.equal(call?.headers["X-Requested-With"], "XMLHttpRequest");
    assert.deepEqual(call?.body, { profile: 1, data: {} });
    assert.equal(cardImage().getAttribute("src"), placeholderImageUrl);
    assert.equal(cardImage().alt, messages.placeholderAlt);
    assert.equal(root.dataset.hasImage, "0");
    assert.equal(
      select(root, '[data-pe-image-view-container] input[type="file"]', HTMLInputElement).required,
      true,
    );
    assert.equal(
      select(root, '[data-pe-status-toast="status"] .status-message', HTMLElement).textContent,
      messages.imageDeleted,
    );
  });

  it("keeps the image and says why when the deletion is refused", async () => {
    fetch.respondWithError({ success: false, message: "Not allowed." }, 403);
    controller.requestDeleteImage();

    await controller.deleteImage();
    await settle(20);

    assert.equal(controller.image.error, "Not allowed.");
    assert.equal(cardImage().getAttribute("src"), "/fileadmin/_processed_/profile.jpg");
    assert.equal(root.dataset.hasImage, "1");
  });
});
