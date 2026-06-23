FROM python:3.11-slim-bookworm

# EDGE_ONLY=1 builds a lightweight "outbound edge only" image: it skips the
# heavy NLP system packages (MeCab, ffmpeg) and installs requirements-edge.txt
# (no spaCy/Whisper/Piper/MeCab). The default (full) build installs everything.
ARG EDGE_ONLY=

# Install system dependencies for MeCab, Piper and Whisper.
# openai-whisper shells out to ffmpeg for audio decoding; without it
# every transcribe job dies with "[Errno 2] No such file or directory:
# 'ffmpeg'" no matter how healthy the model load looks.
# curl is always installed for the container healthcheck.
RUN apt-get update && apt-get install -y --no-install-recommends curl \
    && if [ -z "$EDGE_ONLY" ]; then \
         apt-get install -y --no-install-recommends \
           mecab mecab-ipadic-utf8 libmecab-dev ffmpeg ; \
       fi \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY requirements.txt requirements-edge.txt ./
RUN if [ -n "$EDGE_ONLY" ]; then \
      pip install --no-cache-dir -r requirements-edge.txt ; \
    else \
      pip install --no-cache-dir -r requirements.txt ; \
    fi

COPY app/ ./app/

# Create voices directory
RUN mkdir -p /app/voices

EXPOSE 8000

CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8000"]
