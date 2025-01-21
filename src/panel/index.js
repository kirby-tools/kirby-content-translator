import ContentTranslator from "./components/Sections/ContentTranslator.vue";
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
  use: [legacyViewButtonMixin],
});
