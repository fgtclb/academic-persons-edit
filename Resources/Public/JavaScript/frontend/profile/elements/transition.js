/* Generated from Resources/Private/TypeScript — do not edit. */
const transitionTimeoutSlack = 50;
const toMilliseconds = (value) => {
  const amount = Number.parseFloat(value);
  if (!Number.isFinite(amount)) {
    return 0;
  }
  return value.trim().endsWith("ms") ? amount : amount * 1e3;
};
const transitionDuration = (element) => Math.max(
  0,
  ...globalThis.getComputedStyle(element).transitionDuration.split(",").map(toMilliseconds)
);
const createElementTransition = (prefix) => (element, kind, done) => {
  const active = `${prefix}-${kind}-active`;
  const offset = kind === "enter" ? `${prefix}-enter-from` : `${prefix}-leave-to`;
  element.classList.add(active);
  if (kind === "enter") {
    element.classList.add(offset);
  }
  const duration = transitionDuration(element);
  const clear = () => {
    element.classList.remove(active, offset);
  };
  if (duration === 0) {
    clear();
    done();
    return () => void 0;
  }
  const finish = () => {
    cancel();
    clear();
    done();
  };
  const onTransitionEnd = (event) => {
    if (event.target === element) {
      finish();
    }
  };
  const cancel = () => {
    globalThis.cancelAnimationFrame(frame);
    globalThis.clearTimeout(timeout);
    element.removeEventListener("transitionend", onTransitionEnd);
  };
  element.addEventListener("transitionend", onTransitionEnd);
  const timeout = globalThis.setTimeout(finish, duration + transitionTimeoutSlack);
  let frame = globalThis.requestAnimationFrame(() => {
    frame = globalThis.requestAnimationFrame(() => {
      if (kind === "enter") {
        element.classList.remove(offset);
      } else {
        element.classList.add(offset);
      }
    });
  });
  return () => {
    cancel();
    clear();
  };
};
export {
  createElementTransition
};
