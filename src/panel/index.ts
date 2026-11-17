import Field from "./components/Field.vue";
import LanguagesView from "./components/LanguagesView";
import ViewButton from "./components/ViewButton.vue";
import { icons } from "./config/icons";

import "virtual:uno.css";

window.panel.plugin("johannschopplich/content-translator", {
  fields: {
    "content-translator": Field,
  },
  viewButtons: {
    "content-translator": ViewButton,
  },
  components: {
    "k-languages-view": LanguagesView,
  },
  icons,
});
