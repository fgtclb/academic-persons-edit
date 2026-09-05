import assert from "node:assert/strict";
import { beforeEach, describe, it } from "node:test";
import {
  resetBody,
  setBoundingRect,
  settle,
} from "../../../../../Build/tests/dom.mjs";
import { installFetch, type FetchDouble } from "../../../../../Build/tests/fetch.mjs";
import {
  profileEditingElementName,
  ProfileEditingRootElement,
  profileEditingStatusEvent,
  registerProfileEditingElement,
} from "@fgtclb/academic-persons-edit/frontend/profile/elements/root.js";
import {
  documentRow,
  documentSection,
  endpoints,
  fieldsForm,
  imageCard,
  messages,
  profileEditingElement,
  profileHeader,
  select,
  selectAll,
  textField,
} from "./Fixtures/profile-editing.ts";

/**
 * `<academic-persons-edit-profile-editing>` is what starts an editor. It reads
 * the `data-*` contract of the root below it once, runs the four initialisers,
 * and is the address a descendant reports a status to.
 *
 * What is asserted here is ownership rather than rendering - the element
 * renders nothing at all:
 *
 * - one context per element, read once and kept across a move in the document;
 * - the initialisers run, and run for one element exactly once;
 * - the two live regions are written by severity, whether the caller is the
 *   element's own method or a `pe:status` event from a descendant.
 *
 * `initializePopover()` is deliberately not asserted: it does nothing without
 * Bootstrap's JavaScript, which the harness does not load. What the other three
 * initialisers write is asserted instead, and each of them is covered in full
 * by a file of its own.
 *
 * The upgrade of an element that was already in the document when the module
 * loaded needs a registry that has not seen it yet, which is a process of its
 * own: `profile-editing-element-upgrade.test.ts`.
 */
