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

interface SkipSyncController {
  updateSkipSync(event: Event): Promise<void>;
}

const syncCheckboxSelector = ".academic-persons-profile-editing__sync-checkbox";
const syncFormSelector = "[data-pe-sync-form]";

/**
 * The switch of `Partials/Profile/Header.html`, and the listeners that drive it.
 *
 * Two listeners reach it: one for the change of the switch and one that
 * swallows the form's own submission. They are delegated on the plugin
 * root rather than bound to the form, which is the mechanism every other
 * control of the editor uses: the root is the one node that
 * is certainly there, and a handler that reads its target off the event needs
 * no reference to the markup it serves.
 *
 * `submit` is prevented and nothing else is done with it, which is exactly what
 * the `.prevent` modifier did. The form ships without a submit button, so
 * nothing in the editor submits it; what the guard covers is a submission the
 * markup did not ask for - an integrator's override that adds a button, or a
 * browser's implicit submission - which would navigate away from the editor
 * with a `GET` to the page itself and lose whatever else is open.
 */
export const createSkipSync = (
  editingTarget: EditingTarget,
): SkipSyncController => {
  const context = toEditingContext(editingTarget);
  const root = context.root;
  const checkbox = root.querySelector<HTMLInputElement>(syncCheckboxSelector);
  const form = checkbox?.closest<HTMLFormElement>("form") ?? null;
  let persistedValue = checkbox?.checked ?? false;

  const updateSkipSync = async (event: Event): Promise<void> => {
    const target = event.target;
    if (!(target instanceof HTMLInputElement)) {
      return;
    }
    const profileUid = context.profileUid;
    const updateUrl = context.urls.skipSync;
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
          data: { skipSync: requestedValue },
        }),
      });
      persistedValue = Boolean(result.skipSync);
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
      event.target.closest(syncFormSelector) !== null
    ) {
      event.preventDefault();
    }
  });
  root.addEventListener("change", (event: Event): void => {
    if (
      event.target instanceof Element &&
      event.target.closest(syncFormSelector) !== null
    ) {
      void updateSkipSync(event);
    }
  });

  return { updateSkipSync };
};
