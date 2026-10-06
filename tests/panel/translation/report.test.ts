import type { PanelLanguageInfo } from "kirby-types";
import type { BatchOutcome } from "../../../src/panel/translation/batch";
import { describe, expect, it } from "vitest";
import { describeBatchOutcomes } from "../../../src/panel/translation/report";

const translateKey = (key: string, data?: Record<string, unknown>) =>
  data ? `${key} ${JSON.stringify(data)}` : key;

describe("describeBatchOutcomes", () => {
  it("names a field once for two rejections with the same reason", () => {
    const outcome = {
      language: { code: "de", name: "Deutsch" } as PanelLanguageInfo,
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
      labelField: () => "Text",
      keptSourceKey:
        "johannschopplich.content-translator.batchReport.keptSource",
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
