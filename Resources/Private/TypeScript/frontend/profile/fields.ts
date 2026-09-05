import {
  hooks,
  isEditableField,
  requestJson,
  showStatus,
  type EditableField,
  type StatusRegion,
} from "@fgtclb/academic-persons-edit/frontend/profile/common.js";
import {
  toEditingContext,
  type EditingContext,
  type EditingTarget,
} from "@fgtclb/academic-persons-edit/frontend/profile/context.js";
import {
  ensureRichTextEditor,
  getPlainText,
  getRichTextEditorValue,
  getRichTextInitialValue,
  isRichTextField,
  renderRichTextPreview,
  setRichTextEditorValue,
} from "@fgtclb/academic-persons-edit/frontend/profile/rich-text.js";

type FieldValue = string | boolean;
type ValidationErrors = Record<string, unknown>;

interface RequestError extends Error {
  result?: {
    data?: Record<string, unknown>;
    errors?: ValidationErrors;
    message?: string;
  };
}

const fieldsFormSelector = "[data-pe-fields-form]";
const editButtonSelector = "[data-academic-persons-profile-editing-activate-btn]";
const editAllButtonSelector = "[data-academic-persons-profile-editing-edit-all-btn]";
const editAllButtonLabelSelector = "[data-pe-edit-all-button-label]";
const buttonAreaSelector = "[data-form-field-button-area]";
const fieldSelector = ".academic-persons-profile-editing__field";
const fieldPreviewSelector = "[data-pe-field-preview]";
const fieldEditorSelector = "[data-pe-field-editor]";
const fieldGroupSelector = "[data-pe-field-group]";
const groupPreviewSelector = "[data-pe-group-preview]";
const groupPreviewContentSelector = "[data-pe-group-preview-content]";
const groupEditorSelector = "[data-pe-group-editor]";
const groupEditButtonSelector = "[data-pe-group-edit]";
const profileNameSelector = "[data-pe-profile-name]";
const autosaveOnChangeSelector = "[data-pe-autosave-on-change]";
const autosaveUndoSelector = "[data-pe-autosave-undo]";
const fieldActionsSelector = "[data-pe-field-actions]";
const groupActionsSelector = "[data-pe-group-actions]";
const formActionsSelector = "[data-pe-form-actions]";
const formApplySelector = "[data-pe-form-apply]";
const formUndoSelector = "[data-pe-form-undo]";
const formDiscardSelector = "[data-pe-form-discard]";
// The editing view of CKEditor 5. Escape belongs to it while the caret is
// inside one: it closes the balloon of a link or a list first, and discarding
// the whole form from under an open balloon is not what the key was pressed
// for.
const richTextEditorScopeSelector = ".ck";

const isFieldReadOnly = (field: EditableField): boolean =>
  field instanceof HTMLSelectElement ? false : field.readOnly;

const getFieldEditElement = (field: EditableField): HTMLElement =>
  field
    .closest<HTMLElement>(fieldGroupSelector)
    ?.querySelector<HTMLElement>(groupEditorSelector) ??
  field.closest<HTMLElement>(fieldEditorSelector) ??
  field.closest<HTMLElement>("[data-pe-editor-container]") ??
  field;

const getFieldValue = (field: EditableField): FieldValue => {
  if (field instanceof HTMLInputElement && field.type === "checkbox") {
    return field.checked;
  }
  const editorValue = isRichTextField(field)
    ? getRichTextEditorValue(field)
    : null;
  return editorValue ?? field.value;
};

const setFieldValue = (
  field: EditableField,
  value: unknown,
): void => {
  if (field instanceof HTMLInputElement && field.type === "checkbox") {
    field.checked = Boolean(value);
    return;
  }
  const normalizedValue =
    value === null || value === undefined ? "" : String(value);
  field.value = normalizedValue;
  if (isRichTextField(field)) {
    setRichTextEditorValue(field, normalizedValue);
  }
};

