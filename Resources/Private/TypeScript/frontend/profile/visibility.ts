import {
  requestJson,
  showStatus,
} from "@fgtclb/academic-persons-edit/frontend/profile/common.js";
import {
  toEditingContext,
  type EditingTarget,
} from "@fgtclb/academic-persons-edit/frontend/profile/context.js";

interface ErrorResult {
  message?: string;
}

interface RequestError extends Error {
  result?: ErrorResult;
}

interface VisibilityController {
  updateVisibility(event: Event): Promise<void>;
}

const visibilityCheckboxSelector =
  ".academic-persons-profile-editing__visibility-checkbox";
const visibilityFormSelector = "[data-pe-visibility-form]";

/**
 * The owner's "Show my profile publicly" switch of
 * `Partials/Profile/Header.html`, and the listeners that drive it.
 *
 * It works like the synchronisation switch of `sync.ts`: it saves on change,
 * takes the stored value from the response, goes back to the last stored value
 * when the request fails, and swallows a submission of its form. The one
 * difference is the inversion. The switch is on while the profile is public,
 * and the endpoint takes and answers the profile's `hidden` value.
 */
export const createVisibility = (
  editingTarget: EditingTarget,
): VisibilityController => {
  const context = toEditingContext(editingTarget);
  const root = context.root;
  const checkbox = root.querySelector<HTMLInputElement>(
    visibilityCheckboxSelector,
  );
  const form = checkbox?.closest<HTMLFormElement>("form") ?? null;
  let persistedValue = checkbox?.checked ?? false;

  const updateVisibility = async (event: Event): Promise<void> => {
    const target = event.target;
    if (!(target instanceof HTMLInputElement)) {
      return;
    }
    const profileUid = context.profileUid;
    const updateUrl = context.urls.visibility;
    if (profileUid === null || updateUrl === undefined) {
      target.checked = persistedValue;
      showStatus(context, "danger");
      return;
    }
    const requestedValue = target.checked;
    form?.setAttribute("aria-busy", "true");
    target.disabled = true;
    showStatus(context, "info", context.messages.saving ?? null);
    try {
      const result = await requestJson(updateUrl, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          profile: profileUid,
          data: { hidden: !requestedValue },
        }),
      });
      persistedValue = !result.hidden;
      target.checked = persistedValue;
      target.classList.remove("is-invalid");
      showStatus(context, "success");
    } catch (error) {
      const result = (error as RequestError).result;
      target.checked = persistedValue;
      target.classList.add("is-invalid");
      showStatus(context, "danger", result?.message ?? null);
    } finally {
      target.disabled = false;
      form?.setAttribute("aria-busy", "false");
    }
  };

  root.addEventListener("submit", (event: Event): void => {
    if (
      event.target instanceof Element &&
      event.target.closest(visibilityFormSelector) !== null
    ) {
      event.preventDefault();
    }
  });
  root.addEventListener("change", (event: Event): void => {
    if (
      event.target instanceof Element &&
      event.target.closest(visibilityFormSelector) !== null
    ) {
      void updateVisibility(event);
    }
  });

  return { updateVisibility };
};
