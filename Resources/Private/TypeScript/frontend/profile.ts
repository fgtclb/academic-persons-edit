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
 * `<f:asset.module>` renders `<script type="module" async>`, so this file may
 * be evaluated before the document has been parsed as well as after it. After
 * it, the elements are upgraded; before it, the parser constructs them at
 * their start tags, before their children exist. The elements that Fluid
 * renders wait for their markup in either case - see `whenParsed()` of
 * `profile/elements/base.ts` (ACE-647).
 */
import { registerProfileEditingElement } from "@fgtclb/academic-persons-edit/frontend/profile/elements/root.js";
import { registerProfileContractContactsElement } from "@fgtclb/academic-persons-edit/frontend/profile/elements/contract-contacts.js";
import { registerProfileDocumentEditorElement } from "@fgtclb/academic-persons-edit/frontend/profile/elements/document-editor.js";
import { registerProfileImageEditorElement } from "@fgtclb/academic-persons-edit/frontend/profile/elements/image-editor.js";
import { registerProfileRichTextElement } from "@fgtclb/academic-persons-edit/frontend/profile/elements/rich-text.js";

// The root first, and the order matters: the image editor takes its contract
// from the root. An upgrade starts the elements in the order they are defined
// here, the end of a parse in the order their listeners were added - which for
// elements already parsed when this module runs is that order again. The
// owner, then what it owns.
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