const getFieldDisplayValue = (
  field: EditableField,
  value: unknown,
): string => {
  if (isRichTextField(field)) {
    return getPlainText(String(value ?? ""));
  }
  if (field instanceof HTMLInputElement && field.type === "checkbox") {
    return value
      ? (hooks(field).peCheckedLabel ?? "")
      : (hooks(field).peUncheckedLabel ?? "");
  }
  if (field instanceof HTMLSelectElement) {
    const selectedOption = field.selectedOptions[0];
    return selectedOption?.value
      ? (selectedOption.textContent ?? "").trim()
      : "";
  }
  return String(value ?? "").trim();
};

/**
 * Puts the caret in a field, through its rich text editor where it has one.
 *
 * A CKEditor instance replaces the textarea it is created on, so focusing the
 * textarea would focus an element the visitor cannot type in. The editor is
 * created if it does not exist yet, which is what makes this usable from the
 * paths that open a field rather than only from the ones that react to one.
 */
const focusField = (context: EditingContext, field: EditableField): void => {
  if (isRichTextField(field)) {
    void ensureRichTextEditor(context, field)
      .then((editor): void => editor?.editing.view.focus())
      .catch((): void => field.focus());
    return;
  }
  field.focus();
};

const getFieldPropertyName = (field: EditableField): string => {
  const bracketProperty = field.name.match(/\[([^\]]+)]$/)?.[1];
  return bracketProperty ?? field.name;
};

const getFieldById = (
  context: EditingContext,
  fieldId: string | undefined,
): EditableField | null => {
  if (fieldId === undefined || fieldId === "") {
    return null;
  }
  const normalizedFieldId = fieldId.startsWith("profile-editing-")
    ? fieldId
    : `profile-editing-${context.profileUidValue}-${fieldId}`;
  const field = context.root.querySelector(`#${CSS.escape(normalizedFieldId)}`);
  return isEditableField(field) ? field : null;
};

const getActivateButton = (
  context: EditingContext,
  field: EditableField,
): HTMLButtonElement | null => {
  if (field.id === "") {
    return null;
  }
  return context.root.querySelector<HTMLButtonElement>(
    `${editButtonSelector}[data-pe-for="${CSS.escape(field.id)}"]`,
  );
};

const getFieldPreview = (
  context: EditingContext,
  field: EditableField,
): HTMLElement | null => {
  if (field.id === "") {
    return null;
  }
  return context.root.querySelector<HTMLElement>(
    `${fieldPreviewSelector}[data-pe-for="${CSS.escape(field.id)}"]`,
  );
};

const parseFieldIds = (value: string | undefined): string[] =>
  (value ?? "").split(/\s+/).filter((fieldId): boolean => fieldId !== "");

const getFieldsByIds = (
  context: EditingContext,
  value: string | undefined,
): EditableField[] =>
  parseFieldIds(value)
    .map((fieldId): EditableField | null => getFieldById(context, fieldId))
    .filter((field): field is EditableField => field !== null);

const getGroupFields = (
  context: EditingContext,
  group: HTMLElement,
): EditableField[] => getFieldsByIds(context, hooks(group).peFieldIds);

const renderProfileName = (context: EditingContext): void => {
  const heading = context.root.querySelector<HTMLElement>(profileNameSelector);
  if (heading === null) {
    return;
  }
  heading.textContent = getFieldsByIds(
    context,
    hooks(heading).peProfileNameFieldIds,
  )
    .map((field): string => getFieldDisplayValue(field, getFieldValue(field)))
    .filter((fieldValue): boolean => fieldValue !== "")
    .join(" ");
};

const renderFieldGroupPreview = (
  context: EditingContext,
  group: HTMLElement,
): void => {
  const content = group.querySelector<HTMLElement>(groupPreviewContentSelector);
  if (content === null) {
    return;
  }
  const values = getFieldsByIds(
    context,
    hooks(group).peDisplayFieldIds ?? hooks(group).peFieldIds,
  )
    .map((field): string => getFieldDisplayValue(field, getFieldValue(field)))
    .filter((value): boolean => value !== "");
  const value =
    hooks(group).peDisplayMode === "first"
      ? (values[0] ?? "")
      : values.join(" ");
  content.classList.toggle("text-body-secondary", value === "");
  content.textContent = value || content.dataset.emptyLabel || "";
};

