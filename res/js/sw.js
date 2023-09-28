async function loader(resource, options = {})
{
	const response = await fetch(resource, options);
	let type = response.headers.get('content-type') || 'application/octet-stream', blob;
	if (options.mask || type.startsWith('@'))
	{
		return response;
		const reader = response.body.getReader(), key = new Uint8Array(8), buffer = [];
		for (let read, len = 0, offset = 0;;)
		{
			read = await reader.read();
			if (read.done)
			{
				break;
			}
			if (len < 8)
			{
				//console.log('keyload...')
				let i = 0;
				while (i < read.value.length)
				{
					key[len++] = read.value[i++];
					//console.log('keyload-' + i)
					if (len > 7)
					{
						//console.log('keyloaded over')
						break;
					}
				}
				if (len < 8)
				{
					//console.log('keyload contiune')
					continue;
				}
				//console.log('keyloaded finish')
				read.value = read.value.slice(i);
			}
			//console.log('payload...')
			for (let i = 0; i < read.value.length; ++i)
			{
				read.value[i] = read.value[i] ^ key[offset++ % 8];
			}
			buffer[buffer.length] = read.value;
		}
		console.log('act mask');
		type = options.type || type.startsWith('@') ? type.substring(1) : type;
		blob = new Blob(buffer, {type});
		return new Response(blob, response);
	}
	else
	{
		return response;
		blob = await response.blob();
	}

	
	return new Response(blob);

	switch (options.type || type.split(';')[0])
	{
		case 'application/json': return JSON.parse(await blob.text());
		case 'text/modify': return [response.url, await blob.text(), Object.fromEntries(response.headers.entries())];
		case 'text/plain': return blob.text();
		default: return blob;
	}
}


if (self.window)
{
	const script = document.currentScript;
	self.addEventListener('DOMContentLoaded', () =>
	{
		//try {
			navigator.serviceWorker.register(script.src, {scope: '?'}).then(registration =>
				{
					//console.log('ServiceWorker registration successful with scope: ', registration.scope);
					// registration.active.postMessage({
					// 	a: 123,
					// 	b: 456
					// });
				});
		// } catch (error) {
		// 	alert(error);
		// }


			// navigator.serviceWorker.ready.then(registration =>
			// 	{
			// 		registration.active.postMessage("Hi service worker");
			// 	});
	});
	function aa(url)
	{
		fetch(url);
	}
}
else
{
	// let config;
	// self.addEventListener('message', event =>
	// {
	// 	console.log(event.data);

	// });
	// console.log('event', self);

	// const putInCache = async (request, response) => {
	// 	const cache = await caches.open("v1");
	// 	await cache.put(request, response);
	//   };
	  
	//   const cacheFirst = async (request) => {
	// 	const responseFromCache = await caches.match(request);
	// 	if (responseFromCache) {
	// 		console.log('use cache');
	// 	  return responseFromCache;
	// 	}
	// 	const responseFromNetwork = await fetch(request);
	// 	putInCache(request, responseFromNetwork.clone());
	// 	return responseFromNetwork;
	//   };


	self.addEventListener('fetch', event =>
	{
		const url = new URL(event.request.url);

		console.log(self.location, url );

		
		return event.respondWith(caches.match(event.request).then(async response =>
		{
			//console.log(event.request.url, response)
			if (response)
			{
				console.log('cache hit');
				return response;
			}
			console.log('cache miss');
			//return event.respondWith(cacheFirst(event.request));
			


			
			
			
			if (event.request.url.endsWith('asd.jpg'))
			{

				console.log('loader');
				const r = await fetch('/pwa/logo.png');
				caches.open('v1').then(cache =>{
					cache.put(event.request, r.clone());
				});
				return r;

				// return loader('/pwa/logo.png', {mask: true}).then(function(response){

				// 	caches.open('v1').then(cache =>{
				// 		console.log('cache')
				// 		cache.put(event.request, response.clone());



					
				// 	});

					
			
					
				// 	return response;
				// });
			}
			else{
				return fetch(event.request);
			}



			
				
		}));
	});

	// self.addEventListener("notificationclick", event => console.log(event));
	// self.addEventListener('sync', function (e) {
	// 	console.log('sync', e)
	// });

	// var cachedResponse = caches
	// .match(event.request)
	// .catch(function () {
	//   return fetch(event.request);
	// })
	// .then(function (response) {
	//   caches.open("v1").then(function (cache) {
	// 	cache.put(event.request, response);
	//   });
	//   return response.clone();
	// })
	// .catch(function () {
	//   return caches.match("/sw-test/gallery/myLittleVader.jpg");
	// });

}