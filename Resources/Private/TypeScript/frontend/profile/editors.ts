/**
 * One editor at a time, across every editor of a profile.
 *
 * A profile field, a field group, the whole profile form, a document row of a
 * section and the contact of a contract each open an editor of their own, and
 * each is driven by its own module. Until now the modules knew nothing of one
 * another: a pencil pressed while another module's editor stood open left that
 * editor standing, with whatever had been typed into it, and the two panels
 * then disagreed about which one the visitor was working in. Within one
 * module the answer was a silent discard.
 *
 * This is the one place the modules meet. Each registers what it can do with
 * the editor it may have open - whether one is open, whether it holds changes,
 * how to save it and how to throw it away - and every module asks
 * `closeOtherEditors()` before it opens one. An editor without changes is
 * closed as its own cancel would close it. An editor with changes is not: the
 * visitor is asked, in a dialog of the editor's own, whether to save what is
 * there, to discard it, or to stay where they are. Only the first two let the
 * new editor open, and a save that the server refuses keeps the visitor in the
 * editor it refused, with the messages in front of them.
 *
 * The dialog is a `<dialog>` element cloned from the `unsaved-changes`
 * template of `Partials/Profile/UnsavedChanges.html`, never
 * `window.confirm()`: the question, the three answers and their labels are
 * Fluid's to render and to translate, and a native prompt can be suppressed by
 * the browser after the first one. It is modal where the browser can make it
 * so - `showModal()` puts the rest of the page behind an inert backdrop and
 * returns the focus by itself - and merely open where it cannot.
 */
import { showStatus } from "@fgtclb/academic-persons-edit/frontend/profile/common.js";
import {
  toEditingContext,
  type EditingContext,
  type EditingTarget,
} from "@fgtclb/academic-persons-edit/frontend/profile/context.js";

/** What the visitor answered, or `cancel` for a dialog that was dismissed. */
export type UnsavedChangesChoice = "save" | "discard" | "cancel";

/**
 * What a module can do with the editor it may have open.
 *
 * `save()` resolves `true` once the changes are stored and the editor is
 * closed, and `false` when the server refused them - the editor then stays
 * open with its messages, and the operation that asked is cancelled.
 * `discard()` puts the editor back to what is stored and closes it; it is also
 * what closes an editor that holds no changes, so that a close is a close
 * whichever way it happens.
 */
export interface OpenEditor {
  isOpen(): boolean;
  /**
   * Whether a request of the editor is on its way. Such an editor is neither
   * saved nor discarded - its response is about to write into it - and the
   * caller opens nothing; the visitor is told to wait.
   */
  isBusy?(): boolean;
  isDirty(): boolean;
  save(): Promise<boolean>;
  discard(): void;
}

/**
 * The template the dialog is cloned from: `data-pe-dialog`, not
 * `data-pe-proto`, because it is not a prototype in the filler's sense - it
 * has no slot to fill and is cloned whole - and the inventory of prototypes is
 * pinned by name in two suites.
 */
export const unsavedChangesTemplateName = "unsaved-changes";
const dialogSelector = "[data-pe-unsaved-changes]";

const registries = new WeakMap<HTMLElement, Set<OpenEditor>>();

const registryOf = (context: EditingContext): Set<OpenEditor> => {
  let registry = registries.get(context.root);
  if (registry === undefined) {
    registry = new Set();
    registries.set(context.root, registry);
  }
  return registry;
};

/**
 * Makes an editor known to the profile it belongs to.
 *
 * Registered once per module and per root, at start-up, rather than per
 * editor that opens: the module knows which of its editors is open, and the
 * registry only has to know whom to ask.
 */
export const registerOpenEditor = (
  editingTarget: EditingTarget,
  editor: OpenEditor,
): (() => void) => {
  const registry = registryOf(toEditingContext(editingTarget));
  registry.add(editor);
  return (): void => {
    registry.delete(editor);
  };
};

/**
 * Whether a dialog element can really be modal here.
 *
 * `showModal()` is what makes the rest of the page inert and hands the focus
 * back on close. A DOM without it - jsdom's, at the time of writing - gets the
 * `open` attribute instead, which renders the same markup without the backdrop.
 */
const isModalCapable = (
  dialog: HTMLDialogElement,
): dialog is HTMLDialogElement & { showModal(): void; close(): void } =>
  typeof dialog.showModal === "function" && typeof dialog.close === "function";

/**
 * Asks whether the changes of the editor that is open are to be saved,
 * discarded, or kept in front of the visitor.
 *
 * Resolves once the dialog is gone. `Escape` and the dialog's own cancel are
 * the same answer as the cancel button. A root whose markup carries no
 * `unsaved-changes` template - an override that dropped it - answers `cancel`:
 * nothing is thrown away because a template is missing, and the visitor still
 * has the editor's own buttons.
 */