const toggleEditGroup = (
  context: EditingContext,
  group: HTMLElement,
  state = true,
  focus = true,
): void => {
  const editor = group.querySelector<HTMLElement>(groupEditorSelector);
  const preview = group.querySelector<HTMLElement>(groupPreviewSelector);
  const button = group.querySelector<HTMLButtonElement>(groupEditButtonSelector);
  const fields = getGroupFields(context, group).filter(
    (field): boolean => !field.disabled && !isFieldReadOnly(field),
  );
  if (editor === null || fields.length === 0) {
    return;
  }
  editor.classList.toggle("d-none", !state);
  preview?.classList.toggle("d-none", state);
  button?.setAttribute("aria-expanded", String(state));
  if (!state) {
    if (focus) {
      button?.focus();
    }
    return;
  }
  if (focus) {
    fields[0]?.focus();
  }
};

const setEditAllButtonState = (
  context: EditingContext,
  active: boolean,
): void => {
  const button = context.root.querySelector<HTMLButtonElement>(
    editAllButtonSelector,
  );
  if (button === null) {
    return;
  }
  button.classList.toggle("active", active);
  button.setAttribute("aria-pressed", String(active));
  const label = button.querySelector<HTMLElement>(editAllButtonLabelSelector);
  const nextLabel = active
    ? hooks(button).peCloseAllLabel
    : hooks(button).peEditAllLabel;
  if (label !== null && nextLabel !== undefined) {
    label.textContent = nextLabel;
  }
};

const clearValidationErrors = (fields: EditableField[]): void => {
  fields.forEach((field): void => {
    field.setAttribute("aria-invalid", "false");
    field.classList.remove("is-invalid");
    getFieldEditElement(field).classList.remove("is-invalid");
    const feedback = field
      .closest<HTMLElement>(
        "[data-pe-field-wrapper], [data-pe-group-control], .form-check",
      )
      ?.querySelector<HTMLElement>(".invalid-feedback");
    if (feedback !== null && feedback !== undefined) {
      feedback.textContent = "";
    }
  });
};

const getTemplateButton = (
  template: Element | null,
): HTMLButtonElement | null =>
  template instanceof HTMLTemplateElement
    ? template.content.querySelector<HTMLButtonElement>("button")
    : null;

const createActivateButton = (
  context: EditingContext,
  field: EditableField,
  fieldValue: unknown,
): HTMLButtonElement | null => {
  const displayValue = getFieldDisplayValue(field, fieldValue);
  const template = context.root.querySelector(
    displayValue === ""
      ? "[data-pe-new-button-template]"
      : "[data-pe-edit-button-template]",
  );
  const templateButton = getTemplateButton(template);
  if (templateButton === null) {
    return null;
  }
  const button = templateButton.cloneNode(true);
  if (!(button instanceof HTMLButtonElement)) {
    return null;
  }
  hooks(button).peFor = field.id;
  button.setAttribute("aria-controls", `${field.id}-editor`);
  button.setAttribute("aria-expanded", "false");
  const label = button.querySelector<HTMLElement>("[data-pe-button-label]");
  if (label !== null) {
    label.textContent = displayValue === "" ? "+" : displayValue;
  }
  return button;
};

const renderActivateButton = (
  context: EditingContext,
  field: EditableField,
  fieldValue: unknown,
): void => {
  if (field.id === "") {
    return;
  }
  if (isRichTextField(field)) {
    renderRichTextPreview(context.root, field, fieldValue);
    return;
  }
  const group = field.closest<HTMLElement>(fieldGroupSelector);
  if (group !== null) {
    renderFieldGroupPreview(context, group);
    return;
  }
  const preview = getFieldPreview(context, field);
  const content = preview?.querySelector<HTMLElement>("[data-pe-field-preview-content]");
  if (content === null || content === undefined) {
    const currentButton = getActivateButton(context, field);
    const replacementButton = createActivateButton(context, field, fieldValue);
    if (replacementButton === null) {
      return;
    }
    if (currentButton !== null) {
      replacementButton.classList.toggle(
        "d-none",
        currentButton.classList.contains("d-none"),
      );
      currentButton.replaceWith(replacementButton);
      return;
    }
    field
      .closest<HTMLElement>(".mb-3, .form-check")
      ?.querySelector<HTMLElement>(buttonAreaSelector)
      ?.append(replacementButton);
    return;
  }
  const displayValue = getFieldDisplayValue(field, fieldValue);
  content.classList.toggle("text-body-secondary", displayValue === "");
  content.textContent = displayValue || preview?.dataset.emptyLabel || "";
};

