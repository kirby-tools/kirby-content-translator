import type { PanelLanguage } from "kirby-types";
import type { BatchOutcome } from "../../../src/panel/translation/batch";
import { describe, expect, it } from "vitest";
import { describeBatchOutcomes } from "../../../src/panel/translation/report";
import { field } from "../utils";

const translateKey = (key: string, data?: Record<string, unknown>) =>
  data ? `${key} ${JSON.stringify(data)}` : key;

describe("describeBatchOutcomes", () => {
  it("names a field once for two rejections with the same reason", () => {
    const outcome = {
      model: {
        fields: { text: field({ type: "text", name: "text", label: "Text" }) },
      },
      language: { code: "de", name: "Deutsch" } as PanelLanguage,
      status: "saved",
      result: {
        rejections: [
          { index: 0, fieldKey: "text", reason: "empty translation" },
          { index: 1, fieldKey: "text", reason: "empty translation" },
        ],
      },
    } as unknown as BatchOutcome;

    const [entry] = describeBatchOutcomes([outcome], {
      labelOutcome: ({ language }) => language.name,
      t: translateKey,
    });

    expect(entry).toEqual({
      label: "Deutsch",
      message: [
        'johannschopplich.content-translator.batchReport.keptSource {"field":"Text","reason":"johannschopplich.content-translator.rejection.emptyTranslation"}',
      ],
    });
  });
});
