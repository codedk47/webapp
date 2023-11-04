if (self.window)
{
	const script = document.currentScript, init = new Promise(resolve =>
	{
		navigator.serviceWorker.ready.then(registration =>
		{
			navigator.serviceWorker.addEventListener('message', event =>
			{
				switch (event.data.cmd)
				{
					case 'token':
						registration.active.postMessage({pid: event.data.pid, result: localStorage.getItem('token')});
						break;
					case 'origin':
						origin.then(result => registration.active.postMessage({pid: event.data.pid, result}));
						break;
					default:
						registration.active.postMessage({pid: event.data.pid, result: null});
				}
			});
			navigator.serviceWorker.startMessages();
		});
		addEventListener('DOMContentLoaded', () =>
		{
			addEventListener('load', () => navigator.serviceWorker.register(script.src, {scope: location.pathname}));
			navigator.serviceWorker.ready.then(registration => resolve(registration.active));
		});
	}), origin = new Promise(resolve => init.then(() => 
	{
		if ('reload' in script.dataset)
		{
			return location.replace(script.dataset.reload);
		}
		const resources = Array.from(document.querySelectorAll([
			'link[rel=dns-prefetch]',
			'link[rel=preconnect]'].join(','))).map(link => link.href);
		if (resources.length)
		{
			if (resources.includes(sessionStorage.getItem('origin')))
			{
				return resolve(new URL(sessionStorage.getItem('origin')).origin);
			}
			const controller = new AbortController;
			Promise.any(resources.map(url =>
				fetch(url, {cache: 'no-cache', signal: controller.signal}))).then(response =>
					controller.abort(sessionStorage.setItem('origin', response.url) || resolve(new URL(response.url).origin)));
		}
		addEventListener('offline', () => sessionStorage.removeItem('origin'));
		// if ('splashscreen' in script.dataset)
		// {
		// 	masker.session_once('splashscreen', () =>
		// 	{
		// 		masker.open(script.dataset.splashscreen);
		// 		return script.dataset.splashscreen;
		// 	});
		// }
	}));
	function masker(resource, options = {})
	{
		if (options.body)
		{
			//const key = Math.random().toString(36).slice(-8).split('').map(value => value.codePointAt());
			const key = crypto.getRandomValues(new Uint8Array(8));
			options.headers = Object.assign({}, options.headers);
			//options.headers['Mask-Key'] = key.map(value => value.toString(16)).join('');
			options.headers['Mask-Key'] = Array.from(key, value => value.toString(16).padStart(2, 0)).join('');
			switch (true)
			{
				case options.body instanceof FormData:
					options.body = Object.fromEntries(options.body.entries());
				case Object.prototype.toString.call(options.body) === '[object Object]':
					options.headers['Content-Type'] = 'application/json';
					options.body = JSON.stringify(options.body);
					break;
			}
			options.body = Uint8Array.from(encodeURIComponent(options.body).match(/%[0-F]{2}|[^%]/g), (value, index) =>
			{
				return (value.startsWith('%') ? parseInt(value.substring(1), 16) : value.codePointAt(0)) ^ key[index % 8];
			}).buffer;
			//console.log(options)
		}
		return fetch(resource, options);
	}
	masker.authorization = signature => (signature ? localStorage.setItem('token', signature) : localStorage.removeItem('token')) || init;
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
	let pid = 0;
	const pending = new Map, require = cmd => clients.matchAll().then(windows => new Promise((resolve, reject) =>
	{
		if (windows.length)
		{
			pending.set(++pid, {resolve, reject});
			windows[0].postMessage({pid, cmd});
		}
		else
		{
			reject();
		}
	}));
	// Skip the 'waiting' lifecycle phase, to go directly from 'installed' to 'activated', even if
	// there are still previous incarnations of this service worker registration active.
	self.addEventListener('install', event => event.waitUntil(self.skipWaiting()));
	// Claim any clients immediately, so that the page will be under SW control without reloading.
	self.addEventListener('activate', event => event.waitUntil(self.clients.claim()));
	self.addEventListener('message', event =>
	{
		const promise = pending.get(event.data.pid);
		if (promise)
		{
			pending.delete(event.data.pid);
			'error' in event.data
				? promise.reject(event.data.error)
				: promise.resolve(event.data.result);
		}
	});
	self.addEventListener('fetch', event => event.respondWith(caches.match(event.request).then(response =>
	{
		if (response) return response;
		if (event.request.url.startsWith(self.location.origin))
		{
			const url = new URL(event.request.url);
			if (self.location.pathname === url.pathname)
			{
				if (url.search.startsWith('?/'))
				{
					return require('origin').then(origin => request(`${origin}${url.search.substring(1)}`, true));
				}
				if (event.isReload)
				{
					return new Response(new Blob(['<html lang="en"><head><meta charset="utf-8">',
						`<script src="${self.location.href}" data-reload="${event.request.url}"></script>`,
						'</head><body></body></html>'], {type: 'text/html'}), {headers: {'Cache-Control': 'no-store'}});
				}
				return event.request.url === self.location.href
					? fetch(event.request, {cache: 'reload'})
					: require('token').then(token =>
					{
						const headers = Object.assign({'Service-Worker': 'masker'},
							Object.fromEntries(event.request.headers.entries()));
						if (token)
						{
							headers.Authorization = `Bearer ${token}`;
						}
						return request(event.request, {priority: 'high', headers});
					}, () => {
						
						return fetch(event.request, {headers: {asd: '------------------------'}});
					});
			}
			return request(event.request, true);
		}
		return fetch(event.request);
	})));
}