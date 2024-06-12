import { useApi, usePanel } from "kirbyuse";

const REGISTER_API_PATH = "__content-translator__/register";

export function useLicense() {
  const panel = usePanel();
  const api = useApi();
  const isLocalhost = _isLocalhost();

  const register = async (email, orderId) => {
    if (!email || !orderId) {
      throw new Error("Email and order ID are required");
    }

    const response = await api.post(REGISTER_API_PATH, { email, orderId });
    if (!response?.ok) {
      throw new Error("Registration failed");
    }

    return true;
  };

  const openLicenseModal = () => {
    panel.dialog.open({
      component: "k-form-dialog",
      props: {
        submitButton: {
          icon: "check",
          theme: "love",
          text: panel.t("johannschopplich.content-translator.license.activate"),
        },
        fields: {
          info: {
            type: "info",
            text: panel.t(
              "johannschopplich.content-translator.license.modal.info",
            ),
          },
          email: {
            label: panel.t("email"),
            type: "email",
          },
          orderId: {
            label: "Order ID",
            type: "text",
            help: panel.t(
              "johannschopplich.content-translator.license.modal.help.orderId",
            ),
          },
        },
      },
      on: {
        submit: async (event) => {
          const { email, orderId } = event;
          if (!email || !orderId) {
            panel.notification.error("Email and order ID are required");
            return;
          }

          try {
            await register(email, Number(orderId));
          } catch (error) {
            panel.notification.error(error.message);
            return;
          }

          panel.dialog.close();
          await panel.view.reload();
          panel.notification.success(
            panel.t("johannschopplich.content-translator.license.activated"),
          );
        },
      },
    });
  };

  return {
    isLocalhost,
    openLicenseModal,
  };
}

function _isLocalhost() {
  const { hostname } = window.location;
  const isLocalhost = ["localhost", "127.0.0.1", "::1"].includes(hostname);
  const isTestDomain = hostname.endsWith(".test");

  return isLocalhost || isTestDomain;
}
