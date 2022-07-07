// Install the service worker.
this.addEventListener('install', event =>
{
	event.waitUntil(caches.open('appclound').then(cache =>
	{
		// Path is relative to the origin, not the app directory.
		cache.addAll([//'/webapp/app/news/',
			'/webapp/app/news/index.html',
			'/webapp/app/news/cloud-dark.svg',
			'/webapp/app/news/cloud-play-icon.svg',
			'/webapp/app/news/driver.js',
			'/webapp/app/news/wplayer.js',
			'/webapp/app/news/manifest.json'
			])//.then(() => console.log('Success! App is available offline!'));
	}));
});
self.addEventListener('fetch', event => event.respondWith(caches.match(event.request).then(response => response || fetch(event.request))));