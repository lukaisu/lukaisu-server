"""
Kiwi-based parser for Korean.

Korean is space-delimited at the eojeol (word-phrase) level, so unlike Chinese
and Japanese it is already readable without a tokenizer. What it needs is
morphological analysis: an eojeol bundles a content stem with grammatical
particles/endings (학교에서 = 학교 + 에서), so without splitting them every
surface form looks like a distinct word. Kiwi splits an eojeol into morphemes —
mirroring how the MeCab parser splits Japanese — which lets the learner click
and save the content word (학교) apart from its grammatical endings.

Surface alignment: Kiwi reports each morpheme's *dictionary* form (e.g. 갔 →
가 + 었), which does not always reproduce the original characters. The reader
maps tokens back onto the displayed text, so we emit **surface slices** taken
from the original string by each morpheme's position, merging morphemes that
share a fused syllable block (irregular conjugations) into one token. The token
texts therefore always concatenate back to the input; the dictionary form lives
in the separate ``/lemmatize`` endpoint (Kiwi lemmatizer).
"""
from .base import BaseParser, ParseResult, Token
from app.services.kiwi_engine import get_kiwi

# Kiwi (Sejong) tags for punctuation, symbols and numbers. Everything else —
# nouns, verb/adjective stems, particles, endings, foreign words, hanja — is a
# learnable morpheme, mirroring the MeCab parser marking particles as words.
_NON_WORD_TAGS = {"SF", "SP", "SS", "SE", "SO", "SW", "SN"}


def _surface_tokens(para: str, morphs) -> list[Token]:
    """Turn Kiwi morphemes into non-overlapping surface-slice tokens.

    Gaps between morphemes (spaces and any uncovered characters) are emitted as
    non-word tokens, so the token texts reconstruct ``para`` exactly. Morphemes
    whose surface spans overlap (fused jamo in irregular conjugations) are merged
    into a single token that is a word if any of the merged morphemes is.
    """
    tokens: list[Token] = []
    pos = 0
    ordered = sorted(morphs, key=lambda t: (t.start, t.start + t.len))
    i, n = 0, len(ordered)

    while i < n:
        t = ordered[i]
        start, end = t.start, t.start + t.len
        has_word = t.tag not in _NON_WORD_TAGS
        j = i + 1
        # Absorb following morphemes that overlap this surface span.
        while j < n and ordered[j].start < end:
            end = max(end, ordered[j].start + ordered[j].len)
            has_word = has_word or (ordered[j].tag not in _NON_WORD_TAGS)
            j += 1

        # Emit any gap (whitespace / uncovered text) before this span.
        if start > pos:
            tokens.append(Token(text=para[pos:start], is_word=False, reading=None))

        surface = para[start:end]
        if surface:
            tokens.append(Token(text=surface, is_word=has_word, reading=None))
        pos = end
        i = j

    if pos < len(para):
        tokens.append(Token(text=para[pos:], is_word=False, reading=None))

    return tokens


class KiwiParser(BaseParser):
    def parse(self, text: str) -> ParseResult:
        kiwi = get_kiwi()
        sentences = []
        tokens = []

        # Split by newlines for paragraphs (parity with the MeCab/jieba parsers,
        # which treat each paragraph as one sentence).
        for para in text.split("\n"):
            if not para.strip():
                continue

            sentence_tokens = _surface_tokens(para, kiwi.tokenize(para))
            if sentence_tokens:
                sentences.append(para)
                tokens.extend(sentence_tokens)

        return ParseResult(sentences=sentences, tokens=tokens)


_parser = None


def get_parser() -> KiwiParser:
    global _parser
    if _parser is None:
        _parser = KiwiParser()
    return _parser


def parse(text: str) -> ParseResult:
    return get_parser().parse(text)
