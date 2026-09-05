import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { settle } from "../../../../../Build/tests/dom.mjs";
import { profileContractContactsElementName } from "@fgtclb/academic-persons-edit/frontend/profile/elements/names.js";
import {
  ProfileContractContactsElement,
  registerProfileContractContactsElement,
} from "@fgtclb/academic-persons-edit/frontend/profile/elements/contract-contacts.js";
import { editingHost, select } from "./Fixtures/profile-editing.ts";

/**
 * Both orders in which this element and its properties can meet.
 *
 * It is never in the markup: the document editor creates it and assigns its
 * five properties in the same update - so the order that happens in a browser
 * is "defined first, created afterwards". The
 * other one is not hypothetical either. The entry point is a module and the
 * element modules are loaded through the import map, so a slow map leaves a
 * document editor that was created by an earlier script with an element the
 * registry has not seen yet, and every property it was handed sits on it as a
 * plain own property. The base class takes those over on upgrade; an element
 * that did not would write its defaults and never see the data again.
 *
 * A file of its own, because an element cannot be undefined once it is defined
 * and node runs every test file in a process of its own. Nothing before the
 * first test may register it.
 */
describe("upgrading the contract contacts element", () => {
  const sections = [
    {
      identifier: "addresses",
      items: [{ hidden: false, summary: [{ label: "City", value: "London" }], uid: 21 }],
      label: "Addresses",
      singularLabel: "Address",
    },
  ];

  it("adopts the properties it was handed before it was defined", async () => {
    const { context, root } = editingHost();
    const element = document.createElement(profileContractContactsElementName);
    // Exactly what the document editor's template commits, and the definition
    // has not run: these are own properties on a plain HTMLElement here.
    Object.assign(element, {
      contract: 5,
      emptyMessage: "No contacts yet.",
      sections,
    });
    root.append(element);
    assert.equal(element instanceof ProfileContractContactsElement, false);

    registerProfileContractContactsElement();
    await settle(20);

    assert.ok(element instanceof ProfileContractContactsElement);
    assert.equal(select(root, "h3", HTMLElement).textContent?.trim(), "Addresses");
    assert.equal(
      select(root, "[data-pe-contract-contact-item]", HTMLElement).getAttribute(
        "data-pe-contract-contact-item",
      ),
      "21",
    );
    // The one property whose absence is not visible in the list: the context
    // is resolved from the owner on connection, and this element connected
    // before the definition ran. It is asserted here, in the case that really
    // upgrades, and not in one of its own - by then the definition exists and
    // `document.createElement()` returns a constructed element, so no upgrade
    // would be replayed and the assertion would prove nothing.
    assert.equal(element.context, context);
  });

  it("renders a list that is created after the module loaded", async () => {
    const { root } = editingHost();

    const element = document.createElement(
      profileContractContactsElementName,
    ) as ProfileContractContactsElement;
    element.contract = 5;
    element.sections = sections;
    root.append(element);
    await element.updateComplete;

    assert.ok(element instanceof ProfileContractContactsElement);
    assert.equal(select(root, "h3", HTMLElement).textContent?.trim(), "Addresses");
  });
});
