async function loader(resource, options = {})
{
	const response = await fetch(resource, options);








	if (options.mask || response.headers.get('mask-key'))
	{
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
		return new Response(new Blob(buffer, {type: response.headers.get('content-type')}), response);
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




	console.log( Array.from(document.querySelectorAll('link[rel=dns-prefetch],link[rel=preconnect]')).map(link => link.href) );


	self.addEventListener('DOMContentLoaded', () =>
	{
		//try {
			navigator.serviceWorker.register(script.src, {scope: '?'}).then(registration =>
				{

					
					//console.log(registration);

					//console.log('ServiceWorker registration successful with scope: ', registration.scope);
					//registration.active.postMessage(Array.from(document.querySelectorAll('link[rel=dns-prefetch],link[rel=preconnect]')).map(link => link.href).join(','));



				// navigator.serviceWorker.ready.then(registration =>
				// {
				// 	registration.active.postMessage("Hi service worker");
				// });


				});
		// } catch (error) {
		// 	alert(error);
		// }



	});
	function aa(url)
	{
		fetch(url).then(r => r.text()).then(d => console.log(d));
	}
}
else
{
	const config = {};

	
	let resources = location.origin;
	console.log( location.origin );

	self.addEventListener('message', event =>
	{
		// if (typeof event.data !== 'string')
		// {
		// 	return Object.assign(config, event.data);
		// }

		resources = new Promise((resolve, reject) =>
		{
			const resources = event.data.split(',');
			if (resources.length)
			{
				const controller = new AbortController;
				Promise.any(Array.from(resources).map(link =>
					fetch(`${link.href}`, {cache: 'no-cache', signal: controller.signal}))).then( response => {

						console.log( response );

						//resolve(response.url.slice(0, response.url.indexOf('/', 8)), await response.blob(), controller.abort())
					} , reject);
			}
			else
			{

				resolve(location.origin);
			}
		});

	});
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

	console.log(self.location);

	// self.addEventListener('fetch', event => event.respondWith(caches.match(event.request).then(async response =>{


	// 	return response || (
	// 		/\?mask\d{10}$/.test(event.request.url) || 
	// 	)
	// })));

	self.addEventListener('fetch', event =>
	{

		return event.respondWith(caches.match(event.request).then(async response =>
		{
			

			//console.log(event.request.url, response)
			if (response)
			{
				console.log('cache hit');
				return response;
			}
			console.log('cache miss');

			//'http://127.0.0.1/asda/asdaweawe?mask456548796'
			//return event.respondWith(cacheFirst(event.request));
			


			
			//1695742222
			
			console.log( '----', event.request.url.startsWith(self.location.origin) );
			

			if (event.request.url.startsWith(self.location.origin))
			{
				const url = new URL(event.request.url);
				if (self.location.pathname === url.pathname)
				{
					console.log(self.location.pathname , url.pathname)

					if (url.search.startsWith('?/'))
					{

						
						console.log(`https://res.rstar.cloud/${url.search.substring(2)}`)
						return loader(`https://res.rstar.cloud/${url.search.substring(2)}`, {mask: /\?mask\d{10}$/i.test(event.request.url)});
					}
					else
					{
						return loader(event.request);
					}
				}
				else
				{
					return loader(event.request);
				}
				//return new Promise(() => {});


				//console.log('--->', url, self.location.pathname === url.pathname);

				
	
				//const req = new Request('/pwa/bkdown');

				//console.log(req);

				return /\?mask\d{10}$/.test(event.request.url)
					? loader(event.request, {mask: true})
					: loader(event.request);

				// return loader('/pwa/bkdown', {mask: true}).then(function(response){

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