import { usePanel } from "kirbyuse";

export function useI18n() {
  const panel = usePanel();

  function t(value) {
    if (!value || typeof value === "string") return value;
    return value[panel.translation.code] ?? Object.values(value)[0];
  }

  return {
    t,
  };
}
