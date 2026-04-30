import type { InjectionKey } from "vue";
import type { TranslationTreeEntry } from "../types";
import { reactive, useApi, usePanel } from "kirbyuse";

// eslint-disable-next-line symbol-description
export const TOGGLE_INJECTION_KEY = Symbol() as InjectionKey<
  (item: TranslationTreeEntry) => Promise<void>
>;

export function useTranslationTree() {
  const panel = usePanel();
  const api = useApi();

  const tree = reactive<TranslationTreeEntry[]>([]);

  function setTree(items: TranslationTreeEntry[]) {
    tree.length = 0;
    tree.push(...items.map(initEntry));
  }

  async function toggle(item: TranslationTreeEntry) {
    if (!item.hasChildren) return;

    if (item.open) {
      item.open = false;
      return;
    }

    if (!item.children) {
      item.loading = true;

      try {
        const response = await api.get("__content-translator__/coverage", {
          parent: item.id,
        });
        item.children = response.children.map(initEntry);
      } catch {
        panel.notification.error(
          panel.t("johannschopplich.content-translator.coverage.loadError"),
        );
        return;
      } finally {
        item.loading = false;
      }
    }

    item.open = true;
  }

  return {
    tree,
    setTree,
    toggle,
  };
}
function initEntry(entry: TranslationTreeEntry): TranslationTreeEntry {
  entry.open = false;
  entry.loading = false;
  entry.children = entry.children ?? null;
  return entry;
}
