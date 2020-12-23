/**
 * Welcome to your Workbox-powered service worker!
 *
 * You'll need to register this file in your web app and you should
 * disable HTTP caching for this file too.
 * See https://goo.gl/nhQhGp
 *
 * The rest of the code is auto-generated. Please don't update this file
 * directly; instead, make changes to your Workbox build configuration
 * and re-run your build process.
 * See https://goo.gl/2aRDsh
 */

importScripts("https://storage.googleapis.com/workbox-cdn/releases/4.3.1/workbox-sw.js");

self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});

/**
 * The workboxSW.precacheAndRoute() method efficiently caches and responds to
 * requests for URLs in the manifest.
 * See https://goo.gl/S9QRab
 */
self.__precacheManifest = [
  {
    "url": "01-instalation.html",
    "revision": "48003014f0ba3ac150f260f34ba6bd8c"
  },
  {
    "url": "02-usage.html",
    "revision": "26c7c6e5f9e4c8b39f5aa128e272f01e"
  },
  {
    "url": "03-events.html",
    "revision": "c6e2aaa4d2e3961b0b279b8c7029bff9"
  },
  {
    "url": "04-metered.html",
    "revision": "ee2f6e8a8aeb3d221d6da219fc330d40"
  },
  {
    "url": "05-faq.html",
    "revision": "07b2d4447e6a09fae3bef1e7f74cb020"
  },
  {
    "url": "06-testing.html",
    "revision": "a7c436ebdf98dc43e997e955b0826ae0"
  },
  {
    "url": "404.html",
    "revision": "e9f4010549b2170edddcddcec2afecbc"
  },
  {
    "url": "assets/css/0.styles.607a9ec2.css",
    "revision": "1f9d7b61819cfc2a4231842d5737961c"
  },
  {
    "url": "assets/favicons/android-chrome-192x192.png",
    "revision": "7f1890f254594de8c4b514dee90ed629"
  },
  {
    "url": "assets/favicons/android-chrome-384x384.png",
    "revision": "bc31b03048d4a3ba4fe82ce2389b0b38"
  },
  {
    "url": "assets/favicons/apple-touch-icon.png",
    "revision": "e94a7ab54c258ee8b9fa131d2233cab8"
  },
  {
    "url": "assets/favicons/favicon-16x16.png",
    "revision": "e8cead60a31ba0059df44368227bba35"
  },
  {
    "url": "assets/favicons/favicon-32x32.png",
    "revision": "2f21759d559a5e952851228adbb628ec"
  },
  {
    "url": "assets/favicons/mstile-150x150.png",
    "revision": "19f3e3722c9d450ecfa73e8a92aaa47a"
  },
  {
    "url": "assets/favicons/safari-pinned-tab.svg",
    "revision": "06f0ab467b31062098cffb5ff5d18bc6"
  },
  {
    "url": "assets/img/laravelcashiermollie.533e9ba9.png",
    "revision": "533e9ba96109d8f865943bf402f90083"
  },
  {
    "url": "assets/img/search.83621669.svg",
    "revision": "83621669651b9a3d4bf64d1a670ad856"
  },
  {
    "url": "assets/js/10.2aabd776.js",
    "revision": "ef432e105672631c3e838f3ca07ff4c1"
  },
  {
    "url": "assets/js/11.5e00374a.js",
    "revision": "b23d01748e66180433419ad97fa3fda5"
  },
  {
    "url": "assets/js/12.f41e7a49.js",
    "revision": "0ef1e7fb4912c491fc9b7727f6700286"
  },
  {
    "url": "assets/js/13.0489c8fc.js",
    "revision": "c1fe59aed2598dc675978c72a2b269ba"
  },
  {
    "url": "assets/js/14.0a21b4da.js",
    "revision": "6240b7ee341d64243d3d95cffbc40e88"
  },
  {
    "url": "assets/js/15.3fc088c7.js",
    "revision": "467fff3aa1829f125d1265088b708072"
  },
  {
    "url": "assets/js/2.a887f383.js",
    "revision": "9ec8d93234873251e708f0de537fb390"
  },
  {
    "url": "assets/js/3.a57d0152.js",
    "revision": "a7e5a6a75c56008d837387fd5fce4b99"
  },
  {
    "url": "assets/js/4.88013e85.js",
    "revision": "fce8f473efe7176577de725a552ef15c"
  },
  {
    "url": "assets/js/5.36aa556b.js",
    "revision": "ed71e08eaf6d4e58a483f665d157a3ff"
  },
  {
    "url": "assets/js/6.dd96bc00.js",
    "revision": "9197d34108e50ae9ab8868b1f4b4af81"
  },
  {
    "url": "assets/js/7.636cc6bd.js",
    "revision": "88f29ed1f3f8b2c8a0515f905edeea3f"
  },
  {
    "url": "assets/js/8.ebb547cf.js",
    "revision": "279ed4f6b7584996c8380b1af4cf23b7"
  },
  {
    "url": "assets/js/9.048f1141.js",
    "revision": "b6b01ba29054a7fbf467ef345420402d"
  },
  {
    "url": "assets/js/app.b406b121.js",
    "revision": "2b23915ef5ac0d5f571b273861782b84"
  },
  {
    "url": "assets/pages/laravelcashiermollie.png",
    "revision": "533e9ba96109d8f865943bf402f90083"
  },
  {
    "url": "favicon-32x32.png",
    "revision": "2f21759d559a5e952851228adbb628ec"
  },
  {
    "url": "index.html",
    "revision": "16501f735d599ddffc9448b53e384683"
  }
].concat(self.__precacheManifest || []);
workbox.precaching.precacheAndRoute(self.__precacheManifest, {});
addEventListener('message', event => {
  const replyPort = event.ports[0]
  const message = event.data
  if (replyPort && message && message.type === 'skip-waiting') {
    event.waitUntil(
      self.skipWaiting().then(
        () => replyPort.postMessage({ error: null }),
        error => replyPort.postMessage({ error })
      )
    )
  }
})
