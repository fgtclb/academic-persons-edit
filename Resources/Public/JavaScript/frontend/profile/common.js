/* Generated from Resources/Private/TypeScript — do not edit. */
import {
  toEditingContext
} from "@fgtclb/academic-persons-edit/frontend/profile/context.js";
const rootSelector = "[data-academic-persons-profile-editing]";
const hooks = (element) => element.dataset;
let activeRequestCount = 0;
let previousBodyCursor = "";
const setRequestBusy = (busy) => {
  const body = document.body;
  if (busy) {
    if (activeRequestCount === 0) {
      previousBodyCursor = body.style.cursor;
    }
    activeRequestCount += 1;
    body.style.cursor = "wait";
    return;
  }
  activeRequestCount = Math.max(0, activeRequestCount - 1);
  if (activeRequestCount === 0) {
    body.style.cursor = previousBodyCursor;
  }
};
const getBootstrap = () => globalThis.bootstrap;
const setDisabled = (button, disabled) => {
  button.disabled = disabled;
  button.setAttribute("aria-disabled", String(disabled));
};
const setExpanded = (button, expanded) => {
  button.setAttribute("aria-expanded", expanded ? "true" : "false");
  const label = expanded ? hooks(button).peLabelExpanded : hooks(button).peLabelCollapsed;
  if (label !== void 0 && label !== "") {
    button.setAttribute("aria-label", label);
    button.setAttribute("title", label);
  }
  button.querySelectorAll("[data-pe-view-icon]").forEach((icon) => {
    icon.hidden = hooks(icon).peViewIcon === "expanded" !== expanded;
  });
};
const setHiddenState = (button, hidden) => {
  const label = hidden ? hooks(button).peLabelHidden : hooks(button).peLabelVisible;
  if (label !== void 0 && label !== "") {
    button.setAttribute("aria-label", label);
    button.setAttribute("title", label);
  }
  button.querySelectorAll("[data-pe-visibility-icon]").forEach((icon) => {
    icon.hidden = hooks(icon).peVisibilityIcon === "hidden" !== hidden;
  });
};
const isEditableField = (element) => element instanceof HTMLInputElement || element instanceof HTMLSelectElement || element instanceof HTMLTextAreaElement;
const initializePopover = (scope = document) => {
  var _a;
  const Popover = (_a = getBootstrap()) == null ? void 0 : _a.Popover;
  if (Popover === void 0) {
    return [];
  }
  return Array.from(
    scope.querySelectorAll(`[data-bs-toggle='popover']`),
    (trigger) => new Popover(trigger)
  );
};
const showStatus = (editingTarget, type, message = null, region = null) => {
  var _a, _b;
  const context = toEditingContext(editingTarget);
  const messages = context.messages;
  const statusValues = {
    danger: {
      title: messages.errorTitle ?? "",
      message: messages.errorMessage ?? "",
      className: "bg-danger"
    },
    success: {
      title: messages.successTitle ?? "",
      message: messages.successMessage ?? "",
      className: "bg-success"
    },
    info: {
      title: messages.infoTitle ?? "",
      message: messages.infoMessage ?? "",
      className: "bg-info"
    },
    warning: {
      title: messages.warningTitle ?? "",
      message: messages.validation ?? "",
      className: "bg-warning"
    }
  };
  const status = statusValues[type];
  const statusRegion = region ?? (type === "danger" ? "alert" : "status");
  const statusToast = context.root.querySelector(
    `[data-pe-status-toast='${statusRegion}']`
  );
  if (statusToast === null) {
    return;
  }
  statusToast.classList.remove(
    "d-none",
    "bg-info",
    "bg-success",
    "bg-danger",
    "bg-warning"
  );
  statusToast.classList.add(status.className);
  const titleElement = statusToast.querySelector(".status-title");
  const messageElement = statusToast.querySelector(".status-message");
  if (titleElement !== null) {
    titleElement.textContent = status.title;
  }
  if (messageElement !== null) {
    messageElement.textContent = message ?? status.message;
  }
  (_b = (_a = getBootstrap()) == null ? void 0 : _a.Toast) == null ? void 0 : _b.getOrCreateInstance(statusToast).show();
};
const requestJson = async (url, options = {}) => {
  const { headers = {}, ...requestOptions } = options;
  setRequestBusy(true);
  try {
    const response = await fetch(url, {
      credentials: "same-origin",
      ...requestOptions,
      headers: {
        Accept: "application/json",
        // Required by every writing endpoint. A custom header cannot be set on
        // a cross-origin request without a preflight, which is what keeps a
        // foreign page from posting to the editor with the visitor's session.
        "X-Requested-With": "XMLHttpRequest",
        ...headers
      }
    });
    const result = await response.json().catch(() => null);
    if (!response.ok || typeof result !== "object" || result === null || !("success" in result) || result.success !== true) {
      const error = new Error("The request failed.");
      error.result = result;
      throw error;
    }
    return result;
  } finally {
    setRequestBusy(false);
  }
};
export {
  hooks,
  initializePopover,
  isEditableField,
  requestJson,
  rootSelector,
  setDisabled,
  setExpanded,
  setHiddenState,
  showStatus
};
