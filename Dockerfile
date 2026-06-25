# Stage 1: Build frontend assets on a platform with reliable Node.js support
FROM --platform=$BUILDPLATFORM node:22-bookworm-slim AS frontend-builder

WORKDIR /build
COPY package.json package-lock.json vite.config.ts vite.app.config.ts tsconfig.json ./
COPY src/frontend/ src/frontend/
COPY scripts/ scripts/
# build:app's copyReviewSounds plugin reads the review feedback sounds.
COPY assets/sounds/ assets/sounds/
RUN npm ci --ignore-scripts && npm run build:all

# Stage 2: Final application image
FROM php:8.4-apache-bookworm

LABEL org.opencontainers.image.title="Lukaisu Server Community"
LABEL org.opencontainers.image.description="An image for Lukaisu Server"
LABEL org.opencontainers.image.documentation="https://hugofara.github.io/lukaisu-server/"
LABEL org.opencontainers.image.url="https://hugofara.github.io/lukaisu-server/"
LABEL org.opencontainers.image.author="HugoFara <contact@hugofara.net>"
LABEL org.opencontainers.image.license=Unlicense
LABEL org.opencontainers.image.source="https://github.com/lukaisu/lukaisu-server"


# Creating config file php.ini
RUN apt-get update && apt-get install -y --no-install-recommends libzip-dev \
    && rm -rf /var/lib/apt/lists/* \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && echo 'mysqli.allow_local_infile = On' >> "$PHP_INI_DIR/php.ini" \
    && docker-php-ext-install pdo pdo_mysql mysqli zip

# Install Python and Composer dependencies
ENV DEBIAN_FRONTEND=noninteractive
RUN apt-get update --fix-missing \
    && apt-get install -y --no-install-recommends \
    python3 \
    python3-pip \
    python3-venv \
    unzip \
    git \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install MeCab (Japanese morphological analyzer)
# Only available on architectures where apt packages configure correctly under QEMU.
# arm/v7 and some others hit dpkg segfaults under QEMU emulation (moby/buildkit#1929).
RUN apt-get update \
    && if apt-get install -y --no-install-recommends mecab libmecab-dev mecab-ipadic-utf8; then \
        mkdir -p /usr/local/etc \
        && (test -f /etc/mecabrc && ln -sf /etc/mecabrc /usr/local/etc/mecabrc || true); \
    else \
        echo "WARNING: MeCab installation failed on $(dpkg --print-architecture) — Japanese parsing will be unavailable"; \
    fi \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Create Python virtual environment and install NLP packages
# mecab-python3 only has binary wheels for x86_64 and aarch64;
# on other architectures we skip it (source build requires a full C toolchain).
RUN python3 -m venv /opt/lukaisu-parsers && \
    /opt/lukaisu-parsers/bin/pip install --no-cache-dir jieba>=0.42.1 && \
    /opt/lukaisu-parsers/bin/pip install --no-cache-dir --only-binary=:all: mecab-python3>=1.0.6 \
    || echo "WARNING: mecab-python3 not available for $(uname -m) — Japanese parsing will be unavailable"

# Copy parser scripts first (for better caching)
COPY parsers/ /opt/lukaisu/parsers/

# Application base path configuration
# Set to /lukaisu-server for subdirectory installation, or leave empty for root installation
ARG APP_BASE_PATH=

# Copy application files
# Files go to /var/www/html{APP_BASE_PATH} (e.g., /var/www/html/lukaisu-server or /var/www/html)
COPY . /var/www/html${APP_BASE_PATH}

# Note: Database configuration is provided at runtime via environment variables
# or by mounting a .env file. See docker-compose.yml for examples.

# Apache port configuration (use port >= 1024 to run as non-root)
ARG APACHE_PORT=80
RUN sed -i "s/Listen 80/Listen ${APACHE_PORT}/" /etc/apache2/ports.conf \
    && sed -i "s/:80/:${APACHE_PORT}/" /etc/apache2/sites-available/000-default.conf
EXPOSE ${APACHE_PORT}

# Configure Apache: enable mod_rewrite and AllowOverride for .htaccess
RUN a2enmod rewrite \
    && sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Install PHP dependencies
WORKDIR /var/www/html${APP_BASE_PATH}
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy pre-built frontend assets from the builder stage
COPY --from=frontend-builder /build/dist/ dist/
COPY --from=frontend-builder /build/sw.js sw.js
# The bundled client (served by BundleController under /app/ as the default UI).
COPY --from=frontend-builder /build/dist-app/ dist-app/

# Set proper ownership for Apache (www-data user)
RUN chown -R www-data:www-data /var/www/html${APP_BASE_PATH}
