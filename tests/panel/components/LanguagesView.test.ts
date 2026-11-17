// @vitest-environment happy-dom
import type { Component } from "vue";
import { mount } from "@vue/test-utils";
import { expect, it, vi } from "vitest";
import { defineComponent, h, renderSlot } from "vue";
import LanguagesView from "../../../src/panel/components/LanguagesView";
import KirbyLanguagesView from "./fixtures/KirbyLanguagesView.vue";

vi.mock("../../../src/panel/components/Coverage.vue", () => ({
  default: { render: () => h("div", { class: "coverage" }) },
}));

// Renders its slot through `renderSlot`, as Kirby's compiled `<slot />` does.
const KPanelInside = defineComponent({
  render() {
    return h("main", [renderSlot(this.$slots, "default")]);
  },
});

it("renders the languages once they arrive", async () => {
  Object.assign(LanguagesView, { extends: KirbyLanguagesView });

  const wrapper = mount(LanguagesView as Component, {
    props: { languages: [] },
    global: { components: { "k-panel-inside": KPanelInside } },
  });

  await wrapper.setProps({ languages: ["en", "de"] });

  expect(wrapper.findAll(".languages li")).toHaveLength(2);
  expect(wrapper.findAll(".coverage")).toHaveLength(1);
});
