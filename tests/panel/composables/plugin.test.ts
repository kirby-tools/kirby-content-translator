import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

const get = vi.fn();

beforeEach(() => {
  vi.resetModules();
  get.mockReset();
  vi.stubGlobal("window", { panel: { api: { get } } });
});

afterEach(() => {
  vi.unstubAllGlobals();
});

describe("usePluginContext", () => {
  it("requests the context once and serves the cached response afterwards", async () => {
    get.mockResolvedValue({ config: {} });
    const { usePluginContext } =
      await import("../../../src/panel/composables/plugin");

    const [first, second] = await Promise.all([
      usePluginContext(),
      usePluginContext(),
    ]);
    await usePluginContext();

    expect(get).toHaveBeenCalledTimes(1);
    expect(first).toBe(second);
  });

  it("retries after a failed request instead of caching the rejection", async () => {
    get.mockRejectedValueOnce(new Error('Unknown strategy "banana"'));
    get.mockResolvedValueOnce({ config: {} });
    const { usePluginContext } =
      await import("../../../src/panel/composables/plugin");

    await expect(usePluginContext()).rejects.toThrow("banana");

    expect(await usePluginContext()).toEqual({ config: {} });
  });
});