const toggleEditField = (
  context: EditingContext,
  fieldId: string,
  state = true,
  focus = true,
): void => {
  const field = getFieldById(context, fieldId);
  if (field === null || field.disabled || isFieldReadOnly(field)) {
    return;
  }
  const group = field.closest<HTMLElement>(fieldGroupSelector);
  if (group !== null) {
    toggleEditGroup(context, group, state, focus);
    return;
  }
  getFieldEditElement(field).classList.toggle("d-none", !state);
  getFieldPreview(context, field)?.classList.toggle("d-none", state);
  getActivateButton(context, field)?.setAttribute("aria-expanded", String(state));
  context.root
    .querySelectorAll<HTMLElement>(
      `${fieldActionsSelector}[data-pe-for="${CSS.escape(field.id)}"]`,
    )
    .forEach((actions): void => {
      actions.classList.toggle("d-none", !state);
    });
  if (!state) {
    if (focus) {
      getActivateButton(context, field)?.focus();
    }
    return;
  }
  // The editor is created even when the caret is not moved into it: opening
  // every field at once must not leave the rich text ones as bare textareas.
  if (isRichTextField(field)) {
    void ensureRichTextEditor(context, field)
      .then((editor): void => {
        if (focus) {
          editor?.editing.view.focus();
        }
      })
      .catch((): void => {
        if (focus) {
          field.focus();
        }
      });
    return;
  }
  if (focus) {
    field.focus();
  }
};

const closeFields = (
  context: EditingContext,
  fields: EditableField[],
  focus = true,
): void => {
  const groups = new Set<HTMLElement>();
  fields.forEach((field): void => {
    const group = field.closest<HTMLElement>(fieldGroupSelector);
    if (group !== null) {
      groups.add(group);
    } else if (field.id !== "") {
      toggleEditField(context, field.id, false, focus);
    }
  });
  groups.forEach((group): void => toggleEditGroup(context, group, false, focus));
};

const showValidationErrors = (
  context: EditingContext,
  fields: EditableField[],
  errors: ValidationErrors,
): void => {
  const invalidFields: EditableField[] = [];
  Object.entries(errors).forEach(([propertyPath, messages]): void => {
    const propertyName = propertyPath.split(".").pop();
    const field = fields.find(
      (candidate): boolean => getFieldPropertyName(candidate) === propertyName,
    );
    if (field === undefined) {
      return;
    }
    field.classList.add("is-invalid");
    field.setAttribute("aria-invalid", "true");
    invalidFields.push(field);
    getFieldEditElement(field).classList.add("is-invalid");
    if (field.id !== "") {
      // Opened, never focused: a rich text field focuses asynchronously through
      // its editor promise, so a later one would steal the caret back from the
      // first refused field below.
      toggleEditField(context, field.id, true, false);
    }
    const feedback = field
      .closest<HTMLElement>(
        "[data-pe-field-wrapper], [data-pe-group-control], .form-check",
      )
      ?.querySelector<HTMLElement>(".invalid-feedback");
    if (feedback !== null && feedback !== undefined) {
      feedback.textContent = Array.isArray(messages)
        ? messages.map(String).join(" ")
        : String(messages);
    }
  });
  const firstInvalidField = invalidFields[0];
  if (firstInvalidField !== undefined) {
    focusField(context, firstInvalidField);
  }
};

