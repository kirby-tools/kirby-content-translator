import type { PanelModelData } from "kirby-types";
import { beforeEach, describe, expect, it, vi } from "vitest";

let panel: ReturnType<typeof createPanelStub>;

vi.mock("kirbyuse", async () => {
  const { baseKirbyuseMock } = await import("../helpers/mock-kirbyuse");
  return { ...baseKirbyuseMock(), usePanel: () => panel };
});

function createPanelStub() {
  const listeners = new Map<string, (() => void)[]>();

  return {
    languages: [{ code: "en", default: true }],
    view: { path: "pages/example" },
    api: { get: vi.fn() },
    events: {
      on: (event: string, listener: () => void) => {
        listeners.set(event, [...(listeners.get(event) ?? []), listener]);
      },
      emit: (event: string) => {
        for (const listener of listeners.get(event) ?? []) listener();
      },
    },
  };
}

function createModelData(title: string): PanelModelData {
  return { id: "example", title, content: {} };
}

/**
 * Imports a fresh module per test: `vi.resetModules()` in `beforeEach` clears
 * the module-level model data cache and its listener registration.
 */
async function createModel() {
  const { useModel } = await import("../../../src/panel/composables/model");
  return useModel();
}

describe("useModel", () => {
  beforeEach(() => {
    vi.resetModules();
    panel = createPanelStub();
  });

  it("returns the cached model on a second getModelData", async () => {
    panel.api.get.mockResolvedValueOnce(createModelData("Example"));
    panel.api.get.mockResolvedValueOnce(createModelData("Renamed"));
    const { getModelData } = await createModel();

    await getModelData();

    expect(await getModelData()).toEqual(createModelData("Example"));
  });

  it("refetches a cached model after model.update fires on another view", async () => {
    panel.api.get.mockResolvedValueOnce(createModelData("Example"));
    panel.api.get.mockResolvedValueOnce(createModelData("Renamed"));
    const { getModelData } = await createModel();
    await getModelData();

    panel.view.path = "pages/other";
    panel.events.emit("model.update");
    panel.view.path = "pages/example";

    expect(await getModelData()).toEqual(createModelData("Renamed"));
  });

  it("refetches the site after site.changeTitle", async () => {
    panel.view.path = "site";
    panel.api.get.mockResolvedValueOnce(createModelData("Example"));
    panel.api.get.mockResolvedValueOnce(createModelData("Renamed"));
    const { getModelData } = await createModel();
    await getModelData();

    panel.events.emit("site.changeTitle");

    expect(await getModelData()).toEqual(createModelData("Renamed"));
  });
});
