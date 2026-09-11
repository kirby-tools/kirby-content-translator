export const PLUGIN_CONTEXT_API_ROUTE = "__content-translator__/context";
export const MODEL_FIELDS_API_ROUTE = "__content-translator__/model-fields";
export const TRANSLATE_BATCH_API_ROUTE =
  "__content-translator__/translate-batch";
export const BATCH_STATUS_API_ROUTE = "__content-translator__/batch-status";
export const BATCH_WRITE_API_ROUTE = "__content-translator__/batch-write";

export const STORAGE_KEY_PREFIX = "kirby$content-translator$";

export const STRATEGY_NAMES = ["deepl", "ai"] as const;

export const DEFAULT_FIELD_TYPES = [
  "blocks",
  "layout",
  "list",
  "object",
  "structure",
  "tags",
  "text",
  "textarea",
  "writer",
  // Community plugins
  "markdown",
  "table",
] as const;

export const DEFAULT_BATCH_TRANSLATION_CONCURRENCY = 2;
