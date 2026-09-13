import assert from "node:assert/strict";
import { afterEach, beforeEach, describe, it } from "node:test";
import { resetBody } from "../../../../../Build/tests/dom.mjs";
import {
  profileEditingElementName,
  ProfileEditingRootElement,
  registerProfileEditingElement,
} from "@fgtclb/academic-persons-edit/frontend/profile/elements/root.js";
import {
  profileImageEditorElementName,
  ProfileImageEditorElement,
  registerProfileImageEditorElement,
} from "@fgtclb/academic-persons-edit/frontend/profile/elements/image-editor.js";
import type { EditingContext } from "@fgtclb/academic-persons-edit/frontend/profile/context.js";
import {
  imageCard,
  imageEditor,
  profileEditingRoot,
  select,
} from "./Fixtures/profile-editing.ts";

/**
 * The order a browser's parser produces when the module wins the race.
 *
 * TYPO3 renders `<f:asset.module>` as `<script type="module" async>`, so the
 * entry point may run while the document is still being parsed. The parser
 * then reaches `<academic-persons-edit-profile-editing>` with the element
 * already defined, constructs it at its start tag and connects it before any
 * child exists. Firefox does that often enough on a real network to leave the
 * editor inert (ACE-647); a parser pause in front of the element reproduces it
 * every time.
 *
 * jsdom has no streaming parser, so the order is built by hand: the document
 * reports `loading`, the element is inserted empty, its children follow, and
 * `DOMContentLoaded` ends the parse. The upgrade orders of an element that was
 * already complete when the module ran are the business of the two
 * `*-upgrade.test.ts` files.
 */
describe("an editor whose element is connected while the document is parsed", () => {
  let readyState: DocumentReadyState = "complete";

  const endParsing = (): void => {
    readyState = "interactive";
    document.dispatchEvent(new Event("DOMContentLoaded"));
  };

  /**
   * Read through a function, because an `assert.equal(element.context, null)`
   * narrows the property for the rest of the test.
   */
  const profileUidOf = (element: {
    context: EditingContext | null;
  }): number | null | undefined => element.context?.profileUid;

  /** What the parser does at the start tag: the element, without children. */
  const connectEmpty = (): HTMLElement => {
    const body = resetBody("");
    const element = document.createElement(profileEditingElementName);
    body.append(element);

    return element;
  };

  beforeEach(() => {
    registerProfileEditingElement();
    registerProfileImageEditorElement();
    readyState = "loading";
    Object.defineProperty(document, "readyState", {
      configurable: true,
      get: (): DocumentReadyState => readyState,
    });
  });

  afterEach(() => {
    delete (document as { readyState?: DocumentReadyState }).readyState;
  });

  it("starts once the markup below it has been parsed", () => {
    const element = connectEmpty();
    assert.ok(element instanceof ProfileEditingRootElement);
    element.innerHTML = profileEditingRoot({ profileUid: 3 });

    assert.equal(element.context, null);

    endParsing();

    assert.equal(profileUidOf(element), 3);
  });

  it("waits for the parse even when its markup is already below it", () => {
    const element = connectEmpty();
    assert.ok(element instanceof ProfileEditingRootElement);
    element.innerHTML = profileEditingRoot({ profileUid: 5 });
    element.remove();
    document.body.append(element);

    assert.equal(element.context, null);

    endParsing();

    assert.equal(profileUidOf(element), 5);
  });

  it("starts the image editor below it after the root", () => {
    const element = connectEmpty();
    assert.ok(element instanceof ProfileEditingRootElement);
    element.innerHTML = profileEditingRoot({
      profileUid: 7,
      content: imageCard({ profileUid: 7 }),
      target: imageEditor({ profileUid: 7 }),
    });
    const image = select(element, profileImageEditorElementName, HTMLElement);
    assert.ok(image instanceof ProfileImageEditorElement);

    assert.equal(image.context, null);
    assert.equal(image.controller, null);

    endParsing();

    assert.equal(image.context, element.context);
    assert.equal(profileUidOf(image), 7);
    assert.notEqual(image.controller, null);
  });

  it("adds one listener however often it is connected during the parse", () => {
    const types: string[] = [];
    const addEventListener = document.addEventListener.bind(document);
    Object.defineProperty(document, "addEventListener", {
      configurable: true,
      value: (...args: Parameters<Document["addEventListener"]>): void => {
        types.push(args[0]);
        addEventListener(...args);
      },
    });
    try {
      const element = connectEmpty();
      assert.ok(element instanceof ProfileEditingRootElement);
      element.innerHTML = profileEditingRoot({ profileUid: 11 });
      element.remove();
      document.body.append(element);
      element.remove();
      document.body.append(element);

      assert.equal(types.filter((type) => type === "DOMContentLoaded").length, 1);
    } finally {
      delete (document as { addEventListener?: unknown }).addEventListener;
    }

    endParsing();

    assert.equal(profileUidOf(select(document.body, profileEditingElementName, ProfileEditingRootElement)), 11);
  });

  it("does not start an element that left the document before the parse ended", () => {
    const element = connectEmpty();
    assert.ok(element instanceof ProfileEditingRootElement);
    element.innerHTML = profileEditingRoot({ profileUid: 9 });
    element.remove();

    endParsing();

    assert.equal(element.context, null);
  });
});
