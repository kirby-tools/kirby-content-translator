import { isKirby5 } from "kirbyuse";
import ContentTranslatorDropdownButton from "../components/ViewButtons/ContentTranslatorDropdownButton.vue";

export function legacyViewButtonMixin(Vue) {
  if (isKirby5()) {
    return;
  }

  let buttonComponent;

  Vue.mixin({
    mounted() {
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
      buttonComponent = new ButtonConstructor({ parent: this });
      buttonComponent.$mount();

      languagesDropdown.after(buttonComponent.$el);
    },
    beforeDestroy() {
      if (this.$options.name !== "k-header") return;
      if (!window.panel.multilang) return;

      if (buttonComponent) {
        buttonComponent.$destroy();
        buttonComponent = undefined;
      }
    },
  });
}
