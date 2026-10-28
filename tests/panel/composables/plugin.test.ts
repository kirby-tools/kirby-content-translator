import type { PluginContextResponse } from "../../../src/panel/types";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { LICENSE_ACTIVATE_EVENT } from "../../../src/panel/constants";
import { createEventBus } from "../helpers/event-bus";

const api = { get: vi.fn() };
const events = createEventBus();

vi.mock("kirbyuse", () => ({
  usePanel: () => ({ api, events }),
}));

function contextResponse(
  licenseStatus: PluginContextResponse["licenseStatus"],
): PluginContextResponse {
  return {
    config: {} as PluginContextResponse["config"],
    kirbyTagTypes: [],
    homePageId: "home",
    errorPageId: "error",
    licenseStatus,
    licenseDialog: "johannschopplich-kirby-content-translator/activate",
  };
}

beforeEach(() => {
  vi.resetModules();
  api.get.mockReset();
  events.clear();
});

describe("usePluginContext", () => {
  it("requests the context once and serves the cached response afterwards", async () => {
    api.get.mockResolvedValue(contextResponse("active"));
    const { usePluginContext } =
      await import("../../../src/panel/composables/plugin");

    const [first, second] = await Promise.all([
      usePluginContext(),
      usePluginContext(),
    ]);
    await usePluginContext();

    expect(api.get).toHaveBeenCalledTimes(1);
    expect(first).toBe(second);
  });

  it("retries after a failed request instead of caching the rejection", async () => {
    api.get.mockRejectedValueOnce(new Error('Unknown strategy "banana"'));
    api.get.mockResolvedValueOnce(contextResponse("active"));
    const { usePluginContext } =
      await import("../../../src/panel/composables/plugin");

    await expect(usePluginContext()).rejects.toThrow("banana");

    expect(await usePluginContext()).toEqual(contextResponse("active"));
  });

  it("refetches the cached context after LICENSE_ACTIVATE_EVENT", async () => {
    const { usePluginContext } =
      await import("../../../src/panel/composables/plugin");
    api.get
      .mockResolvedValueOnce(contextResponse("inactive"))
      .mockResolvedValueOnce(contextResponse("active"));

    expect((await usePluginContext()).licenseStatus).toBe("inactive");
    expect((await usePluginContext()).licenseStatus).toBe("inactive");
    expect(api.get).toHaveBeenCalledTimes(1);

    events.emit(LICENSE_ACTIVATE_EVENT, undefined);

    expect((await usePluginContext()).licenseStatus).toBe("active");
    expect(api.get).toHaveBeenCalledTimes(2);
  });
});
