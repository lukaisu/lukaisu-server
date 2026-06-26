/**
 * Documentation Pre-build Script
 *
 * Copies root markdown files to the docs-src directory before VitePress build.
 * This allows files like CHANGELOG.md, CONTRIBUTING.md, and UNLICENSE.md to
 * remain in the repository root while being included in the documentation.
 */

import { mkdirSync, existsSync, readFileSync, writeFileSync, cpSync } from 'fs'
import { dirname, join } from 'path'
import { fileURLToPath } from 'url'

const __filename = fileURLToPath(import.meta.url)
const __dirname = dirname(__filename)
const rootDir = join(__dirname, '..')
const docsDir = join(rootDir, 'docs-src')

/**
 * Files to copy from root to docs-src
 * Each entry has:
 *   - src: Source path relative to project root
 *   - dest: Destination path relative to docs-src
 *   - frontmatter: Optional frontmatter to prepend
 */
const filesToCopy = [
  {
    src: 'CHANGELOG.md',
    dest: 'changelog.md',
    frontmatter: `---
title: Changelog
description: Version history and release notes for Lukaisu Server
---

`
  },
  {
    src: 'CONTRIBUTING.md',
    dest: 'developer/contributing.md',
    frontmatter: `---
title: Contributing
description: How to contribute to the Lukaisu Server project
---

`
  }
]

console.log('📄 Documentation Pre-build Script')
console.log('==================================')

for (const { src, dest, frontmatter } of filesToCopy) {
  const srcPath = join(rootDir, src)
  const destPath = join(docsDir, dest)

  if (existsSync(srcPath)) {
    // Ensure destination directory exists
    mkdirSync(dirname(destPath), { recursive: true })

    // Read source file
    let content = readFileSync(srcPath, 'utf-8')

    // Add frontmatter (if specified) followed by a "do not edit" banner so the
    // generated copy is self-documenting. The banner must come AFTER the
    // frontmatter block, which has to stay at the very top of the file.
    const banner = `<!--
  AUTO-GENERATED — DO NOT EDIT.
  Copied from \`${src}\` by scripts/docs-pre-build.js at build time.
  This file is gitignored; edit the source (\`${src}\`) instead.
-->

`
    if (frontmatter) {
      // Remove existing frontmatter if present
      content = content.replace(/^---[\s\S]*?---\n*/, '')
      content = frontmatter + banner + content
    } else {
      content = banner + content
    }

    // Escape Vue template syntax {{ }} to prevent VitePress from interpreting it
    // Only escape occurrences outside of code blocks (backticks)
    // Replace {{ with escaped version
    content = content.replace(/(?<!`)\{\{([^}`]+)\}\}(?!`)/g, (match, inner) => {
      // Use HTML entity escaping
      return `&#123;&#123;${inner}&#125;&#125;`
    })

    // Rewrite repo-root-relative links into docs-src/* so they resolve
    // inside VitePress. CHANGELOG.md uses paths like
    // `docs-src/guide/foo.md` which work on GitHub (where CHANGELOG sits
    // at the repo root) but become `docs-src/docs-src/...` when the file
    // is copied into docs-src/. Strip the prefix for the copied form.
    content = content.replace(
      /\]\(docs-src\/([^)]+)\)/g,
      (_match, target) => `](./${target})`
    )

    // Write to destination
    writeFileSync(destPath, content, 'utf-8')
    console.log(`✓ Copied ${src} -> docs-src/${dest}`)
  } else {
    console.warn(`⚠ Source file not found: ${src}`)
  }
}

// Copy assets/images to public folder for VitePress
const imagesSource = join(rootDir, 'assets', 'images')
const imagesDest = join(docsDir, 'public', 'assets', 'images')

if (existsSync(imagesSource)) {
  mkdirSync(imagesDest, { recursive: true })
  cpSync(imagesSource, imagesDest, { recursive: true })
  console.log(`✓ Copied assets/images -> docs-src/public/assets/images/`)
}

console.log('')
console.log('Pre-build complete!')
