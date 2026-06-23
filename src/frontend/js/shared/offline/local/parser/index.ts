/**
 * Local-first tokenizer entry point.
 *
 * Picks the right parser for a language and runs it: character-by-character for
 * CJK (`splitEachChar`), otherwise the regex parser for space-separated and
 * right-to-left languages. Character substitutions are applied first, mirroring
 * the server's text-preparation step.
 *
 * @license Unlicense <http://unlicense.org/>
 */

import type { Parser, ParserConfig, ParserResult } from './types';
import { regexParser } from './regex-parser';
import { characterParser } from './character-parser';
import { applyCharacterSubstitutions } from './string-utils';

export type { Parser, ParserConfig, ParserResult, Token } from './types';
export { regexParser } from './regex-parser';
export { characterParser } from './character-parser';
export { applyCharacterSubstitutions, removeSpaces } from './string-utils';

/** Choose the tokenizer that matches a language's configuration. */
export function parserFor(config: ParserConfig): Parser {
  return config.splitEachChar ? characterParser : regexParser;
}

/**
 * Parse text into sentences + tokens for a language. This is the single entry
 * point the data layer uses to turn a raw text into word-occurrences on-device.
 */
export function parseText(text: string, config: ParserConfig): ParserResult {
  const prepared = applyCharacterSubstitutions(text, config.characterSubstitutions);
  return parserFor(config).parse(prepared, config);
}
