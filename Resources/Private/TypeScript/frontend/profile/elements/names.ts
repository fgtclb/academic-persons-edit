/**
 * The names of every custom element this extension defines.
 *
 * A leaf module with no imports, and that is its whole reason for existing: the
 * document editing controller creates the document editor element by name, the
 * element module needs the same name to define itself, and the element also
 * resolves its editing context through the root element - so if each name lived
 * in the module that implements it, `profile/documents.ts`,
 * `elements/document-editor.ts` and `elements/root.ts` would form an import
 * cycle whose top level `const` reads land in the temporal dead zone. Naming is
 * not behaviour and does not belong in any of the three.
 *
 * The names themselves are public API from the moment they ship. A custom
 * element name is global and has no scoping mechanism of any kind, so the
 * prefix has to be one this extension provably owns - the extension key with
 * its underscores replaced, which is the same token the icon identifiers
 * (`academic-persons-edit-add`) and the import map specifier
 * (`@fgtclb/academic-persons-edit/`) already use, and whose uniqueness TER and
 * packagist enforce. A shorter `academic-profile-` would read better and would
 * be a name `academic_persons`, which ships frontend JavaScript of its own,
 * could claim just as well.
 */

/** The prefix of every custom element name below. */
export const profileEditingElementPrefix = "academic-persons-edit-";

/** The element that owns one profile editor: `elements/root.ts`. */
export const profileEditingElementName = `${profileEditingElementPrefix}profile-editing`;

/** The profile image editor: `elements/image-editor.ts`. */
export const profileImageEditorElementName = `${profileEditingElementPrefix}image-editor`;

/** One open document or contract editor: `elements/document-editor.ts`. */
export const profileDocumentEditorElementName = `${profileEditingElementPrefix}document-editor`;

/** One rich text field and the CKEditor on it: `elements/rich-text.ts`. */
export const profileRichTextElementName = `${profileEditingElementPrefix}rich-text`;

/** The contacts of one contract: `elements/contract-contacts.ts`. */
export const profileContractContactsElementName = `${profileEditingElementPrefix}contract-contacts`;
