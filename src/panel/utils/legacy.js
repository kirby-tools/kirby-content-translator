import { isKirby5 } from "kirbyuse";
import DropdownButton from "../components/ViewButtons/DropdownButton.vue";

export function legacyViewButtonMixin(Vue) {
  if (isKirby5()) {
    return;
  }

  Vue.mixin({
    mounted() {
      if (!window.panel.multilang) return;
      if (this.$options.name !== "k-header") return;

      const buttonGroup = this.$children.find(
        (child) => child.$options.name === "k-button-group",
      );
      if (!buttonGroup) return;

      const button = new Vue({
        render: (h) => h(DropdownButton),
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
