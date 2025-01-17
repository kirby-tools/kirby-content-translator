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

      const button = new Vue({
        render: (h) => h(ContentTranslatorDropdownButton),
      }).$mount();

      const languagesDropdown = buttonGroup.$el.querySelector(
        ".k-languages-dropdown",
      );
      if (!languagesDropdown) return;

      languagesDropdown.after(button.$el);
      this.$forceUpdate();
    },
  });
}
