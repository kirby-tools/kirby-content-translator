import type { PanelLanguageInfo } from "kirby-types";
import type { BatchStatusResponse } from "../types";
import type { BatchModel, BatchModelSettings, HeldBackOutcome } from "./batch";

export interface BatchCandidate extends Omit<
  BatchModel,
  "targets" | "settings"
> {
  status: BatchStatusResponse;
}

/**
 * Decides which selected languages of the host and of each cascaded model a
 * batch translation runs, and holds back the rest.
 */
export function planBatchRun(
  host: BatchCandidate | undefined,
  cascade: BatchCandidate[],
  {
    selectedLanguages,
    defaultLanguageCode,
    settings,
    globalFieldLists,
  }: {
    selectedLanguages: PanelLanguageInfo[];
    defaultLanguageCode: string;
    settings: BatchModelSettings;
    /**
     * The `includeFields` and `excludeFields` of the plugin config. A cascaded
     * model gets them in place of the host's, which may name fields of the
     * host's blueprint.
     */
    globalFieldLists: Pick<
      BatchModelSettings,
      "includeFields" | "excludeFields"
    >;
  },
): BatchModel[] {
  return [
    // The host is absent from a single-language translation, which leaves its
    // fields in the form for review.
    ...(host ? [planModel(host, settings)] : []),
    ...cascade.map((candidate) => {
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

      return planModel(
        candidate,
        {
          ...settings,
          ...globalFieldLists,
          isTitleTranslationEnabled:
            settings.isTitleTranslationEnabled && status.isTitleChangeAllowed,
          isSlugTranslationEnabled: false,
        },
        heldBackOutcome,
      );
    }),
  ];

  function planModel(
    { status, ...candidate }: BatchCandidate,
    modelSettings: BatchModelSettings,
    modelHeldBackOutcome?: HeldBackOutcome,
  ): BatchModel {
    return {
      ...candidate,
      settings: modelSettings,
      targets: selectedLanguages.map((language) => {
        const heldBackOutcome =
          modelHeldBackOutcome ??
          (status.languagesWithUnsavedChanges.includes(language.code)
            ? ({ status: "unsavedChanges" } as const)
            : undefined);

        return heldBackOutcome ? { language, heldBackOutcome } : { language };
      }),
    };
  }
}
