import assert from "node:assert/strict";
import { afterEach, beforeEach, describe, it, mock } from "node:test";
import {
  resetBody,
  setClientSize,
  settle,
} from "../../../../../Build/tests/dom.mjs";
import { installFetch, type FetchDouble } from "../../../../../Build/tests/fetch.mjs";
import {
  profileEditingElementName,
  ProfileEditingRootElement,
  registerProfileEditingElement,
} from "@fgtclb/academic-persons-edit/frontend/profile/elements/root.js";
import {
  profileImageEditorElementName,
  ProfileImageEditorElement,
  registerProfileImageEditorElement,
  runElementTransition,
} from "@fgtclb/academic-persons-edit/frontend/profile/elements/image-editor.js";
import {
  endpoints,
  imageCard,
  imageEditor,
  imageEditorView,
  messages,
  profileEditingElement,
  profileFieldsColumn,
  select,
  type RootOptions,
} from "./Fixtures/profile-editing.ts";

/**
 * `<academic-persons-edit-image-editor>` is the controller over the Fluid
 * rendered upload form. It renders nothing, and that is the first thing
 * asserted here: the `<f:form>` carries the `__trustedProperties` signature the
 * property mapper validates the upload against, and a form the browser rebuilt
 * would be refused by the server.
 *
 * What it does own is everything the state means for
 * `Partials/Profile/Image/Editor.html` used to derive - what is shown, what is
 * disabled, which preview is which - plus the two column widths of
 * `Templates/Profile/Index.html` and the transition the editor opens and closes
 * with.
 *
 * The state and the requests are not asserted here. They belong to
 * `profile/image.ts` and are pinned by `image-editing.test.ts`, which drives the
 * controller without a registry; this file asserts what the element makes of
 * them.
 */
