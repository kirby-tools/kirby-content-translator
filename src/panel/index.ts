/* eslint-disable ts/ban-ts-comment */
// @ts-ignore - Vue component
import ContentTranslator from "./components/Sections/ContentTranslator.vue";
// @ts-ignore - Vue component
import ContentTranslatorDropdownButton from "./components/ViewButtons/ContentTranslatorDropdownButton.vue";
import { icons } from "./config/icons";
import { legacyViewButtonMixin } from "./utils/legacy";

window.panel.plugin("johannschopplich/content-translator", {
  sections: {
    "content-translator": ContentTranslator,
  },
  viewButtons: {
    "content-translator": ContentTranslatorDropdownButton,
  },
  icons,
  use: {
    legacyViewButtonSupport: legacyViewButtonMixin,
  },
});
