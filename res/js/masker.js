if (self.window)
{
	const script = document.currentScript, init = new Promise(resolve =>
		addEventListener('DOMContentLoaded', () =>
			navigator.serviceWorker.register(script.src, {scope: location.pathname}).then(() =>
				navigator.serviceWorker.ready.then(registration => resolve(registration.active)))));

	init.then(sw =>
	{
		sw.postMessage(localStorage.getItem('token'));
		if ('reload' in script.dataset)
		{
			return location.assign(script.dataset.reload);
		}
		const speedtest = callback => new Promise(resolve =>
		{
			const resources = Array.from(document.querySelectorAll([
				'link[rel=dns-prefetch]',
				'link[rel=preconnect]'].join(','))).map(link => link.href);
			if (resources.includes(sessionStorage.getItem('origin')))
			{
				return resolve(new URL(sessionStorage.getItem('origin')).origin);
			}
			if (resources.length)
			{
				const controller = new AbortController;
				Promise.any(resources.map(url =>
					fetch(url, {cache: 'no-cache', signal: controller.signal}))).then(response =>
						controller.abort(sessionStorage.setItem('origin', response.url) || resolve(new URL(response.url).origin)));
			}
		}).then(callback);
		speedtest(origin => sw.postMessage(origin));
		addEventListener('online', () => sessionStorage.removeItem('origin') || speedtest(origin => sw.postMessage(origin)));
		if ('splashscreen' in script.dataset)
		{
			masker.session_once('splashscreen', () =>
			{
				masker.open(script.dataset.splashscreen);
				return script.dataset.splashscreen;
			});
		}
	});
	function masker(resource, options = {})
	{
		if (options.body)
		{

		}
		fetch(resource, options).then(r => r.text()).then(d => console.log(d));
	}
	masker.authorization = signature => localStorage.setItem('token', signature) || init.then(sw => sw.postMessage(signature));
	masker.then = callback => init.then(callback);
	// masker.once = callback => sessionStorage.getItem('token') === localStorage.getItem('token')
	// 	//|| sessionStorage.setItem('token', localStorage.getItem('token'))
	// 	|| init.then(callback);
	masker.session_once = (name, callinit) => init.then(() => sessionStorage.getItem(name) || sessionStorage.setItem(name, callinit()));
	
	masker.open = resources => init.then(() =>
	{
		const frame = document.createElement('iframe');
		frame.src = resources;
		frame.style.cssText = [
			'position: fixed',
			'inset: 0',
			'width: 100%',
			'height: 100%',
			'border: none'
		].join(';');
		document.body.appendChild(frame).contentWindow.addEventListener('message', event =>
		{
			switch (event.data)
			{
				case 'close': return document.body.removeChild(frame);
			}
		});
		return frame;
	});
}
else
{
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
	let token, origin;
	const
	authorization = new Promise(resolve => token = resolve),
	resources = new Promise(resolve => origin = resolve);

	self.addEventListener('message', event =>
	{
		const options = {priority: 'high', headers: {asd: 1}};
		if (typeof event.data === 'string' && event.data.length)
		{
			if (/^https?:\/\//i.test(event.data))
			{
				return typeof origin === 'string'
					? origin = event.data
					: origin(origin = event.data)
			}
			options.headers.Authorization = `Bearer ${event.data}`;
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
				return event.request.url === self.location.href
					? fetch(self.location.href)
					: authorization.then(() => request(event.request, token));
			}
			return request(event.request, true);
		}
		return fetch(event.request);
	})));
}