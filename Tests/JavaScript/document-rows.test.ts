import assert from "node:assert/strict";
import { beforeEach, describe, it } from "node:test";
import {
  createDragEvent,
  resetBody,
  setBoundingRect,
  settle,
} from "../../../../../Build/tests/dom.mjs";
import { installFetch, type FetchDouble } from "../../../../../Build/tests/fetch.mjs";
import {
  createDocumentEditing,
  initializeDocumentSections,
} from "@fgtclb/academic-persons-edit/frontend/profile/documents.js";
import {
  documentRow,
  documentSection,
  endpoints,
  messages,
  profileEditingRoot,
  select,
  selectAll,
} from "./Fixtures/profile-editing.ts";

/**
 * The document lists - publications, qualifications, contracts - are rendered
 * by Fluid and maintained by the JavaScript from there on: it renumbers the
 * rows, disables the arrow at each end of the list, and reorders the rows
 * before the server has confirmed anything.
 *
 * Two of the hooks read here are among the thirteen that were read under the
 * wrong name for a release: `data-pe-document-sort`, which is what tells the
 * click handler that a button is an arrow and in which direction, and
 * `data-pe-document-value`, which names the column a value belongs in. Both
 * are `dataset` reads that no PHP test and no type check can see.
 */
describe("a rendered document list", () => {
  let root: HTMLElement;

  const render = ({
    sortable = true,
    rows = [1, 2, 3],
  }: { sortable?: boolean; rows?: number[] } = {}): HTMLElement => {
    const body = resetBody(
      profileEditingRoot({
        content: documentSection({
          identifier: "publications",
          sortable,
          rows: rows
            .map((uid, index): string =>
              documentRow({
                uid,
                sorting: (index + 1) * 10,
                position: index,
                title: `Paper ${uid}`,
                sortable,
              }),
            )
            .join(""),
        }),
      }),
    );
    const rendered = select(
      body,
      "[data-academic-persons-profile-editing]",
      HTMLElement,
    );
    createDocumentEditing(rendered);
    initializeDocumentSections(rendered);

    return rendered;
  };

  const rows = (): HTMLElement[] =>
    selectAll(root, "[data-pe-document-items] > [data-pe-document-item]", HTMLElement);
  const arrow = (uid: number, direction: "up" | "down"): HTMLButtonElement =>
    select(
      root,
      `[data-item-uid="${uid}"] [data-pe-document-sort="${direction}"]`,
      HTMLButtonElement,
    );

  beforeEach(() => {
    // Nothing is requested here, but the double keeps a stray request from
    // reaching the network if one ever is.
    installFetch();
  });

  /**
   * The position is the only thing written onto a row here. Its striping is a
   * stylesheet rule on `:nth-child(odd)`, so no class is toggled for it and a
   * row that carries one would be a partial writing state.
   */
  it("numbers the rows and leaves the striping to the stylesheet", () => {
    root = render();

    assert.deepEqual(
      rows().map((row): string | undefined => row.dataset.itemPosition),
      ["0", "1", "2"],
    );
    assert.deepEqual(
      rows().map((row): boolean => row.classList.contains("bg-body-tertiary")),
      [false, false, false],
    );
  });

  it("disables the arrow that would move a row out of the list", () => {
    root = render();

    assert.equal(arrow(1, "up").disabled, true);
    assert.equal(arrow(1, "up").getAttribute("aria-disabled"), "true");
    assert.equal(arrow(1, "down").disabled, false);
    assert.equal(arrow(3, "down").disabled, true);
    assert.equal(arrow(2, "up").disabled, false);
    assert.equal(arrow(2, "down").disabled, false);
  });

  it("shows the list header only while the list has rows", () => {
    root = render();
    assert.ok(
      select(root, "[data-pe-document-list-header]", HTMLElement).classList.contains("d-md-flex"),
    );
    assert.ok(
      select(root, "[data-pe-document-empty-state]", HTMLElement).classList.contains("d-none"),
    );

    root = render({ rows: [] });
    assert.equal(
      select(root, "[data-pe-document-list-header]", HTMLElement).classList.contains("d-md-flex"),
      false,
    );
    assert.equal(
      select(root, "[data-pe-document-empty-state]", HTMLElement).classList.contains("d-none"),
      false,
    );
  });

  it("offers dragging where the section is sortable", () => {
    root = render();

    const draggable = select(root, "[data-pe-document-drag]", HTMLButtonElement);
    assert.equal(draggable.disabled, false);
    assert.equal(draggable.draggable, true);
  });

  /**
   * A section that is not sortable renders no handle at all - the template
   * leaves it out - so there is nothing for the drag handlers to pick up.
   */
  it("renders no drag handle where the section is not sortable", () => {
    root = render({ sortable: false });

    assert.equal(root.querySelector("[data-pe-document-drag]"), null);
  });

  /**
   * A list of one is not sortable even where the section is: there is nothing
   * to move it past.
   */
  it("does not offer dragging a single row", () => {
    root = render({ rows: [1] });

    assert.equal(select(root, "[data-pe-document-drag]", HTMLButtonElement).disabled, true);
  });
});