describe("the profile image editor element", () => {
  let fetch: FetchDouble;

  const render = (options: RootOptions = {}): ProfileImageEditorElement => {
    const body = resetBody(
      profileEditingElement({
        content: imageCard() + profileFieldsColumn(),
        target: imageEditor(),
        ...options,
      }),
    );
    const element = select(body, profileImageEditorElementName, HTMLElement);
    assert.ok(element instanceof ProfileImageEditorElement);

    return element;
  };

  const rootOf = (): HTMLElement =>
    select(document.body, "[data-academic-persons-profile-editing]", HTMLElement);

  const at = (element: ProfileImageEditorElement, selector: string): HTMLElement =>
    select(element, selector, HTMLElement);

  const click = (scope: ParentNode, selector: string): void => {
    select(scope, selector, HTMLElement).click();
  };

  /** A chosen file, handed over the way the DOM hands it over. */
  const choose = (
    element: ProfileImageEditorElement,
    name = "portrait.png",
    type = "image/png",
  ): File => {
    const file = new File(["binary"], name, { type });
    const input = select(element, 'input[type="file"]', HTMLInputElement);
    Object.defineProperty(input, "files", { value: [file], configurable: true });
    input.dispatchEvent(new CustomEvent("change", { bubbles: true }));

    return file;
  };

  const submit = (element: ProfileImageEditorElement): void => {
    select(element, "form", HTMLFormElement).dispatchEvent(
      new CustomEvent("submit", { bubbles: true, cancelable: true }),
    );
  };

  beforeEach(() => {
    registerProfileEditingElement();
    registerProfileImageEditorElement();
    resetBody("");
    fetch = installFetch();
  });

  it("is defined under its published name", () => {
    assert.equal(profileImageEditorElementName, "academic-persons-edit-image-editor");
    assert.equal(
      customElements.get(profileImageEditorElementName),
      ProfileImageEditorElement,
    );
    // A second call is a no-op, not the "NotSupportedError" a repeated
    // definition of the same name raises - and it leaves the definition alone.
    const defined = customElements.get(profileImageEditorElementName);
    registerProfileImageEditorElement();
    assert.equal(customElements.get(profileImageEditorElementName), defined);
  });

  it("takes the contract from the editor it is rendered in", () => {
    const element = render();
    const owner = select(document.body, profileEditingElementName, HTMLElement);
    assert.ok(owner instanceof ProfileEditingRootElement);

    assert.equal(element.context, owner.context);
    assert.equal(element.context?.profileUid, 1);
    assert.notEqual(element.controller, null);
  });

  /**
   * The contract arrives as a property, which is what an element created in
   * JavaScript is handed and what the components of the following commits are
   * given. Asserted outside the editor on purpose: an element that resolved the
   * contract itself would find none here and start nothing.
   */
  it("takes a contract that was assigned before it connected", () => {
    const context = render().context;
    assert.ok(context !== null);
    const element = document.createElement(
      profileImageEditorElementName,
    ) as ProfileImageEditorElement;
    element.innerHTML = imageEditorView();

    element.context = context;
    document.body.append(element);

    assert.equal(element.context, context);
    assert.notEqual(element.controller, null);
  });

  it("starts nothing where there is no editor above it", () => {
    const body = resetBody(
      `<${profileImageEditorElementName}>${imageEditor()}</${profileImageEditorElementName}>`,
    );
    const element = select(body, profileImageEditorElementName, HTMLElement);
    assert.ok(element instanceof ProfileImageEditorElement);

    assert.equal(element.context, null);
    assert.equal(element.controller, null);
  });

  /**
   * The reason this element declares no properties of its own: the form is
   * server rendered and has to stay byte for byte what the server sent.
   */
  it("leaves the upload form and its hidden fields alone", () => {
    const element = render();
    click(rootOf(), "[data-pe-open-image-view]");

    // One child, the section Fluid rendered. The element adds no markup of its
    // own and wraps nothing in anything.
    assert.equal(element.children.length, 1);
    assert.equal(element.children[0]?.getAttribute("data-pe-image-view-container"), "");
    const hidden = Array.from(
      select(element, "form", HTMLFormElement).querySelectorAll<HTMLInputElement>(
        'input[type="hidden"]',
      ),
      (input): string => input.name,
    );
    assert.deepEqual(hidden, [
      "tx_academicpersonsedit_profile[__referrer][@extension]",
      "tx_academicpersonsedit_profile[__referrer][@controller]",
      "tx_academicpersonsedit_profile[__referrer][@action]",
      "tx_academicpersonsedit_profile[__referrer][arguments]",
      "tx_academicpersonsedit_profile[__trustedProperties]",
    ]);
  });

  it("sends the signature the server rendered along with the file", async () => {
    const element = render();
    click(rootOf(), "[data-pe-open-image-view]");
    await settle(20);
    choose(element);
    await settle(20);
    fetch.respond({ success: true, hasImage: true });

    submit(element);
    await settle(20);

    const body = fetch.calls[0]?.rawBody as FormData;
    assert.ok(body instanceof FormData);
    assert.equal(
      body.get("tx_academicpersonsedit_profile[__referrer][@action]"),
      "edit",
    );
    assert.match(
      String(body.get("tx_academicpersonsedit_profile[__trustedProperties]")),
      /^a:1:/,
    );
    assert.ok(
      body.get("tx_academicpersonsedit_profile[profile][image]") instanceof File,
    );
  });

  it("opens the editor from the image card and gives it the full width", async () => {
    const element = render();
    const root = rootOf();

    click(root, "[data-pe-open-image-view]");
    await settle(20);

    assert.equal(at(element, "[data-pe-image-view-container]").hidden, false);
    assert.equal(
      select(root, "[data-pe-open-image-view]", HTMLElement).getAttribute(
        "aria-expanded",
      ),
      "true",
    );
    const preview = select(root, "[data-pe-image-preview-column]", HTMLElement);
    assert.equal(preview.hidden, true);
    assert.equal(preview.classList.contains("col-lg-4"), false);
    const fields = select(
      root,
      ".academic-persons-profile-editing__profile-fields-column",
      HTMLElement,
    );
    assert.equal(fields.classList.contains("col-lg-12"), true);
    assert.equal(fields.classList.contains("col-lg-8"), false);
  });

  it("closes it again and gives the columns back", async () => {
    const element = render();
    const root = rootOf();
    click(root, "[data-pe-open-image-view]");
    await settle(20);

    click(root, "[data-pe-close-image-view]");
    await settle(20);

    assert.equal(at(element, "[data-pe-image-view-container]").hidden, true);
    assert.equal(
      select(root, "[data-pe-open-image-view]", HTMLElement).getAttribute(
        "aria-expanded",
      ),
      "false",
    );
    const preview = select(root, "[data-pe-image-preview-column]", HTMLElement);
    assert.equal(preview.hidden, false);
    assert.equal(preview.classList.contains("col-lg-4"), true);
  });

  it("shows the chosen file and allows saving it", async () => {
    const element = render();
    click(rootOf(), "[data-pe-open-image-view]");
    await settle(20);

    choose(element, "ada.png");
    await settle(20);

    const preview = at(element, "[data-pe-image-selected-preview]");
    assert.equal(preview.hidden, false);
    assert.ok(preview.getAttribute("src")?.startsWith("blob:"));
    assert.equal(preview.getAttribute("alt"), "ada.png");
    // Nothing to crop: the template asked for a plain preview.
    assert.equal(at(element, "[data-pe-image-cropper-stage]").hidden, true);
    assert.equal(
      select(element, "[data-pe-upload-image]", HTMLButtonElement).disabled,
      false,
    );
  });

  it("asks before it deletes, and takes the question back", async () => {
    const element = render();
    click(rootOf(), "[data-pe-open-image-view]");
    await settle(20);
    assert.equal(at(element, "[data-pe-image-delete-actions]").hidden, false);

    click(element, "[data-pe-delete-image]");

    assert.equal(at(element, "[data-pe-delete-image]").hidden, true);
    assert.equal(at(element, "[data-pe-delete-image-confirm-question]").hidden, false);
    assert.equal(at(element, "[data-pe-confirm-delete-image]").hidden, false);

    click(element, "[data-pe-cancel-delete-image]");

    assert.equal(at(element, "[data-pe-delete-image]").hidden, false);
    assert.equal(at(element, "[data-pe-cancel-delete-image]").hidden, true);
  });

  it("offers no deletion for a profile that has no image", () => {
    const element = render({ hasImage: false });

    assert.equal(at(element, "[data-pe-image-delete-actions]").hidden, true);
  });

  it("deletes through the button it just showed", async () => {
    const element = render();
    click(rootOf(), "[data-pe-open-image-view]");
    await settle(20);
    fetch.respond({ success: true });

    click(element, "[data-pe-delete-image]");
    click(element, "[data-pe-confirm-delete-image]");
    await settle(20);

    assert.equal(fetch.calls[0]?.url, endpoints.deleteImage);
    assert.equal(at(element, "[data-pe-image-delete-actions]").hidden, true);
  });

  it("shows the error the upload was refused with", async () => {
    const element = render();
    click(rootOf(), "[data-pe-open-image-view]");
    await settle(20);
    choose(element);
    await settle(20);
    fetch.respondWithError({ success: false, message: "The file is too large." }, 413);

    submit(element);
    await settle(20);

    const error = at(element, "[data-pe-image-error]");
    assert.equal(error.hidden, false);
    assert.equal(error.textContent, "The file is too large.");
    assert.equal(
      select(element, 'input[type="file"]', HTMLInputElement).getAttribute(
        "aria-invalid",
      ),
      "true",
    );
    // Refused, so the editor stays open with the choice still in it.
    assert.equal(at(element, "[data-pe-image-view-container]").hidden, false);
  });

  it("marks the editor busy and locks it while the upload runs", async () => {
    const element = render();
    click(rootOf(), "[data-pe-open-image-view]");
    await settle(20);
    choose(element);
    await settle(20);
    const pending = fetch.respondLater();

    submit(element);
    await settle(20);

    const section = at(element, "[data-pe-image-view-container]");
    assert.equal(section.getAttribute("aria-busy"), "true");
    assert.equal(section.style.cursor, "wait");
    assert.equal(
      select(element, "[data-pe-image-fieldset]", HTMLFieldSetElement).disabled,
      true,
    );
    assert.equal(at(element, "[data-pe-image-upload-spinner]").hidden, false);

    pending.settle({ success: true, hasImage: true });
    await settle(20);

    assert.equal(section.getAttribute("aria-busy"), "false");
    assert.equal(
      select(element, "[data-pe-image-fieldset]", HTMLFieldSetElement).disabled,
      false,
    );
  });

  /**
   * The open button belongs to the image card and stands outside this element,
   * so the click is delegated on the editor root - and a listener on a node the
   * element does not own has to leave with it.
   */
  it("stops driving the image card while it is disconnected", async () => {
    const element = render();
    const root = rootOf();
    element.remove();

    click(root, "[data-pe-open-image-view]");
    await settle(20);

    assert.equal(at(element, "[data-pe-image-view-container]").hidden, true);

    select(root, "[data-pe-image-editor-target]", HTMLElement).append(element);
    click(root, "[data-pe-open-image-view]");
    await settle(20);

    assert.equal(at(element, "[data-pe-image-view-container]").hidden, false);
  });

  it("keeps its editor when it is moved in the document", async () => {
    const element = render();
    const root = rootOf();
    const controller = element.controller;
    click(root, "[data-pe-open-image-view]");
    await settle(20);
    choose(element, "ada.png");
    await settle(20);

    element.remove();
    select(root, "[data-pe-image-editor-target]", HTMLElement).append(element);

    assert.equal(element.controller, controller);
    assert.equal(element.controller?.image.selectedName, "ada.png");
  });

  it("says nothing about a profile whose image cannot be edited", () => {
    // "Index.html" renders the partial only for a writable image, so the
    // element is simply absent - and the columns keep the widths the markup
    // was rendered with.
    const body = resetBody(profileEditingElement({ content: imageCard() }));
    registerProfileImageEditorElement();

    // Nothing creates one, and nothing on the page is touched in its place.
    assert.equal(body.querySelector(profileImageEditorElementName), null);
    assert.equal(
      select(body, "[data-pe-image-preview-column]", HTMLElement).getAttribute("style"),
      null,
    );
  });
});

