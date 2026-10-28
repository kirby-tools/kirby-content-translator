// @vitest-environment happy-dom
import { flushPromises, mount } from "@vue/test-utils";
import { expect, it, vi } from "vitest";
import { defineComponent, h, ref } from "vue";
import ContentTranslatorField from "../../../src/panel/components/Fields/ContentTranslatorField.vue";

const { panel, initialize } = vi.hoisted(() => ({
  panel: { multilang: true, t: (key: string) => key, error: vi.fn() },
  initialize: vi.fn(async () => {}),
}));

vi.mock("kirbyuse", () => ({
  usePanel: () => panel,
}));

vi.mock("@kirby-tools/licensing/components", () => ({
  LicensingButtonGroup: {},
}));

vi.mock("../../../src/panel/composables/plugin", () => ({
  usePluginContext: async () => ({ config: {} }),
}));

vi.mock("../../../src/panel/composables/translator-actions", () => ({
  useContentTranslatorActions: () => ({
    initialize,
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

it("hands a failed setup to panel.error", async () => {
  const error = new Error("Setup failed");
  initialize.mockRejectedValueOnce(error);

  mountField();
  await flushPromises();

  expect(panel.error).toHaveBeenCalledWith(error);
});
