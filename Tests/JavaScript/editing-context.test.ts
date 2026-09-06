import assert from "node:assert/strict";
import { beforeEach, describe, it } from "node:test";
import { resetBody } from "../../../../../Build/tests/dom.mjs";
import {
  readEditingContext,
  toEditingContext,
  type EditingContext,
} from "@fgtclb/academic-persons-edit/frontend/profile/context.js";
import {
  endpoints,
  labels,
  messages,
  placeholderImageUrl,
  profileEditingRoot,
  select,
} from "./Fixtures/profile-editing.ts";

/**
 * `readEditingContext()` is the only reader of the root's `data-*` contract.
 * Every module below it is handed the result, so a key it drops, renames or
 * coerces differently is not a wrong value in one place - it is a wrong value
 * everywhere at once, and the modules cannot notice because a
 * `DOMStringMap` returns `undefined` for a key that does not exist.
 *
 * What is pinned here:
 *
 * - the complete root: that every url, message and label of
 *   `Templates/Profile/Index.html` arrives, under the name the modules use;
 * - the minimal root: what a root that carries nothing but the marker reads
 *   as, because that is the shape a template regression produces;
 * - the malformed root: the coercions, which is where a "read once" reader
 *   can silently disagree with the per-call-site reads it replaced;
 * - that the result is frozen, because it is shared by every controller of a
 *   root and one of them writing to it would reconfigure the others.
 *
 * The strings are deliberately *not* defaulted by the reader. `?? null` and
 * `?? ""` mean different things to `showStatus()` - the first falls back to
 * the severity's own text, the second to no text at all - so the reader
 * returns what the attribute holds and each call site keeps its own fallback.
 */
