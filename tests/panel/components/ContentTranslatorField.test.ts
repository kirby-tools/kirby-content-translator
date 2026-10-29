// @vitest-environment happy-dom
import { mount } from "@vue/test-utils";
import { expect, it, vi } from "vitest";
import { defineComponent, h, ref } from "vue";
import ContentTranslatorField from "../../../src/panel/components/Fields/ContentTranslatorField.vue";

vi.mock("kirbyuse", () => ({
  usePanel: () => ({ multilang: true, t: (key: string) => key }),
}));

vi.mock("@kirby-tools/licensing/components", () => ({
  LicensingButtonGroup: {},
}));

vi.mock("../../../src/panel/composables/plugin", () => ({
  usePluginContext: async () => ({ config: {} }),
}));

vi.mock("../../../src/panel/composables/translator-actions", () => ({
  useContentTranslatorActions: () => ({
    initialize: async () => {},
    isInitialized: ref(false),
    actions: ref([]),
    hasDefaultLanguageInfo: ref(false),
    hasAnyStrategy: ref(true),
    missingStrategyMessage: ref(),
    run: vi.fn(),
  }),
}));

const KField = defineComponent({
  setup:
    (_, { slots }) =>
    () =>
      h("section", slots.default?.()),
});

function mountField(attrs: Record<string, unknown> = {}) {
  return mount(ContentTranslatorField, {
    props: {
      name: "translator",
      label: "Content Translator",
      licenseStatus: "active",
      licenseDialog: "",
    },
    attrs,
    global: { stubs: { "k-field": KField } },
  });
}

it("keeps disabled off k-field", () => {
  const wrapper = mountField({ disabled: true });

  expect(wrapper.find("section").attributes("disabled")).toBeUndefined();
});
