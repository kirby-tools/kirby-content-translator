import { describe, expect, it } from "vitest";
import { splitKirbyText } from "../../../src/panel/translation/kirby-text";

describe("splitKirbyText", () => {
  describe("parsing", () => {
    it("handles nested parentheses inside an attribute value", () => {
      const text = "(link: /a text: our (awesome) site)";
      const { fragments, restore } = splitKirbyText(text, { link: ["text"] });

      expect(fragments).toContain("our (awesome) site");
      expect(restore(fragments)).toBe(text);
    });

    it("does not split URL schemes as attribute boundaries", () => {
      const text = "(link: https://example.com text: visit us)";
      const { fragments, restore } = splitKirbyText(text, { link: ["text"] });

      expect(fragments).toContain("visit us");
      expect(fragments).not.toContain("https://example.com");
      expect(restore(fragments)).toBe(text);
    });

    it("leaves an unclosed tag untouched in the prose", () => {
      const text = "Visit (link: /a text: incomplete";
      const { fragments, restore } = splitKirbyText(text, { link: ["text"] });

      expect(fragments).toEqual([text]);
      expect(restore(fragments)).toBe(text);
    });
  });

  describe("isolation", () => {
    it("protects every tag verbatim when config is empty", () => {
      const text = "Hello (link: /a text: world)!";
      const { fragments, restore } = splitKirbyText(text, {});

      expect(fragments).toHaveLength(1);
      expect(fragments[0]).not.toContain("link");
      expect(fragments[0]).not.toContain("world");

      const translated = [fragments[0]!.replace("Hello", "Hallo")];
      expect(restore(translated)).toBe("Hallo (link: /a text: world)!");
    });
  });

  describe("round-trip restoration", () => {
    it("round-trips when fragments are restored unchanged", () => {
      const text = "Visit (link: /a text: our site)!";
      const { fragments, restore } = splitKirbyText(text, { link: ["text"] });
      expect(restore(fragments)).toBe(text);
    });

    it("handles multiple tags in one string with independent attr translations", () => {
      const text =
        "Visit (link: /a text: site) or (email: x@y.com text: email us)";
      const { fragments, restore } = splitKirbyText(text, {
        link: ["text"],
        email: ["text"],
      });

      expect(fragments.slice(1)).toEqual(["site", "email us"]);

      const translated = [...fragments];
      translated[1] = "Seite";
      translated[2] = "schreib uns";

      expect(restore(translated)).toBe(
        "Visit (link: /a text: Seite) or (email: x@y.com text: schreib uns)",
      );
    });

    it("preserves structural attributes when only one attr value is translated", () => {
      const text = "(image: hero.jpg alt: Sunset caption: Mountain view)";
      const { fragments, restore } = splitKirbyText(text, { image: ["alt"] });

      const translated = [...fragments];
      translated[1] = "Sonnenuntergang";

      expect(restore(translated)).toBe(
        "(image: hero.jpg alt: Sonnenuntergang caption: Mountain view)",
      );
    });
  });

  describe("length mismatch", () => {
    it("throws when restore receives fewer fragments than expected", () => {
      const text = "(link: /a text: site)";
      const { fragments, restore } = splitKirbyText(text, { link: ["text"] });

      expect(fragments).toHaveLength(2);
      expect(() => restore([fragments[0]!])).toThrow(
        "Expected 2 translated fragments, got 1",
      );
    });

    it("throws when restore receives more fragments than expected", () => {
      const text = "(link: /a text: site)";
      const { fragments, restore } = splitKirbyText(text, { link: ["text"] });

      expect(fragments).toHaveLength(2);
      expect(() => restore([...fragments, "extra"])).toThrow(
        "Expected 2 translated fragments, got 3",
      );
    });
  });
});