describe("the profile editing contract", () => {
  describe("a complete root", () => {
    let context: EditingContext;

    beforeEach(() => {
      const body = resetBody(profileEditingRoot());
      context = readEditingContext(
        select(body, "[data-academic-persons-profile-editing]", HTMLElement),
      );
    });

    it("carries every endpoint the editors write to", () => {
      assert.deepEqual(
        { ...context.urls },
        {
          contractContactForm: endpoints.contractContactForm,
          createContractContact: endpoints.createContractContact,
          createDocument: endpoints.createDocument,
          deleteContractContact: endpoints.deleteContractContact,
          deleteDocument: endpoints.deleteDocument,
          deleteImage: endpoints.deleteImage,
          documentForm: endpoints.documentForm,
          skipSync: endpoints.skipSync,
          sortContractContact: endpoints.sortContractContact,
          sortDocument: endpoints.sortDocument,
          toggleContractContactVisibility: endpoints.toggleContractContactVisibility,
          update: endpoints.update,
          updateContractContact: endpoints.updateContractContact,
          updateDocument: endpoints.updateDocument,
        },
      );
    });

    it("carries every translated status message", () => {
      assert.deepEqual(
        { ...context.messages },
        {
          contractContactDeleteConfirm: messages.contractContactDeleteConfirm,
          contractContactEmpty: messages.contractContactEmpty,
          contractContactHidden: messages.contractContactHidden,
          contractContactShown: messages.contractContactShown,
          discarded: messages.discarded,
          documentDeleteConfirm: messages.documentDeleteConfirm,
          documentDeleted: messages.documentDeleted,
          documentSaved: messages.documentSaved,
          documentSorted: messages.documentSorted,
          editorError: messages.editorError,
          errorMessage: messages.errorMessage,
          errorTitle: messages.errorTitle,
          imageDeleted: messages.imageDeleted,
          imageUploadMissing: messages.imageUploadMissing,
          imageUploaded: messages.imageUploaded,
          infoMessage: messages.infoMessage,
          infoTitle: messages.infoTitle,
          saveInProgress: messages.saveInProgress,
          saving: messages.saving,
          successMessage: messages.successMessage,
          successTitle: messages.successTitle,
          unchanged: messages.unchanged,
          validation: messages.validation,
          warningTitle: messages.warningTitle,
        },
      );
    });

    it("carries the action labels of a document editor", () => {
      assert.deepEqual(
        { ...context.labels },
        {
          documentAdd: labels.add,
          documentDelete: labels.delete,
          documentEdit: labels.edit,
          documentEmpty: messages.empty,
          documentSave: labels.save,
          documentView: labels.view,
        },
      );
    });

    /**
     * The two labels the contract lost with the editor rewrite, asserted from
     * both sides: the root does not carry them any more, and nothing reads
     * them. They are rendered by `Partials/Profile/Documents/ContractContacts.html`
     * into the prototype of a row, where an override reaches them together
     * with the button they belong to.
     */
    it("carries no sort labels, which the prototypes render themselves", () => {
      assert.equal(context.root.hasAttribute("data-label-sort-up"), false);
      assert.equal(context.root.hasAttribute("data-label-sort-down"), false);
    });

    it("keeps the labels a value is composed from, and only those", () => {
      assert.deepEqual(Object.keys(context.labels).sort(), [
        "documentAdd",
        "documentDelete",
        "documentEdit",
        "documentEmpty",
        "documentSave",
        "documentView",
      ]);
    });

    it("carries what the template knows about the image", () => {
      assert.deepEqual(
        { ...context.image },
        {
          cropperRatio: "",
          hasImage: true,
          placeholderAlt: messages.placeholderAlt,
          placeholderUrl: placeholderImageUrl,
          renderType: "",
        },
      );
    });

    it("carries the profile and the editor language", () => {
      assert.equal(context.profileUid, 1);
      assert.equal(context.profileUidValue, "1");
      assert.equal(context.editorLanguage, "en");
    });

    it("carries the element it was read from", () => {
      assert.equal(
        context.root,
        select(
          document.body,
          "[data-academic-persons-profile-editing]",
          HTMLElement,
        ),
      );
    });
  });

  /**
   * Not a shape any template renders - which is the point. A root that lost
   * its attributes must produce a context the modules can hold without
   * throwing, so that the guard each of them has ("no endpoint, no request")
   * is the thing that stops them.
   */
  describe("a minimal root", () => {
    let context: EditingContext;

    beforeEach(() => {
      const body = resetBody('<div data-academic-persons-profile-editing></div>');
      context = readEditingContext(
        select(body, "[data-academic-persons-profile-editing]", HTMLElement),
      );
    });

    it("reports every endpoint as unconfigured rather than as an empty url", () => {
      assert.deepEqual(
        Object.values({ ...context.urls }),
        new Array(14).fill(undefined),
      );
    });

    it("reports every message and label as absent", () => {
      assert.deepEqual(
        Object.values({ ...context.messages }),
        new Array(24).fill(undefined),
      );
      assert.deepEqual(
        Object.values({ ...context.labels }),
        new Array(6).fill(undefined),
      );
    });

    it("has no profile, no language and no image", () => {
      assert.equal(context.profileUid, null);
      assert.equal(context.profileUidValue, "");
      assert.equal(context.editorLanguage, "");
      assert.equal(context.image.hasImage, false);
      assert.equal(context.image.renderType, "");
      assert.equal(context.image.cropperRatio, undefined);
      assert.equal(context.image.placeholderUrl, undefined);
    });
  });

  /**
   * The four values that are coerced rather than passed through. Each of them
   * was coerced at its call site before, and moving that here is the only way
   * the reader can disagree with what it replaced.
   */
  describe("a malformed root", () => {
    const contextWith = (
      attributes: Record<string, string>,
    ): EditingContext => {
      const body = resetBody(profileEditingRoot());
      const root = select(
        body,
        "[data-academic-persons-profile-editing]",
        HTMLElement,
      );
      Object.entries(attributes).forEach(([name, value]): void => {
        root.setAttribute(name, value);
      });

      return readEditingContext(root);
    };

    it("rejects a profile uid that is not a positive integer", () => {
      assert.equal(contextWith({ "data-profile-uid": "0" }).profileUid, null);
      assert.equal(contextWith({ "data-profile-uid": "-3" }).profileUid, null);
      assert.equal(contextWith({ "data-profile-uid": "x7" }).profileUid, null);
      assert.equal(contextWith({ "data-profile-uid": "" }).profileUid, null);
    });

    it("keeps the profile uid attribute verbatim for the element ids", () => {
      // The ids of the profile fields are "profile-editing-{uid}-{name}",
      // written by Fluid from this very attribute, so a normalised uid would
      // build an id the markup does not contain. The two therefore disagree
      // on a malformed attribute - "07x" is a valid element id fragment and
      // is not a uid any endpoint is called with - and that is deliberate.
      const context = contextWith({ "data-profile-uid": "07x" });
      assert.equal(context.profileUidValue, "07x");
      assert.equal(context.profileUid, 7);
    });

    it("treats anything but \"1\" as no image", () => {
      assert.equal(contextWith({ "data-has-image": "true" }).image.hasImage, false);
      assert.equal(contextWith({ "data-has-image": "0" }).image.hasImage, false);
      assert.equal(contextWith({ "data-has-image": "" }).image.hasImage, false);
      assert.equal(contextWith({ "data-has-image": "1" }).image.hasImage, true);
    });

    it("lower cases the image render type", () => {
      assert.equal(
        contextWith({ "data-image-render-type": "Cropper" }).image.renderType,
        "cropper",
      );
    });

    it("trims and lower cases the editor language", () => {
      assert.equal(
        contextWith({ "data-editor-language": "  DE-de \n" }).editorLanguage,
        "de-de",
      );
    });

    it("leaves the cropper ratio to the module that owns the cropper", () => {
      assert.equal(
        contextWith({ "data-image-cropper-ratio": " 4 : 3 " }).image
          .cropperRatio,
        " 4 : 3 ",
      );
    });
  });

  describe("the result", () => {
    let context: EditingContext;

    beforeEach(() => {
      const body = resetBody(profileEditingRoot());
      context = readEditingContext(
        select(body, "[data-academic-persons-profile-editing]", HTMLElement),
      );
    });

    it("is frozen, and so is every group in it", () => {
      assert.equal(Object.isFrozen(context), true);
      assert.equal(Object.isFrozen(context.urls), true);
      assert.equal(Object.isFrozen(context.image), true);
      assert.equal(Object.isFrozen(context.messages), true);
      assert.equal(Object.isFrozen(context.labels), true);
    });

    it("refuses a write, rather than accepting it silently", () => {
      // The modules run as ES modules and are therefore strict: an assignment
      // to a frozen object throws instead of being dropped.
      assert.throws(
        (): void => {
          (context.urls as { update?: string }).update = "https://example.test/";
        },
        TypeError,
      );
      assert.equal(context.urls.update, endpoints.update);
    });

    it("is passed through unchanged when it is handed back", () => {
      assert.equal(toEditingContext(context), context);
    });

    it("is read from the element when an element is handed in", () => {
      const readAgain = toEditingContext(context.root);
      assert.notEqual(readAgain, context);
      assert.equal(readAgain.root, context.root);
      assert.equal(readAgain.urls.update, endpoints.update);
    });
  });
});
