/* Generated from Resources/Private/TypeScript — do not edit. */
import {
  hooks,
  isEditableField,
  requestJson,
  showStatus
} from "@fgtclb/academic-persons-edit/frontend/profile/common.js";
import {
  toEditingContext
} from "@fgtclb/academic-persons-edit/frontend/profile/context.js";
import {
  ensureRichTextEditor,
  getPlainText,
  getRichTextEditorValue,
  getRichTextInitialValue,
  isRichTextField,
  renderRichTextPreview,
  setRichTextEditorValue
} from "@fgtclb/academic-persons-edit/frontend/profile/rich-text.js";
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
const richTextEditorScopeSelector = ".ck";
const isFieldReadOnly = (field) => field instanceof HTMLSelectElement ? false : field.readOnly;
const getFieldEditElement = (field) => {
  var _a;
  return ((_a = field.closest(fieldGroupSelector)) == null ? void 0 : _a.querySelector(groupEditorSelector)) ?? field.closest(fieldEditorSelector) ?? field.closest("[data-pe-editor-container]") ?? field;
};
const getFieldValue = (field) => {
  if (field instanceof HTMLInputElement && field.type === "checkbox") {
    return field.checked;
  }
  const editorValue = isRichTextField(field) ? getRichTextEditorValue(field) : null;
  return editorValue ?? field.value;
};
const setFieldValue = (field, value) => {
  if (field instanceof HTMLInputElement && field.type === "checkbox") {
    field.checked = Boolean(value);
    return;
  }
  const normalizedValue = value === null || value === void 0 ? "" : String(value);
  field.value = normalizedValue;
  if (isRichTextField(field)) {
    setRichTextEditorValue(field, normalizedValue);
  }
};
const getFieldDisplayValue = (field, value) => {
  if (isRichTextField(field)) {
    return getPlainText(String(value ?? ""));
  }
  if (field instanceof HTMLInputElement && field.type === "checkbox") {
    return value ? hooks(field).peCheckedLabel ?? "" : hooks(field).peUncheckedLabel ?? "";
  }
  if (field instanceof HTMLSelectElement) {
    const selectedOption = field.selectedOptions[0];
    return (selectedOption == null ? void 0 : selectedOption.value) ? (selectedOption.textContent ?? "").trim() : "";
  }
  return String(value ?? "").trim();
};
const focusField = (context, field) => {
  if (isRichTextField(field)) {
    void ensureRichTextEditor(context, field).then((editor) => editor == null ? void 0 : editor.editing.view.focus()).catch(() => field.focus());
    return;
  }
  field.focus();
};
const getFieldPropertyName = (field) => {
  var _a;
  const bracketProperty = (_a = field.name.match(/\[([^\]]+)]$/)) == null ? void 0 : _a[1];
  return bracketProperty ?? field.name;
};
const getFieldById = (context, fieldId) => {
  if (fieldId === void 0 || fieldId === "") {
    return null;
  }
  const normalizedFieldId = fieldId.startsWith("profile-editing-") ? fieldId : `profile-editing-${context.profileUidValue}-${fieldId}`;
  const field = context.root.querySelector(`#${CSS.escape(normalizedFieldId)}`);
  return isEditableField(field) ? field : null;
};
const getActivateButton = (context, field) => {
  if (field.id === "") {
    return null;
  }
  return context.root.querySelector(
    `${editButtonSelector}[data-pe-for="${CSS.escape(field.id)}"]`
  );
};
const getFieldPreview = (context, field) => {
  if (field.id === "") {
    return null;
  }
  return context.root.querySelector(
    `${fieldPreviewSelector}[data-pe-for="${CSS.escape(field.id)}"]`
  );
};
const parseFieldIds = (value) => (value ?? "").split(/\s+/).filter((fieldId) => fieldId !== "");
const getFieldsByIds = (context, value) => parseFieldIds(value).map((fieldId) => getFieldById(context, fieldId)).filter((field) => field !== null);
const getGroupFields = (context, group) => getFieldsByIds(context, hooks(group).peFieldIds);
const renderProfileName = (context) => {
  const heading = context.root.querySelector(profileNameSelector);
  if (heading === null) {
    return;
  }
  heading.textContent = getFieldsByIds(
    context,
    hooks(heading).peProfileNameFieldIds
  ).map((field) => getFieldDisplayValue(field, getFieldValue(field))).filter((fieldValue) => fieldValue !== "").join(" ");
};
const renderFieldGroupPreview = (context, group) => {
  const content = group.querySelector(groupPreviewContentSelector);
  if (content === null) {
    return;
  }
  const values = getFieldsByIds(
    context,
    hooks(group).peDisplayFieldIds ?? hooks(group).peFieldIds
  ).map((field) => getFieldDisplayValue(field, getFieldValue(field))).filter((value2) => value2 !== "");
  const value = hooks(group).peDisplayMode === "first" ? values[0] ?? "" : values.join(" ");
  content.classList.toggle("text-body-secondary", value === "");
  content.textContent = value || content.dataset.emptyLabel || "";
};
const toggleEditGroup = (context, group, state = true, focus = true) => {
  var _a;
  const editor = group.querySelector(groupEditorSelector);
  const preview = group.querySelector(groupPreviewSelector);
  const button = group.querySelector(groupEditButtonSelector);
  const fields = getGroupFields(context, group).filter(
    (field) => !field.disabled && !isFieldReadOnly(field)
  );
  if (editor === null || fields.length === 0) {
    return;
  }
  editor.classList.toggle("d-none", !state);
  preview == null ? void 0 : preview.classList.toggle("d-none", state);
  button == null ? void 0 : button.setAttribute("aria-expanded", String(state));
  if (!state) {
    if (focus) {
      button == null ? void 0 : button.focus();
    }
    return;
  }
  if (focus) {
    (_a = fields[0]) == null ? void 0 : _a.focus();
  }
};
const setEditAllButtonState = (context, active) => {
  const button = context.root.querySelector(
    editAllButtonSelector
  );
  if (button === null) {
    return;
  }
  button.classList.toggle("active", active);
  button.setAttribute("aria-pressed", String(active));
  const label = button.querySelector(editAllButtonLabelSelector);
  const nextLabel = active ? hooks(button).peCloseAllLabel : hooks(button).peEditAllLabel;
  if (label !== null && nextLabel !== void 0) {
    label.textContent = nextLabel;
  }
};
const clearValidationErrors = (fields) => {
  fields.forEach((field) => {
    var _a;
    field.setAttribute("aria-invalid", "false");
    field.classList.remove("is-invalid");
    getFieldEditElement(field).classList.remove("is-invalid");
    const feedback = (_a = field.closest(
      "[data-pe-field-wrapper], [data-pe-group-control], .form-check"
    )) == null ? void 0 : _a.querySelector(".invalid-feedback");
    if (feedback !== null && feedback !== void 0) {
      feedback.textContent = "";
    }
  });
};
const getTemplateButton = (template) => template instanceof HTMLTemplateElement ? template.content.querySelector("button") : null;
const createActivateButton = (context, field, fieldValue) => {
  const displayValue = getFieldDisplayValue(field, fieldValue);
  const template = context.root.querySelector(
    displayValue === "" ? "[data-pe-new-button-template]" : "[data-pe-edit-button-template]"
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
  const label = button.querySelector("[data-pe-button-label]");
  if (label !== null) {
    label.textContent = displayValue === "" ? "+" : displayValue;
  }
  return button;
};
const renderActivateButton = (context, field, fieldValue) => {
  var _a, _b;
  if (field.id === "") {
    return;
  }
  if (isRichTextField(field)) {
    renderRichTextPreview(context.root, field, fieldValue);
    return;
  }
  const group = field.closest(fieldGroupSelector);
  if (group !== null) {
    renderFieldGroupPreview(context, group);
    return;
  }
  const preview = getFieldPreview(context, field);
  const content = preview == null ? void 0 : preview.querySelector("[data-pe-field-preview-content]");
  if (content === null || content === void 0) {
    const currentButton = getActivateButton(context, field);
    const replacementButton = createActivateButton(context, field, fieldValue);
    if (replacementButton === null) {
      return;
    }
    if (currentButton !== null) {
      replacementButton.classList.toggle(
        "d-none",
        currentButton.classList.contains("d-none")
      );
      currentButton.replaceWith(replacementButton);
      return;
    }
    (_b = (_a = field.closest(".mb-3, .form-check")) == null ? void 0 : _a.querySelector(buttonAreaSelector)) == null ? void 0 : _b.append(replacementButton);
    return;
  }
  const displayValue = getFieldDisplayValue(field, fieldValue);
  content.classList.toggle("text-body-secondary", displayValue === "");
  content.textContent = displayValue || (preview == null ? void 0 : preview.dataset.emptyLabel) || "";
};
const toggleEditField = (context, fieldId, state = true, focus = true) => {
  var _a, _b, _c;
  const field = getFieldById(context, fieldId);
  if (field === null || field.disabled || isFieldReadOnly(field)) {
    return;
  }
  const group = field.closest(fieldGroupSelector);
  if (group !== null) {
    toggleEditGroup(context, group, state, focus);
    return;
  }
  getFieldEditElement(field).classList.toggle("d-none", !state);
  (_a = getFieldPreview(context, field)) == null ? void 0 : _a.classList.toggle("d-none", state);
  (_b = getActivateButton(context, field)) == null ? void 0 : _b.setAttribute("aria-expanded", String(state));
  context.root.querySelectorAll(
    `${fieldActionsSelector}[data-pe-for="${CSS.escape(field.id)}"]`
  ).forEach((actions) => {
    actions.classList.toggle("d-none", !state);
  });
  if (!state) {
    if (focus) {
      (_c = getActivateButton(context, field)) == null ? void 0 : _c.focus();
    }
    return;
  }
  if (isRichTextField(field)) {
    void ensureRichTextEditor(context, field).then((editor) => {
      if (focus) {
        editor == null ? void 0 : editor.editing.view.focus();
      }
    }).catch(() => {
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
const closeFields = (context, fields, focus = true) => {
  const groups = /* @__PURE__ */ new Set();
  fields.forEach((field) => {
    const group = field.closest(fieldGroupSelector);
    if (group !== null) {
      groups.add(group);
    } else if (field.id !== "") {
      toggleEditField(context, field.id, false, focus);
    }
  });
  groups.forEach((group) => toggleEditGroup(context, group, false, focus));
};
const showValidationErrors = (context, fields, errors) => {
  const invalidFields = [];
  Object.entries(errors).forEach(([propertyPath, messages]) => {
    var _a;
    const propertyName = propertyPath.split(".").pop();
    const field = fields.find(
      (candidate) => getFieldPropertyName(candidate) === propertyName
    );
    if (field === void 0) {
      return;
    }
    field.classList.add("is-invalid");
    field.setAttribute("aria-invalid", "true");
    invalidFields.push(field);
    getFieldEditElement(field).classList.add("is-invalid");
    if (field.id !== "") {
      toggleEditField(context, field.id, true, false);
    }
    const feedback = (_a = field.closest(
      "[data-pe-field-wrapper], [data-pe-group-control], .form-check"
    )) == null ? void 0 : _a.querySelector(".invalid-feedback");
    if (feedback !== null && feedback !== void 0) {
      feedback.textContent = Array.isArray(messages) ? messages.map(String).join(" ") : String(messages);
    }
  });
  const firstInvalidField = invalidFields[0];
  if (firstInvalidField !== void 0) {
    focusField(context, firstInvalidField);
  }
};
const initializeFieldEditing = (editingTarget) => {
  const context = toEditingContext(editingTarget);
  const root = context.root;
  const forms = Array.from(
    root.querySelectorAll(fieldsFormSelector)
  );
  if (forms.length === 0) {
    return;
  }
  const fields = Array.from(root.querySelectorAll(fieldSelector)).filter(
    isEditableField
  );
  const persistedValues = new Map(
    fields.map((field) => [field, getFieldValue(field)])
  );
  renderProfileName(context);
  root.querySelectorAll(fieldGroupSelector).forEach((group) => {
    var _a;
    renderFieldGroupPreview(context, group);
    const hasEditableField = getGroupFields(context, group).some(
      (field) => !field.disabled && !isFieldReadOnly(field)
    );
    (_a = group.querySelector(groupEditButtonSelector)) == null ? void 0 : _a.classList.toggle("d-none", !hasEditableField);
  });
  fields.filter((field) => field.closest(fieldGroupSelector) === null).forEach(
    (field) => renderActivateButton(context, field, getFieldValue(field))
  );
  const normalizedRichTextBaselines = /* @__PURE__ */ new WeakSet();
  let formEditingActive = false;
  let formRequestPending = false;
  const formActionBars = Array.from(
    root.querySelectorAll(formActionsSelector)
  );
  setEditAllButtonState(context, formEditingActive);
  const validationRegion = () => formEditingActive ? "alert" : "status";
  const editableFields = () => fields.filter(
    (field) => !field.disabled && !isFieldReadOnly(field)
  );
  const perFieldActionGroups = () => forms.flatMap(
    (form) => Array.from(
      form.querySelectorAll(
        `${fieldActionsSelector}, ${groupActionsSelector}, ${autosaveUndoSelector}`
      )
    )
  );
  const normalizeRichTextBaselines = (candidates) => {
    candidates.filter(isRichTextField).forEach((field) => {
      if (normalizedRichTextBaselines.has(field)) {
        return;
      }
      const initialValue = getRichTextInitialValue(field);
      if (initialValue === void 0) {
        return;
      }
      persistedValues.set(field, initialValue);
      normalizedRichTextBaselines.add(field);
    });
  };
  const resetFields = (fieldsToReset) => {
    fieldsToReset.forEach((field) => {
      setFieldValue(field, persistedValues.get(field) ?? "");
    });
    clearValidationErrors(fieldsToReset);
  };
  const saveFields = async (fieldsToSave) => {
    if (root.getAttribute("aria-busy") === "true") {
      return false;
    }
    const richTextFields = fieldsToSave.filter(isRichTextField);
    try {
      await Promise.all(
        richTextFields.map((field) => ensureRichTextEditor(context, field))
      );
    } catch {
      return false;
    }
    normalizeRichTextBaselines(richTextFields);
    clearValidationErrors(fieldsToSave);
    const changedFields = fieldsToSave.filter(
      (field) => getFieldPropertyName(field) !== "" && !field.disabled && !isFieldReadOnly(field) && persistedValues.get(field) !== getFieldValue(field)
    );
    if (changedFields.length === 0) {
      closeFields(context, fieldsToSave);
      showStatus(context, "info", context.messages.unchanged ?? null);
      return true;
    }
    const invalidField = changedFields.find(
      (field) => !field.checkValidity()
    );
    if (invalidField !== void 0) {
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
        validationRegion()
      );
      return false;
    }
    const profileUid = context.profileUid;
    const updateUrl = context.urls.update;
    if (profileUid === null || updateUrl === void 0) {
      showStatus(context, "danger");
      return false;
    }
    const data = Object.fromEntries(
      changedFields.map((field) => [
        getFieldPropertyName(field),
        getFieldValue(field)
      ])
    );
    root.setAttribute("aria-busy", "true");
    showStatus(context, "info", context.messages.saving ?? null);
    try {
      const result = await requestJson(updateUrl, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ profile: profileUid, data })
      });
      const responseData = typeof result.data === "object" && result.data !== null ? result.data : {};
      changedFields.forEach((field) => {
        const propertyName = getFieldPropertyName(field);
        const value = Object.hasOwn(responseData, propertyName) ? responseData[propertyName] : getFieldValue(field);
        setFieldValue(field, value);
        persistedValues.set(field, getFieldValue(field));
        renderActivateButton(context, field, value);
      });
      renderProfileName(context);
      closeFields(context, changedFields, !formEditingActive);
      showStatus(context, "success");
      return true;
    } catch (error) {
      const result = error.result;
      if ((result == null ? void 0 : result.errors) !== void 0) {
        showValidationErrors(context, fields, result.errors);
        showStatus(
          context,
          "warning",
          context.messages.validation ?? null,
          validationRegion()
        );
      } else {
        showStatus(context, "danger", (result == null ? void 0 : result.message) ?? null);
      }
      return false;
    } finally {
      root.setAttribute("aria-busy", "false");
    }
  };
  const renderEveryPreview = () => {
    root.querySelectorAll(fieldGroupSelector).forEach((group) => renderFieldGroupPreview(context, group));
    fields.filter((field) => field.closest(fieldGroupSelector) === null).forEach(
      (field) => renderActivateButton(context, field, getFieldValue(field))
    );
    renderProfileName(context);
  };
  const setFormEditingState = (active) => {
    formEditingActive = active;
    perFieldActionGroups().forEach((group) => {
      group.hidden = active;
    });
    formActionBars.forEach((bar) => {
      bar.hidden = !active;
    });
    setEditAllButtonState(context, active);
  };
  const enterFormEditing = () => {
    setFormEditingState(true);
    root.querySelectorAll(fieldGroupSelector).forEach((group) => {
      toggleEditGroup(context, group, true, false);
    });
    root.querySelectorAll(editButtonSelector).forEach((editButton) => {
      const fieldId = hooks(editButton).peFor;
      if (fieldId !== void 0) {
        toggleEditField(context, fieldId, true, false);
      }
    });
    const firstField = editableFields()[0];
    if (firstField !== void 0) {
      focusField(context, firstField);
    }
  };
  const leaveFormEditing = () => {
    var _a;
    setFormEditingState(false);
    closeFields(context, fields, false);
    (_a = root.querySelector(editAllButtonSelector)) == null ? void 0 : _a.focus();
  };
  const revertForm = () => {
    normalizeRichTextBaselines(editableFields());
    resetFields(editableFields());
    renderEveryPreview();
  };
  const discardForm = () => {
    revertForm();
    leaveFormEditing();
  };
  const formTransitionAllowed = () => !formRequestPending;
  const applyForm = async () => {
    if (formRequestPending || root.getAttribute("aria-busy") === "true") {
      return;
    }
    formRequestPending = true;
    try {
      const applied = await saveFields(editableFields());
      if (applied) {
        leaveFormEditing();
      }
    } finally {
      formRequestPending = false;
    }
  };
  root.addEventListener("click", (event) => {
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
      const group = button.closest(fieldGroupSelector);
      if (group !== null) {
        toggleEditGroup(context, group, true);
      }
      return;
    }
    if (button.matches("[data-pe-group-dismiss]")) {
      event.preventDefault();
      const group = button.closest(fieldGroupSelector);
      if (group !== null) {
        const groupFields = getGroupFields(context, group).filter(
          (field) => !field.disabled && !isFieldReadOnly(field)
        );
        groupFields.forEach((field) => setFieldValue(field, ""));
        clearValidationErrors(groupFields);
        toggleEditGroup(context, group, true);
      }
      return;
    }
    if (button.matches("[data-pe-group-cancel]")) {
      event.preventDefault();
      const group = button.closest(fieldGroupSelector);
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
      const group = button.closest(fieldGroupSelector);
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
        hooks(button.closest(formActionsSelector) ?? button).peFormRevertedMessage ?? null
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
      if (fieldId !== void 0) {
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
  root.addEventListener("change", (event) => {
    const field = event.target;
    if (!isEditableField(field) || !field.matches(autosaveOnChangeSelector)) {
      return;
    }
    if (formEditingActive) {
      return;
    }
    const previousValue = persistedValues.get(field);
    void saveFields([field]).then((saved) => {
      if (!saved && previousValue !== void 0) {
        setFieldValue(field, previousValue);
      }
    });
  });
  forms.forEach((form) => {
    form.addEventListener("keydown", (event) => {
      if (!formEditingActive || !formTransitionAllowed()) {
        return;
      }
      const target = event.target;
      if (event.key === "Escape") {
        if (target instanceof Element && target.closest(richTextEditorScopeSelector) !== null) {
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
    form.addEventListener("submit", (event) => {
      event.preventDefault();
      if (formEditingActive) {
        void applyForm();
      }
    });
    form.addEventListener("reset", (event) => event.preventDefault());
  });
};
export {
  initializeFieldEditing
};
