// @vitest-environment happy-dom
import { shallowMount } from "@vue/test-utils";
import { expect, it, vi } from "vitest";
import { ref } from "vue";
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

vi.mock("../../../src/panel/composables/translation", () => ({
  useTranslationState: () => ({ isTranslating: ref(false) }),
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

it("keeps disabled off k-field", () => {
  const wrapper = shallowMount(ContentTranslatorField, {
    props: {
      name: "translator",
      label: "Content Translator",
      licenseStatus: "active",
      licenseDialog: "",
    },
    attrs: { disabled: true },
    global: { stubs: { "k-box": true, "k-text": true } },
  });

  expect(wrapper.attributes("disabled")).toBeUndefined();
});
