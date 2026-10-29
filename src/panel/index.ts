import ContentTranslatorField from "./components/Fields/ContentTranslatorField.vue";
import ContentTranslatorDropdownButton from "./components/ViewButtons/ContentTranslatorDropdownButton.vue";
import LanguagesView from "./components/Views/LanguagesView";
import { icons } from "./config/icons";

import "virtual:uno.css";

window.panel.plugin("johannschopplich/content-translator", {
  fields: {
    "content-translator": ContentTranslatorField,
  },
  viewButtons: {
    "content-translator": ContentTranslatorDropdownButton,
  },
  components: {
    "k-languages-view": LanguagesView,
  },
  icons,
});
