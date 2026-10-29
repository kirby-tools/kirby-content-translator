import type { ComponentPublicInstance, Slot, VNode } from "vue";
import { isLocalDev } from "kirbyuse";
import { h } from "vue";
import TranslationCoverageView from "./TranslationCoverageView.vue";

type Render = (this: ComponentPublicInstance, ...args: unknown[]) => VNode;

// TODO: Fill the view's `after` slot instead once getkirby/kirby#8704 adds it.
const LanguagesView = {
  // Kirby's plugin loader replaces the name with the registered component.
  extends: "k-languages-view" as string | { render: Render },
  render(this: ComponentPublicInstance, ...args: unknown[]): VNode {
    // This component's own parent, not `this.$options.extends`, which points
    // back here when another component extends this one.
    const parent = LanguagesView.extends as { render: Render };
    const vnode = parent.render.apply(this, args);
    const slots = vnode.children as Record<string, unknown> | null;
    const renderDefault = slots?.default;

    if (typeof renderDefault === "function") {
      // Without the compiler's `_` flag, Vue diffs the slot in full. With it,
      // Vue patches only the nodes the slot tracked, and the compiled content
      // tracks none when this wrapper calls it in place of `renderSlot`, so
      // none of the view's content would update.
      const { _: _compiledFlag, ...namedSlots } = slots!;
      vnode.children = {
        ...namedSlots,
        default: (...slotArgs: unknown[]) => [
          ...(renderDefault as Slot)(...slotArgs),
          h(TranslationCoverageView),
        ],
      };
    } else if (isLocalDev()) {
      console.warn(
        "[kirby-content-translator] Cannot inject TranslationCoverageView into k-languages-view: parent render shape changed.",
      );
    }

    return vnode;
  },
};

export default LanguagesView;
