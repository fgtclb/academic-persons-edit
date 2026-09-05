/* Generated from Resources/Private/TypeScript — do not edit. */
import { observePageHeaderOffset } from "@fgtclb/academic-persons/frontend/sticky-offset.js";
const stickyImageSelector = "[data-pe-sticky-image]";
const pageHeaderSelector = "#page-header.navbar-fixed-top";
const initializeStickyImageOffset = (root) => {
  const stickyImage = root.querySelector(stickyImageSelector);
  if (stickyImage === null) {
    return;
  }
  observePageHeaderOffset(pageHeaderSelector, (offset) => {
    if (offset === null) {
      stickyImage.style.removeProperty("top");
      return;
    }
    stickyImage.style.setProperty("top", `${offset}px`, "important");
  });
};
export {
  initializeStickyImageOffset
};
