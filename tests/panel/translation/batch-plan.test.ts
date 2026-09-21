import type { PanelLanguage } from "kirby-types";
import type { BatchCandidate } from "../../../src/panel/translation/batch-plan";
import type { BatchStatusResponse } from "../../../src/panel/types";
import { describe, expect, it } from "vitest";
import { planBatchRun } from "../../../src/panel/translation/batch-plan";
import { field } from "../utils";

const GERMAN = { code: "de", name: "Deutsch" } as PanelLanguage;
const FRENCH = { code: "fr", name: "Français" } as PanelLanguage;

const HOST_SETTINGS = {
  fieldTypes: ["text"],
  includeFields: ["text"],
  excludeFields: ["notes"],
  kirbyTags: { link: ["text"] },
  isTitleTranslationEnabled: true,
  isSlugTranslationEnabled: true,
};

function createCandidate(
  path: string,
  status: Partial<BatchStatusResponse> = {},
): BatchCandidate {
  return {
    path,
    title: path,
    isHomePage: false,
    isErrorPage: false,
    defaultLanguageData: { id: path, title: path, content: {} },
    fields: { text: field({ type: "text", name: "text" }) },
    status: {
      isUpdateAllowed: true,
      isTitleChangeAllowed: true,
      lockedBy: null,
      languagesWithUnsavedChanges: [],
      ...status,
    },
  };
}

function createPlanOptions() {
  return {
    selectedLanguages: [GERMAN, FRENCH],
    defaultLanguageCode: "en",
    settings: HOST_SETTINGS,
  };
}

describe("planBatchRun", () => {
  it("holds back a host language with unsaved changes", () => {
    const host = createCandidate("pages/notes", {
      languagesWithUnsavedChanges: ["de"],
    });

    const models = planBatchRun(host, [], createPlanOptions());

    expect(models).toMatchObject([
      {
        path: "pages/notes",
        targets: [
          { language: GERMAN, heldBackOutcome: { status: "unsavedChanges" } },
          { language: FRENCH },
        ],
        settings: HOST_SETTINGS,
      },
    ]);
  });

  it("gives a cascaded model the fieldTypes and kirbyTags of the host", () => {
    const models = planBatchRun(
      createCandidate("pages/notes"),
      [createCandidate("pages/notes+intro")],
      createPlanOptions(),
    );

    expect(models[1]).toMatchObject({
      path: "pages/notes+intro",
      targets: [{ language: GERMAN }, { language: FRENCH }],
      settings: { fieldTypes: ["text"], kirbyTags: { link: ["text"] } },
    });
  });

  it("never passes the includeFields, excludeFields or isSlugTranslationEnabled of the host on to a cascaded model", () => {
    const models = planBatchRun(
      createCandidate("pages/notes"),
      [createCandidate("pages/notes+intro")],
      createPlanOptions(),
    );

    expect(models[1]!.settings).toMatchObject({
      includeFields: [],
      excludeFields: [],
      isSlugTranslationEnabled: false,
    });
  });

  it("never enables the title of a cascaded model with isTitleChangeAllowed false", () => {
    const models = planBatchRun(
      createCandidate("pages/notes"),
      [createCandidate("pages/notes+intro", { isTitleChangeAllowed: false })],
      createPlanOptions(),
    );

    expect(models[1]!.settings.isTitleTranslationEnabled).toBe(false);
  });

  it("holds back a cascaded model another user edits as locked in every language", () => {
    const models = planBatchRun(
      createCandidate("pages/notes"),
      [createCandidate("pages/notes+intro", { lockedBy: "Ada" })],
      createPlanOptions(),
    );

    expect(models[1]!.targets).toEqual([
      {
        language: GERMAN,
        heldBackOutcome: { status: "locked", lockedBy: "Ada" },
      },
      {
        language: FRENCH,
        heldBackOutcome: { status: "locked", lockedBy: "Ada" },
      },
    ]);
  });

  it("holds back every language of a cascaded model with unsaved changes in the default language", () => {
    const models = planBatchRun(
      createCandidate("pages/notes"),
      [
        createCandidate("pages/notes+intro", {
          languagesWithUnsavedChanges: ["en"],
        }),
      ],
      createPlanOptions(),
    );

    const heldBackOutcome = {
      status: "unsavedChanges",
      isDefaultLanguageUnsaved: true,
    };
    expect(models[1]!.targets).toEqual([
      { language: GERMAN, heldBackOutcome },
      { language: FRENCH, heldBackOutcome },
    ]);
  });

  it("keeps translating a host with unsaved changes in the default language", () => {
    const models = planBatchRun(
      createCandidate("pages/notes", { languagesWithUnsavedChanges: ["en"] }),
      [],
      createPlanOptions(),
    );

    expect(models[0]!.targets).toEqual([
      { language: GERMAN },
      { language: FRENCH },
    ]);
  });

  it("plans only the cascade without a host", () => {
    const models = planBatchRun(
      undefined,
      [createCandidate("pages/notes+intro")],
      createPlanOptions(),
    );

    expect(models.map(({ path }) => path)).toEqual(["pages/notes+intro"]);
  });
});
