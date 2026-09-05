import assert from "node:assert/strict";
import { beforeEach, describe, it } from "node:test";
import { resetBody, setBoundingRect } from "../../../../../Build/tests/dom.mjs";
import { initializeStickyImageOffset } from "@fgtclb/academic-persons-edit/frontend/profile/sticky-image.js";
import { imageCard, profileEditingRoot, select } from "./Fixtures/profile-editing.ts";

/**
 * The image column is parked below the site's fixed header, and the offset it
 * needs is measured from that header rather than configured. The measurement
 * is the one of EXT:academic_persons, shared with the public detail view.
 *
 * jsdom lays nothing out, so the header's height is injected: what is asserted
 * is the arithmetic and the lifecycle, not the geometry.
 */
describe("the sticky image column", () => {
  let root: HTMLElement;

  const render = (header: string): void => {
    const body = resetBody(header + profileEditingRoot({ content: imageCard() }));
    root = select(body, "[data-academic-persons-profile-editing]", HTMLElement);
  };

  const pageHeader = (): string =>
    '<div id="page-header" class="navbar-fixed-top"></div>';

  const sticky = (): HTMLElement =>
    select(root, "[data-pe-sticky-image]", HTMLElement);

  const layOutHeader = (height: number): void => {
    setBoundingRect(select(document.body, "#page-header", HTMLElement), { height });
  };

  beforeEach(() => {
    resetBody("");
  });

  it("parks the column below the fixed header, with a gap", () => {
    render(pageHeader());
    layOutHeader(64);

    initializeStickyImageOffset(root);

    assert.equal(sticky().style.getPropertyValue("top"), "74px");
    // Set as important, because the theme's own "top" would otherwise win.
    assert.equal(sticky().style.getPropertyPriority("top"), "important");
  });

  /**
   * A site whose header scrolls away needs no offset at all, and the column
   * keeps whatever the stylesheet gives it.
   */
  it("sets no offset where the page has no fixed header", () => {
    render("");

    initializeStickyImageOffset(root);

    assert.equal(sticky().style.getPropertyValue("top"), "");
  });

  it("measures again when the window is resized", () => {
    render(pageHeader());
    layOutHeader(64);
    initializeStickyImageOffset(root);

    layOutHeader(120);
    window.dispatchEvent(new CustomEvent("resize"));

    assert.equal(sticky().style.getPropertyValue("top"), "130px");
  });

  it("stops measuring when the page is left", () => {
    render(pageHeader());
    layOutHeader(64);
    initializeStickyImageOffset(root);

    window.dispatchEvent(new CustomEvent("pagehide"));
    layOutHeader(120);
    window.dispatchEvent(new CustomEvent("resize"));

    assert.equal(sticky().style.getPropertyValue("top"), "74px");
  });

  /**
   * An override that drops the image column, or a profile whose image field is
   * not writable: there is nothing to park, and the initialisation has to stay
   * silent rather than throw - including on the resize it subscribes to, which
   * is where a missing element would be dereferenced a second time.
   */
  it("does nothing where the page renders no image column", () => {
    render(pageHeader());
    layOutHeader(64);
    const column = sticky();
    column.remove();

    initializeStickyImageOffset(root);
    window.dispatchEvent(new CustomEvent("resize"));

    assert.equal(root.querySelector("[data-pe-sticky-image]"), null);
    assert.equal(column.style.getPropertyValue("top"), "");
    assert.equal(root.getAttribute("style"), null);
  });
});
