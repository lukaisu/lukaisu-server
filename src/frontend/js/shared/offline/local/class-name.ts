/**
 * CSS-class-name encoding for terms — a port of the server
 * `StringUtils::toClassName` / `toHex`, so the offline reader produces the
 * exact same `TERM…` class names / `data_hex` values the rendering code keys
 * on.
 *
 * Alphanumeric and multi-byte characters are kept literally; ASCII punctuation,
 * spaces and control bytes become `¤` + uppercase hex of their UTF-8 bytes.
 *
 * @license Unlicense <http://unlicense.org/>
 */

const encoder = new TextEncoder();

/** Uppercase hex of a string's UTF-8 bytes (port of `StringUtils::toHex`). */
export function toHex(value: string): string {
  let hex = '';
  for (const byte of encoder.encode(value)) {
    hex += byte.toString(16).padStart(2, '0');
  }
  return hex.toUpperCase();
}

/**
 * Encode a (lower-cased) term to its `hex` class-name token, matching
 * `StringUtils::toClassName`. The result is stable across the server and the
 * offline path so DOM lookups (`.TERM<hex>`, `data_hex`) line up.
 */
export function toClassName(value: string): string {
  let result = '';
  for (const ch of value) {
    // ord() of the first UTF-8 byte — for ASCII this is the code point; for
    // multi-byte characters it is the lead byte (>=194), which falls outside
    // every escaped range, so such characters are kept literally.
    const firstByte = encoder.encode(ch)[0];
    if (
      firstByte < 48 ||
      (firstByte > 57 && firstByte < 65) ||
      (firstByte > 90 && firstByte < 97) ||
      (firstByte > 122 && firstByte < 165)
    ) {
      result += '¤' + toHex(ch);
    } else {
      result += ch;
    }
  }
  return result;
}
