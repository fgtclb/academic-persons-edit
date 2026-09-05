import { observePageHeaderOffset } from "@fgtclb/academic-persons/frontend/sticky-offset.js";

const stickyImageSelector = "[data-pe-sticky-image]";
const pageHeaderSelector = "#page-header.navbar-fixed-top";

/**
 * Parks the image column below the site's fixed header. The measurement is the
 * one of EXT:academic_persons, which the public detail view uses for its sticky
 * section navigation - one implementation, two callers.
 */
export const initializeStickyImageOffset = (root: HTMLElement): void => {
  const stickyImage = root.querySelector<HTMLElement>(stickyImageSelector);
  if (stickyImage === null) {
    return;
  }
  observePageHeaderOffset(pageHeaderSelector, (offset): void => {
    if (offset === null) {
      stickyImage.style.removeProperty("top");
      return;
    }
    stickyImage.style.setProperty("top", `${offset}px`, "important");
  });
};
