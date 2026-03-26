
const cacheName = 'cache-240811-02';

const resToCache = [
	'/',
    '/manifest.json',
	'/ICO/offline.html'
];

self.addEventListener('install', event => {
	console.log('Install event!');
	event.waitUntil(
		caches.open(cacheName)
			.then(cache => {
				return cache.addAll(resToCache);
			}).then(function(){
				return self.skipWaiting();
			})
	);
});

self.addEventListener('activate', event => {
	event.waitUntil(
		caches.keys().then(keys => {
			return Promise.all(keys
				.filter(key => key !== cacheName)
				.map(key => caches.delete(key))
			)
		})
	);
});

self.addEventListener('fetch', function(event) {
  event.respondWith(
    caches.match(event.request).then(function(response) {
      if (response) {
        return response;
      }
      return fetch(event.request).then(function(response) {
        return response
      });
    }).catch(function() {
      return caches.match('/ICO/offline.html');
    })
  );
});