describe("the profile editing element", () => {
  let fetch: FetchDouble;

  const pageHeader = '<div id="page-header" class="navbar-fixed-top"></div>';

  const editorMarkup = (profileUid = 1): string =>
    profileEditingElement({
      profileUid,
      content:
        profileHeader({ profileUid, name: "stale" }) +
        fieldsForm(
          textField({ identifier: "firstName", profileUid, value: "Ada" }) +
            textField({ identifier: "lastName", profileUid, value: "Lovelace" }),
        ) +
        imageCard() +
        documentSection({
          identifier: "publications",
          profileUid,
          rows: documentRow({ uid: 7, title: "A paper" }),
        }),
    });

  /**
   * The markup is inserted in a second step, after the page header has been
   * given a height. The element starts the editor the moment it is connected,
   * and the sticky column is measured from a header that a browser has laid out
   * long before the deferred module runs - a fixture that measures afterwards
   * would measure jsdom's zero.
   */
  const render = (markup: string): ProfileEditingRootElement => {
    const body = resetBody(pageHeader);
    setBoundingRect(select(body, "#page-header", HTMLElement), { height: 64 });
    body.insertAdjacentHTML("beforeend", markup);
    const element = select(body, profileEditingElementName, HTMLElement);
    assert.ok(element instanceof ProfileEditingRootElement);

    return element;
  };

  const rootOf = (element: ProfileEditingRootElement): HTMLElement =>
    select(element, "[data-academic-persons-profile-editing]", HTMLElement);

  beforeEach(() => {
    registerProfileEditingElement();
    resetBody("");
    fetch = installFetch();
  });

  it("is defined under its published name", () => {
    assert.equal(profileEditingElementName, "academic-persons-edit-profile-editing");
    assert.equal(
      customElements.get(profileEditingElementName),
      ProfileEditingRootElement,
    );
    // A second call is a no-op, not the "NotSupportedError" a repeated
    // definition of the same name raises - and it leaves the definition alone.
    const defined = customElements.get(profileEditingElementName);
    registerProfileEditingElement();
    assert.equal(customElements.get(profileEditingElementName), defined);
  });

  it("reads the contract of the root below it", () => {
    const element = render(editorMarkup());

    const context = element.context;
    assert.ok(context !== null);
    assert.equal(context.root, rootOf(element));
    assert.equal(context.profileUid, 1);
    assert.equal(context.urls.update, endpoints.update);
    assert.equal(context.messages.documentSaved, messages.documentSaved);
  });

  it("runs the initialisers of the editor", () => {
    const element = render(editorMarkup());
    const root = rootOf(element);

    // "initializeStickyImageOffset()": the image column below the fixed header.
    assert.equal(
      select(root, "[data-pe-sticky-image]", HTMLElement).style.getPropertyValue(
        "top",
      ),
      "74px",
    );
    // "initializeFieldEditing()": the heading rendered from the name fields,
    // which replaces the "stale" the markup was rendered with.
    assert.equal(
      select(root, "[data-pe-profile-name]", HTMLElement).textContent,
      "Ada Lovelace",
    );
    // "initializeDocumentSections()": the row bookkeeping of the one section.
    assert.equal(
      select(root, '[data-pe-document-sort="up"]', HTMLButtonElement).disabled,
      true,
    );
    assert.equal(
      select(root, "[data-pe-document-empty-state]", HTMLElement).classList.contains(
        "d-none",
      ),
      true,
    );
  });

  /**
   * The delegated click handler and the controller it dispatches to are wired
   * by two different calls - `initializeDocumentSections()` registers the
   * listener, `createDocumentEditing()` registers the controller - and only
   * their combination opens an editor. The request that leaves is the proof
   * that the element made both.
   */
  it("wires the document actions to the controller it created", async () => {
    const element = render(editorMarkup());
    fetch.respond({ success: true, fields: [], values: {} });

    select(rootOf(element), "[data-pe-document-add]", HTMLButtonElement).click();
    await settle();

    assert.equal(fetch.calls[0]?.url, endpoints.documentForm);
  });

  it("starts every editor on the page, each with its own context", () => {
    const body = resetBody(editorMarkup(1) + editorMarkup(2));
    const elements = selectAll(body, profileEditingElementName, HTMLElement);

    assert.equal(elements.length, 2);
    assert.equal((elements[0] as ProfileEditingRootElement).context?.profileUid, 1);
    assert.equal((elements[1] as ProfileEditingRootElement).context?.profileUid, 2);
    // And both were started, not only read: the heading each of them renders
    // from its own name fields replaced the "stale" it was rendered with.
    for (const element of elements) {
      assert.equal(
        select(
          rootOf(element as ProfileEditingRootElement),
          "[data-pe-profile-name]",
          HTMLElement,
        ).textContent,
        "Ada Lovelace",
      );
    }
  });

  /**
   * A move in the document disconnects and reconnects the element. The editor
   * behind it is the same one - its listeners, its controllers and the markup
   * they hold are untouched by a move - so the second connection must neither
   * read the contract again nor build a second set of controllers over the
   * first.
   */
  it("keeps its editor when it is moved in the document", async () => {
    const element = render(editorMarkup());
    const context = element.context;

    element.remove();
    document.body.append(element);

    assert.equal(element.context, context);
    // One controller, and that is what a second delegated click listener on the
    // same root would show: the button below it would open two editors and send
    // two requests for one press.
    fetch.respond({ success: true, fields: [], values: {} });
    select(rootOf(element), "[data-pe-document-add]", HTMLButtonElement).click();
    await settle();

    assert.equal(fetch.calls.length, 1);
  });

  it("starts nothing for an element that carries no editor root", () => {
    const element = render(
      `<${profileEditingElementName}></${profileEditingElementName}>`,
    );

    assert.equal(element.context, null);
    // Not an error either: an element whose markup has not arrived yet is
    // connected all the same, and asking it for a status has to stay a no-op
    // rather than reach a controller that was never built.
    element.showStatus("danger", "ignored");
    assert.equal(fetch.calls.length, 0);
  });

  it("writes the assertive region for a failure and the polite one otherwise", () => {
    const element = render(editorMarkup());
    const root = rootOf(element);

    element.showStatus("danger", "The record is locked.");

    assert.equal(
      select(root, '[data-pe-status-toast="alert"] .status-message', HTMLElement)
        .textContent,
      "The record is locked.",
    );

    element.showStatus("success");

    assert.equal(
      select(root, '[data-pe-status-toast="status"] .status-title', HTMLElement)
        .textContent,
      messages.successTitle,
    );
  });

  /**
   * The upward channel the components of the following commits use: a
   * descendant dispatches `pe:status` and needs to know neither the root nor
   * the module that writes the region.
   */
  it("shows a status a descendant asked for", () => {
    const element = render(editorMarkup());
    const root = rootOf(element);

    select(root, "[data-pe-document-add]", HTMLButtonElement).dispatchEvent(
      new CustomEvent(profileEditingStatusEvent, {
        bubbles: true,
        detail: { type: "warning" },
      }),
    );

    const status = select(root, '[data-pe-status-toast="status"]', HTMLElement);
    assert.equal(
      select(status, ".status-title", HTMLElement).textContent,
      messages.warningTitle,
    );
    assert.ok(status.classList.contains("bg-warning"));
  });

  /**
   * Asserted against a region that already says something, because an unknown
   * severity has to be dropped *before* anything is written: the renderer
   * clears the severity classes first and reads the severity afterwards, so a
   * status that is only rejected halfway leaves the region empty and styleless.
   */
  it("ignores a status event that names no severity it knows", () => {
    const element = render(editorMarkup());
    const root = rootOf(element);
    element.showStatus("info");
    const status = select(root, '[data-pe-status-toast="status"]', HTMLElement);

    element.dispatchEvent(
      new CustomEvent(profileEditingStatusEvent, { detail: { type: "notice" } }),
    );
    element.dispatchEvent(new CustomEvent(profileEditingStatusEvent));
    element.dispatchEvent(
      new CustomEvent(profileEditingStatusEvent, { detail: "danger" }),
    );

    assert.ok(status.classList.contains("bg-info"));
    assert.equal(
      select(status, ".status-title", HTMLElement).textContent,
      messages.infoTitle,
    );
  });

  it("stops listening for a status while it is disconnected", () => {
    const element = render(editorMarkup());
    const root = rootOf(element);
    const title = select(
      root,
      '[data-pe-status-toast="status"] .status-title',
      HTMLElement,
    );

    element.remove();
    element.dispatchEvent(
      new CustomEvent(profileEditingStatusEvent, { detail: { type: "success" } }),
    );

    assert.equal(title.textContent, "");

    document.body.append(element);
    element.dispatchEvent(
      new CustomEvent(profileEditingStatusEvent, { detail: { type: "success" } }),
    );

    assert.equal(title.textContent, messages.successTitle);
  });
});
