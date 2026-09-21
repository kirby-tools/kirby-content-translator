import type { PanelLanguage, PanelLanguageInfo } from "kirby-types";
import type { BatchStatusResponse } from "../types";
import type { BatchModel, BatchModelSettings, BatchOutcome } from "./batch";

export interface BatchCandidate extends Omit<
  BatchModel,
  "targetLanguages" | "settings"
> {
  status: BatchStatusResponse;
}

type HeldBackOutcome =
  | { status: "unsavedChanges"; isDefaultLanguageUnsaved?: boolean }
  | { status: "locked"; lockedBy: string };

/**
 * Decides which target languages of the host and of each cascaded model a
 * batch translation runs, and holds back the rest as outcomes.
 */
export function planBatchRun(
  host: BatchCandidate | undefined,
  cascade: BatchCandidate[],
  {
    selectedLanguages,
    defaultLanguageCode,
    settings,
  }: {
    selectedLanguages: (PanelLanguageInfo | PanelLanguage)[];
    defaultLanguageCode: string;
    settings: BatchModelSettings;
  },
): { models: BatchModel[]; heldBack: BatchOutcome[] } {
  const models: BatchModel[] = [];
  const heldBack: BatchOutcome[] = [];

  // The host is absent from a single-language translation, which leaves its
  // fields in the form for review.
  if (host) planModel(host, settings);

  for (const candidate of cascade) {
    const { status } = candidate;
    let heldBackOutcome: HeldBackOutcome | undefined;

    if (status.lockedBy !== null) {
      heldBackOutcome = { status: "locked", lockedBy: status.lockedBy };
    } else if (
      status.languagesWithUnsavedChanges.includes(defaultLanguageCode)
    ) {
      // A cascaded model is translated from its saved default language, so
      // unsaved changes there would be missing from every translation.
      heldBackOutcome = {
        status: "unsavedChanges",
        isDefaultLanguageUnsaved: true,
      };
    }

    planModel(
      candidate,
      {
        ...settings,
        includeFields: [],
        excludeFields: [],
        isTitleTranslationEnabled:
          settings.isTitleTranslationEnabled && status.isTitleChangeAllowed,
        isSlugTranslationEnabled: false,
      },
      heldBackOutcome,
    );
  }

  return { models, heldBack };

  function planModel(
    { status, ...candidate }: BatchCandidate,
    modelSettings: BatchModelSettings,
    heldBackOutcome?: HeldBackOutcome,
  ) {
    const model: BatchModel = {
      ...candidate,
      settings: modelSettings,
      targetLanguages: heldBackOutcome
        ? []
        : selectedLanguages.filter(
            ({ code }) => !status.languagesWithUnsavedChanges.includes(code),
          ),
    };

    models.push(model);

    for (const language of selectedLanguages) {
      if (model.targetLanguages.includes(language)) continue;

      heldBack.push({
        model,
        language,
        ...(heldBackOutcome ?? { status: "unsavedChanges" }),
      });
    }
  }
}