/**
 * The cropping path.
 *
 * The cropper's stage and the image it crops are queried rather than held as
 * references, so the cropping path is reachable without a
 * browser, so `initializeCropper()` reached its own error branch and stopped.
 * They are queried out of the server rendered markup now, which is what makes
 * this testable at all - and `parseImageRatio()` is only reached through here.
 *
 * CropperJS is the one EXT:core publishes as `cropperjs`, and here it is the
 * stub of `Build/tests/stubs/cropper.mjs`: the real one measures a layout and
 * rasterises through a canvas, and jsdom has neither. What is asserted is
 * therefore the code around it - when a cropper is created, on which stage, at
 * which ratio, at which resolution the crop is rasterised, and on every path
 * that drops it that it is destroyed again.
 */
describe("the profile image cropper", () => {
  let fetch: FetchDouble;

  const render = (ratio = "4:3"): ProfileImageEditorElement => {
    const body = resetBody(
      profileEditingElement({
        content: imageCard() + profileFieldsColumn(),
        target: imageEditor(),
        imageRenderType: "cropper",
        imageCropperRatio: ratio,
      }),
    );
    const element = select(body, profileImageEditorElementName, HTMLElement);
    assert.ok(element instanceof ProfileImageEditorElement);
    // The cropper refuses to place a selection in a box of zero, and jsdom
    // reports zero for everything it has not been told about.
    setClientSize(select(element, "[data-pe-image-cropper-stage]", HTMLElement), {
      width: 800,
      height: 600,
    });

    return element;
  };

  const stageOf = (element: ProfileImageEditorElement): HTMLElement =>
    select(element, "[data-pe-image-cropper-stage]", HTMLElement);

  const open = async (): Promise<void> => {
    select(document.body, "[data-pe-open-image-view]", HTMLElement).click();
    await settle(20);
  };

  /**
   * The natural size of the chosen file, which jsdom reports as zero because it
   * decodes nothing. It is what the displayed crop is scaled back to, so a test
   * about the uploaded resolution has to say what it is.
   */
  const withNaturalWidth = (
    element: ProfileImageEditorElement,
    naturalWidth: number,
  ): void => {
    Object.defineProperty(
      select(element, "[data-pe-image-cropper-source]", HTMLImageElement),
      "naturalWidth",
      { value: naturalWidth, configurable: true },
    );
  };

  const submit = async (element: ProfileImageEditorElement): Promise<void> => {
    select(element, "form", HTMLFormElement).dispatchEvent(
      new CustomEvent("submit", { bubbles: true, cancelable: true }),
    );
    await settle(20);
  };

  const choose = (
    element: ProfileImageEditorElement,
    name = "portrait.png",
    type = "image/png",
  ): File => {
    const file = new File(["binary"], name, { type });
    const input = select(element, 'input[type="file"]', HTMLInputElement);
    Object.defineProperty(input, "files", { value: [file], configurable: true });
    input.dispatchEvent(new CustomEvent("change", { bubbles: true }));

    return file;
  };

  beforeEach(() => {
    registerProfileEditingElement();
    registerProfileImageEditorElement();
    resetBody("");
    fetch = installFetch();
  });

  it("crops the chosen file at the ratio the template configured", async () => {
    const element = render("4:3");
    await open();

    choose(element);
    await settle(20);

    const stage = stageOf(element);
    assert.equal(stage.hidden, false);
    assert.equal(stage.getAttribute("data-test-cropper"), "live");
    assert.equal(stage.getAttribute("data-test-cropper-ratio"), String(4 / 3));
    assert.ok(
      stage.querySelector("[data-pe-image-cropper-source]")?.getAttribute("src")
        ?.startsWith("blob:"),
    );
    // The plain preview stands down while the cropper has the file.
    assert.equal(select(element, "[data-pe-image-selected-preview]", HTMLElement).hidden, true);
    assert.equal(element.controller?.image.cropperReady, true);
    assert.equal(
      select(element, "[data-pe-upload-image]", HTMLButtonElement).disabled,
      false,
    );
  });

  it("reads every spelling of a ratio the template may carry", async () => {
    for (const [ratio, expected] of [
      ["4:3", 4 / 3],
      ["4/3", 4 / 3],
      ["4x3", 4 / 3],
      ["1.5", 1.5],
    ] as [string, number][]) {
      const element = render(ratio);
      await open();

      choose(element);
      await settle(20);

      assert.equal(
        element.controller?.image.cropperReady,
        true,
        `The ratio "${ratio}" was not understood.`,
      );
      // Not only that it was understood: that the number it was understood as
      // is the one the cropper was configured with.
      assert.equal(
        stageOf(element).getAttribute("data-test-cropper-ratio"),
        String(expected),
        `The ratio "${ratio}" did not reach the cropper.`,
      );
    }
  });

  /**
   * A ratio the template cannot mean is not a crop with a default: it is a
   * misconfiguration, the editor says so, and no cropper is created for it.
   */
  it("refuses a ratio it cannot read and says so", async () => {
    const element = render("x");
    await open();

    choose(element);
    await settle(20);

    assert.equal(stageOf(element).hasAttribute("data-test-cropper"), false);
    assert.equal(element.controller?.image.cropperReady, false);
    assert.equal(
      select(element, "[data-pe-image-error]", HTMLElement).textContent,
      messages.errorMessage,
    );
    assert.equal(
      select(element, "[data-pe-upload-image]", HTMLButtonElement).disabled,
      true,
    );
  });

  it("destroys the cropper of the file that was replaced", async () => {
    const element = render();
    await open();
    choose(element, "first.png");
    await settle(20);

    choose(element, "second.png");
    await settle(20);

    const stage = stageOf(element);
    // Exactly one dropped and exactly one live: an instance that outlived its
    // file would leave the count behind, and CropperJS keeps custom elements
    // and listeners on the stage that a second one would fight over.
    assert.equal(stage.getAttribute("data-test-cropper-destroys"), "1");
    assert.equal(stage.getAttribute("data-test-cropper"), "live");
  });

  it("destroys the cropper when the editor is closed", async () => {
    const element = render();
    await open();
    choose(element);
    await settle(20);
    assert.equal(stageOf(element).getAttribute("data-test-cropper"), "live");

    select(document.body, "[data-pe-close-image-view]", HTMLElement).click();
    await settle(20);

    assert.equal(stageOf(element).getAttribute("data-test-cropper"), "destroyed");
    assert.equal(stageOf(element).hidden, true);
  });

  it("uploads what was cropped and not what was chosen", async () => {
    const element = render();
    await open();
    const file = choose(element, "portrait.jpg", "image/jpeg");
    await settle(20);
    fetch.respond({ success: true, hasImage: true });

    await submit(element);

    const body = fetch.calls[0]?.rawBody as FormData;
    const uploaded = body.get("tx_academicpersonsedit_profile[profile][image]");
    assert.ok(uploaded instanceof File);
    assert.notEqual(uploaded, file);
    assert.equal((uploaded as File).type, "image/jpeg");
  });

  /**
   * The crop is rasterised at the resolution of the file, not at the resolution
   * the stage happened to be laid out at.
   *
   * The stage is 800 wide here and the chosen file is 1600, so the crop is
   * displayed at half size: a crop box of 680 stage pixels is 1360 pixels of
   * the file. Uploading the 680 would throw away half the detail of every
   * profile image on a narrow screen, and the visitor has no way of noticing.
   */
  it("rasterises the crop at the resolution of the chosen file", async () => {
    const element = render();
    await open();
    withNaturalWidth(element, 1600);
    choose(element, "portrait.jpg", "image/jpeg");
    await settle(20);
    fetch.respond({ success: true, hasImage: true });

    await submit(element);

    assert.equal(stageOf(element).getAttribute("data-test-cropper-width"), "1360");
  });

  /**
   * And not at any resolution: a photograph straight off a camera is wider than
   * anything the site renders, and the upload it would post is a file the
   * visitor waits for and the storage keeps for nothing.
   */
  it("caps the crop at the widest image the site renders", async () => {
    const element = render();
    await open();
    withNaturalWidth(element, 16000);
    choose(element, "portrait.jpg", "image/jpeg");
    await settle(20);
    fetch.respond({ success: true, hasImage: true });

    await submit(element);

    assert.equal(stageOf(element).getAttribute("data-test-cropper-width"), "2400");
  });
});

