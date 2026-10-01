import type { NotificationTheme } from "kirby-types";
import type { BatchOutcome } from "../translation/batch";
import type { LabelField } from "../translation/report";
import type { ContentTranslationResult } from "../translation/types";
import { usePanel } from "kirbyuse";
import {
  describeBatchOutcomes,
  listKeptSourceFields,
} from "../translation/report";
import { mergeTranslationResults } from "../translation/result";
import { formatList, translatePlural } from "../utils/i18n";

/**
 * Long enough that a notification stays until something replaces it. A falsy
 * timeout would be coerced back to four seconds, and `type: "error"` – the
 * other way out of that coercion – keeps the view shell from rendering it.
 */
const PERSISTENT_TIMEOUT = 60 * 60 * 1000;

interface RejectionMessageKeys {
  partiallyTranslated: string;
  noneTranslated: string;
  batchPartiallyTranslated: string;
  keptSource: string;
}

const CONTENT_REJECTION_MESSAGE_KEYS: RejectionMessageKeys = {
  partiallyTranslated:
    "johannschopplich.content-translator.notification.partiallyTranslated",
  noneTranslated:
    "johannschopplich.content-translator.notification.noSegmentTranslated",
  batchPartiallyTranslated:
    "johannschopplich.content-translator.notification.batchPartiallyTranslated",
  keptSource: "johannschopplich.content-translator.batchReport.keptSource",
};

export function useTranslationNotifications(
  rejectionMessageKeys = CONTENT_REJECTION_MESSAGE_KEYS,
) {
  const panel = usePanel();

  function notifyProgress(message: string) {
    panel.notification.open({
      message,
      icon: "loader",
      theme: "info",
      timeout: PERSISTENT_TIMEOUT,
    });
  }

  function notifyPartialTranslation(message: string) {
    panel.notification.open({
      message,
      icon: "alert",
      theme: "notice" as NotificationTheme,
      timeout: PERSISTENT_TIMEOUT,
    });
  }

  // Only one notification is visible at a time, so the most specific outcome wins.
  function notifyTranslationResult(
    result: ContentTranslationResult,
    {
      successMessage,
      nothingToTranslateMessage,
      labelField,
    }: {
      successMessage: string;
      nothingToTranslateMessage: string;
      labelField: LabelField;
    },
  ) {
    if (result.translatableCount === 0) {
      panel.notification.open({
        message: nothingToTranslateMessage,
        icon: "info",
        theme: "info",
      });
      return;
    }

    const untranslatedCount = result.translatableCount - result.translatedCount;

    if (untranslatedCount === 0) {
      panel.notification.success(successMessage);
      return;
    }

    if (result.translatedCount === 0) {
      // Not `notification.error`, which in a view also opens Kirby's blocking
      // error dialog.
      panel.notification.open({
        message: panel.t(rejectionMessageKeys.noneTranslated, {
          total: result.translatableCount,
        }),
        icon: "alert",
        theme: "negative",
        timeout: PERSISTENT_TIMEOUT,
      });
      return;
    }

    notifyPartialTranslation(
      translatePlural(
        panel.t,
        rejectionMessageKeys.partiallyTranslated,
        {
          untranslated: untranslatedCount,
          total: result.translatableCount,
          fields: listKeptSourceFields(result.rejections, {
            labelField,
            t: panel.t,
          }),
        },
        untranslatedCount,
      ),
    );
  }

  /**
   * Notifies per language rather than summing across them: one total would
   * fold a language at 0 of 10 into "10 of 20 kept their source text" and hide
   * which language went wrong.
   */
  function notifyBatchTranslationResult<Outcome extends BatchOutcome>(
    outcomes: Outcome[],
    {
      labelOutcome,
      labelField,
      successMessage,
      nothingToTranslateMessage,
    }: {
      labelOutcome: (outcome: Outcome) => string;
      labelField: LabelField<Outcome>;
      successMessage: string;
      nothingToTranslateMessage: string;
    },
  ) {
    const savedResults = outcomes.flatMap((outcome) =>
      outcome.status === "saved" ? [outcome.result] : [],
    );
    const languagesWithKeptSource = outcomes.flatMap((outcome) => {
      if (
        outcome.status !== "saved" ||
        outcome.result.translatedCount === outcome.result.translatableCount
      ) {
        return [];
      }

      const keptSourceFields = listKeptSourceFields(outcome.result.rejections, {
        labelField: (fieldKey) => labelField(fieldKey, outcome),
        t: panel.t,
      });

      return [`${labelOutcome(outcome)} (${keptSourceFields})`];
    });

    if (languagesWithKeptSource.length === 0) {
      notifyTranslationResult(mergeTranslationResults(savedResults), {
        successMessage,
        nothingToTranslateMessage,
        // No language kept a source text, so no field is named.
        labelField: (fieldKey) => fieldKey ?? "",
      });
      return;
    }

    notifyPartialTranslation(
      panel.t(rejectionMessageKeys.batchPartiallyTranslated, {
        languages: formatList(languagesWithKeptSource, panel.translation.code),
      }),
    );
  }

  function openBatchReport<Outcome extends BatchOutcome>(
    outcomes: Outcome[],
    {
      labelOutcome,
      labelField,
      messageKey,
      onClose,
    }: {
      labelOutcome: (outcome: Outcome) => string;
      labelField: LabelField<Outcome>;
      messageKey: string;
      onClose?: () => void;
    },
  ) {
    panel.dialog.open({
      component: "k-error-dialog",
      props: {
        message: translatePlural(
          panel.t,
          messageKey,
          {
            saved: outcomes.filter(({ status }) => status === "saved").length,
            total: outcomes.length,
          },
          outcomes.length,
        ),
        details: describeBatchOutcomes(outcomes, {
          labelOutcome,
          labelField,
          keptSourceKey: rejectionMessageKeys.keptSource,
          t: panel.t,
        }),
      },
      on: { close: onClose },
    });
  }

  return {
    notifyProgress,
    notifyTranslationResult,
    notifyBatchTranslationResult,
    openBatchReport,
  };
}
