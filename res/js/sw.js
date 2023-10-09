async function request(resource, options)
{
	const response = await fetch(resource, options instanceof Object ? options : null), key = response.headers.get('mask-key')
		? response.headers.get('mask-key').match(/[0-f]{2}/gi).map(value => parseInt(value, 16))
		: (options && /\?mask\d{10}$/i.test(response.url) ? [] : null);
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
				read.value[i] ^= key[offset++ % 8];
			}
			buffer[buffer.length] = read.value;
		}
		return new Response(new Blob(buffer, {type: response.headers.get('content-type')}), response);
	}
	return response;
}
if (self.window)
{
	const script = document.currentScript;
	window.authorization = async signature =>
		localStorage.setItem(script.dataset.authorization, signature)
			|| navigator.serviceWorker.ready.then(registration => registration.active.postMessage({signature}));


	// const configs = new Promise(resolve => {
	// 	sessionStorage.getItem('configs')
	// 	? resolve(JSON.parse(sessionStorage.getItem('configs')))
	// 	: request(script.src).then(response => response.json()).then(resolve)
	// });


	
	








	window.addEventListener('DOMContentLoaded', async () =>
	{
		const
		resources = Array.from(document.querySelectorAll([
			'link[rel=dns-prefetch]',
			'link[rel=preconnect]'].join(','))).map(link => link.href);



		

		//try {
			navigator.serviceWorker.register(script.src, {scope: '?'}).then(registration =>
				{
					
					//registration.update()
				
					//registration.active.postMessage({authorization: localStorage.getItem('token')});
					// resources.then(origin => {
					// 	console.log('origin:', origin);
					// 	registration.active.postMessage({origin})
					// });
					
					// registration.active.postMessage(Array.from(document.querySelectorAll([
					// 	'link[rel=dns-prefetch]',
					// 	'link[rel=preconnect]'].join(','))).map(link => link.href));
					

					//console.log(registration);

					//console.log('ServiceWorker registration successful with scope: ', registration.scope);
					//registration.active.postMessage(Array.from(document.querySelectorAll('link[rel=dns-prefetch],link[rel=preconnect]')).map(link => link.href).join(','));

				//navigator.serviceWorker.ready.then(registration =>
				//{
					console.log('signature')
					registration.active.postMessage(localStorage.getItem('token'));
		
					
					if ('reload' in script.dataset)
					{
						if (sessionStorage.getItem('resources'))
						{
							registration.active.postMessage(sessionStorage.getItem('resources').split('|'));
						}
						//console.log(script.dataset.reload)
						location.assign(script.dataset.reload)
					}
					else
					{
						if (resources.length && resources.join('|') !== sessionStorage.getItem('resources'))
						{
							sessionStorage.setItem('resources', resources.join('|'));
							registration.active.postMessage(resources);
						}
					}
				//});


				});
		// } catch (error) {
		// 	alert(error);
		// }
		// navigator.serviceWorker.addEventListener("message", (event) => {
		// 	console.log('serviceWorker - event',event);
		//   });

		window.addEventListener('online', () =>
			navigator.serviceWorker.ready.then(registration =>
				registration.active.postMessage(resources)));
	});

	
	function aa(url)
	{
		fetch(url).then(r => r.text()).then(d => console.log(d));
	}
	function ss()
	{
		
	}


}
else
{
	let token, origin;
	const
		authorization = new Promise(resolve => token = resolve),
		resources = new Promise(resolve => origin = resolve);

	self.addEventListener('message', event =>
	{
		if (Array.isArray(event.data))
		{
			if (event.data.length)
			{
				const controller = new AbortController;
				Promise.any(event.data.map(url =>
					fetch(url, {cache: 'no-cache', signal: controller.signal}))).then(response =>
						controller.abort(typeof origin === 'string'
							? origin = new URL(response.url).origin
							: origin(origin = new URL(response.url).origin)));
			}
			return;
		}
		const options = {priority: 'high'};
		if (typeof event.data === 'string')
		{
			options.headers = {Authorization: `Bearer ${event.data}`};
		}
		token.length ? token(token = options) : token = options;
	});
	self.addEventListener('fetch', event => event.respondWith(caches.match(event.request).then(async response =>
	{
		if (response) return response;
		if (event.request.url.startsWith(self.location.origin))
		{
			const url = new URL(event.request.url);
			if (self.location.pathname === url.pathname)
			{
				if (url.search.startsWith('?/'))
				{
					return resources.then(() => request(`${origin}${url.search.substring(1)}`, true));
				}
				if (event.isReload)
				{
					return new Response(new Blob(['<html lang="en"><head><meta charset="utf-8">',
						`<script src="${self.location.href}" data-reload="${event.request.url}"></script>`,
						'</head><body></body></html>'], {type: 'text/html'}), {headers: {'Cache-Control': 'no-cache'}});
				}
				return self.location.href === event.request.url
					? fetch(self.location.href)
					: authorization.then(() => request(event.request, token));
			}
			return request(event.request, true);
		}
		return fetch(event.request);
	})));
}