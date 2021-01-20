module.exports = {
  title: 'Laravel Cashier Mollie v2.x',
  description: 'Laravel Cashier provides an expressive, fluent interface to subscriptions using Mollie\'s billing services.',
  head: [
    ['link', { rel: "apple-touch-icon", sizes: "180x180", href: "/apple-touch-icon.png"}],
    ['link', { rel: "icon", type: "image/png", sizes: "32x32", href: "/favicon-32x32.png"}],
    ['link', { rel: "icon", type: "image/png", sizes: "16x16", href: "/favicon-16x16.png"}],
    ['link', { rel: "manifest", href: "/manifest.json"}],
    ['link', { rel: "mask-icon", href: "/safari-pinned-tab.svg", color: "#ffffff"}],
    ['meta', { name: "theme-color", content: "#ffffff"}],
    ['meta', { name: "viewport", content: "width=device-width"}],
    ['script', { src: "https://cdn.usefathom.com/script.js", spa: "auto", site: "ANMLOYPH", defer:true}]

  ],
  themeConfig: {
    logo: '/assets/img/cashier-mollie.svg',
    repo: 'laravel/cashier-mollie',
    authors: [
      {
        'name': 'Mollie.com',
        'email': 'support@mollie.com',
        'homepage': 'https://www.mollie.com',
        'role': 'Owner'
      },
      {
        'name': 'Sander van Hooft',
        'email': 'info@sandervanhooft.com',
        'homepage': 'https://www.sandervanhooft.com',
        'role': 'Developer'
      }
    ],
    docsDir: 'docs',
    editLinks: true,
    editLinkText: 'Improve this page (submit a PR)',
    domain: 'https://www.cashiermollie.com',
    displayAllHeaders: true,
    sidebar: [
        ['/', 'Introduction'],
        '/01-instalation',
        '/02-subscriptions',
        '/03-trials',
        '/04-oneoffcharges',
        '/05-metered',
        '/06-customer',
        '/07-invoices',
        '/08-events',
        '/09-webhook',
        '/10-testing',
        '/11-faq',

    ]
  },
  base: '/cashier-mollie/',
  plugins: [
      ['seo', {
        siteTitle: (_, $site) => $site.title,
        title: $page => $page.title,
        description: $page => $page.frontmatter.description,
        author: (_, $site) => $site.themeConfig.authors[0],
        tags: $page => $page.frontmatter.tags,
        twitterCard: _ => 'summary_large_image',
        type: $page => 'website',
        url: (_, $site, path) => ($site.themeConfig.domain || '') + path,
        image: ($page, $site) => "https://ciungulete.github.io/cashier-mollie/assets/pages/laravelcashiermollie.jpg",
        publishedAt: $page => $page.frontmatter.date && new Date($page.frontmatter.date),
        modifiedAt: $page => $page.lastUpdated && new Date($page.lastUpdated),
    }],
    '@vuepress/last-updated',
    ['@vuepress/pwa', {
      serviceWorker: true,
      updatePopup: true
    }]
  ]
}
