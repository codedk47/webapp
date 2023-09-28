async function loader(resource, options = {})
{
	const response = await fetch(resource, options);
	let type = response.headers.get('content-type') || 'application/octet-stream', blob;
	if (options.mask || type.startsWith('@'))
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
		type = options.type || type.startsWith('@') ? type.substring(1) : type;
		blob = new Blob(buffer, {type});
	}
	else
	{
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
	if ('serviceWorker' in navigator)
	{
		self.addEventListener('DOMContentLoaded', () =>
		{
			navigator.serviceWorker.register(script.src, {scope: '?'}).then(registration =>
			{
				console.log('ServiceWorker registration successful with scope: ', registration.scope);
				//registration.active.postMessage("Hi service worker");
			});

				// navigator.serviceWorker.ready.then(registration =>
				// 	{
				// 		registration.active.postMessage("Hi service worker");
				// 	});
		});
	}
	function aa(q)
	{
		fetch('?aaaa').then(d => {

			console.log(d)
		});
	}
}
else
{
	console.log('event', self);
	self.addEventListener('fetch', event =>
	{
		//event.request.url

		console.log(222,event.request.url );

		//console.log('event', event);
		return event.respondWith(caches.match(event.request).then(response =>
		{
			
			if (event.request.url.endsWith('asd.jpg'))
			{
				console.log('loader');
				return loader('/pwa/bkdown', {mask: true});
			}


		
			return fetch(event.request);
				
		}));
	});
	// self.addEventListener('message', event =>
	// {
	// 	console.log(event);

	// });
	// self.addEventListener("notificationclick", event => console.log(event));
	// self.addEventListener('sync', function (e) {
	// 	console.log('sync', e)
	// });



}