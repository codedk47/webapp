if (self.window)
{
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
	//addEventListener('DOMContentLoaded', ()=> console.log('DOMContentLoaded'));
	const script = document.currentScript, init = new Promise(resolve =>
	{
		//addEventListener('DOMContentLoaded', ()=> console.log('DOMContentLoaded1111'));
		navigator.serviceWorker.register(script.src, {scope: location.pathname})
		.then(registration => navigator.serviceWorker.ready.then(registration))
		.then(registration =>
		{
			navigator.serviceWorker.addEventListener('message', event =>
			{
				//console.log('windows m', event);
				if (typeof event.data === 'string')
				{
					switch (event.data)
					{
						case 'pong':
							setTimeout(() => registration.active.postMessage('ping'), 1000);
							break;
						default:
							console.log(event.data);
							location.replace(script.dataset.reload);
					}
					
				}
				
			});
			navigator.serviceWorker.startMessages();
			if ('reload' in script.dataset)
			{
				sessionStorage.setItem('init', JSON.stringify(script.dataset));
				registration.active.postMessage('init');
			}
			else
			{
				//console.log('sw load')
				const init = JSON.parse(sessionStorage.getItem('init'));
				if (init)
				{
					masker.open(`?${(location.search.match(/\?(\w+)/) || ['home']).pop()}/splashscreen`);
					sessionStorage.removeItem('init');
					resolve(init);
				}
				registration.active.postMessage('ping');
				callbacks.forEach(callback => callback());
			}
			
	
	
			// registration.active.postMessage('reload' in script.dataset ? 'init' : 'ping');
			// if (self.name !== 'masker')
			// {
			// 	self.name = 'masker';
			// 	resolve(123)
			// }
			
				
			
		});
	}), callbacks = [];
	masker.then = callback => callbacks[callbacks.length] = callback;
	masker.init = callback => init.then(callback);
	masker.open = url => init.then(() =>
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
				case 'close': return document.body.removeChild(frame);
			}
		});
		frame.focus();
		return frame;
	});
	masker.close = () => postMessage('close');

	
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
}
else
{
	const headers = {'Service-Worker': 'masker'};
	async function request(resource, options)
	{
		// if (resource instanceof Request && resource.url.indexOf('#') > 0)
		// {
		// 	console.log(resource, resource.url = resource.url.substring(0, resource.url.indexOf('#')))
		// }
		
		const response = await fetch(resource, options instanceof Object ? options : null), key = response.headers.get('masker-key')
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
		if (typeof event.data === 'string')
		{
			let data = 'init';
			switch (event.data)
			{
				case 'ping':
					data = 'pong';
					break;
			}
			return event.source.postMessage(data);
		}


	});



	addEventListener('fetch', event => event.respondWith(caches.match(event.request).then(response =>
	{
		if (response) return response;
		const url = new URL(event.request.url);




		// const req = masker
		// 	? [event.request.url.substring(0, event.request.url.indexOf('#')), []]
		// 	: [event.request, {priority: 'high', headers: Object.assign(Object.fromEntries(event.request.headers.entries()), headers)}];

		///\?mask\d*$/i.test(event.request.url)
		//event.request.url.startsWith(location.origin)
		if (url.hash.startsWith('#!'))
		{
			return request(event.request.url, []);
		}
		if (event.request.url.startsWith(location.origin))
		{
			if (url.pathname === location.pathname)
			{
				return request(event.request, {priority: 'high', headers:
					Object.assign(Object.fromEntries(event.request.headers.entries()), headers)});
			}
			//do something here
		}
		return fetch(event.request);
	})));

}