"""
Configuration for the Lukaisu Python edge service.

All settings can be overridden via environment variables (no prefix). The
service is designed to run **standalone** — without the PHP container in front
of it — so the client (the Lukaisu app) can call it directly. See
``services/nlp/README.md`` for the standalone run instructions and
``docs-src/server/http-contract.md`` for the stable HTTP contract.
"""

from pydantic_settings import BaseSettings


class Settings(BaseSettings):
    # --- TTS (Piper) ---------------------------------------------------------
    piper_voices_dir: str = "/app/voices"
    piper_voices_url: str = "https://huggingface.co/rhasspy/piper-voices/resolve/main"

    # --- CORS ----------------------------------------------------------------
    # Comma-separated list of allowed origins. The default ``*`` is appropriate
    # because this service is stateless and carries no cookies/credentials — it
    # is a public-API-style edge the client may call cross-origin (e.g. the
    # Capacitor app served from ``capacitor://localhost`` / ``https://localhost``).
    # Tighten this in deployments that put the service on a shared origin.
    cors_allowed_origins: str = "*"

    # --- Outbound / network edge --------------------------------------------
    # Shared by the content-discovery, feed, and extraction routers. These are
    # the inherently server-side integrations (a phone cannot make arbitrary
    # cross-origin requests safely).
    outbound_user_agent: str = "Lukaisu Server/0.1 (Language Learning Tool)"
    # Default per-request fetch caps (individual routers may override).
    outbound_timeout_seconds: float = 15.0
    outbound_max_redirects: int = 5
    # Allow fetching from private/loopback addresses. MUST stay False in any
    # network-exposed deployment — it is the SSRF guard. Only flip to True for
    # isolated local testing against a fixture server.
    outbound_allow_private_hosts: bool = False

    # --- Content discovery sources ------------------------------------------
    gutendex_base_url: str = "https://gutendex.com/books/"
    gdl_base_url: str = (
        "https://content.digitallibrary.io/wp-json/content-api/v1/contentsearch"
    )
    internet_archive_base_url: str = "https://archive.org/advancedsearch.php"

    # --- YouTube -------------------------------------------------------------
    # Optional: only needed for the YouTube video-info endpoint (Data API v3).
    # Transcript fetching does not require a key.
    yt_api_key: str = ""

    class Config:
        env_prefix = ""

    @property
    def cors_origins_list(self) -> list[str]:
        raw = self.cors_allowed_origins.strip()
        if raw == "*" or raw == "":
            return ["*"]
        return [origin.strip() for origin in raw.split(",") if origin.strip()]


settings = Settings()
