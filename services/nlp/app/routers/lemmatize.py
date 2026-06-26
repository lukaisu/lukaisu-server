"""
Lemmatization API endpoints.

Provides lemmatization services using various backends (spaCy, etc.).
"""
from fastapi import APIRouter, HTTPException
from pydantic import BaseModel
from app.services.lemmatizers.base import LemmaResult, BatchLemmaResult, LemmatizerInfo
from app.services.lemmatizers.spacy_lemmatizer import get_spacy_lemmatizer, SPACY_MODELS
from app.services.lemmatizers.kiwi_lemmatizer import get_kiwi_lemmatizer

router = APIRouter()


class LemmatizeRequest(BaseModel):
    """Request to lemmatize a single word."""
    word: str
    language: str
    lemmatizer: str = "spacy"  # 'spacy' | 'kiwi' (Korean) | 'stanza' (future)


class BatchLemmatizeRequest(BaseModel):
    """Request to lemmatize multiple words."""
    words: list[str]
    language: str
    lemmatizer: str = "spacy"


def _resolve_lemmatizer(name: str):
    """Map a lemmatizer id to its singleton. Kiwi is recommended for Korean."""
    if name == "spacy":
        return get_spacy_lemmatizer()
    if name == "kiwi":
        return get_kiwi_lemmatizer()
    return None


@router.post("/", response_model=LemmaResult)
async def lemmatize(request: LemmatizeRequest):
    """
    Lemmatize a single word.

    Returns the base form (lemma) of the word, or null if the word
    is already in base form or not recognized.
    """
    lemmatizer = _resolve_lemmatizer(request.lemmatizer)
    if lemmatizer is None:
        raise HTTPException(400, f"Unknown lemmatizer: {request.lemmatizer}")
    if not lemmatizer.supports_language(request.language):
        raise HTTPException(
            400,
            f"Language '{request.language}' not supported by {request.lemmatizer}. "
            f"Available: {lemmatizer.get_supported_languages()}"
        )
    lemma = lemmatizer.lemmatize(request.word, request.language)
    return LemmaResult(word=request.word, lemma=lemma)


@router.post("/batch", response_model=BatchLemmaResult)
async def lemmatize_batch(request: BatchLemmatizeRequest):
    """
    Lemmatize multiple words.

    Returns a mapping of words to their lemmas. Words that are already
    in base form or not recognized will have null as their lemma.
    """
    if not request.words:
        return BatchLemmaResult(results={})

    lemmatizer = _resolve_lemmatizer(request.lemmatizer)
    if lemmatizer is None:
        raise HTTPException(400, f"Unknown lemmatizer: {request.lemmatizer}")
    if not lemmatizer.supports_language(request.language):
        raise HTTPException(
            400,
            f"Language '{request.language}' not supported by {request.lemmatizer}. "
            f"Available: {lemmatizer.get_supported_languages()}"
        )
    results = lemmatizer.lemmatize_batch(request.words, request.language)
    return BatchLemmaResult(results=results)


@router.get("/available")
async def available_lemmatizers():
    """
    List available lemmatizers and their supported languages.

    Shows both installed and potentially available lemmatizers.
    """
    spacy = get_spacy_lemmatizer()
    installed_langs = spacy.get_supported_languages()
    all_langs = spacy.get_all_languages()

    kiwi = get_kiwi_lemmatizer()
    kiwi_langs = kiwi.get_supported_languages()

    lemmatizers = [
        LemmatizerInfo(
            id="spacy",
            name="spaCy NLP",
            languages=installed_langs,
            available=len(installed_langs) > 0
        ),
        LemmatizerInfo(
            id="kiwi",
            name="Kiwi (Korean)",
            languages=kiwi_langs,
            available=len(kiwi_langs) > 0
        ),
    ]

    return {
        "lemmatizers": lemmatizers,
        "spacy_models": {
            "installed": installed_langs,
            "available": all_langs,
            "model_map": SPACY_MODELS
        }
    }


@router.get("/languages/{language}")
async def check_language(language: str):
    """
    Check if a language is supported for lemmatization.

    Returns details about available lemmatizers for the language.
    """
    spacy = get_spacy_lemmatizer()
    kiwi = get_kiwi_lemmatizer()

    return {
        "language": language,
        "spacy": {
            "supported": language in SPACY_MODELS,
            "installed": spacy.supports_language(language),
            "model": SPACY_MODELS.get(language)
        },
        "kiwi": {
            "supported": language == "ko",
            "installed": kiwi.supports_language(language)
        }
    }