export const askUnsavedChanges = (
  editingTarget: EditingTarget,
): Promise<UnsavedChangesChoice> => {
  const context = toEditingContext(editingTarget);
  const root = context.root;
  const prototype = root.querySelector<HTMLTemplateElement>(
    `template[data-pe-dialog="${unsavedChangesTemplateName}"]`,
  );
  const dialog = prototype?.content
    .querySelector(dialogSelector)
    ?.cloneNode(true);
  if (!(dialog instanceof HTMLDialogElement)) {
    return Promise.resolve("cancel");
  }
  const previouslyFocused =
    document.activeElement instanceof HTMLElement ? document.activeElement : null;
  return new Promise((resolve): void => {
    let settled = false;
    const finish = (choice: UnsavedChangesChoice): void => {
      if (settled) {
        return;
      }
      settled = true;
      if (isModalCapable(dialog) && dialog.open) {
        dialog.close();
      }
      dialog.remove();
      if (previouslyFocused?.isConnected === true) {
        previouslyFocused.focus({ preventScroll: true });
      }
      resolve(choice);
    };
    dialog.addEventListener("click", (event): void => {
      const target = event.target instanceof Element ? event.target : null;
      const button = target?.closest<HTMLButtonElement>("button[data-pe-unsaved-choice]");
      if (button === null || button === undefined || button.disabled) {
        return;
      }
      event.preventDefault();
      const choice = button.dataset.peUnsavedChoice;
      finish(
        choice === "save" || choice === "discard" ? choice : "cancel",
      );
    });
    // `Escape` closes a modal dialog through its "cancel" event, and a
    // `<form method="dialog">` closes it through "close"; both are a dismissal.
    dialog.addEventListener("cancel", (event): void => {
      event.preventDefault();
      finish("cancel");
    });
    dialog.addEventListener("close", (): void => finish("cancel"));
    root.append(dialog);
    if (isModalCapable(dialog)) {
      dialog.showModal();
    } else {
      dialog.setAttribute("open", "");
      dialog
        .querySelector<HTMLElement>("button[data-pe-unsaved-choice]")
        ?.focus({ preventScroll: true });
    }
  });
};

/**
 * Closes every editor of the profile but `keep`, and says whether the caller
 * may open its own.
 *
 * Asked by every module before it opens an editor. A module that is about to
 * replace its own open editor passes `null`, so that its own is closed - and
 * asked about - like everybody else's; a module that keeps its editor and only
 * wants the others gone passes it as `keep`.
 *
 * The answer is synchronous for as long as it can be: an editor that is not
 * open costs nothing, one without changes is discarded on the spot, and only
 * an editor with changes turns the answer into a promise, because the visitor
 * has to be asked. That keeps a pencil pressed on a quiet page as immediate
 * as it was before the modules knew of each other. `false` means an editor is
 * busy with a request, the visitor chose to stay, or a save they chose was
 * refused - the editor that was open is still open, and the caller opens
 * nothing.
 */
export const closeOtherEditors = (
  editingTarget: EditingTarget,
  keep: OpenEditor | null = null,
): boolean | Promise<boolean> => {
  const context = toEditingContext(editingTarget);
  const editors = [...registryOf(context)].filter(
    (editor): boolean => editor !== keep,
  );
  const closeFrom = (index: number): boolean | Promise<boolean> => {
    for (let position = index; position < editors.length; position += 1) {
      const editor = editors[position];
      if (editor === undefined || !editor.isOpen()) {
        continue;
      }
      if (editor.isBusy?.() === true) {
        showStatus(context, "info", context.messages.saveInProgress ?? null);
        return false;
      }
      if (!editor.isDirty()) {
        editor.discard();
        continue;
      }
      return askUnsavedChanges(context).then(
        async (choice): Promise<boolean> => {
          if (choice === "cancel") {
            return false;
          }
          if (choice === "save") {
            if (!(await editor.save())) {
              return false;
            }
          } else {
            editor.discard();
          }
          return closeFrom(position + 1);
        },
      );
    }
    return true;
  };
  return closeFrom(0);
};

/**
 * Runs `action` once the other editors are closed - now, when nothing had to
 * be asked, and after the visitor's answer otherwise.
 */
export const withOtherEditorsClosed = (
  editingTarget: EditingTarget,
  keep: OpenEditor | null,
  action: () => void,
): void => {
  const closed = closeOtherEditors(editingTarget, keep);
  if (closed === true) {
    action();
  } else if (closed !== false) {
    void closed.then((ok): void => {
      if (ok) {
        action();
      }
    });
  }
};
