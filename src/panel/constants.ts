export const PLUGIN_CONTEXT_API_ROUTE = "__content-translator__/context";
export const MODEL_FIELDS_API_ROUTE = "__content-translator__/model-fields";
export const TRANSLATE_API_ROUTE = "__content-translator__/translate";
export const TRANSLATE_BATCH_API_ROUTE =
  "__content-translator__/translate-batch";
export const TRANSLATE_KIRBYTEXT_API_ROUTE =
  "__content-translator__/translate-kirbytext";

export const STORAGE_KEY_PREFIX = "kirby$content-translator$";

export const TRANSLATION_PROVIDERS = ["deepl", "ai"] as const;

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
