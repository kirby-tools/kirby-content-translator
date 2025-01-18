import { usePanel } from "kirbyuse";

export function openTextDialog(text, callback) {
  const panel = usePanel();

  panel.dialog.open({
    component: "k-text-dialog",
    props: { text },
    on: {
      submit: () => {
        callback?.();
        panel.dialog.close();
      },
    },
  });
}
