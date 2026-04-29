<script lang="ts">
import type { Component } from "vue";
import Vue from "vue";
import TranslationStatusView from "./TranslationStatusView.vue";

// TODO: Replace with native `<Teleport>` in Kirby 6 and Vue 3
function mountChild(
  parent: InstanceType<typeof Vue>,
  component: Component,
  container: Element,
): () => void {
  const vm = new Vue({
    parent,
    render: (h) => h(component),
  });

  vm.$mount();
  container.appendChild(vm.$el);

  return () => {
    vm.$destroy();
    vm.$el?.remove();
  };
}

let cleanup: (() => void) | null = null;

export default {
  // @ts-expect-error: Kirby component extension
  extends: "k-languages-view",
  mounted() {
    const main = this.$el.querySelector(".k-panel-main");
    if (!main) return;

    cleanup = mountChild(
      this as InstanceType<typeof Vue>,
      TranslationStatusView,
      main,
    );
  },
  beforeDestroy() {
    cleanup?.();
  },
};
</script>
