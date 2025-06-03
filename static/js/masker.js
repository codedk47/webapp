'use strict';
if (self.window)
{
	const script = document.currentScript, sw = navigator.serviceWorker.register(script.src, {scope:
		location.pathname}).then(() => navigator.serviceWorker.ready.then(registration => registration.active)),
		init = new Promise(resolve => addEventListener('DOMContentLoaded', () => sw.then(active => {
			const handle =
			{
				ping: countdown => setTimeout(() => active.postMessage(['ping', countdown]), countdown * 1000),
				error: message => {
					const main = document.querySelector('main');
					if (main)
					{
						main.style.cssText = 'white-space:pre';
						main.textContent = message;
					}
				},
				message: content => alert(content),
				reload: url => location.replace(url)
			};
			//notice = Notification.requestPermission(), 
			// notice.then(permission =>
			// {
			// 	navigator.serviceWorker.ready.then(registration =>
			// 	{
			// 		registration.showNotification('Title',{icon: '?favicon', body: 'Buzz! Buzz!'});
			// 	});
			// });
			navigator.serviceWorker.addEventListener('message', event =>
			{
				//console.log('Global Window Message', event.data);
				handle[event.data.shift()](...event.data);
			});
			navigator.serviceWorker.startMessages();
			if ('reload' in script.dataset)
			{
				const data = Object.fromEntries(Object.entries(script.dataset));
				sessionStorage.setItem('init', JSON.stringify(data));
				data.token = localStorage.getItem('token');
				data.origin = Array.from(document.querySelectorAll('link[rel=preconnect]'))
					.map(link => `${link.href}${link.dataset.file || 'robots.txt'}`);
				active.postMessage(data);
			}
			else
			{
				const init = JSON.parse(sessionStorage.getItem('init'));
				if (init)
				{
					sessionStorage.removeItem('init');
					init.splashscreen ? masker.open(init.splashscreen).then(() => resolve(init)) : resolve(init);
				}
				active.postMessage(['ping', 4]);
				callbacks.forEach(callback => callback());
			}
		}))), observes = new Map, viewport = new IntersectionObserver(entries =>
		{
			entries.forEach(entry =>
			{
				if (entry.isIntersecting && observes.has(entry.target))
				{
					viewport.unobserve(entry.target);
					observes.get(entry.target).resolve(entry.target);
					observes.delete(entry.target);
				}
			});
		}), callbacks = [];
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
	masker.then = callback => callbacks[callbacks.length] = callback;
	masker.init = callback => init.then(callback);
	masker.open = url => new Promise(resolve => 
	{
		const frame = document.createElement('iframe');
		frame.src = url;
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
				case 'close': return document.body.removeChild(frame), resolve(frame);
			}
		});
		frame.focus();
		return frame;
	});
	masker.close = () => postMessage('close');
	masker.token = token => sw.then(active => (token ? localStorage.setItem('token', token)
		: localStorage.removeItem('token'), active.postMessage({token})));
	masker.skipsplashscreen = (skip = 'SKIP', second = 4, auto = false) => masker.then(() =>
	{
		const anchor = document.createElement('a');
		anchor.style.cssText = [
			'position: fixed',
			'top: 1rem',
			'right: 1rem',
			'width: 6rem',
			'color: white',
			'padding: .4rem 0',
			'text-align: center',
			'font-family: Consolas',
			'border-radius: .4rem',
			'background-color: rgba(0, 0, 0, .6)'
		].join(';');
		function cd()
		{
			if (second > 0)
			{
				anchor.textContent = `${skip} ${second--}`;
				setTimeout(cd, 1000);
			}
			else
			{
				anchor.href = 'javascript:masker.close();';
				anchor.textContent = skip;
				auto && masker.close();
			}
		}
		cd(document.body.appendChild(anchor));
	});
	masker.viewport = async element =>
	{
		const pending = observes.get(element) || {};
		return pending.promise || (observes.promise = new Promise(resolve =>
		{
			pending.resolve = resolve;
			observes.set(element, pending);
			viewport.observe(element);
		}));
	};
	// masker.clickad = anchor => (anchor.dataset.hash ? navigator.sendBeacon('?clickad', anchor.dataset.hash) : true)
	// 	&& (anchor.href.startsWith(location.origin) || anchor.href.startsWith('javascript:') || !window.open(anchor.href));
	masker.clickad = (anchor, event) =>
	{
		for (let log = anchor.dataset.log, target = event ? event.target : anchor; log && target; target = target.parentNode)
		{
			if (target.tagName === 'A')
			{
				if (target.dataset.key ? navigator.sendBeacon(log, target.dataset.key) : true)
				{
					return target.href.startsWith(location.origin)
						|| target.href.startsWith('javascript:')
						|| (window.open(target.href), event && event.preventDefault(), false);
				}
				break;
			}
		}
	};
	masker.isStandaloneApp = () => window.matchMedia('(display-mode: standalone)').matches;
	self.masker = masker;
}
else
{
	let confirm;
	const cachename = location.search.substring(location.search.indexOf('/')), headers = {}, origin = new Promise(resolve => confirm = resolve);
	async function request(resource, options)
	{
		const response = await fetch(resource, options instanceof Object ? options : {cache: 'no-cache'}), key = response.headers.get('masker-key')
			? response.headers.get('masker-key').match(/[0-f]{2}/gi).map(value => parseInt(value, 16))
			: options;
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
				//console.log(key, 'payload...')
				for (let i = 0; i < read.value.length; ++i)
				{
					read.value[i] = key[offset % 8] ^ (key[offset++ % 8] = read.value[i]);
				}
				buffer[buffer.length] = read.value;
			}
			return new Response(new Blob(buffer, {type: response.headers.get('content-type')}), response);
		}
		return response;
	}
	addEventListener('message', event =>
	{
		//console.log('Service Worker Message', event.data);
		if (Object.prototype.toString.call(event.data) === '[object Object]')
		{
			if ('token' in event.data)
			{
				if (typeof event.data.token === 'string')
				{
					headers.Authorization = `Bearer ${event.data.token}`;
				}
				else
				{
					delete headers.Authorization;
				}
			}
			if (typeof event.data.reload === 'string')
			{
				const controller = new AbortController, speedtest = event.data.origin.length ? Promise.any(event.data.origin.map(url =>
					fetch(url, {cache: 'no-cache', signal: controller.signal}).then(response => response.blob().then(() =>
						controller.abort(new URL(response.url).origin)).then(() => controller.signal.reason)))) : Promise.resolve(location.origin);
				setTimeout(() => controller.abort('Connection to origin timed out'), 4786);
				function reject(error, message)
				{
					//unregister()
					delete headers['Service-Worker'];
					event.source.postMessage(['error', error]);
					message && event.source.postMessage(['message', message]);
				}

				function resolve(url)
				{
					speedtest.then(origin =>
					{
						//console.log('resolve')
						headers['Service-Worker'] = 'masker';
						event.source.postMessage(['reload', url]);
						confirm(origin);
					}, error => reject(error.toString(), controller.signal.reason));
				}
				return typeof event.data.init === 'string' ? request(event.data.init, {method: 'POST', headers: {'Service-Worker': 'masker'},
					body: JSON.stringify(event.data)}).then(response =>response.text()).then(text => {
					try {
						const data = JSON.parse(text);
						//console.log(data);
						if (data.error)
						{
							return reject(data.error.message);
						}
						typeof data.token === 'string' && void(headers.Authorization = `Bearer ${data.token}`);
						'message' in data && event.source.postMessage(['message', data.message]);
						resolve('redirect' in data ? data.redirect : event.data.reload);
					} catch(error) {
						event.source.postMessage(['error', text.trim()]);
					}
				}) : resolve(event.data.reload);
			}
			return;
		}
		event.source.postMessage(event.data);
	});
	// Skip the 'waiting' lifecycle phase, to go directly from 'installed' to 'activated',
	// even if there are still previous incarnations of this service worker registration active.
	addEventListener('install', event => event.waitUntil(skipWaiting()));
	// Claim any clients immediately, so that the page will be under SW control without reloading.
	addEventListener('activate', event => event.waitUntil(clients.claim()));
	addEventListener('fetch', event => event.respondWith(caches.open(cachename).then(cache => caches.match(event.request).then(response =>
	{
		if (response) return response;
		// if (response) return console.log(`form cache ${event.request.url}`), response;
		const url = new URL(event.request.url), key = /^#[0-9a-f]{16}$|^#!$/.test(url.hash)
			? (url.hash.startsWith('#!') ? [] : url.hash.match(/[0-9a-f]{2}/g).map(key => parseInt(key, 16))) : null;
		if (url.origin === location.origin)
		{
			switch (true)
			{
				case url.pathname === location.pathname:
					return request(event.request, {priority: 'high', cache: 'no-cache', headers:
						Object.assign(Object.fromEntries(event.request.headers.entries()), headers)});
				case /^\/[0-9]{1,3}\//.test(url.pathname):
					return origin.then(origin => origin + url.pathname + url.search).then(url => request(url, key).then(response =>
						response.ok && key ? cache.put(event.request, response.clone()).then(() => response) : response));
				default:
					// return fetch(event.request).then(response =>
					// response.ok ? cache.put(event.request, response.clone()).then(() => response) : response);
			}
		}
		else
		{
			if (Array.isArray(key))
			{
				return request(event.request.url, key);
			}
		}
		return fetch(event.request);
	}))));
	//addEventListener('notificationclick', event => console.log(event));
}