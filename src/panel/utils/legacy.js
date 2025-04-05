import { isKirby5 } from "kirbyuse";
import ContentTranslatorDropdownButton from "../components/ViewButtons/ContentTranslatorDropdownButton.vue";

export function legacyViewButtonMixin(Vue) {
  if (isKirby5()) {
    return;
  }

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
      const button = new ButtonConstructor({ parent: this });
      button.$mount();

      languagesDropdown.after(button.$el);
    },
  });
}
