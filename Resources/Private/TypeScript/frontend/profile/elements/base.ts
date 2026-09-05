/**
 * `ProfileEditingElement` - the base class of every custom element of the
 * profile editor, and the whole of the state handling they share.
 *
 * ## The elements control markup, they do not produce it
 *
 * Every editor of this extension is a *controller over server rendered
 * markup*. Fluid renders the frame, the field rows, the panels and the
 * `<template data-pe-proto>` prototypes; an element clones a prototype, fills
 * its slots and toggles what the state changed. An integrator therefore
 * overrides a Fluid partial, never a JavaScript module.
 *
 * ## Why there is no framework below this
 *
 * None of the five elements renders markup, so a rendering base class would
 * have nothing to render and would have to be kept away from the children
 * Fluid put below the element. That is the whole reason this file exists as
 * something other than `HTMLElement`: what the elements actually need is not a
 * render pipeline but the little that is around one, and it is here in full -
 * an assignment schedules one update, several assignments in the same turn
 * become one, `updated()` learns which properties changed, and
 * `updateComplete` settles after it ran: `false` when a callback scheduled the
 * next update, and rejected when one threw. 136 lines of code in this file,
 * all of them readable, and nothing in the element trees below can reach the
 * markup.
 *
 * Three of the five elements use none of it and extend this class for the two
 * lifecycle callbacks alone. The document editor is the one that needs all of
 * it: a keystroke assigns `values`, a refusal assigns `errors`, a request
 * assigns `pending`, and none of those may rebuild the panel, because
 * rebuilding it replaces every control the visitor is typing in and every live
 * CKEditor. The changed-property map is what separates the structural change
 * that rebuilds from the value change that patches, and the batching is what
 * turns the five assignments of one `openDocument()` into one DOM pass.
 *
 * ## No decorators, and no class fields for a reactive property
 *
 * The behavioural suite runs these sources under node's type stripping, which
 * erases annotations but does not transform, and `Build/tsconfig.tests.json`
 * sets `erasableSyntaxOnly` so a decorator is a type error rather than a
 * runtime one. A reactive property is therefore a plain accessor pair over a
 * private field, and its setter ends in `requestUpdate()`.
 *
 * Pinned by `Tests/JavaScript/element-lifecycle.test.ts`.
 */

/**
 * What `updated()` is handed: the properties that changed since the previous
 * update, each mapped to the value it had before.
 */
export type ChangedProperties<T = Record<string, unknown>> = ReadonlyMap<
  Extract<keyof T, string>,
  unknown
>;

/**
 * Whether `name` is answered by a setter somewhere on the prototype chain.
 *
 * Used to tell the reactive properties of an upgraded element from whatever
 * else an owner happened to hang on it, without a list of names that would
 * have to be kept in step with the accessors themselves.
 */
const hasAccessor = (start: object | null, name: string): boolean => {
  for (
    let prototype = start;
    prototype !== null;
    prototype = Object.getPrototypeOf(prototype) as object | null
  ) {
    const descriptor = Object.getOwnPropertyDescriptor(prototype, name);
    if (descriptor !== undefined) {
      return descriptor.set !== undefined;
    }
  }

  return false;
};

/**
 * Takes the own properties an owner assigned before the element was upgraded
 * off the instance, so that the accessors on the prototype are reachable
 * again.
 *
 * An element the browser upgrades keeps those assignments as own data
 * properties, and an own data property shadows an accessor: the setter would
 * never run, the element would never update, and the value would be lost the
 * moment the constructor writes its default over it. They are handed back at
 * the start of the first update instead, which is after the constructor.
 */
const takeInstanceProperties = (
  element: HTMLElement,
): Map<string, unknown> | null => {
  const target = element as unknown as Record<string, unknown>;
  const prototype = Object.getPrototypeOf(element) as object | null;
  let taken: Map<string, unknown> | null = null;
  Object.getOwnPropertyNames(element).forEach((name: string): void => {
    const own = Object.getOwnPropertyDescriptor(element, name);
    if (own === undefined || !("value" in own) || !hasAccessor(prototype, name)) {
      return;
    }
    taken ??= new Map<string, unknown>();
    taken.set(name, own.value);
    delete target[name];
  });

  return taken;
};

/**
 * The base class of `elements/root.ts`, `elements/image-editor.ts`,
 * `elements/document-editor.ts`, `elements/contract-contacts.ts` and
 * `elements/rich-text.ts`.
 *
 * `TSelf` is the element itself - `class X extends ProfileEditingElement<X>`
 * for the two that declare reactive properties - and is what types the name a
 * setter hands to `requestUpdate()`. An element with no property of its own
 * leaves it out and can then only request an update without a name, which is
 * all any of the three does.
 */
export abstract class ProfileEditingElement<TSelf = unknown> extends HTMLElement {
  #changed = new Map<string, unknown>();
  #adopted: Map<string, unknown> | null = null;
  #complete: Promise<boolean> = Promise.resolve(true);
  #settle: ((updated: boolean) => void) | null = null;
  #fail: ((reason: unknown) => void) | null = null;
  #enabled = false;
  #hasUpdated = false;
  #pending = false;

