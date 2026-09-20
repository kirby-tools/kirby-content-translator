import type { PanelLanguage } from "kirby-types";
import type { BatchModel } from "../../../src/panel/translation/batch";
import type { TranslationStrategy } from "../../../src/panel/translation/types";
import type {
  BatchWriteRequest,
  BatchWriteResponse,
} from "../../../src/panel/types";
import { describe, expect, it, vi } from "vitest";
import { runBatchTranslation } from "../../../src/panel/translation/batch";
import { field } from "../utils";

// The strategies behind the `translation` barrel import `kirbyuse`, which reads
// `window` on load.
vi.mock("kirbyuse", async () => {
  const { baseKirbyuseMock } = await import("../helpers/mock-kirbyuse");
  return baseKirbyuseMock();
});

const ENGLISH = { code: "en", name: "English", default: true } as PanelLanguage;
const GERMAN = { code: "de", name: "Deutsch" } as PanelLanguage;
const FRENCH = { code: "fr", name: "Français" } as PanelLanguage;

const suffixStrategy: TranslationStrategy = {
  execute: async (units, { targetLanguage }) =>
    units.map((unit) => `${unit.text} (${targetLanguage.code})`),
};

function createBatchModel(path: string, title: string): BatchModel {
  return {
    path,
    defaultLanguageData: { id: path, title, content: { text: "Hello" } },
    fields: { text: field({ type: "text", name: "text" }) },
  };
}

const NOTES_PAGE = createBatchModel("pages/notes", "Notes");
const COVER_FILE = createBatchModel("pages/notes/files/cover.jpg", "cover.jpg");

function createWrite(
  respond: (request: BatchWriteRequest) => BatchWriteResponse = () => ({
    status: "saved",
  }),
) {
  return vi.fn(async (request: BatchWriteRequest) => respond(request));
}

function createBatchOptions(
  write: (request: BatchWriteRequest) => Promise<BatchWriteResponse>,
  { isTitleTranslationEnabled = false, strategy = suffixStrategy } = {},
) {
  return {
    sourceLanguage: ENGLISH,
    settings: {
      fieldTypes: ["text"],
      includeFields: [],
      excludeFields: [],
      kirbyTags: {},
      isTitleTranslationEnabled,
      isSlugTranslationEnabled: false,
      concurrency: 1,
    },
    strategy,
    write,
  };
}

describe("runBatchTranslation", () => {
  it("writes every model into every target language under the model's path", async () => {
    const write = createWrite();

    await runBatchTranslation(
      [NOTES_PAGE, COVER_FILE],
      [GERMAN, FRENCH],
      createBatchOptions(write),
    );

    expect(write.mock.calls.map(([request]) => request)).toEqual([
      expect.objectContaining({
        path: "pages/notes",
        language: "de",
        content: { text: "Hello (de)" },
      }),
      expect.objectContaining({
        path: "pages/notes",
        language: "fr",
        content: { text: "Hello (fr)" },
      }),
      expect.objectContaining({
        path: "pages/notes/files/cover.jpg",
        language: "de",
        content: { text: "Hello (de)" },
      }),
      expect.objectContaining({
        path: "pages/notes/files/cover.jpg",
        language: "fr",
        content: { text: "Hello (fr)" },
      }),
    ]);
  });

  it("names the model and the target language in every outcome", async () => {
    const outcomes = await runBatchTranslation(
      [NOTES_PAGE, COVER_FILE],
      [GERMAN, FRENCH],
      createBatchOptions(createWrite()),
    );

    expect(
      outcomes.map(({ model, language, status }) => [model, language, status]),
    ).toEqual([
      [NOTES_PAGE, GERMAN, "saved"],
      [NOTES_PAGE, FRENCH, "saved"],
      [COVER_FILE, GERMAN, "saved"],
      [COVER_FILE, FRENCH, "saved"],
    ]);
  });

  it("stops only the locked model and keeps translating the others", async () => {
    const write = createWrite(({ path }) =>
      path === "pages/notes"
        ? { status: "locked", lockedBy: "Ada" }
        : { status: "saved" },
    );

    const outcomes = await runBatchTranslation(
      [NOTES_PAGE, COVER_FILE],
      [GERMAN, FRENCH],
      createBatchOptions(write),
    );

    expect(outcomes.map(({ status }) => status)).toEqual([
      "locked",
      "notStarted",
      "saved",
      "saved",
    ]);
    expect(write).toHaveBeenCalledTimes(3);
  });

  it("counts every model-language pair in the onProgress total", async () => {
    const onProgress = vi.fn();

    await runBatchTranslation([NOTES_PAGE, COVER_FILE], [GERMAN, FRENCH], {
      ...createBatchOptions(createWrite()),
      onProgress,
    });

    expect(onProgress.mock.calls).toEqual([
      [1, 4],
      [2, 4],
      [3, 4],
      [4, 4],
    ]);
  });

  it("writes the translated title of a page but never the title of a file", async () => {
    const write = createWrite();

    await runBatchTranslation(
      [NOTES_PAGE, COVER_FILE],
      [GERMAN],
      createBatchOptions(write, { isTitleTranslationEnabled: true }),
    );

    expect(write.mock.calls.map(([{ path, title }]) => [path, title])).toEqual([
      ["pages/notes", "Notes (de)"],
      ["pages/notes/files/cover.jpg", undefined],
    ]);
  });

  it("withholds a rejected title from the write and reports the rejection", async () => {
    const write = createWrite();
    const strategy: TranslationStrategy = {
      execute: async (units) =>
        units.map((unit) =>
          unit.fieldKey === "title"
            ? { reason: "missing translation" }
            : `${unit.text} (de)`,
        ),
    };

    const [outcome] = await runBatchTranslation(
      [NOTES_PAGE],
      [GERMAN],
      createBatchOptions(write, { isTitleTranslationEnabled: true, strategy }),
    );

    expect(write).toHaveBeenCalledWith(
      expect.objectContaining({
        content: { text: "Hello (de)" },
        title: undefined,
      }),
    );
    expect(outcome).toMatchObject({
      status: "saved",
      result: {
        translatableCount: 2,
        translatedCount: 1,
        rejections: [{ fieldKey: "title", reason: "missing translation" }],
      },
    });
  });
});
