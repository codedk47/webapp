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
					this.#observes.get(entry.target)(entry.target);
					this.#observes.delete(entry.target);
				}
			});
		});
		constructor(frame)
		{
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
			this.show(() => this.load(resource))
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
			return Promise.all([this.load(), loader(resource, {headers})]).then(([frame, data]) => new Promise(resolve =>
			{
				frame.contentWindow.framer = framer;
				frame.contentWindow.viewport = element => this.viewport(element);

				//frame.contentWindow.close = () => sandbox.close();

				frame.contentDocument.open();
				frame.contentDocument.write(data);
				frame.contentDocument.close();
				frame.contentWindow.requestAnimationFrame(function detect()
				{
					frame.contentDocument.readyState === 'loading'
						? frame.contentWindow.requestAnimationFrame(detect)
						: resolve(frame);
				});
			})).then(frame =>
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
				document.querySelectorAll('img[data-src]').forEach(element =>
					this.viewport(element).then(element =>
						worker(element.dataset.src, {mask: true}).then(blob =>
							element.src = blob)));
			});
		}
		async viewport(element)
		{
			return new Promise(resolve =>
			{
				if (this.#observes.has(element) === false)
				{
					this.#observes.set(element, resolve);
					this.#viewport.observe(element);
				}
			});
		}

	}

	const
	headers = {},
	render = new frame(document.querySelector('iframe')),
	sandbox = new frame(document.createElement('iframe')),
	framer = resource => render.draw(resource),
	prefetch = new Promise((resolve, reject) =>
	{
		const routelines = document.querySelectorAll('link[rel="dns-prefetch"][data-speedtest]');
		if (routelines.length)
		{
			const controller = new AbortController;
			Promise.any(Array.from(routelines).map(link =>
				fetch(`${link.href}${link.dataset.speedtest}`, {cache: 'no-cache', signal: controller.signal}))).then(async response =>
					resolve(response.url.slice(0, response.url.indexOf('/', 8)), await response.blob(), controller.abort()), reject);
		}
		else
		{
			resolve(location.origin);
		}
	});
	worker = async (resource, options) => prefetch.then(fastestline =>
		loader.worker(resource.startsWith('/') ? `${fastestline}${resource}` : resource, options), () => Promise.reject(resource));

	event.currentTarget.framer = framer;
	//addEventListener('message', event => framer(event.data));
	sandbox.hide(frame =>
	{
		frame.name = 'sandbox';
		frame.width = frame.height = '100%';
		frame.style.cssText = 'position:fixed;border:none;overflow:hidden;background:white;display:none';
		document.body.appendChild(frame);
	});

	framer.worker = worker;
	framer.loader = async (resource, options) => loader(resource, {...options, headers: options && 'headers' in options ? {...options.headers, ...headers} : headers});
	framer.open = resource => sandbox.open(resource);
	framer.close = () => sandbox.close();

	framer.dialog = new class
	{
		#dialog = document.createElement('dialog');
		#section = document.createElement('section');
		#footer = document.createElement('footer');
		constructor()
		{
			this.#dialog.style.cssText = [
				'padding: 0',
				'border: 1px solid dimgray',
				'border-radius: .4rem',
				'box-shadow: 0 .1rem .4rem rgb(27, 31, 35)'
			].join(';');
			this.#section.style.cssText = [
				'padding: .8rem',
				'white-space: pre-wrap',
			].join(';');
			this.#footer.style.cssText = [
				'padding: .4rem',
				'border-top: 1px solid silver',
				'background-image: linear-gradient(-180deg, #fafbfc 0%, #eff3f6 90%)',
				'text-align: center'
			].join(';');
			this.#footer.innerText = 'Close';
			this.#footer.onclick = () => this.#dialog.close();
			this.#dialog.append(this.#section, this.#footer);
			document.body.appendChild(this.#dialog);
		}
		show(context)
		{
			this.#section.innerHTML = context;
			this.#dialog.open || this.#dialog.showModal();
		}
		close()
		{
			this.#dialog.close();
		}
	};
	//framer.dialog.show(`This test dialog show style`);



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
			//frame.style.background = 'white url(/webapp/res/ps/loading.svg) center no-repeat';
			frame.style.background = 'white url(data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4NCjwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMS4xLy9FTiIgImh0dHA6Ly93d3cudzMub3JnL0dyYXBoaWNzL1NWRy8xLjEvRFREL3N2ZzExLmR0ZCI+DQo8c3ZnIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiIGhlaWdodD0iNjQiIHdpZHRoPSI2NCIgdmlld0JveD0iMCAwIDEwMCAxMDAiPg0KCTxzdHlsZT4NCgljaXJjbGV7DQoJCWZpbGw6IG5vbmU7DQoJCXN0cm9rZS13aWR0aDogLjRyZW07DQoJCXN0cm9rZS1saW5lY2FwOiByb3VuZDsNCgl9DQoJQGtleWZyYW1lcyBsb2FkaW5new0KCQkwJXsNCgkJCXN0cm9rZS1kYXNoYXJyYXk6IDQwIDI0Mi42Ow0KCQkJc3Ryb2tlLWRhc2hvZmZzZXQ6IDA7DQoJCX0NCgkJNTAlew0KCQkJc3Ryb2tlLWRhc2hhcnJheTogMTQxLjM7DQoJCQlzdHJva2UtZGFzaG9mZnNldDogMTQxLjM7DQoJCX0NCgkJMTAwJXsNCgkJCXN0cm9rZS1kYXNoYXJyYXk6IDQwIDI0Mi42Ow0KCQkJc3Ryb2tlLWRhc2hvZmZzZXQ6IDI4Mi42Ow0KCQl9DQoJfQ0KCTwvc3R5bGU+DQoJPGNpcmNsZSBjeD0iNTAiIGN5PSI1MCIgcj0iNDUiIHN0cm9rZT0ic2lsdmVyIj48L2NpcmNsZT4NCgk8Y2lyY2xlIGN4PSI1MCIgY3k9IjUwIiByPSI0NSIgc3Ryb2tlPSJibGFjayIgc3R5bGU9ImFuaW1hdGlvbjpsb2FkaW5nIDFzIGN1YmljLWJlemllcigxLDEsMSwxKSAwcyBpbmZpbml0ZSIvPg0KPC9zdmc+) center no-repeat';
			const
			splashscreen = JSON.parse(render.dataset.splashscreen),
			document = frame.contentDocument,
			button = document.createElement('span'),
			clear = () =>
			{
				sandbox.hide(frame => frame.style.background = 'white');
				document.onclick = null
			},
			timeout = setTimeout(clear, splashscreen.timeout || 6000);
			worker(splashscreen.picture, {mask: splashscreen.mask}).finally(() => clearTimeout(timeout)).then(blob =>
			{
				frame.style.background = `white url(${blob}) no-repeat center/cover`;
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

	if ('query' in render.dataset)
	{
		framer(render.dataset.query);
	}

});