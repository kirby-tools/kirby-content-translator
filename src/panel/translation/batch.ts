import type {
  KirbyFieldProps,
  PanelLanguage,
  PanelLanguageInfo,
  PanelModelData,
} from "kirby-types";
import type { BatchWriteRequest, BatchWriteResponse } from "../types";
import type { ContentTranslationResult, TranslationStrategy } from "./types";
import pAll from "p-all";
import { filterEligibleContent } from "../utils/filter";
import { isFileModelPath, isSiteModelPath } from "../utils/model-path";
import { translateContent } from "./index";
import { planBatchLanguageTranslation } from "./plan";
import { mergeTranslationResults, reportRejections } from "./result";
import { translateTitle } from "./text";

export interface BatchModel {
  /** Panel API path, such as `pages/notes+exploring` or `site`. */
  path: string;
  defaultLanguageData: PanelModelData;
  fields: Record<string, KirbyFieldProps>;
}

export interface BatchSettings {
  fieldTypes: string[];
  includeFields: string[];
  excludeFields: string[];
  kirbyTags: Record<string, string[]>;
  homePageId?: string;
  errorPageId?: string;
  isTitleTranslationEnabled: boolean;
  isSlugTranslationEnabled: boolean;
  concurrency: number;
}

export type BatchOutcome = {
  model: BatchModel;
  language: PanelLanguageInfo | PanelLanguage;
} & (
  | { status: "failed"; message: string }
  | { status: "unsavedChanges" }
  | { status: "locked"; lockedBy: string }
  | { status: "notStarted"; lockedBy: string }
  | ({ status: "saved"; result: ContentTranslationResult } & Pick<
      Extract<BatchWriteResponse, { status: "saved" }>,
      "invalidFields" | "titleError" | "slugError"
    >)
);

/**
 * Translates every model into each target language and saves each through
 * `write`, returning one outcome per model and target language: model by
 * model in input order, and within a model language by language.
 */
export async function runBatchTranslation(
  models: BatchModel[],
  targetLanguages: (PanelLanguageInfo | PanelLanguage)[],
  {
    sourceLanguage,
    settings,
    strategy,
    write,
    onProgress,
  }: {
    sourceLanguage: PanelLanguageInfo | PanelLanguage;
    settings: BatchSettings;
    strategy: TranslationStrategy;
    write: (request: BatchWriteRequest) => Promise<BatchWriteResponse>;
    onProgress?: (completed: number, total: number) => void;
  },
): Promise<BatchOutcome[]> {
  const pairs = models.flatMap((model) =>
    targetLanguages.map((language) => ({ model, language })),
  );
  const lockedByPath = new Map<string, string>();
  let completed = 0;

  // A language of a model is isolated so one dead provider call cannot discard
  // what is already saved or stop what is still queued. A lock stops the
  // remaining languages of its model instead: Kirby refuses every further
  // write to that model while the lock holds.
  return await pAll(
    pairs.map((pair) => async (): Promise<BatchOutcome> => {
      const lockedBy = lockedByPath.get(pair.model.path);

      if (lockedBy !== undefined) {
        return { ...pair, status: "notStarted", lockedBy };
      }

      try {
        const outcome = await translatePair(pair);
        if (outcome.status === "locked") {
          lockedByPath.set(pair.model.path, outcome.lockedBy);
        }
        return outcome;
      } catch (error) {
        console.error(
          `Failed to translate into "${pair.language.code}":`,
          error,
        );
        return {
          ...pair,
          status: "failed",
          message: error instanceof Error ? error.message : String(error),
        };
      } finally {
        onProgress?.(++completed, pairs.length);
      }
    }),
    { concurrency: settings.concurrency },
  );

  async function translatePair(
    pair: Pick<BatchOutcome, "model" | "language">,
  ): Promise<BatchOutcome> {
    const { model, language: targetLanguage } = pair;

    const eligibleContent = filterEligibleContent(
      model.defaultLanguageData.content,
      {
        fields: model.fields,
        fieldTypes: settings.fieldTypes,
        includeFields: settings.includeFields,
        excludeFields: settings.excludeFields,
      },
    );

    const contentCopy = JSON.parse(JSON.stringify(eligibleContent));

    const contentResult = await translateContent(contentCopy, {
      strategy,
      sourceLanguage,
      targetLanguage,
      fieldTypes: settings.fieldTypes,
      includeFields: settings.includeFields,
      excludeFields: settings.excludeFields,
      kirbyTags: settings.kirbyTags,
      fields: model.fields,
    });

    reportRejections(contentResult, targetLanguage);

    const plan = planBatchLanguageTranslation({
      isHomePage: model.defaultLanguageData.id === settings.homePageId,
      isErrorPage: model.defaultLanguageData.id === settings.errorPageId,
      isFileModel: isFileModelPath(model.path),
      isSiteModel: isSiteModelPath(model.path),
      isTitleTranslationEnabled: settings.isTitleTranslationEnabled,
      isSlugTranslationEnabled: settings.isSlugTranslationEnabled,
      isTargetLanguageDefault: targetLanguage.default === true,
    });

    const languageResults = [contentResult];
    let title: string | undefined;

    if (plan.shouldRequestTitleTranslation) {
      const translatedTitle = await translateTitle(
        model.defaultLanguageData.title,
        { strategy, targetLanguage, sourceLanguage },
      );
      languageResults.push(translatedTitle.result);
      title = translatedTitle.text;
    }

    // The server builds the slug from the translated title, with the slug
    // rules of the target language.
    const response = await write({
      path: model.path,
      language: targetLanguage.code,
      content: contentCopy,
      title: plan.shouldPatchTitle ? title : undefined,
      slug: plan.shouldPatchSlug ? title : undefined,
    });

    if (response.status !== "saved") return { ...pair, ...response };

    return {
      ...pair,
      ...response,
      result: mergeTranslationResults(languageResults),
    };
  }
}
