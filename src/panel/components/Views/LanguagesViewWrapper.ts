/* eslint-disable ts/ban-ts-comment */
import type Vue from "vue";
import type {
  ComponentOptions,
  CreateElement,
  VNode,
  VueConstructor,
} from "vue";
import { isLocalDev } from "kirbyuse";
// @ts-ignore - Vue component
import TranslationStatusView from "./TranslationStatusView.vue";

type ResolvedExtends = VueConstructor & { options: ComponentOptions<Vue> };

export default {
  extends: "k-languages-view", // Resolved by Kirby's plugin loader
  render(this: Vue, h: CreateElement): VNode {
    const parent = this.$options.extends as ResolvedExtends;
    const parentRender = parent.options.render as (
      this: Vue,
      h: CreateElement,
    ) => VNode;
    const vnode = parentRender.call(this, h);

    if (vnode.componentOptions?.children) {
      vnode.componentOptions.children.push(h(TranslationStatusView));
    } else if (isLocalDev()) {
      console.warn(
        "[kirby-content-translator] Cannot inject TranslationStatusView into k-languages-view: parent render shape changed.",
      );
    }

    return vnode;
  },
};