/**
 * The transition helper, driven directly.
 *
 * `<Transition>` did this, and the callback it ends with is where the editor's
 * close path hangs: the focus returns to the open button there and the
 * collapsed layout is restored there. A transition that never ends is therefore
 * not a missing animation but a silent, intermittent defect, and the three ways
 * it can fail to end - no transition at all, an event that never fires, a
 * cancellation - are the reason the helper exists.
 *
 * jsdom computes no styles, so the duration is stubbed. That is the whole of
 * what the browser contributes here: whether a transition runs, and whether it
 * reports.
 */
describe("the editor transition", () => {
  let element: HTMLElement;
  const computeStyle = globalThis.getComputedStyle;

  /**
   * jsdom computes no styles, so the one thing a browser contributes here - how
   * long the transition takes, and therefore whether it runs at all - is said
   * out loud.
   */
  const withDuration = (duration: string): void => {
    globalThis.getComputedStyle = ((): CSSStyleDeclaration =>
      ({ transitionDuration: duration }) as CSSStyleDeclaration) as typeof computeStyle;
    mock.timers.enable({ apis: ["setTimeout"] });
  };

  beforeEach(() => {
    element = select(resetBody("<div></div>"), "div", HTMLElement);
  });

  afterEach(() => {
    globalThis.getComputedStyle = computeStyle;
    mock.timers.reset();
  });

  it("reports at once where there is nothing to animate", () => {
    let reported = 0;

    runElementTransition(element, "leave", (): void => {
      reported += 1;
    });

    // jsdom computes no transition, which is the same answer a browser gives
    // under "prefers-reduced-motion" - and the visitor who asked for that must
    // not wait out a timeout.
    assert.equal(reported, 1);
    assert.equal(element.className, "");
  });

  it("dresses the element for the transition it starts", () => {
    withDuration("0.3s");

    runElementTransition(element, "leave", (): void => undefined);

    assert.ok(
      element.classList.contains(
        "academic-persons-profile-editing-image-editor-leave-active",
      ),
    );
  });

  it("reports when the transition ends", () => {
    withDuration("0.3s");
    let reported = 0;
    runElementTransition(element, "enter", (): void => {
      reported += 1;
    });

    element.dispatchEvent(new CustomEvent("transitionend"));

    assert.equal(reported, 1);
    assert.equal(element.className, "");
  });

  it("reports even when the transition never does", () => {
    withDuration("0.3s");
    let reported = 0;
    runElementTransition(element, "leave", (): void => {
      reported += 1;
    });

    assert.equal(reported, 0);
    mock.timers.tick(400);

    assert.equal(reported, 1);
    assert.equal(element.className, "");
  });

  it("reports once, whichever of the two arrives first", () => {
    withDuration("0.3s");
    let reported = 0;
    runElementTransition(element, "leave", (): void => {
      reported += 1;
    });

    element.dispatchEvent(new CustomEvent("transitionend"));
    mock.timers.tick(400);

    assert.equal(reported, 1);
  });

  it("reports not at all once it is cancelled", () => {
    withDuration("0.3s");
    let reported = 0;
    const cancel = runElementTransition(element, "leave", (): void => {
      reported += 1;
    });

    cancel();
    element.dispatchEvent(new CustomEvent("transitionend"));
    mock.timers.tick(400);

    assert.equal(reported, 0);
    assert.equal(element.className, "");
  });

  it("reads a duration in milliseconds as well as in seconds", () => {
    withDuration("300ms");
    let reported = 0;
    runElementTransition(element, "leave", (): void => {
      reported += 1;
    });

    assert.equal(reported, 0);
    mock.timers.tick(400);

    assert.equal(reported, 1);
  });
});
