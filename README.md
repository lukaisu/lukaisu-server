# Lukaisu Server

<p align="center">
  <img src="assets/images/lukaisu_icon_512.png" alt="Lukaisu Server logo - an open book" width="200"/>
</p>

<p align="center">
  <strong>Learn languages by reading — with smart suggestions and built-in definitions</strong>
</p>

<p align="center">
  <a href="http://unlicense.org/"><img src="https://img.shields.io/badge/license-Unlicense-blue.svg" alt="License: Unlicense"></a>
</p>

---

**Lukaisu Server** is the self-hosted backend that powers the
[Lukaisu](https://github.com/lukaisu/lukaisu) reading app. It's a web app for
language learning through reading: **import any text you want to study** — a
novel, a news article, song lyrics — or let it **suggest reading material
adapted to your level** from open libraries. Either way, words are enriched with
**translations and definitions from open sources**, and you build vocabulary
through reading and spaced repetition.

Don't know where to start? Lukaisu Server suggests books from
[Project Gutenberg](https://www.gutenberg.org/) ranked by difficulty, plus
curated news feeds in 19 languages. Already have a text you love? Paste it in and
start reading. As you mark words, suggestions get smarter and new vocabulary is
pre-enriched from [Wiktionary](https://www.wiktionary.org/).

> [!NOTE]
> **Lineage.** Lukaisu Server is a clean-cut fork of
> [Learning with Texts (LWT)](https://github.com/HugoFara/lwt) at version 3.2.0 —
> the community-maintained LWT by HugoFara, itself a modern descendant of the
> original LWT created by
> [lang-learn-guy](https://sourceforge.net/u/lang-learn-guy/) on
> [SourceForge](https://sourceforge.net/projects/learning-with-texts) (2011).
> Lukaisu Server exists as the dedicated server for the Lukaisu app; see
> [NOTICE](NOTICE) for full attribution.

## Table of Contents

- [Quick Start](#quick-start)
- [How It Works](#how-it-works)
- [Features](#features)
- [Installation](#installation)
- [Requirements](#requirements)
- [Documentation](#documentation)
- [Contributing](#contributing)
- [Alternatives](#alternatives)
- [License](#license)

## Quick Start

The fastest way to get started is with Docker:

```bash
git clone https://github.com/lukaisu/lukaisu-server.git
cd lukaisu-server
docker compose up
```

Then open <http://localhost:8010/> in your browser.

## How It Works

**1. Pick a text** — Import something you want to read, or browse suggestions matched to your level from Project Gutenberg and curated news feeds.

**2. Read and learn** — Unknown words are highlighted. Click any word to see its translation (pre-loaded from Wiktionary) and save it to your vocabulary.

**3. Review with context** — Practice vocabulary with spaced repetition, always seeing words in their original context.

**4. Keep going** — As you mark words known or unknown, difficulty estimates adapt. Suggestions get smarter, and your next text is always ready.

Unlike flashcard apps like [Anki](https://apps.ankiweb.net/), Lukaisu Server keeps words connected to the texts where you found them. We include an Anki exporter if you want both.

## Features

### Smart Content Suggestions

- **Book suggestions** — Browse Project Gutenberg's catalog, ranked by difficulty for your level
- **Curated news feeds** — [Ready-to-use RSS sources](data/curated_feeds.json) for 19 languages (Arabic, Chinese, French, German, Japanese, Korean, Spanish, and more)
- **Difficulty estimation** — Books are classified easy/medium/hard based on subject matter and your known vocabulary
- **Adaptive recommendations** — Suggestions improve as you learn more words

### Built-in Enrichment

- **Wiktionary definitions** — Starter vocabulary comes pre-enriched with translations and definitions from open sources
- **Click-to-translate** — Instant dictionary lookups while reading
- **Bulk translation** — Translate multiple new words at once
- **Text-to-speech** — Hear pronunciation of words

### Reading & Review

- **40+ languages supported** — Roman, right-to-left, and East-Asian writing systems
- **Audio integration** — Sync audio tracks with your texts
- **Spaced repetition** — Review words at optimal intervals, always in context
- **Progress tracking** — Statistics to monitor your learning
- **Multi-word selection** — Click and drag to select phrases

### More Features

| Feature | Description |
| --- | --- |
| Mobile support | Responsive design for phones and tablets, plus the native [Lukaisu](https://github.com/lukaisu/lukaisu) app |
| Themes | Customizable appearance |
| Keyboard shortcuts | Navigate efficiently while reading |
| Video embedding | Include videos from YouTube and other platforms |
| MeCab integration | Japanese word-by-word translation |
| Position memory | Resume reading where you left off |
| Anki export | Export vocabulary to Anki for additional review |

## Installation

### Docker (Recommended)

Works on any OS with Docker installed.

```bash
git clone https://github.com/lukaisu/lukaisu-server.git
cd lukaisu-server

# Optional: customize settings (database password, etc.)
cp .env.example .env
# Edit .env with your preferences

docker compose up
```

Access at <http://localhost:8010/>. Configuration is done via the `.env` file
(see `.env.example` for all options).

### Manual Installation (Windows/macOS/Linux)

1. Install prerequisites: PHP 8.2+, MySQL/MariaDB, a web server (Apache/Nginx)
2. Clone or download the repository
3. Configure the database:

   ```bash
   cp .env.example .env
   # Edit .env with your database credentials
   ```

4. Install dependencies and build assets:

   ```bash
   composer install
   npm install && npm run build:all
   ```

See the [`docs-src/`](docs-src/) guides for detailed instructions.

## Requirements

| Component | Version |
| --- | --- |
| PHP | 8.2, 8.3, 8.4, or 8.5 |
| MySQL/MariaDB | 5.7+ / 10.3+ |
| PHP Extensions | mysqli, mbstring, dom, zip (for EPUB support) |

For development, you'll also need [Composer](https://getcomposer.org/) and [Node.js](https://nodejs.org/) 18+.

## Documentation

Documentation sources live in [`docs-src/`](docs-src/):

- **[User Guide](docs-src/guide/getting-started.md)** — Getting started and usage
- **[Developer Docs](docs-src/developer/)** — Architecture and contribution guide
- **[CLAUDE.md](CLAUDE.md)** — Architecture overview and common commands

## Contributing

Contributions are welcome! Here's how to set up a development environment:

```bash
git clone https://github.com/lukaisu/lukaisu-server.git
cd lukaisu-server
composer install --dev
npm install
```

### Development Commands

```bash
# Run tests
composer test              # PHP tests with coverage
npm test                   # Frontend tests

# Code quality
./vendor/bin/psalm         # Static analysis
npm run lint               # ESLint
npm run typecheck          # TypeScript checking

# Build assets
npm run dev                # Development server with HMR
npm run build:all          # Production build
```

## Alternatives

If Lukaisu Server doesn't fit your needs, consider these projects:

- **[Learning with Texts (LWT)](https://github.com/HugoFara/lwt)** — The upstream project this fork is derived from
- **[LUTE v3](https://github.com/jzohrab/lute-v3)** — Modern rewrite using Python/Flask, actively developed
- **[LinguaCafe](https://github.com/simjanos-dev/LinguaCafe)** — Beautiful Vue.js/PHP implementation
- **[FLTR](https://sourceforge.net/projects/foreign-language-text-reader/)** — Java desktop app by LWT's original author

## License

This project is released into the **public domain** under the
[Unlicense](LICENSE), inherited from Learning with Texts. You're free to use,
modify, and distribute it however you like. See [NOTICE](NOTICE) for attribution.

---

<p align="center">
  <strong>Happy reading, happy learning!</strong>
</p>
