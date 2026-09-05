/* Generated from Resources/Private/TypeScript — do not edit. */
const hasAccessor = (start, name) => {
  for (let prototype = start; prototype !== null; prototype = Object.getPrototypeOf(prototype)) {
    const descriptor = Object.getOwnPropertyDescriptor(prototype, name);
    if (descriptor !== void 0) {
      return descriptor.set !== void 0;
    }
  }
  return false;
};
const takeInstanceProperties = (element) => {
  const target = element;
  const prototype = Object.getPrototypeOf(element);
  let taken = null;
  Object.getOwnPropertyNames(element).forEach((name) => {
    const own = Object.getOwnPropertyDescriptor(element, name);
    if (own === void 0 || !("value" in own) || !hasAccessor(prototype, name)) {
      return;
    }
    taken ??= /* @__PURE__ */ new Map();
    taken.set(name, own.value);
    delete target[name];
  });
  return taken;
};
class ProfileEditingElement extends HTMLElement {
  #changed = /* @__PURE__ */ new Map();
  #adopted = null;
  #complete = Promise.resolve(true);
  #settle = null;
  #fail = null;
  #enabled = false;
  #hasUpdated = false;
  #pending = false;
  constructor() {
    super();
    this.#adopted = takeInstanceProperties(this);
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
  get hasUpdated() {
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
  get updateComplete() {
    return this.getUpdateComplete();
  }
  connectedCallback() {
    if (this.#enabled) {
      return;
    }
    this.#enabled = true;
    if (this.#pending) {
      this.#schedule();
    }
  }
  disconnectedCallback() {
  }
  /**
   * Overridden by an element whose `updateComplete` has to cover more than its
   * own update - the document editor awaits its contact list as well.
   */
  getUpdateComplete() {
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
  requestUpdate(name, previous) {
    if (name !== void 0) {
      const current = this[name];
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
    this.#complete = new Promise((resolve, reject) => {
      this.#settle = resolve;
      this.#fail = reject;
    });
    if (this.#enabled) {
      this.#schedule();
    }
  }
  #schedule() {
    queueMicrotask(() => {
      this.#performUpdate();
    });
  }
  #performUpdate() {
    var _a, _b;
    if (!this.#pending) {
      return;
    }
    this.#applyAdopted();
    const changed = this.#changed;
    const settle = this.#settle;
    const fail = this.#fail;
    this.#changed = /* @__PURE__ */ new Map();
    this.#settle = null;
    this.#fail = null;
    this.#pending = false;
    try {
      if (!this.#hasUpdated) {
        this.#hasUpdated = true;
        (_a = this.firstUpdated) == null ? void 0 : _a.call(this);
      }
      (_b = this.updated) == null ? void 0 : _b.call(this, changed);
    } catch (error) {
      fail == null ? void 0 : fail(error);
    } finally {
      settle == null ? void 0 : settle(!this.#pending);
    }
  }
  #applyAdopted() {
    const adopted = this.#adopted;
    if (adopted === null) {
      return;
    }
    this.#adopted = null;
    const target = this;
    adopted.forEach((value, name) => {
      target[name] = value;
    });
  }
}
export {
  ProfileEditingElement
};
