// @vitest-environment happy-dom
import { flushPromises, mount } from "@vue/test-utils";
import { expect, it, vi } from "vitest";
import { defineComponent, h, ref } from "vue";
import ContentTranslatorDropdownButton from "../../../src/panel/components/ViewButtons/ContentTranslatorDropdownButton.vue";

const initialize = vi.hoisted(() => vi.fn(async () => {}));

vi.mock("kirbyuse", () => ({
  usePanel: () => ({
    multilang: true,
    t: (key: string) => key,
    view: { component: "k-site-view" },
  }),
}));

vi.mock("@kirby-tools/licensing/components", () => ({
  LicensingDropdownItems: {},
}));

vi.mock("../../../src/panel/composables/plugin", () => ({
  usePluginContext: async () => ({ licenseStatus: "active" }),
}));

vi.mock("../../../src/panel/composables/translation", () => ({
  useTranslationState: () => ({ isTranslating: ref(false) }),
}));

vi.mock("../../../src/panel/composables/translator-actions", () => ({
  useContentTranslatorActions: () => ({
    initialize,
    actions: ref([]),
    missingStrategyMessage: ref(),
    run: vi.fn(),
  }),
}));

const KButton = defineComponent({
  emits: ["click"],
  setup:
    (_, { emit }) =>
    () =>
      h("button", { onClick: () => emit("click") }),
});

const KDropdown = defineComponent({
  setup: (_, { expose }) => {
    expose({ toggle: () => {} });
    return () => null;
  },
});

function mountButton(props: Record<string, unknown> = {}) {
  return mount(ContentTranslatorDropdownButton, {
    props,
    global: { stubs: { "k-button": KButton, "k-dropdown": KDropdown } },
  });
}

async function toggle(wrapper: ReturnType<typeof mountButton>) {
  await wrapper.find("button").trigger("click");
  await flushPromises();
}

it("reads the empty title Kirby sends for false as a string", () => {
  const wrapper = mountButton({ title: "" });

  expect(wrapper.props("title")).toBe("");
});

it("calls initialize once from the first click and not at mount", async () => {
  const wrapper = mountButton();
  await flushPromises();

  expect(initialize).not.toHaveBeenCalled();

  await toggle(wrapper);
  await toggle(wrapper);

  expect(initialize).toHaveBeenCalledTimes(1);
});
