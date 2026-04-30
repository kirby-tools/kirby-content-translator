/* eslint-disable ts/ban-ts-comment */
import { isKirby5 } from "kirbyuse";
// @ts-ignore - Vue component
import ContentTranslator from "./components/Sections/ContentTranslator.vue";
// @ts-ignore - Vue component
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
