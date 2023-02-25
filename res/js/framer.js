addEventListener('DOMContentLoaded', async event =>
{
	class frame
	{
		#frame;
		#observes = new Map;
		#viewport = new IntersectionObserver(entries =>
		{
			entries.forEach(entry =>
			{
				if (entry.isIntersecting && this.#observes.has(entry.target))
				{
					this.#viewport.unobserve(entry.target);
					this.#observes.get(entry.target).resolve(entry.target);
					this.#observes.delete(entry.target);
				}
			});
		});
		constructor(frame)
		{
			frame.style.background = 'white url(data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIGhlaWdodD0iNjQiIHdpZHRoPSI2NCIgdmlld0JveD0iMCAwIDEwMCAxMDAiPg0KPHN0eWxlPmNpcmNsZXtmaWxsOm5vbmU7c3Ryb2tlLXdpZHRoOi40cmVtO3N0cm9rZS1saW5lY2FwOnJvdW5kO30NCkBrZXlmcmFtZXMgbG9hZGluZ3sNCjAle3N0cm9rZS1kYXNoYXJyYXk6NDAgMjQyLjY7c3Ryb2tlLWRhc2hvZmZzZXQ6MDt9DQo1MCV7c3Ryb2tlLWRhc2hhcnJheToxNDEuMztzdHJva2UtZGFzaG9mZnNldDoxNDEuMzt9DQoxMDAle3N0cm9rZS1kYXNoYXJyYXk6NDAgMjQyLjY7c3Ryb2tlLWRhc2hvZmZzZXQ6MjgyLjY7fQ0KfTwvc3R5bGU+DQo8Y2lyY2xlIGN4PSI1MCIgY3k9IjUwIiByPSI0NSIgc3Ryb2tlPSJzaWx2ZXIiPjwvY2lyY2xlPg0KPGNpcmNsZSBjeD0iNTAiIGN5PSI1MCIgcj0iNDUiIHN0cm9rZT0iYmxhY2siIHN0eWxlPSJhbmltYXRpb246bG9hZGluZyAxcyBjdWJpYy1iZXppZXIoMSwxLDEsMSkgMHMgaW5maW5pdGUiLz4NCjwvc3ZnPg==) center/20% no-repeat';
			this.dataset = frame.dataset;
			this.#frame = frame;
		}
		show(callback)
		{
			callback && callback(this.#frame);
			this.#frame.style.display = 'block';
		}
		hide(callback)
		{
			this.#frame.style.display = 'none';
			callback && callback(this.#frame);
		}
		open(resource)
		{
			this.show(() => this.load(resource));
		}
		close()
		{
			this.hide(() => this.load());
		}
		async load(resource = 'about:blank')
		{
			return new Promise(resolve =>
			{
				const frame = this.#frame, source = frame.contentDocument || {};
				frame.src = resource;
				requestAnimationFrame(function detect()
				{
					const target = frame.contentDocument || {};
					target === source || target.readyState === 'loading'
						? requestAnimationFrame(detect)
						: resolve(frame);
				});
			});
		}
		async draw(resource)
		{
			this.#observes.clear(Array.from(this.#observes.keys()).forEach(element => this.#viewport.unobserve(element)));
			return loader(resource, {headers}).then(blob => this.load(blob)).then(frame =>
			{
				const document = frame.contentDocument;
				document.onclick = event =>
				{
					for (let target = event.target; target.parentNode; target = target.parentNode)
					{
						if (target.tagName === 'A' && target.hasAttribute('href'))
						{
							if (target.hasAttribute('target'))
							{
								if (target.getAttribute('target') === 'sandbox')
								{
									event.preventDefault();
									target.href.startsWith(location.origin)
										? sandbox.show(() => sandbox.draw(target.href))
										: sandbox.open(target.href);
									break;
								}
							}
							else
							{
								if (target.href.startsWith(location.origin))
								{
									event.preventDefault();
									render.draw(target.href);
									sandbox.close();
									break;
								}
							}
						}
					}
				};
				document.querySelectorAll('img[data-src]').forEach(image =>
					this.viewport(image).then(image =>
						framer.worker(image.dataset.src, {mask: image.hasAttribute('data-mask')}).then(blob =>
							image.src = blob)));
				frame.contentWindow.framer && frame.contentWindow.framer(framer, element => this.viewport(element));
			});
		}
		async viewport(element)
		{
			const observes = this.#observes.get(element) || {};
			return observes.promise || (observes.promise = new Promise(resolve =>
			{
				observes.resolve = resolve;
				this.#observes.set(element, observes);
				this.#viewport.observe(element);
			}));
		}
	}

	const
	headers = {},
	render = new frame(document.querySelector('iframe')),
	sandbox = new frame(document.createElement('iframe')),
	framer = resource => render.draw(resource),
	prefetch = new Promise((resolve, reject) =>
	{
		const resources = document.querySelectorAll('link[rel="dns-prefetch"][data-speedtest]');
		if (resources.length)
		{
			const controller = new AbortController;
			Promise.any(Array.from(resources).map(link =>
				fetch(`${link.href}${link.dataset.speedtest}`, {cache: 'no-cache', signal: controller.signal}))).then(async response =>
					resolve(response.url.slice(0, response.url.indexOf('/', 8)), await response.blob(), controller.abort()), reject);
		}
		else
		{
			resolve(location.origin);
		}
	}),
	request = async (method, resource, options) => prefetch.then(origin => framer.origin || origin).then(origin =>
		method(resource.startsWith('/') ? `${origin}${resource}` : resource, options), () => Promise.reject(resource));

	event.currentTarget.framer = framer;
	//addEventListener('message', event => framer(event.data));

	framer.source = async (resource, options) => request(loader, resource, options);
	framer.worker = async (resource, options) => request(loader.worker, resource, options);
	framer.loader = async (resource, options) => loader(resource, {...options, headers: options && 'headers' in options ? {...options.headers, ...headers} : headers});
	framer.open = resource => sandbox.open(resource);
	framer.close = () => sandbox.close();

	sandbox.hide(frame =>
	{
		frame.name = 'sandbox';
		frame.width = frame.height = '100%';
		frame.style.cssText += 'position:fixed;border:none;overflow:hidden';
		document.body.appendChild(frame);
	});

	if ('splashscreen' in render.dataset)
	{
		// console.log({
		// 	autoskip: true,
		// 	duration: 1,
		// 	mask: true,
		// 	picture: "https://domain/picture",
		// 	support: "https://domain/support"
		// });
		sandbox.show(frame =>
		{
			const
			splashscreen = JSON.parse(render.dataset.splashscreen),
			document = frame.contentDocument,
			button = document.createElement('span'),
			clear = () =>
			{
				sandbox.hide();
				document.body.style.background = document.onclick = null
			},
			timeout = setTimeout(clear, splashscreen.timeout || 6000);
			worker(splashscreen.picture, {mask: splashscreen.mask}).finally(() => clearTimeout(timeout)).then(blob =>
			{
				document.body.style.background = `white url(${blob}) center/cover no-repeat`;
				button.style.cssText = [
					'position: fixed',
					'top: .8rem',
					'right: .8rem',
					'padding: .5rem',
					'color: white',
					'font: 100% consolas,monospace',
					'background-color: rgba(0, 0, 0, .6)',
					'box-shadow: 0 .1rem .6rem rgb(27, 31, 35)',
					'text-shadow: black 1px 1px',
					'border-radius: .6rem'
				].join(';');
				document.onclick = event =>
				{
					if (event.target === button)
					{
						if (splashscreen.duration < 1)
						{
							clear();
						}
					}
					else
					{
						if ('support' in splashscreen)
						{
							open(splashscreen.support);
							clear();
						}
					}
				};
				requestAnimationFrame(function timer()
				{
					if (splashscreen.duration > 0)
					{
						button.innerText = `${String(splashscreen.duration--).padStart(2, 0)} s`;
						setTimeout(timer, 1000);
					}
					else
					{
						if (splashscreen.autoskip)
						{
							clear();
						}
						else
						{
							button.innerText = 'Into';
						}
					}
				});
				document.body.appendChild(button);
			}, clear);
		});
	}

	if ('authorization' in render.dataset)
	{
		while (!(localStorage.getItem('token') && 'token' in await loader(`${render.dataset.authorization}/${localStorage.getItem('token')}`)))
		{
			localStorage.setItem('token', await new Promise(resolve =>
			{
				framer(render.dataset.authorization);
				framer.authorization = resolve;
			}));
		}
		delete framer.authorization;
		headers.Authorization = `Bearer ${localStorage.getItem('token')}`;
	}

	// history.pushState(null, null, null);
	// window.onpopstate = event =>
	// {
	// 	framer.close();
	// 	if ('entry' in render.dataset)
	// 	{
	// 		framer(render.dataset.entry);
	// 	}
	// 	console.log(render.dataset.entry)
	// };


	render.load().then(frame => framer(frame.dataset.load));
});