describe("sorting a document list with the arrows", () => {
  let fetch: FetchDouble;
  let root: HTMLElement;

  const render = (): HTMLElement => {
    const body = resetBody(
      profileEditingRoot({
        content: documentSection({
          identifier: "publications",
          rows: [1, 2, 3]
            .map((uid, index): string =>
              documentRow({ uid, sorting: (index + 1) * 10, position: index, title: `Paper ${uid}` }),
            )
            .join(""),
        }),
      }),
    );
    const rendered = select(body, "[data-academic-persons-profile-editing]", HTMLElement);
    createDocumentEditing(rendered);
    initializeDocumentSections(rendered);

    return rendered;
  };

  const uids = (): string[] =>
    selectAll(root, "[data-pe-document-items] > [data-pe-document-item]", HTMLElement)
      .map((row): string => row.dataset.itemUid ?? "");
  const arrow = (uid: number, direction: "up" | "down"): HTMLButtonElement =>
    select(
      root,
      `[data-item-uid="${uid}"] [data-pe-document-sort="${direction}"]`,
      HTMLButtonElement,
    );

  beforeEach(() => {
    fetch = installFetch();
    root = render();
  });

  it("asks the server to move that record in that direction", async () => {
    fetch.respond({ success: true, order: [2, 1, 3] });

    arrow(2, "up").click();
    await settle(20);

    const call = fetch.calls[0];
    assert.equal(call?.url, endpoints.sortDocument);
    assert.equal(call?.method, "POST");
    assert.equal(call?.headers["X-Requested-With"], "XMLHttpRequest");
    assert.deepEqual(call?.body, {
      profile: 1,
      data: { section: "publications", record: 2, direction: "up" },
    });
  });

  it("rearranges the rows into the order the server answers with", async () => {
    fetch.respond({ success: true, order: [2, 1, 3] });

    arrow(2, "up").click();
    await settle(20);

    assert.deepEqual(uids(), ["2", "1", "3"]);
    assert.deepEqual(
      selectAll(root, "[data-pe-document-item]", HTMLElement)
        .slice(0, 3)
        .map((row): string | undefined => row.dataset.itemPosition),
      ["0", "1", "2"],
    );
    assert.equal(arrow(2, "up").disabled, true);
    assert.equal(arrow(1, "up").disabled, false);
    assert.equal(
      select(root, '[data-pe-status-toast="status"] .status-message', HTMLElement).textContent,
      messages.documentSorted,
    );
  });

  /**
   * While the section is waiting, nothing in it may be pressed - and afterwards
   * every button is put back into the state it had, which is not "enabled":
   * the arrow at each end of the list stays disabled.
   */
  it("locks the section while it waits and restores the end arrows afterwards", async () => {
    const slow = fetch.respondLater();
    const section = select(root, "[data-pe-document-section]", HTMLElement);

    arrow(2, "up").click();
    await settle(5);
    assert.equal(section.getAttribute("aria-busy"), "true");
    assert.equal(arrow(2, "down").disabled, true);

    slow.settle({ success: true, order: [2, 1, 3] });
    await settle(20);
    assert.equal(section.getAttribute("aria-busy"), "false");
    assert.equal(arrow(1, "down").disabled, false);
    assert.equal(arrow(2, "up").disabled, true);
  });

  it("leaves the order alone and reports the refusal", async () => {
    fetch.respondWithError({ success: false, message: "Not sortable." }, 400);

    arrow(2, "up").click();
    await settle(20);

    assert.deepEqual(uids(), ["1", "2", "3"]);
    assert.equal(
      select(root, '[data-pe-status-toast="alert"] .status-message', HTMLElement).textContent,
      "Not sortable.",
    );
  });

  it("ignores a click on an arrow that is already disabled", async () => {
    arrow(1, "up").click();
    await settle(5);

    assert.equal(fetch.calls.length, 0);
  });
});

/**
 * Dragging a row. The handlers live on the plugin root and read the pointer
 * position against the row's own rectangle, so the drop indicator says where
 * the row will land before it is dropped.
 */
