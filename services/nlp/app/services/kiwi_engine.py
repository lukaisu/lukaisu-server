"""
Shared Kiwi engine for Korean NLP.

Kiwi (``kiwipiepy``) is a modern, MIT-licensed Korean morphological analyzer
that installs as a pure pip wheel with its model bundled — no system package to
compile (unlike ``mecab-ko``) and no separate model download (unlike spaCy). A
single analyzer instance is shared by the Korean parser and lemmatizer so the
model is loaded at most once.

The import is lazy: this module is always importable, so a missing ``kiwipiepy``
only disables the Korean features (reported via ``/parse/available`` and
``/lemmatize/available``) instead of breaking the parse/lemmatize routers.
"""
import importlib.util
import logging

logger = logging.getLogger(__name__)

_kiwi = None


def kiwi_available() -> bool:
    """True when ``kiwipiepy`` is installed (without loading the heavy model)."""
    return importlib.util.find_spec("kiwipiepy") is not None


def get_kiwi():
    """Return the shared, lazily-constructed Kiwi analyzer.

    Raises ``ImportError`` if ``kiwipiepy`` is not installed; callers gate on
    ``kiwi_available()`` (or live inside the defensively-mounted routers).
    """
    global _kiwi
    if _kiwi is None:
        from kiwipiepy import Kiwi  # lazy: heavy import + model load

        _kiwi = Kiwi()
        logger.info("Kiwi Korean analyzer loaded")
    return _kiwi
