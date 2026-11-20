// @vitest-environment happy-dom
import { mount } from "@vue/test-utils";
import { expect, it, vi } from "vitest";
import { ref } from "vue";
import Field from "../../../src/panel/components/Field.vue";

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

it("passes disabled on to k-field", () => {
  const wrapper = mount(Field, {
    props: {
      name: "translator",
      label: "Content Translator",
      licenseStatus: "active",
      licenseDialog: "",
      disabled: true,
    },
    global: { stubs: { "k-field": true, "k-box": true, "k-text": true } },
  });

  expect(wrapper.find("k-field-stub").attributes("disabled")).toBe("true");
});
