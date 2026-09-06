/* Generated from Resources/Private/TypeScript — do not edit. */
import { showStatus } from "@fgtclb/academic-persons-edit/frontend/profile/common.js";
import {
  toEditingContext
} from "@fgtclb/academic-persons-edit/frontend/profile/context.js";
const unsavedChangesTemplateName = "unsaved-changes";
const dialogSelector = "[data-pe-unsaved-changes]";
const registries = /* @__PURE__ */ new WeakMap();
const registryOf = (context) => {
  let registry = registries.get(context.root);
  if (registry === void 0) {
    registry = /* @__PURE__ */ new Set();
    registries.set(context.root, registry);
  }
  return registry;
};
const registerOpenEditor = (editingTarget, editor) => {
  const registry = registryOf(toEditingContext(editingTarget));
  registry.add(editor);
  return () => {
    registry.delete(editor);
  };
};
const isModalCapable = (dialog) => typeof dialog.showModal === "function" && typeof dialog.close === "function";
const askUnsavedChanges = (editingTarget) => {
  var _a;
  const context = toEditingContext(editingTarget);
  const root = context.root;
  const prototype = root.querySelector(
    `template[data-pe-dialog="${unsavedChangesTemplateName}"]`
  );
  const dialog = (_a = prototype == null ? void 0 : prototype.content.querySelector(dialogSelector)) == null ? void 0 : _a.cloneNode(true);
  if (!(dialog instanceof HTMLDialogElement)) {
    return Promise.resolve("cancel");
  }
  const previouslyFocused = document.activeElement instanceof HTMLElement ? document.activeElement : null;
  return new Promise((resolve) => {
    var _a2;
    let settled = false;
    const finish = (choice) => {
      if (settled) {
        return;
      }
      settled = true;
      if (isModalCapable(dialog) && dialog.open) {
        dialog.close();
      }
      dialog.remove();
      if ((previouslyFocused == null ? void 0 : previouslyFocused.isConnected) === true) {
        previouslyFocused.focus({ preventScroll: true });
      }
      resolve(choice);
    };
    dialog.addEventListener("click", (event) => {
      const target = event.target instanceof Element ? event.target : null;
      const button = target == null ? void 0 : target.closest("button[data-pe-unsaved-choice]");
      if (button === null || button === void 0 || button.disabled) {
        return;
      }
      event.preventDefault();
      const choice = button.dataset.peUnsavedChoice;
      finish(
        choice === "save" || choice === "discard" ? choice : "cancel"
      );
    });
    dialog.addEventListener("cancel", (event) => {
      event.preventDefault();
      finish("cancel");
    });
    dialog.addEventListener("close", () => finish("cancel"));
    root.append(dialog);
    if (isModalCapable(dialog)) {
      dialog.showModal();
    } else {
      dialog.setAttribute("open", "");
      (_a2 = dialog.querySelector("button[data-pe-unsaved-choice]")) == null ? void 0 : _a2.focus({ preventScroll: true });
    }
  });
};
const closeOtherEditors = (editingTarget, keep = null) => {
  const context = toEditingContext(editingTarget);
  const editors = [...registryOf(context)].filter(
    (editor) => editor !== keep
  );
  const closeFrom = (index) => {
    var _a;
    for (let position = index; position < editors.length; position += 1) {
      const editor = editors[position];
      if (editor === void 0 || !editor.isOpen()) {
        continue;
      }
      if (((_a = editor.isBusy) == null ? void 0 : _a.call(editor)) === true) {
        showStatus(context, "info", context.messages.saveInProgress ?? null);
        return false;
      }
      if (!editor.isDirty()) {
        editor.discard();
        continue;
      }
      return askUnsavedChanges(context).then(
        async (choice) => {
          if (choice === "cancel") {
            return false;
          }
          if (choice === "save") {
            if (!await editor.save()) {
              return false;
            }
          } else {
            editor.discard();
          }
          return closeFrom(position + 1);
        }
      );
    }
    return true;
  };
  return closeFrom(0);
};
const withOtherEditorsClosed = (editingTarget, keep, action) => {
  const closed = closeOtherEditors(editingTarget, keep);
  if (closed === true) {
    action();
  } else if (closed !== false) {
    void closed.then((ok) => {
      if (ok) {
        action();
      }
    });
  }
};
export {
  askUnsavedChanges,
  closeOtherEditors,
  registerOpenEditor,
  unsavedChangesTemplateName,
  withOtherEditorsClosed
};
