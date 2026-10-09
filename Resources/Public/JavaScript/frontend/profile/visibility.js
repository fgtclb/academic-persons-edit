/* Generated from Resources/Private/TypeScript — do not edit. */
import {
  requestJson,
  showStatus
} from "@fgtclb/academic-persons-edit/frontend/profile/common.js";
import {
  toEditingContext
} from "@fgtclb/academic-persons-edit/frontend/profile/context.js";
const visibilityCheckboxSelector = "[data-pe-visibility-checkbox]";
const visibilityFormSelector = "[data-pe-visibility-form]";
const createVisibility = (editingTarget) => {
  const context = toEditingContext(editingTarget);
  const root = context.root;
  const checkbox = root.querySelector(
    visibilityCheckboxSelector
  );
  const form = (checkbox == null ? void 0 : checkbox.closest("form")) ?? null;
  let persistedValue = (checkbox == null ? void 0 : checkbox.checked) ?? false;
  const updateVisibility = async (event) => {
    const target = event.target;
    if (!(target instanceof HTMLInputElement)) {
      return;
    }
    const profileUid = context.profileUid;
    const updateUrl = context.urls.visibility;
    if (profileUid === null || updateUrl === void 0) {
      target.checked = persistedValue;
      showStatus(context, "danger");
      return;
    }
    const requestedValue = target.checked;
    form == null ? void 0 : form.setAttribute("aria-busy", "true");
    target.disabled = true;
    showStatus(context, "info", context.messages.saving ?? null);
    try {
      const result = await requestJson(updateUrl, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          profile: profileUid,
          data: { hidden: !requestedValue }
        })
      });
      persistedValue = !result.hidden;
      target.checked = persistedValue;
      target.classList.remove("is-invalid");
      showStatus(context, "success");
    } catch (error) {
      const result = error.result;
      target.checked = persistedValue;
      target.classList.add("is-invalid");
      showStatus(context, "danger", (result == null ? void 0 : result.message) ?? null);
    } finally {
      target.disabled = false;
      form == null ? void 0 : form.setAttribute("aria-busy", "false");
    }
  };
  root.addEventListener("submit", (event) => {
    if (event.target instanceof Element && event.target.closest(visibilityFormSelector) !== null) {
      event.preventDefault();
    }
  });
  root.addEventListener("change", (event) => {
    if (event.target instanceof Element && event.target.closest(visibilityFormSelector) !== null) {
      void updateVisibility(event);
    }
  });
  return { updateVisibility };
};
export {
  createVisibility
};
