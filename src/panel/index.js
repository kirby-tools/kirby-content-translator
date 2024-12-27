import ContentTranslator from "./components/Sections/ContentTranslator.vue";
import DropdownButton from "./components/ViewButtons/DropdownButton.vue";
import { icons } from "./config/icons";
import { legacyViewButtonMixin } from "./utils/legacy";
import "./index.css";

window.panel.plugin("johannschopplich/content-translator", {
  sections: {
    "content-translator": ContentTranslator,
  },
  viewButtons: {
    "content-translator": DropdownButton,
  },
  icons,
  use: [legacyViewButtonMixin],
});
