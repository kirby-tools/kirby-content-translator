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

export type BatchOutcome =
  | { status: "failed"; message: string }
  | { status: "unsavedChanges" }
  | { status: "locked"; lockedBy: string }
  | { status: "notStarted"; lockedBy: string }
  | ({ status: "saved"; result: ContentTranslationResult } & Pick<
      Extract<BatchWriteResponse, { status: "saved" }>,
      "invalidFields" | "titleError" | "slugError"
    >);

/**
 * Translates the model into each target language and saves every language
 * through `write`, returning one outcome per target language, in input order.
 */
export async function runBatchTranslation(
  model: BatchModel,
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
  let completed = 0;
  let lockedBy: string | undefined;

  // A language is isolated so one dead provider call cannot discard the
  // languages already saved or stop the ones still queued. A lock stops the
  // queue instead: Kirby refuses every further write while it holds.
  return await pAll(
    targetLanguages.map((targetLanguage) => async (): Promise<BatchOutcome> => {
      if (lockedBy !== undefined) {
        return { status: "notStarted", lockedBy };
      }

      try {
        const outcome = await translateIntoLanguage(targetLanguage);
        if (outcome.status === "locked") lockedBy = outcome.lockedBy;
        return outcome;
      } catch (error) {
        console.error(
          `Failed to translate into "${targetLanguage.code}":`,
          error,
        );
        return {
          status: "failed",
          message: error instanceof Error ? error.message : String(error),
        };
      } finally {
        onProgress?.(++completed, targetLanguages.length);
      }
    }),
    { concurrency: settings.concurrency },
  );

  async function translateIntoLanguage(
    targetLanguage: PanelLanguageInfo | PanelLanguage,
  ): Promise<BatchOutcome> {
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

    if (response.status !== "saved") return response;

    return { ...response, result: mergeTranslationResults(languageResults) };
  }
}
