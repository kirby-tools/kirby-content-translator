import { describe, expect, it, vi } from "vitest";
import { resolveCopilotReadiness } from "../../../src/panel/utils/copilot";
import { copilotWith } from "../helpers/mock-copilot";

// Assigned per test and read lazily by the `kirbyuse` mock below.
let thirdPartyPlugins: Record<string, unknown> = {};

vi.mock("kirbyuse", async () => {
  const { baseKirbyuseMock } = await import("../helpers/mock-kirbyuse");
  return {
    ...baseKirbyuseMock(),
    usePanel: () => ({ plugins: { thirdParty: thirdPartyPlugins } }),
  };
});

describe("resolveCopilotReadiness", () => {
  it("returns `ready` when Copilot meets REQUIRED_COPILOT_API_VERSION and has a key for its provider", async () => {
    thirdPartyPlugins = { copilot: copilotWith() };

    expect(await resolveCopilotReadiness()).toBe("ready");
  });

  it("returns `missing` without Copilot", async () => {
    thirdPartyPlugins = {};

    expect(await resolveCopilotReadiness()).toBe("missing");
  });

  it("returns `outdated` for an apiVersion below REQUIRED_COPILOT_API_VERSION", async () => {
    thirdPartyPlugins = { copilot: copilotWith({ apiVersion: 1 }) };

    expect(await resolveCopilotReadiness()).toBe("outdated");
  });

  it("returns missingApiKey when the configured provider has no API key", async () => {
    thirdPartyPlugins = { copilot: copilotWith({ hasApiKey: false }) };

    expect(await resolveCopilotReadiness()).toBe("missingApiKey");
  });

  it("returns `unavailable` when resolvePluginContext rejects", async () => {
    thirdPartyPlugins = {
      copilot: {
        apiVersion: 2,
        resolvePluginContext: async () => {
          throw new Error("offline");
        },
      },
    };

    expect(await resolveCopilotReadiness()).toBe("unavailable");
  });
});
