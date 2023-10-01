async function loader(resource, options = {})
{
	const response = await fetch(resource, options), key = response.headers.get('mask-key')
		? response.headers.get('mask-key').match(/[0-f]{2}/gi).map(value => parseInt(value, 16))
		: (options.mask ? [] : null);
	if (Array.isArray(key))
	{
		const reader = response.body.getReader(), buffer = [];
		for (let read, offset = 0;;)
		{
			read = await reader.read();
			if (read.done)
			{
				break;
			}
			if (key.length < 8)
			{
				//console.log('keyload...')
				let i = 0;
				while (i < read.value.length)
				{
					//console.log('keyload-' + i)
					key[key.length] = read.value[i++];
					if (key.length > 7)
					{
						//console.log('keyloaded over')
						break;
					}
				}
				if (key.length < 8)
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
				//read.value[i] = chr(key[i % 8] ^ key[i % 8] = ord($bindata[$i]));
				read.value[i] = read.value[i] ^ key[offset++ % 8];
			}
			buffer[buffer.length] = read.value;
		}
		return new Response(new Blob(buffer, {type: response.headers.get('content-type')}), response);
	}
	return response;





	// if (options.mask || response.headers.get('mask-key'))
	// {
	// 	const reader = response.body.getReader(), key = new Uint8Array(8), buffer = [];
	// 	for (let read, len = 0, offset = 0;;)
	// 	{
	// 		read = await reader.read();
	// 		if (read.done)
	// 		{
	// 			break;
	// 		}
	// 		if (len < 8)
	// 		{
	// 			//console.log('keyload...')
	// 			let i = 0;
	// 			while (i < read.value.length)
	// 			{
	// 				key[len++] = read.value[i++];
	// 				//console.log('keyload-' + i)
	// 				if (len > 7)
	// 				{
	// 					//console.log('keyloaded over')
	// 					break;
	// 				}
	// 			}
	// 			if (len < 8)
	// 			{
	// 				//console.log('keyload contiune')
	// 				continue;
	// 			}
	// 			//console.log('keyloaded finish')
	// 			read.value = read.value.slice(i);
	// 		}
	// 		//console.log('payload...')
	// 		for (let i = 0; i < read.value.length; ++i)
	// 		{
	// 			read.value[i] = read.value[i] ^ key[offset++ % 8];
	// 		}
	// 		buffer[buffer.length] = read.value;
	// 	}
	// 	return new Response(new Blob(buffer, {type: response.headers.get('content-type')}), response);
	// }
	// else
	// {
	// 	return response;
	// 	blob = await response.blob();
	// }

	
	// return new Response(blob);

	// switch (options.type || type.split(';')[0])
	// {
	// 	case 'application/json': return JSON.parse(await blob.text());
	// 	case 'text/modify': return [response.url, await blob.text(), Object.fromEntries(response.headers.entries())];
	// 	case 'text/plain': return blob.text();
	// 	default: return blob;
	// }
}


if (self.window)
{
	const script = document.currentScript, resources = new Promise((resolve, reject) =>
	{
		const resources = document.querySelectorAll('link[rel=dns-prefetch],link[rel=preconnect]');
		if (resources.length)
		{
			const controller = new AbortController;
			Promise.any(Array.from(resources).map(link =>
				fetch(`${link.href}`, {cache: 'no-cache', signal: controller.signal}))).then(response =>
					controller.abort(resolve(new URL(response.url).origin)), reject);
		}
		else
		{
			resolve(location.origin);
		}
	});




	self.addEventListener('DOMContentLoaded', async () =>
	{



		//try {
			navigator.serviceWorker.register(script.src, {scope: '?'}).then(registration =>
				{
					//registration.update()
				
					registration.active.postMessage({authorization: localStorage.getItem('token')});
					resources.then(origin => registration.active.postMessage({origin}));
					
		
					

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
	const config = {origin: location.origin};


	self.addEventListener('message', event =>
	{
		Object.assign(config, event.data);
		console.log(config);
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

	self.addEventListener('fetch', event => event.respondWith(caches.match(event.request).then(async response =>
	{
		

		console.log('>>>>>>>>>>>>>',event.request.url)
		if (response)
		{
			console.log('cache hit');
			return response;
		}
		console.log('cache miss');

		//'http://127.0.0.1/asda/asdaweawe?mask456548796'
		//return event.respondWith(cacheFirst(event.request));
		


		
		//1695742222
		
		//console.log( '----', event.request.url.startsWith(self.location.origin) );
		

		if (event.request.url.startsWith(self.location.origin))
		{
			
			const url = new URL(event.request.url);
			if (self.location.pathname === url.pathname)
			{
				

				if (url.search.startsWith('?/'))
				{

					
					console.log(`${config.origin}${url.search.substring(1)}`)
					return loader(`${config.origin}${url.search.substring(1)}`, {mask: /\?mask\d{10}$/i.test(event.request.url)});
				}
				else
				{
					//event.request.headers.set('Authorization', `Bearer ${config.authorization}`);
					//console.log( Object.fromEntries(event.request.headers.entries())  );
					return loader(event.request, config.authorization ? {headers: {Authorization: `Bearer ${config.authorization}`}} : {});
				}
			}
			else
			{

				return loader(event.request, {mask: /\?mask\d{10}$/i.test(event.request.url)});
			}
		
		}
		else{
			return fetch(event.request);
		}



		
			
	})));

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