  constructor() {
    super();
    this.#adopted = takeInstanceProperties(this);
    // The first update is requested here and performed on the first
    // connection: an element that is created, assigned and only then put in
    // the document writes its markup once, not once per assignment.
    this.requestUpdate();
  }

  /**
   * Whether the first update has run.
   *
   * Nothing in the editor reads it and nothing implements `firstUpdated()`
   * below. Both are kept on purpose, as the seam the next element that has to
   * set something up once will use, and `element-lifecycle.test.ts` pins them
   * so that a seam nobody uses cannot rot.
   */
  get hasUpdated(): boolean {
    return this.#hasUpdated;
  }

  /**
   * Resolves after the scheduled update ran, or right away when none is.
   *
   * `false` says that a callback of the update this promise reports assigned a
   * property and scheduled the next one, so the markup is one pass behind and
   * the caller has to await again. The promise **rejects** when
   * `firstUpdated()` or `updated()` threw: an element whose update failed has
   * no markup for the caller to work with, and the callers here have an error
   * path of their own to run.
   */
  get updateComplete(): Promise<boolean> {
    return this.getUpdateComplete();
  }

  connectedCallback(): void {
    if (this.#enabled) {
      return;
    }
    this.#enabled = true;
    if (this.#pending) {
      this.#schedule();
    }
  }

  disconnectedCallback(): void {
    // Nothing of its own. Declared so that every element can call it from its
    // own override without having to know which of them the base implements.
  }

  /**
   * Overridden by an element whose `updateComplete` has to cover more than its
   * own update - the document editor awaits its contact list as well.
   */
  protected getUpdateComplete(): Promise<boolean> {
    return this.#complete;
  }

  /**
   * Schedules one update, and records `name` as changed.
   *
   * Called by every reactive setter with the value the property had before.
   * An assignment that changes nothing is dropped here rather than in each
   * setter, which is what keeps a setter three lines long.
   *
   * `name` is a property of the element and not a free-form string. Twenty
   * setters hand-type it, a typo would take the property out of the changed
   * map, and one of the seven structural names of the document editor would
   * then stop rebuilding its panel - silently, and past every gate. Typing it
   * as `keyof TSelf` makes it a type error instead.
   */
  protected requestUpdate(
    name?: Extract<keyof TSelf, string>,
    previous?: unknown,
  ): void {
    if (name !== undefined) {
      const current = (this as unknown as Record<string, unknown>)[name];
      if (Object.is(current, previous)) {
        return;
      }
      if (!this.#changed.has(name)) {
        this.#changed.set(name, previous);
      }
    }
    if (this.#pending) {
      return;
    }
    this.#pending = true;
    this.#complete = new Promise<boolean>((resolve, reject): void => {
      this.#settle = resolve;
      this.#fail = reject;
    });
    if (this.#enabled) {
      this.#schedule();
    }
  }

  /**
   * Runs once, before the first `updated()`.
   *
   * Declared rather than implemented: an element that has nothing to do here
   * says so by not writing it, and none of the five does - see `hasUpdated`
   * for why it is here regardless.
   */
  protected firstUpdated?(): void;

  /**
   * Runs after every update, with the properties that changed since the last
   * one. Declared rather than implemented, for the same reason.
   */
  protected updated?(changed: ChangedProperties): void;

  #schedule(): void {
    queueMicrotask((): void => {
      this.#performUpdate();
    });
  }

  #performUpdate(): void {
    if (!this.#pending) {
      return;
    }
    this.#applyAdopted();
    // Read and cleared before the callbacks run: one of them may assign a
    // property, and that assignment belongs to the next update rather than to
    // the map this one is reporting.
    const changed = this.#changed;
    const settle = this.#settle;
    const fail = this.#fail;
    this.#changed = new Map<string, unknown>();
    this.#settle = null;
    this.#fail = null;
    this.#pending = false;
    try {
      if (!this.#hasUpdated) {
        this.#hasUpdated = true;
        this.firstUpdated?.();
      }
      this.updated?.(changed);
    } catch (error) {
      // Reported through the promise and not re-thrown out of this
      // microtask: an awaiting caller - `documents.ts` awaits this before it
      // initialises the editors of the panel it just opened - has an error
      // path of its own to run, and an uncaught exception here would leave it
      // awaiting instead. With nobody awaiting, the rejection stays unhandled
      // and the console reports it, which is what a dropped prototype has to
      // produce rather than an empty panel.
      fail?.(error);
    } finally {
      // In a `finally`, and that is the whole point of the block: the two
      // settlers were taken off the instance above, so any path out of this
      // method that skips this line leaves the promise unsettled for ever - a
      // later `requestUpdate()` replaces `#complete` and never the reference
      // an awaiter is already holding. Resolving a promise the `catch`
      // rejected is a no-op.
      //
      // `!this.#pending`: a callback that assigned a property scheduled the
      // next update, and this promise then reports a DOM one pass behind.
      settle?.(!this.#pending);
    }
  }

  #applyAdopted(): void {
    const adopted = this.#adopted;
    if (adopted === null) {
      return;
    }
    // Cleared first: the assignments below go through the setters, and a
    // setter that requested an update must not find them again.
    this.#adopted = null;
    const target = this as unknown as Record<string, unknown>;
    adopted.forEach((value: unknown, name: string): void => {
      target[name] = value;
    });
  }
}