export const initializeFieldEditing = (editingTarget: EditingTarget): void => {
  const context = toEditingContext(editingTarget);
  const root = context.root;
  const forms = Array.from(
    root.querySelectorAll<HTMLFormElement>(fieldsFormSelector),
  );
  if (forms.length === 0) {
    return;
  }
  const fields = Array.from(root.querySelectorAll(fieldSelector)).filter(
    isEditableField,
  );
  const persistedValues = new Map<EditableField, FieldValue>(
    fields.map((field): [EditableField, FieldValue] => [field, getFieldValue(field)]),
  );
  renderProfileName(context);
  root.querySelectorAll<HTMLElement>(fieldGroupSelector).forEach((group): void => {
    renderFieldGroupPreview(context, group);
    const hasEditableField = getGroupFields(context, group).some(
      (field): boolean => !field.disabled && !isFieldReadOnly(field),
    );
    group
      .querySelector(groupEditButtonSelector)
      ?.classList.toggle("d-none", !hasEditableField);
  });
  fields
    .filter((field): boolean => field.closest(fieldGroupSelector) === null)
    .forEach((field): void =>
      renderActivateButton(context, field, getFieldValue(field)),
    );

  const normalizedRichTextBaselines = new WeakSet<HTMLTextAreaElement>();

  /**
   * Full form editing: every editable field of the profile open at once, with
   * one set of controls for all of them and none of the per-field ones.
   *
   * The state is exclusive with single-field editing rather than a variant of
   * it. Entering closes whatever one field was open, hides every per-field and
   * per-group action group and shows the bar `Field/FormActions.html` renders
   * at the end of each form; leaving does the reverse. Nothing is removed from
   * the document for it, because single-field editing has to work again the
   * moment the form is closed.
   */
  let formEditingActive = false;
  /**
   * Set before the first `await` of an apply, which `aria-busy` is not: the
   * root is marked busy inside `saveFields()`, several microtasks after the
   * click, so a second press in the same turn would reach the endpoint.
   */
  let formRequestPending = false;
  const formActionBars = Array.from(
    root.querySelectorAll<HTMLElement>(formActionsSelector),
  );
  setEditAllButtonState(context, formEditingActive);

  /**
   * A refusal of the whole form interrupts, a refusal of one field waits.
   *
   * Applying the form moves the caret to the first refused field in the same
   * turn, and a polite region queued behind that focus change is routinely
   * dropped by a screen reader. Beside a single field the message stands next
   * to the control the visitor is already in, so it stays polite - which is
   * also what keeps single-field editing unchanged.
   */
  const validationRegion = (): StatusRegion =>
    formEditingActive ? "alert" : "status";

  const editableFields = (): EditableField[] =>
    fields.filter(
      (field): boolean => !field.disabled && !isFieldReadOnly(field),
    );

  /**
   * Every control that acts on one field or one group, including the undo of
   * an autosaving checkbox - which is a per-field control without being part
   * of a `[data-pe-field-actions]` group. Scoped to the forms, so the controls
   * of a document or contact editor are not touched: those keep their own
   * buttons, and full form editing is about the profile fields only.
   */
  const perFieldActionGroups = (): HTMLElement[] =>
    forms.flatMap((form): HTMLElement[] =>
      Array.from(
        form.querySelectorAll<HTMLElement>(
          `${fieldActionsSelector}, ${groupActionsSelector}, ${autosaveUndoSelector}`,
        ),
      ),
    );

  /**
   * Takes the baseline of a rich text field from the editor rather than from
   * the markup, once per field.
   *
   * CKEditor normalises what it is given - `<p>a</p>` and `a` are the same
   * document to it - so the rendered value and the value the editor hands back
   * differ for the same content. Comparing against the rendered one would make
   * every rich text field look changed on the first save, and, since undo
   * writes the baseline back, would put the un-normalised source into the
   * editor and post it as a change on the next apply.
   */
  const normalizeRichTextBaselines = (candidates: EditableField[]): void => {
    candidates.filter(isRichTextField).forEach((field): void => {
      if (normalizedRichTextBaselines.has(field)) {
        return;
      }
      const initialValue = getRichTextInitialValue(field);
      if (initialValue === undefined) {
        return;
      }
      persistedValues.set(field, initialValue);
      normalizedRichTextBaselines.add(field);
    });
  };

  const resetFields = (fieldsToReset: EditableField[]): void => {
    fieldsToReset.forEach((field): void => {
      setFieldValue(field, persistedValues.get(field) ?? "");
    });
    clearValidationErrors(fieldsToReset);
  };

  const saveFields = async (fieldsToSave: EditableField[]): Promise<boolean> => {
    if (root.getAttribute("aria-busy") === "true") {
      return false;
    }
    const richTextFields = fieldsToSave.filter(isRichTextField);
    try {
      await Promise.all(
        richTextFields.map((field) => ensureRichTextEditor(context, field)),
      );
    } catch {
      return false;
    }
    normalizeRichTextBaselines(richTextFields);
    clearValidationErrors(fieldsToSave);
    const changedFields = fieldsToSave.filter(
      (field): boolean =>
        getFieldPropertyName(field) !== "" &&
        !field.disabled &&
        !isFieldReadOnly(field) &&
        persistedValues.get(field) !== getFieldValue(field),
    );
    if (changedFields.length === 0) {
      closeFields(context, fieldsToSave);
      showStatus(context, "info", context.messages.unchanged ?? null);
      return true;
    }
    const invalidField = changedFields.find(
      (field): boolean => !field.checkValidity(),
    );
    if (invalidField !== undefined) {
      invalidField.classList.add("is-invalid");
      invalidField.setAttribute("aria-invalid", "true");
      getFieldEditElement(invalidField).classList.add("is-invalid");
      if (isRichTextField(invalidField)) {
        toggleEditField(context, invalidField.id, true);
      } else {
        invalidField.reportValidity();
      }
      showStatus(
        context,
        "warning",
        context.messages.validation ?? null,
        validationRegion(),
      );
      return false;
    }
    const profileUid = context.profileUid;
    const updateUrl = context.urls.update;
    if (profileUid === null || updateUrl === undefined) {
      showStatus(context, "danger");
      return false;
    }
    const data = Object.fromEntries(
      changedFields.map((field): [string, FieldValue] => [
        getFieldPropertyName(field),
        getFieldValue(field),
      ]),
    );
    root.setAttribute("aria-busy", "true");
    showStatus(context, "info", context.messages.saving ?? null);
    try {
      const result = await requestJson(updateUrl, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ profile: profileUid, data }),
      });
      const responseData =
        typeof result.data === "object" && result.data !== null
          ? (result.data as Record<string, unknown>)
          : {};
      changedFields.forEach((field): void => {
        const propertyName = getFieldPropertyName(field);
        const value = Object.hasOwn(responseData, propertyName)
          ? responseData[propertyName]
          : getFieldValue(field);
        setFieldValue(field, value);
        persistedValues.set(field, getFieldValue(field));
        renderActivateButton(context, field, value);
      });
      renderProfileName(context);
      // In form editing the caret goes to the toggle when the form closes, so
      // the per-field activate buttons must not be focused on the way there.
      closeFields(context, changedFields, !formEditingActive);
      showStatus(context, "success");
      return true;
    } catch (error) {
      const result = (error as RequestError).result;
      if (result?.errors !== undefined) {
        showValidationErrors(context, fields, result.errors);
        showStatus(
          context,
          "warning",
          context.messages.validation ?? null,
          validationRegion(),
        );
      } else {
        showStatus(context, "danger", result?.message ?? null);
      }
      return false;
    } finally {
      root.setAttribute("aria-busy", "false");
    }
  };

  const renderEveryPreview = (): void => {
    root
      .querySelectorAll<HTMLElement>(fieldGroupSelector)
      .forEach((group): void => renderFieldGroupPreview(context, group));
    fields
      .filter((field): boolean => field.closest(fieldGroupSelector) === null)
      .forEach((field): void =>
        renderActivateButton(context, field, getFieldValue(field)),
      );
    renderProfileName(context);
  };

  const setFormEditingState = (active: boolean): void => {
    formEditingActive = active;
    perFieldActionGroups().forEach((group): void => {
      group.hidden = active;
    });
    formActionBars.forEach((bar): void => {
      bar.hidden = !active;
    });
    setEditAllButtonState(context, active);
  };

  const enterFormEditing = (): void => {
    // A field that is already open is not closed first: every editable field
    // opens anyway, and the state it is left in - editor shown, per-field group
    // hidden - is the same one it would be reopened into. What does change is
    // the caret, which goes to the first field of the form rather than staying
    // where the visitor happened to be.
    setFormEditingState(true);
    root
      .querySelectorAll<HTMLElement>(fieldGroupSelector)
      .forEach((group): void => {
        toggleEditGroup(context, group, true, false);
      });
    root
      .querySelectorAll<HTMLElement>(editButtonSelector)
      .forEach((editButton): void => {
        const fieldId = hooks(editButton).peFor;
        if (fieldId !== undefined) {
          toggleEditField(context, fieldId, true, false);
        }
      });
    const firstField = editableFields()[0];
    if (firstField !== undefined) {
      focusField(context, firstField);
    }
  };

  const leaveFormEditing = (): void => {
    setFormEditingState(false);
    closeFields(context, fields, false);
    root.querySelector<HTMLButtonElement>(editAllButtonSelector)?.focus();
  };

  /**
   * Back to what is stored, for every field at once.
   *
   * `persistedValues` is the baseline the module keeps anyway: seeded from the
   * rendered values at start-up and rewritten after every successful save. Undo
   * here means the same thing it means beside a single field, and no history is
   * kept for it.
   */
  const revertForm = (): void => {
    // The editors are created when the form opens, so their normalised values
    // are available by now - and the baseline has to be corrected before it is
    // written back, or undo puts the rendered source into the editor and the
    // next apply posts it as a change nobody made.
    normalizeRichTextBaselines(editableFields());
    resetFields(editableFields());
    renderEveryPreview();
  };

  const discardForm = (): void => {
    revertForm();
    leaveFormEditing();
  };

  /**
   * Whether a transition may run at all.
   *
   * Undo, discard and the toggle stay pressable while an apply is on its way to
   * the server, and reverting under it is not a cosmetic race: the request is
   * already being persisted, and the response handler would then write the
   * reverted values into `persistedValues` for every property the endpoint does
   * not echo. The baseline would say "unchanged" for a value the database does
   * not hold, so the next apply would not resend it - the discarded value would
   * stay stored, with nothing on screen saying so. The request is therefore
   * allowed to finish and the transition is refused, rather than the request
   * being abandoned: nothing here can un-persist it.
   */
  const formTransitionAllowed = (): boolean => !formRequestPending;

  const applyForm = async (): Promise<void> => {
    if (formRequestPending || root.getAttribute("aria-busy") === "true") {
      return;
    }
    formRequestPending = true;
    try {
      // One request for everything that changed. `updateAction()` validates the
      // whole map before it writes anything, so a refusal leaves the profile
      // untouched and there is no partial result to undo.
      const applied = await saveFields(editableFields());
      if (applied) {
        leaveFormEditing();
      }
    } finally {
      formRequestPending = false;
    }
  };

  root.addEventListener("click", (event): void => {
    const target = event.target;
    if (!(target instanceof Element)) {
      return;
    }
    const button = target.closest("button");
    if (!(button instanceof HTMLButtonElement)) {
      return;
    }
    if (button.matches(groupEditButtonSelector)) {
      event.preventDefault();
      const group = button.closest<HTMLElement>(fieldGroupSelector);
      if (group !== null) {
        toggleEditGroup(context, group, true);
      }
      return;
    }
    if (button.matches("[data-pe-group-dismiss]")) {
      event.preventDefault();
      const group = button.closest<HTMLElement>(fieldGroupSelector);
      if (group !== null) {
        const groupFields = getGroupFields(context, group).filter(
          (field): boolean => !field.disabled && !isFieldReadOnly(field),
        );
        groupFields.forEach((field): void => setFieldValue(field, ""));
        clearValidationErrors(groupFields);
        toggleEditGroup(context, group, true);
      }
      return;
    }
    if (button.matches("[data-pe-group-cancel]")) {
      event.preventDefault();
      const group = button.closest<HTMLElement>(fieldGroupSelector);
      if (group !== null) {
        const groupFields = getGroupFields(context, group);
        resetFields(groupFields);
        renderFieldGroupPreview(context, group);
        toggleEditGroup(context, group, false);
      }
      return;
    }
    if (button.matches("[data-pe-group-save]")) {
      event.preventDefault();
      const group = button.closest<HTMLElement>(fieldGroupSelector);
      if (group !== null) {
        void saveFields(getGroupFields(context, group));
      }
      return;
    }
    if (button.matches(formApplySelector)) {
      event.preventDefault();
      void applyForm();
      return;
    }
    if (button.matches(formUndoSelector)) {
      event.preventDefault();
      if (!formTransitionAllowed()) {
        return;
      }
      revertForm();
      showStatus(
        context,
        "info",
        hooks(button.closest<HTMLElement>(formActionsSelector) ?? button)
          .peFormRevertedMessage ?? null,
      );
      return;
    }
    if (button.matches(formDiscardSelector)) {
      event.preventDefault();
      if (formTransitionAllowed()) {
        discardForm();
      }
      return;
    }
    if (button.matches(editAllButtonSelector)) {
      event.preventDefault();
      // The toggle is the way in and one of the two ways out. Closing the form
      // is discarding it: a value left in a control the visitor cannot see any
      // more would contradict the preview beside it and would be sent by the
      // next apply without ever having been looked at again.
      if (!formTransitionAllowed()) {
        return;
      }
      if (formEditingActive) {
        discardForm();
      } else {
        enterFormEditing();
      }
      return;
    }
    if (button.matches(editButtonSelector)) {
      event.preventDefault();
      const fieldId = hooks(button).peFor;
      if (fieldId !== undefined) {
        toggleEditField(context, fieldId, true);
      }
      return;
    }
    if (button.matches("[data-pe-dismiss]")) {
      event.preventDefault();
      const field = getFieldById(context, hooks(button).peFor);
      if (field !== null) {
        setFieldValue(field, "");
        clearValidationErrors([field]);
        toggleEditField(context, field.id, true);
      }
      return;
    }
    if (button.matches("[data-pe-cancel]")) {
      event.preventDefault();
      const field = getFieldById(context, hooks(button).peFor);
      if (field !== null) {
        setFieldValue(field, persistedValues.get(field) ?? "");
        clearValidationErrors([field]);
        toggleEditField(context, field.id, false);
      }
      return;
    }
    if (button.matches("[data-pe-save]")) {
      event.preventDefault();
      const field = getFieldById(context, hooks(button).peFor);
      if (field !== null) {
        void saveFields([field]);
      }
    }
  });
  root.addEventListener("change", (event): void => {
    const field = event.target;
    if (!isEditableField(field) || !field.matches(autosaveOnChangeSelector)) {
      return;
    }
    if (formEditingActive) {
      // While the whole form is open the checkbox is applied with everything
      // else. Writing on change here would put a value in the database that
      // abort promises to take back and could not.
      return;
    }
    // A checkbox saves on change, without an explicit save button, so a failed
    // request has to put the control back where it was: otherwise it shows a
    // state the database does not have, and nothing on screen says so. This is
    // what sync.ts does for the synchronisation switch.
    const previousValue = persistedValues.get(field);
    void saveFields([field]).then((saved): void => {
      if (!saved && previousValue !== undefined) {
        setFieldValue(field, previousValue);
      }
    });
  });
  forms.forEach((form): void => {
    // Escape discards and Ctrl/Cmd + Enter applies, both only while the form is
    // open and only for a key pressed inside it. The listener sits on the form
    // rather than on the plugin root because a document, contact or image
    // editor may be open at the same time - those panels are outside every
    // `data-pe-fields-form`, they keep their own handling of both keys, and
    // full form editing does not touch them.
    form.addEventListener("keydown", (event): void => {
      if (!formEditingActive || !formTransitionAllowed()) {
        return;
      }
      const target = event.target;
      if (event.key === "Escape") {
        if (
          target instanceof Element &&
          target.closest(richTextEditorScopeSelector) !== null
        ) {
          return;
        }
        event.preventDefault();
        discardForm();
        return;
      }
      if (event.key === "Enter" && (event.ctrlKey || event.metaKey)) {
        event.preventDefault();
        void applyForm();
      }
    });
    form.addEventListener("submit", (event): void => {
      // The form has no action and never navigates. Enter in a text field
      // submits it, and while the whole form is open that is the same
      // intention as pressing apply.
      event.preventDefault();
      if (formEditingActive) {
        void applyForm();
      }
    });
    form.addEventListener("reset", (event): void => event.preventDefault());
  });
};
