// @vitest-environment happy-dom
import { mount } from "@vue/test-utils";
import { expect, it, vi } from "vitest";
import { ref } from "vue";
import ContentTranslatorDropdownButton from "../../../src/panel/components/ViewButtons/ContentTranslatorDropdownButton.vue";

vi.mock("kirbyuse", () => ({
  usePanel: () => ({
    multilang: true,
    languages: [],
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
    initialize: async () => {},
    isInitialized: ref(false),
    missingStrategyMessage: ref(),
  }),
}));

it("reads the empty title Kirby sends for false as a string", () => {
  const wrapper = mount(ContentTranslatorDropdownButton, {
    props: { title: "" },
    global: { stubs: { "k-button": true, "k-dropdown": true } },
  });

  expect(wrapper.props("title")).toBe("");
});
