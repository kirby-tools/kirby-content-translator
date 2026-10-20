import { isKirby5 } from "kirbyuse";
import ContentTranslator from "./components/Sections/ContentTranslator.vue";
import ContentTranslatorDropdownButton from "./components/ViewButtons/ContentTranslatorDropdownButton.vue";
import LanguagesViewWrapper from "./components/Views/LanguagesViewWrapper";
import { icons } from "./config/icons";
import { legacyViewButtonMixin } from "./legacy";

import "virtual:uno.css";

window.panel.plugin("johannschopplich/content-translator", {
  sections: {
    "content-translator": ContentTranslator,
  },
  viewButtons: {
    "content-translator": ContentTranslatorDropdownButton,
  },
  components: {
    ...(isKirby5() && {
      "k-languages-view": LanguagesViewWrapper,
    }),
  },
  icons,
  use: {
    legacyViewButtonSupport: legacyViewButtonMixin,
  },
});
