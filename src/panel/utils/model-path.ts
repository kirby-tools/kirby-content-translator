const FILE_MODEL_PATH_PATTERN =
  /^(?:account|pages\/[^/]+|site|users\/[^/]+)\/files\//;

export function isFileModelPath(path: string) {
  return FILE_MODEL_PATH_PATTERN.test(path);
}

export function isSiteModelPath(path: string) {
  return path === "site";
}
