/**
 * The one enter or leave transition both editors open and close with, lifted
 * out of `elements/image-editor.ts` when the document editor needed the same
 * thing with a different set of class names.
 *
 * A named transition derives `x-enter-active`, `x-enter-from`,
 * `x-leave-active` and `x-leave-to` and applied them around the change. The
 * class names are kept - the declarations they select are unchanged and a class
 * an integrator may have overridden is not renamed by a commit that does not
 * have to - and what changes is who applies them.
 */

/** How long after the computed duration a transition is given up on. */
const transitionTimeoutSlack = 50;

const toMilliseconds = (value: string): number => {
  const amount = Number.parseFloat(value);
  if (!Number.isFinite(amount)) {
    return 0;
  }

  return value.trim().endsWith("ms") ? amount : amount * 1000;
};

const transitionDuration = (element: HTMLElement): number =>
  Math.max(
    0,
    ...globalThis
      .getComputedStyle(element)
      .transitionDuration.split(",")
      .map(toMilliseconds),
  );

/**
 * Runs one enter or leave transition on an element and calls back when it is
 * over, whether it ran or not. Returns the cancellation of what it started.
 */
export type ElementTransition = (
  element: HTMLElement,
  kind: "enter" | "leave",
  done: () => void,
) => () => void;

/**
 * Builds the transition runner of one editor from the class name prefix its
 * stylesheet declares.
 *
 * `<Transition>` used to do this. Three things it did that a bare
 * `transitionend` listener does not, and all three are the reason this is a
 * function rather than one line:
 *
 * - **It ends even when the transition never starts.** `transitionend` does not
 *   fire for an element that is `display: none`, for a property that does not
 *   actually change, or for one that is removed halfway. The close path of both
 *   editors hangs off the callback - the focus returns to the trigger there and
 *   the row a deletion emptied is removed there - so a transition that never
 *   ends is a silent, intermittent defect rather than a missing animation.
 * - **It ends at once when there is nothing to animate.** The stylesheet turns
 *   the transition off under `prefers-reduced-motion`, and a visitor who asked
 *   for that must not wait out a timeout.
 * - **It can be cancelled.** Reopening an editor while it closes has to drop
 *   the pending leave, its classes and its callback.
 */
export const createElementTransition = (prefix: string): ElementTransition =>
  (element, kind, done): (() => void) => {
    const active = `${prefix}-${kind}-active`;
    // The stylesheet declares the two ends of the animation as "-enter-from"
    // and "-leave-to"; "-enter-to" and "-leave-from" are the names of the
    // resting state and carry no declarations, so they are not applied.
    const offset = kind === "enter" ? `${prefix}-enter-from` : `${prefix}-leave-to`;

    element.classList.add(active);
    if (kind === "enter") {
      element.classList.add(offset);
    }

    const duration = transitionDuration(element);
    const clear = (): void => {
      element.classList.remove(active, offset);
    };
    if (duration === 0) {
      clear();
      done();

      return (): void => undefined;
    }

    const finish = (): void => {
      cancel();
      clear();
      done();
    };
    const onTransitionEnd = (event: Event): void => {
      if (event.target === element) {
        finish();
      }
    };
    // Reads "timeout" and "frame" below it. Neither can be read before it is
    // written: nothing calls this until the browser has been given the chance
    // to run one of the two, and both are handed out by then.
    const cancel = (): void => {
      globalThis.cancelAnimationFrame(frame);
      globalThis.clearTimeout(timeout);
      element.removeEventListener("transitionend", onTransitionEnd);
    };

    element.addEventListener("transitionend", onTransitionEnd);
    const timeout = globalThis.setTimeout(finish, duration + transitionTimeoutSlack);
    // Two frames, never one: a single one can be flushed
    // together with the style that was just written, and the browser then never
    // sees the state the animation starts from - so nothing animates and the
    // timeout below is what ends the transition.
    let frame = globalThis.requestAnimationFrame((): void => {
      frame = globalThis.requestAnimationFrame((): void => {
        if (kind === "enter") {
          element.classList.remove(offset);
        } else {
          element.classList.add(offset);
        }
      });
    });

    return (): void => {
      cancel();
      clear();
    };
  };
