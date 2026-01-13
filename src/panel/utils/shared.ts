export function isObject(value: unknown): value is Record<any, any> {
  return Object.prototype.toString.call(value) === "[object Object]";
}
