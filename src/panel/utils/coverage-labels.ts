import type { CoverageLabels, Translate } from "../types";
import { translatePlural } from "./i18n";

export function createCoverageLabels(t: Translate): CoverageLabels {
  return {
    title: t("johannschopplich.content-translator.coverage.title"),
    pagesToTranslate: t(
      "johannschopplich.content-translator.coverage.pagesToTranslate",
    ),
    allTranslated: t(
      "johannschopplich.content-translator.coverage.allTranslated",
    ),
    pagesIncomplete: (count) =>
      translatePlural(
        t,
        "johannschopplich.content-translator.coverage.pagesIncomplete",
        { count },
        count,
      ),
  };
}
