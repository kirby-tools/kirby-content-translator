/* eslint-disable ts/ban-ts-comment */
import type { ComponentPublicInstance, PluginFunction } from "vue";
import { isKirby5 } from "kirbyuse";
// @ts-ignore - Vue component
import ContentTranslatorDropdownButton from "../components/ViewButtons/ContentTranslatorDropdownButton.vue";

export const legacyViewButtonMixin: PluginFunction<any> = (Vue) => {
  if (isKirby5()) {
    return;
  }

  let buttonComponent: ComponentPublicInstance | undefined;

  Vue.mixin({
    mounted(this: ComponentPublicInstance) {
      if (this.$options.name !== "k-header") return;
      if (!window.panel.multilang) return;

      const buttonGroup = this.$children.find(
        (child) => child.$options.name === "k-button-group",
      );
      if (!buttonGroup) return;

      const languagesDropdown = buttonGroup.$el.querySelector(
        ".k-languages-dropdown",
      );
      if (!languagesDropdown) return;

      const ButtonConstructor = Vue.extend(ContentTranslatorDropdownButton);
      buttonComponent = new ButtonConstructor({ parent: this as any });
      buttonComponent.$mount();

      languagesDropdown.after(buttonComponent.$el);
    },
    beforeDestroy(this: ComponentPublicInstance) {
      if (this.$options.name !== "k-header") return;
      if (!window.panel.multilang) return;

      if (buttonComponent) {
        buttonComponent.$destroy();
        buttonComponent = undefined;
      }
    },
  });
};