describe("sorting a document list by dragging", () => {
  let fetch: FetchDouble;
  let root: HTMLElement;

  const render = (): HTMLElement => {
    const body = resetBody(
      profileEditingRoot({
        content: documentSection({
          identifier: "publications",
          rows: [1, 2, 3]
            .map((uid, index): string =>
              documentRow({ uid, sorting: (index + 1) * 10, position: index, title: `Paper ${uid}` }),
            )
            .join(""),
        }),
      }),
    );
    const rendered = select(body, "[data-academic-persons-profile-editing]", HTMLElement);
    createDocumentEditing(rendered);
    initializeDocumentSections(rendered);

    return rendered;
  };

  const row = (uid: number): HTMLElement =>
    select(root, `[data-item-uid="${uid}"]`, HTMLElement);
  const items = (): HTMLElement =>
    select(root, "[data-pe-document-items]", HTMLElement);
  const uids = (): string[] =>
    selectAll(root, "[data-pe-document-items] > [data-pe-document-item]", HTMLElement)
      .map((element): string => element.dataset.itemUid ?? "");

  /** Each row is 40 pixels tall and they follow each other from the top. */
  const layOutRows = (): void => {
    [1, 2, 3].forEach((uid, index): void => {
      setBoundingRect(row(uid), { top: index * 40, height: 40, width: 600 });
    });
  };

  const startDragging = (uid: number): void => {
    const handle = select(row(uid), "[data-pe-document-drag]", HTMLButtonElement);
    handle.dispatchEvent(createDragEvent("dragstart", { clientY: 10 }));
  };

  beforeEach(() => {
    fetch = installFetch();
    root = render();
    layOutRows();
  });

  it("marks the dragged row and its list while a drag is running", () => {
    startDragging(1);

    assert.ok(row(1).classList.contains("is-dragging"));
    assert.ok(items().classList.contains("is-drag-active"));

    root.dispatchEvent(createDragEvent("dragend"));
    assert.equal(row(1).classList.contains("is-dragging"), false);
    assert.equal(items().classList.contains("is-drag-active"), false);
  });

  it("hands the row's identity to the drag and asks for a move", () => {
    const handle = select(row(2), "[data-pe-document-drag]", HTMLButtonElement);
    const event = createDragEvent("dragstart", { clientY: 50 });

    handle.dispatchEvent(event);

    assert.equal(event.dataTransfer.effectAllowed, "move");
    assert.equal(event.dataTransfer.getData("text/plain"), "2");
    assert.equal(event.dataTransfer.dragImage?.element, row(2));
  });

  it("shows the drop above the row while the pointer is in its upper half", () => {
    startDragging(3);

    row(1).dispatchEvent(createDragEvent("dragover", { clientY: 5 }));

    assert.ok(row(1).classList.contains("is-drop-before"));
    assert.equal(row(1).classList.contains("is-drop-after"), false);
  });

  it("shows the drop below the row while the pointer is in its lower half", () => {
    startDragging(3);

    row(1).dispatchEvent(createDragEvent("dragover", { clientY: 35 }));

    assert.ok(row(1).classList.contains("is-drop-after"));
  });

  it("shows the drop at the end while the pointer is past the last row", () => {
    startDragging(1);

    items().dispatchEvent(createDragEvent("dragover", { clientY: 500 }));

    assert.ok(items().classList.contains("is-drop-at-end"));
  });

  it("moves the row and saves the order it produced", async () => {
    fetch.respond({ success: true, order: [2, 3, 1] });
    startDragging(1);

    items().dispatchEvent(createDragEvent("dragover", { clientY: 500 }));
    items().dispatchEvent(createDragEvent("drop", { clientY: 500 }));
    await settle(20);

    assert.deepEqual(uids(), ["2", "3", "1"]);
    assert.deepEqual(fetch.calls[0]?.body, {
      profile: 1,
      data: { section: "publications", order: [2, 3, 1] },
    });
  });

  /**
   * The row is moved before the server is asked, so a refusal has to put the
   * list back where it was - otherwise the page shows an order that was never
   * stored.
   */
  it("puts the list back when the server refuses the new order", async () => {
    fetch.respondWithError({ success: false, message: "Refused." }, 400);
    startDragging(1);

    row(3).dispatchEvent(createDragEvent("dragover", { clientY: 115 }));
    row(3).dispatchEvent(createDragEvent("drop", { clientY: 115 }));
    assert.deepEqual(uids(), ["2", "3", "1"]);

    await settle(20);
    assert.deepEqual(uids(), ["1", "2", "3"]);
    assert.equal(
      select(root, '[data-pe-status-toast="alert"] .status-message', HTMLElement).textContent,
      "Refused.",
    );
  });

  it("saves nothing when the row is dropped where it already was", async () => {
    startDragging(2);

    row(2).dispatchEvent(createDragEvent("dragover", { clientY: 50 }));
    row(2).dispatchEvent(createDragEvent("drop", { clientY: 50 }));
    await settle(20);

    assert.equal(fetch.calls.length, 0);
    assert.deepEqual(uids(), ["1", "2", "3"]);
  });

  it("clears the drop indicator when the pointer leaves the section", () => {
    startDragging(3);
    row(1).dispatchEvent(createDragEvent("dragover", { clientY: 5 }));

    select(root, "[data-pe-status-toast='status']", HTMLElement)
      .dispatchEvent(createDragEvent("dragover", { clientY: 5 }));

    assert.equal(row(1).classList.contains("is-drop-before"), false);
  });
});
