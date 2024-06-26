if (self.window)
{
	const script = document.currentScript, init = new Promise(resolve =>
	{
		const init = new Promise(resolve => navigator.serviceWorker.ready.then(registration =>
		{
			const message = new MessageChannel;
			message.port1.onmessage = () =>
			{
				if ('reload' in script.dataset)
				{
					sessionStorage.setItem('init', JSON.stringify(script.dataset));
					return location.replace(script.dataset.reload);
				}
				const init = JSON.parse(sessionStorage.getItem('init'));
				if (init)
				{
					sessionStorage.removeItem('init');
					if (init.splashscreen)
					{
						masker.open(init.splashscreen);
					}
					resolve(init);
				}
				setTimeout(function aws(){fetch('/ping').then(r => setTimeout(aws, 1000));}, 1000);
			};
			registration.active.postMessage([localStorage.getItem('token'),
				(/DID\/(\w{16})/.exec(navigator.userAgent) || [null]).pop(),
				(/CID\/(\w{4})/.exec(navigator.userAgent) || [null]).pop()], [message.port2]);
			navigator.serviceWorker.addEventListener('message', event => origin.then(result =>
				registration.active.postMessage({pid: event.data, result})));
			navigator.serviceWorker.startMessages();
		}));
		addEventListener('DOMContentLoaded', () =>
		{
			navigator.serviceWorker.ready.then(registration => resolve([registration.active, init]));
			addEventListener('load', () => navigator.serviceWorker.register(script.src, {scope: location.pathname}));
		});
	}), origin = new Promise(resolve => init.then(() =>
	{
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
	}));
	function masker(resource, options = {})
	{
		if (options.body)
		{
			const key = crypto.getRandomValues(new Uint8Array(8));
			options.headers = Object.assign({}, options.headers);
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
	masker.then = callback => init.then(([sw]) => callback(sw));
	masker.init = callback => init.then(([, init]) => init.then(callback));
	masker.homescreen = callback => init.then(() => callback(matchMedia('(display-mode: standalone)').matches));
	masker.authorization = signature => masker.then(active => active.postMessage([localStorage.setItem('token', signature) || localStorage.getItem('token')]));
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
		frame.focus();
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
	let pid = 0, passive = true;
	const pending = new Map, headers = {'Service-Worker': 'masker'}, origin = event =>
		clients.get(event.clientId).then(client => new Promise((resolve, reject) =>
			client ? (pending.set(++pid, {resolve, reject}), client.postMessage(pid)) : reject()));
	addEventListener('message', event =>
	{
		if (Array.isArray(event.data))
		{
			const [token, did, cid] = event.data;
			if (event.ports.length ? passive : true)
			{
				if (token)
				{
					headers.Authorization = `Bearer ${token}`;
				}
				else
				{
					delete headers.Authorization;
				}
			}
			if (event.ports.length)
			{
				if (did)
				{
					headers['Device-Id'] = did;
				}
				if (cid)
				{
					headers['Channel-Id'] = cid;
				}
				passive = event.ports[0].postMessage(null);
			}
		}
		else
		{
			const promise = pending.get(event.data.pid);
			if (promise)
			{
				pending.delete(event.data.pid);
				'error' in event.data
					? promise.reject(event.data.error)
					: promise.resolve(event.data.result);
			}
		}
	});
	// Skip the 'waiting' lifecycle phase, to go directly from 'installed' to 'activated', even if
	// there are still previous incarnations of this service worker registration active.
	addEventListener('install', event => event.waitUntil(skipWaiting()));
	// Claim any clients immediately, so that the page will be under SW control without reloading.
	addEventListener('activate', event => event.waitUntil(clients.claim()));

	addEventListener('fetch', event => event.respondWith(caches.match(event.request).then(response =>
	{
		if (response) return response;
		if (event.request.url.startsWith(location.origin))
		{
			const url = new URL(event.request.url);
			if (url.pathname === location.pathname)
			{
				return url.search.startsWith('?/')
					? origin(event).then(origin =>
						request(`${origin}${url.search.substring(1)}`, true), () =>
							new Response(null, {status: 404, headers: {'Cache-Control': 'no-store'}}))
					: request(...[event.request, ...passive ? [] : [{priority: 'high', headers:
						Object.assign(Object.fromEntries(event.request.headers.entries()), headers)}]]);
			}
			switch (url.pathname)
			{
				case '/favicon.ico':
					return fetch('/webapp/favicon.ico');
				case '/ping':
					return new Response(null);
					return Response.json({});
				default:
					return request(event.request, true);
			}
		}
		return fetch(event.request);
	})));
}