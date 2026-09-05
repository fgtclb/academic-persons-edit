/**
 * The entry point of the profile editor, loaded by
 * `Templates/Profile/Index.html` through `<f:asset.module>`.
 *
 * It defines the custom elements and does nothing else. Every editor on the page
 * is a `<academic-persons-edit-profile-editing>` element that starts itself when
 * the browser upgrades it, which is what replaced the start-up scan for
 * `[data-academic-persons-profile-editing]` and the `WeakSet` of roots that had
 * already been mounted.
 *
 * `<f:asset.module>` renders `type="module"`, so this file is evaluated after
 * the document has been parsed and the elements are upgraded rather than
 * constructed. Neither order matters to the element: a custom element is
 * upgraded whether it was already in the document when it was defined or is
 * inserted afterwards.
 */
import { registerProfileEditingElement } from "@fgtclb/academic-persons-edit/frontend/profile/elements/root.js";
import { registerProfileContractContactsElement } from "@fgtclb/academic-persons-edit/frontend/profile/elements/contract-contacts.js";
import { registerProfileDocumentEditorElement } from "@fgtclb/academic-persons-edit/frontend/profile/elements/document-editor.js";
import { registerProfileImageEditorElement } from "@fgtclb/academic-persons-edit/frontend/profile/elements/image-editor.js";
import { registerProfileRichTextElement } from "@fgtclb/academic-persons-edit/frontend/profile/elements/rich-text.js";

// The root first. The order decides nothing - every element is upgraded
// whenever the registry sees it - but it is the order the page starts in and it
// reads that way: the owner, then what it owns.
registerProfileEditingElement();
registerProfileImageEditorElement();
// The document editor, the rich text field it renders and the contract
// contacts it renders are created by "profile/documents.ts" and by each other
// rather than by Fluid, and that module registers them itself for the same
// reason - it cannot depend on an entry point having run. Every registration is
// idempotent, and this one is the page's: an editor is registered whether or
// not one is ever opened.
registerProfileDocumentEditorElement();
registerProfileRichTextElement();
registerProfileContractContactsElement();
