import { destr } from "destr";
import { ofetch } from "ofetch";
import { STORAGE_KEY_PREFIX } from "../constants";

const LICENSE_STORAGE_KEY = `${STORAGE_KEY_PREFIX}license`;
const LICENSE_EXPIRY_DURATION = 1000 * 60 * 60 * 24 * 30; // 30 days
const $repo = ofetch.create({
  baseURL: "https://repo.kirby.tools/api",
});

export function useLicense() {
  const isLocalhost = _isLocalhost();

  const validateLicense = async (licenseKey) => {
    if (!licenseKey) return false;

    const storedLicense = destr(localStorage.getItem(LICENSE_STORAGE_KEY));

    // Periodically check if the license is valid
    if (storedLicense) {
      const { licenseKey: storedKey, expiresAt } = storedLicense;

      if (storedKey === licenseKey && expiresAt > Date.now() && !isLocalhost) {
        return true;
      } else {
        localStorage.removeItem(LICENSE_STORAGE_KEY);
      }
    }

    try {
      const license = await $repo("licenses/validate", {
        query: { key: licenseKey },
      });

      localStorage.setItem(
        LICENSE_STORAGE_KEY,
        JSON.stringify({
          ...license,
          order: undefined,
          expiresAt: Date.now() + LICENSE_EXPIRY_DURATION,
        }),
      );

      return true;
    } catch (error) {
      console.error(error);
      return false;
    }
  };

  return {
    isLocalhost,
    validateLicense,
  };
}

function _isLocalhost() {
  const { hostname } = window.location;

  // Check for localhost and 127.0.0.1 (IPv4) or ::1 (IPv6)
  const isLocalhost =
    hostname === "localhost" || hostname === "127.0.0.1" || hostname === "::1";
  const isTestDomain = hostname.endsWith(".test");

  return isLocalhost || isTestDomain;
}
