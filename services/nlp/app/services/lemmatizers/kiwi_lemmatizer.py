"""
Kiwi-based lemmatizer for Korean.

Korean is agglutinative: an eojeol carries a content stem plus grammatical
particles/endings, and verbs/adjectives conjugate heavily (공부했습니다 →
공부하다). Reducing each surface form to its dictionary headword keeps the
learner's word list and review schedule from fragmenting across inflections —
the same role spaCy plays for the space-separated languages, but Kiwi handles
Korean morphology far better and installs without a system package or a separate
model download.

Lemma reconstruction is heuristic but covers the common cases:
  * verbs/adjectives (incl. 하다-derived): leading content morphemes + 다
    — 공부했습니다 → 공부하다, 갔습니다 → 가다, 예뻤어요 → 예쁘다
  * nouns + particles: the content morphemes joined — 학교에서 → 학교, 저는 → 저
The copula (이다/아니다) is treated as grammatical, so 학생입니다 → 학생.
"""
import logging

from .base import BaseLemmatizer
from app.services.kiwi_engine import get_kiwi, kiwi_available

logger = logging.getLogger(__name__)

# Verb/adjective morphemes whose dictionary form is the stem + 다. The copula
# (VCP 이다 / VCN 아니다) is deliberately excluded so noun+copula reduces to the
# noun (학생입니다 → 학생) rather than 학생이다.
_PREDICATE_TAGS = {"VV", "VA", "VX", "XSV", "XSA"}
_COPULA_TAGS = {"VCP", "VCN"}
_PUNCT_TAGS = {"SF", "SP", "SS", "SE", "SO", "SW", "SN"}


def _is_content(tag: str) -> bool:
    """True for morphemes that make up a nominal lemma (drop particles/endings/
    copula/punctuation)."""
    if tag in _PUNCT_TAGS or tag in _COPULA_TAGS:
        return False
    # Josa (particles, J*) and eomi (endings, E*) are grammatical.
    return not (tag.startswith("J") or tag.startswith("E"))


class KiwiLemmatizer(BaseLemmatizer):
    """Lemmatizer for Korean using the Kiwi morphological analyzer."""

    LANGUAGES = ["ko"]

    def supports_language(self, language: str) -> bool:
        return language == "ko" and kiwi_available()

    def get_supported_languages(self) -> list[str]:
        return list(self.LANGUAGES) if kiwi_available() else []

    def lemmatize(self, word: str, language: str) -> str | None:
        """Return the dictionary form of a Korean word, or None if it is already
        in base form / not recognized (mirrors the spaCy lemmatizer contract)."""
        if not self.supports_language(language):
            return None
        lemma = self._lemma(word)
        if not lemma or lemma == word:
            return None
        return lemma

    def lemmatize_batch(self, words: list[str], language: str) -> dict[str, str | None]:
        if not self.supports_language(language):
            return {word: None for word in words}
        return {word: self.lemmatize(word, language) for word in words}

    def _lemma(self, word: str) -> str | None:
        morphs = get_kiwi().tokenize(word)
        if not morphs:
            return None

        # A predicate (verb/adjective): dictionary form is the content morphemes
        # up to and including the predicate stem, plus 다.
        for idx, token in enumerate(morphs):
            if token.tag in _PREDICATE_TAGS:
                stem = "".join(m.form for m in morphs[: idx + 1])
                return stem + "다" if stem else None

        # Otherwise nominal/other: join the content morphemes.
        content = "".join(t.form for t in morphs if _is_content(t.tag))
        return content or None


_instance: KiwiLemmatizer | None = None


def get_kiwi_lemmatizer() -> KiwiLemmatizer:
    """Get the singleton KiwiLemmatizer instance."""
    global _instance
    if _instance is None:
        _instance = KiwiLemmatizer()
    return _instance
