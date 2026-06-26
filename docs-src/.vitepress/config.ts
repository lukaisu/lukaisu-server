import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'Lukaisu Server Documentation',
  description: 'Lukaisu Server - Documentation',
  base: '/lukaisu-server/',
  outDir: '../docs',
  srcDir: '.',
  cleanUrls: true,

  // Exclude API docs from processing
  srcExclude: ['**/api/**'],

  // Ignore dead links for localhost URLs (used in examples) and changelog references
  ignoreDeadLinks: [
    /^http:\/\/localhost/,
    /CHANGELOG$/,
    /^\/api\//,
    // Server docs link to source files outside the docs root (the Python edge).
    /services\/nlp/
  ],

  head: [
    ['link', { rel: 'icon', href: '/lukaisu-server/assets/images/lukaisu_icon_48.png' }],
    ['link', { rel: 'apple-touch-icon', href: '/lukaisu-server/assets/images/lukaisu_icon_180.png' }]
  ],

  themeConfig: {
    logo: '/assets/images/lukaisu_icon_192.png',
    siteTitle: 'Lukaisu Server Docs',

    nav: [
      { text: 'Home', link: '/' },
      { text: 'Getting Started', link: '/guide/getting-started' },
      {
        text: 'User Guide',
        items: [
          { text: 'Installation', link: '/guide/installation' },
          { text: 'Post-Installation', link: '/guide/post-installation' },
          { text: 'How to Use', link: '/guide/how-to-use' },
          { text: 'FAQ', link: '/guide/faq' }
        ]
      },
      { text: 'Reference', link: '/reference/features' },
      { text: 'Developer', link: '/developer/api' },
      { text: 'Server', link: '/server/local-first' },
      { text: 'Changelog', link: '/changelog' }
    ],

    sidebar: {
      '/server/': [
        {
          text: 'Server (Local-First)',
          items: [
            { text: 'Local-First Migration', link: '/server/local-first' },
            { text: 'Edge HTTP Contract', link: '/server/http-contract' },
            { text: 'Sync Contract (design)', link: '/server/sync-contract' },
            { text: 'Auth (design note)', link: '/server/auth' },
            { text: 'PHP View Retirement (plan)', link: '/server/php-view-retirement' }
          ]
        }
      ],
      '/guide/': [
        {
          text: 'Getting Started',
          items: [
            { text: 'Introduction', link: '/guide/getting-started' },
            { text: 'Installation', link: '/guide/installation' },
            { text: 'Upgrading', link: '/guide/upgrade' },
            { text: 'Post-Installation', link: '/guide/post-installation' }
          ]
        },
        {
          text: 'Using Lukaisu Server',
          items: [
            { text: 'How to Learn', link: '/guide/how-to-learn' },
            { text: 'How to Use', link: '/guide/how-to-use' },
            { text: 'FAQ', link: '/guide/faq' }
          ]
        },
        {
          text: 'Troubleshooting',
          items: [
            { text: 'WordPress Integration', link: '/guide/troubleshooting/wordpress' }
          ]
        }
      ],
      '/reference/': [
        {
          text: 'Reference',
          items: [
            { text: 'Features', link: '/reference/features' },
            { text: 'New Features', link: '/reference/new-features' },
            { text: 'Keyboard Shortcuts', link: '/reference/keyboard-shortcuts' },
            { text: 'Language Setup', link: '/reference/language-setup' },
            { text: 'Text Parsers', link: '/reference/parsers' },
            { text: 'Lemmatization', link: '/reference/lemmatization' },
            { text: 'Term Scores', link: '/reference/term-scores' },
            { text: 'Export Templates', link: '/reference/export-templates' },
            { text: 'Database Schema', link: '/reference/database-schema' },
            { text: 'Restrictions', link: '/reference/restrictions' }
          ]
        }
      ],
      '/developer/': [
        {
          text: 'Developer Guide',
          items: [
            { text: 'API Reference', link: '/developer/api' },
            { text: 'Contributing', link: '/developer/contributing' },
            { text: 'Security Followups', link: '/developer/security-followups' }
          ]
        },
        {
          text: 'Code Documentation',
          items: [
            { text: 'PHP API (phpDoc)', link: '/api/php/' },
            { text: 'JavaScript API (JSDoc)', link: '/api/js/' }
          ]
        }
      ],
      '/legal/': [
        {
          text: 'Legal',
          items: [
            { text: 'License', link: '/legal/license' },
            { text: 'Third-Party Licenses', link: '/legal/third-party-licenses' },
            { text: 'Links', link: '/legal/links' }
          ]
        }
      ]
    },

    socialLinks: [
      { icon: 'github', link: 'https://github.com/lukaisu/lukaisu-server' }
    ],

    search: {
      provider: 'local'
    },

    footer: {
      message: 'Released into the Public Domain under the Unlicense.',
      copyright: 'Lukaisu Server Community'
    },

    editLink: {
      pattern: 'https://github.com/lukaisu/lukaisu-server/edit/main/docs-src/:path',
      text: 'Edit this page on GitHub'
    }
  },

  // Raise esbuild target so VitePress's internal client bundle (which uses
  // destructuring) can be transpiled. Default targets like "chrome87" trip up
  // esbuild on destructuring patterns; "esnext" leaves them intact.
  vite: {
    build: {
      target: 'esnext'
    }
  }
})
