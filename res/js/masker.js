if (self.window)
{
	const script = document.currentScript, init = new Promise(resolve =>
		addEventListener('DOMContentLoaded', () =>
			navigator.serviceWorker.register(script.src, {scope: location.pathname}).then(() =>
				navigator.serviceWorker.ready.then(registration => resolve(registration.active)))));




	init.then(sw =>
	{
		const speedtest = callback => new Promise(resolve =>
		{
			const controller = new AbortController, resources = document.querySelectorAll([
				'link[rel=dns-prefetch]',
				'link[rel=preconnect]'].join(','));
			resources.length && Promise.any(Array.from(resources).map(link =>
				fetch(link.href, {cache: 'no-cache', signal: controller.signal}))).then(response =>
					controller.abort(resolve(new URL(response.url).origin)));
		}).then(callback);
		sw.postMessage(localStorage.getItem('token'));
		sessionStorage.getItem('origin') || speedtest(origin => sw.postMessage(origin) || sessionStorage.setItem('origin', origin));



		if ('reload' in script.dataset)
		{
			return location.assign(script.dataset.reload);
		}
		if ('splashscreen' in script.dataset)
		{
			masker.session_once('splashscreen', () => masker.open(script.dataset.splashscreen));
		}
	});
	function masker(resource, options)
	{
		fetch(resource, options).then(r => r.text()).then(d => console.log(d));
	}
	masker.authorization = signature => localStorage.setItem('token', signature) || init.then(sw => sw.postMessage(signature));
	masker.then = callback => init.then(callback);
	masker.once = callback => sessionStorage.getItem('token') === localStorage.getItem('token')
		//|| sessionStorage.setItem('token', localStorage.getItem('token'))
		|| init.then(callback);
	masker.session_once = (name, init) => init.then(() => sessionStorage.getItem(name) || sessionStorage.setItem(name, init()));
	
	masker.open = resources => init.then(() =>
	{
		const frame = document.createElement('iframe');
		frame.style.cssText = [
			'position: fixed',
			'inset: 0',
			'width: 100%',
			'height: 100%',
			'border: none'
		].join(';');
		frame.src = resources;
		document.body.appendChild(frame).contentWindow.addEventListener('message', event =>
		{
			switch (event.data)
			{
				case 'close': return document.body.removeChild(frame);
			}
		});
		return frame;
	});
	



	
	






	// try
	// {
	// 	navigator.serviceWorker.register(script.src, {scope: location.pathname}).then(registration =>
	// 	{
	// 		console.log(registration)
	// 		addEventListener('DOMContentLoaded', () =>
	// 		{
	// 			const resources = Array.from(document.querySelectorAll([
	// 				'link[rel=dns-prefetch]',
	// 				'link[rel=preconnect]'].join(','))).map(link => link.href),
	// 			reselect = registration => registration.active.postMessage(resources);
	// 			addEventListener('online', () => navigator.serviceWorker.ready.then(reselect));
		
	// 		});
	// 		navigator.serviceWorker.ready.then(registration =>
	// 		{
				
	// 			console.log('signature')
	// 			registration.active.postMessage(localStorage.getItem('token'));



				
	
				
	// 			if ('reload' in script.dataset)
	// 			{
	// 				// if (sessionStorage.getItem('resources'))
	// 				// {
	// 				// 	registration.active.postMessage(sessionStorage.getItem('resources').split('|'));
	// 				// }
	// 				//console.log(script.dataset.reload)
	// 				//location.assign(script.dataset.reload)
	// 			}
	// 			else
	// 			{

	// 			}
	// 		});


	// 	});
	// 	// if (resources.length && resources.join('|') !== sessionStorage.getItem('resources'))
	// 	// {
	// 	// 	sessionStorage.setItem('resources', resources.join('|'));
	// 	// 	navigator.serviceWorker.ready.then(reselect);
	// 	// 	console.log(resources);
	// 	// }
	// }
	// catch(error)
	// {
	// 	console.error(error);
	// }







	
	// window.authorization = async signature =>
	// 	localStorage.setItem(script.dataset.authorization, signature)
	// 		|| navigator.serviceWorker.ready.then(registration => registration.active.postMessage({signature}));



	// const configs = new Promise(resolve => {
	// 	sessionStorage.getItem('configs')
	// 	? resolve(JSON.parse(sessionStorage.getItem('configs')))
	// 	: request(script.src).then(response => response.json()).then(resolve)
	// });


	// if ('splashscreen' in script.dataset)
	// {


	// }
	







	// window.addEventListener('DOMContentLoaded', async () =>
	// {
	// 	console.log('DOMContentLoaded');
	// 	const
	// 	resources = Array.from(document.querySelectorAll([
	// 		'link[rel=dns-prefetch]',
	// 		'link[rel=preconnect]'].join(','))).map(link => link.href);



		


	// 	navigator.serviceWorker.ready.then(registration =>{
	// 		console.log('navigator.serviceWorker');
	// 	}, ()=>{
	// 		console.log('navigator.serviceWorker1')
	// 	});

	// 	window.addEventListener('online', () =>
	// 		navigator.serviceWorker.ready.then(registration =>
	// 			registration.active.postMessage(resources)));
	// });

	



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
	resources = new Promise(resolve => origin = resolve),
	asd = cmd => new Promise((resolve, reject) => {
		self.postMessage({id})
	});


	self.addEventListener('message', event =>
	{
		// if (Array.isArray(event.data))
		// {
		// 	console.log(event.data)
		// 	if (event.data.length)
		// 	{
				
		// 		const controller = new AbortController;
		// 		Promise.any(event.data.map(url =>
		// 			fetch(url, {cache: 'no-cache', signal: controller.signal}))).then(response =>
		// 				controller.abort(typeof origin === 'string'
		// 					? origin = new URL(response.url).origin
		// 					: origin(origin = new URL(response.url).origin)));
		// 	}
		// 	return;
		// }
		const options = {priority: 'high'};
		if (typeof event.data === 'string')
		{
			if (/^https?:\/\//i.test(event.data))
			{
				return typeof origin === 'string'
					? origin = event.data
					: origin(origin = event.data)
			}
			options.headers = {Authorization: `Bearer ${event.data}`};
		}
		token.length ? token(token = options) : token = options;
	});
	self.addEventListener('fetch', event => event.respondWith(caches.match(event.request).then(async response =>
	{
		//console.log('event',clients.get(event.));
		// clients.get(event.clientId).then(c => {
		// 	console.log('ccc', c)
		// })
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