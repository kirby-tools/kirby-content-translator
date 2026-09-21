import type { BatchModel, BatchOutcome } from "./batch";
import type { TranslationRejection } from "./types";
import { formatPlural } from "../utils/i18n";

type Translate = (key: string, data?: Record<string, unknown>) => string;

/** How many fields of one language a notice names before it counts the rest. */
const MAX_NAMED_FIELDS = 3;

/**
 * Keeps a language with nothing but kept source text in a notice, since it
 * was saved. A title whose translation failed is reported like failed
 * content.
 */
export function shouldReportBatchOutcome(outcome: BatchOutcome) {
  return (
    outcome.status !== "saved" ||
    outcome.result.rejections.some(
      ({ reason }) => reason === "request failed",
    ) ||
    Boolean(outcome.titleError) ||
    Boolean(outcome.slugError) ||
    Object.keys(outcome.invalidFields ?? {}).length > 0
  );
}

export function listKeptSourceFields(
  rejections: TranslationRejection[],
  { fields, t }: { fields: BatchModel["fields"] | undefined; t: Translate },
) {
  const uniqueLabels = [
    ...new Set(
      rejections.map(({ fieldKey }) => fieldLabel(fieldKey, { fields, t })),
    ),
  ];
  const namedLabels = uniqueLabels.slice(0, MAX_NAMED_FIELDS).join(", ");
  if (uniqueLabels.length <= MAX_NAMED_FIELDS) return namedLabels;

  const remainingCount = uniqueLabels.length - MAX_NAMED_FIELDS;

  return formatPlural(
    t("johannschopplich.content-translator.notification.andMore", {
      fields: namedLabels,
      count: remainingCount,
    }),
    remainingCount,
  );
}

export function describeBatchOutcomes(
  outcomes: BatchOutcome[],
  {
    labelOutcome,
    t,
  }: { labelOutcome: (outcome: BatchOutcome) => string; t: Translate },
) {
  return outcomes.flatMap((outcome) => {
    const lines = describeBatchOutcome(outcome, t);
    return lines.length > 0
      ? [{ label: labelOutcome(outcome), message: lines }]
      : [];
  });
}

function describeBatchOutcome(outcome: BatchOutcome, t: Translate): string[] {
  const reportLine = (key: string, data?: Record<string, unknown>) =>
    t(`johannschopplich.content-translator.batchReport.${key}`, data);

  if (outcome.status === "failed") {
    return [reportLine("failed", { message: outcome.message })];
  }

  if (outcome.status === "unsavedChanges") {
    return [
      reportLine(
        outcome.isDefaultLanguageUnsaved
          ? "unsavedDefaultLanguageChanges"
          : "unsavedChanges",
      ),
    ];
  }

  if (outcome.status === "locked") {
    return [reportLine("locked", { user: outcome.lockedBy })];
  }

  if (outcome.status === "notStarted") {
    return [reportLine("notStarted", { user: outcome.lockedBy })];
  }

  // A textarea or a structure yields several units per field, which would
  // otherwise name the same field once per unit.
  const lines = new Set<string>();

  for (const rejection of outcome.result.rejections) {
    lines.add(
      reportLine("keptSource", {
        field: fieldLabel(rejection.fieldKey, {
          fields: outcome.model.fields,
          t,
        }),
        reason: describeRejection(rejection, t),
      }),
    );
  }

  if (outcome.titleError) {
    lines.add(
      reportLine("notChanged", {
        field: t("title"),
        message: outcome.titleError,
      }),
    );
  }

  if (outcome.slugError) {
    lines.add(
      reportLine("notChanged", {
        field: t("slug"),
        message: outcome.slugError,
      }),
    );
  }

  for (const [name, { label, message }] of Object.entries(
    outcome.invalidFields ?? {},
  )) {
    for (const validationMessage of Object.values(message)) {
      lines.add(
        reportLine("invalidField", {
          field: label || name,
          message: validationMessage,
        }),
      );
    }
  }

  return [...lines];
}

function describeRejection(
  { reason, detail }: TranslationRejection,
  t: Translate,
) {
  switch (reason) {
    case "missing translation":
    case "non-string translation":
      return t(
        "johannschopplich.content-translator.rejection.missingTranslation",
      );
    case "empty translation":
      return t(
        "johannschopplich.content-translator.rejection.emptyTranslation",
      );
    case "placeholder mismatch":
      return t(
        "johannschopplich.content-translator.rejection.placeholderMismatch",
      );
    default:
      return detail ?? reason;
  }
}

/**
 * Names a unit's field by the label of its top-level field, which a nested
 * unit's key leads with.
 */
function fieldLabel(
  fieldKey = "",
  { fields, t }: { fields: BatchModel["fields"] | undefined; t: Translate },
) {
  const name = fieldKey.split(/[.[]/)[0]!;
  const label = fields?.[name]?.label;
  if (label) return label;
  return name === "title" ? t("title") : name;
}
