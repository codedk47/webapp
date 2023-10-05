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



	if ('reload' in script.dataset)
	{
		console.log(script.dataset.reload)
		//location.assign(script.dataset.reload)
	}








	window.addEventListener('DOMContentLoaded', async () =>
	{
		const
		resources = Array.from(document.querySelectorAll([
			'link[rel=dns-prefetch]',
			'link[rel=preconnect]'].join(','))).map(link => link.href),
		speedtest = urls =>
		{
			const controller = new AbortController;
			return new Promise(resolve => urls.length && Promise.any(urls.map(url =>
				fetch(url, {cache: 'no-cache', signal: controller.signal}))).then(response =>
					controller.abort(sessionStorage.setItem('originurl', response.url) || resolve(response.url))));
		},
		speedfirst = new Promise(resolve => resources.includes(sessionStorage.getItem('originurl'))
			? resolve(sessionStorage.getItem('originurl'))
			: speedtest(resources).then(resolve));



		

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

				navigator.serviceWorker.ready.then(registration =>
				{
					console.log('signature')
					registration.active.postMessage({signature: localStorage.getItem('authorization')});
					speedfirst.then(originurl => registration.active.postMessage({originurl}));
				});


				});
		// } catch (error) {
		// 	alert(error);
		// }
		// navigator.serviceWorker.addEventListener("message", (event) => {
		// 	console.log('serviceWorker - event',event);
		//   });

		window.addEventListener('online', () =>
			speedtest(resources).then(originurl => console.log('reconnect') ||
				navigator.serviceWorker.ready.then(registration =>
					registration.active.postMessage({originurl}))));
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
		console.log(clients, event);
		if (typeof event.data === 'string') return;

		
		if (Object.hasOwn(event.data, 'signature'))
		{
			const options = {priority: 'high'};
			if (typeof event.data.signature === 'string')
			{
				options.headers = {Authorization: `Bearer ${event.data.signature}`};
			}
			token.length ? token(token = options) : token = options;
		}
		if (typeof event.data.originurl === 'string')
		{
			typeof origin === 'string'
				? origin = new URL(event.data.originurl).origin
				: origin(origin = new URL(event.data.originurl).origin);
		}
	});


	console.log('asdasdasd');


	self.addEventListener('fetch', event => event.respondWith(caches.match(event.request).then(async response =>
	{
		// clients.get(event.clientId).then(client => {
		// 	console.log(client.postMessage('asdasd'));
		// });
		console.log(event, event.clientId)
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
				
				if (event.clientId)
				{
					return self.location.href === event.request.url
						? fetch(self.location.href)
						: authorization.then(() => request(event.request, token));
				}
				return new Response(new Blob([`<html lang="en"><head><meta charset="utf-8"><script src="${self.location.href}" data-reload="${event.request.url}"></script></head><body></body></html>`], {type: 'text/html'}));
			}
			return request(event.request, true);
		}
		return fetch(event.request);
	})));
}