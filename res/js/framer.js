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
		open(src)
		{
			this.show(() => this.load(src))
		}
		close()
		{
			this.hide(() => this.load());
		}
		async load(src = 'about:blank')
		{
			return new Promise(resolve =>
			{
				const frame = this.#frame, source = frame.contentDocument || {};
				frame.src = src;
				requestAnimationFrame(function detect()
				{
					const target = frame.contentDocument || {};
					target === source || target.readyState === 'loading'
						? requestAnimationFrame(detect)
						: resolve(frame);
				});
			});
		}
		async draw(src)
		{
			this.#observes.clear(Array.from(this.#observes.keys()).forEach(element => this.#viewport.unobserve(element)));
			return Promise.all([this.load(), loader(src)]).then(([frame, data]) => new Promise(resolve =>
			{
				frame.contentWindow.viewport = element => this.viewport(element);
				frame.contentWindow.close = () => this.close();
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
						loader.worker(element.dataset.src, {mask: true}).then(blob =>
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
	framer = src => render.draw(src);
	event.currentTarget.framer = framer;
	//addEventListener('message', event => framer(event.data));
	sandbox.hide(frame =>
	{
		frame.name = 'sandbox';
		frame.width = frame.height = '100%';
		frame.style.cssText = 'position:fixed;border:none;overflow:hidden;background:white;display:none';
		document.body.appendChild(frame);
	});

	framer.open = src => sandbox.open(src);
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
			const
			splashscreen = JSON.parse(render.dataset.splashscreen),
			document = frame.contentDocument,
			button = document.createElement('span'),
			clear = () =>
			{
				sandbox.hide(frame => frame.style.background = 'white');
				document.onclick = null
			};
			loader(splashscreen.picture, {mask: splashscreen.mask}).then(blob =>
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
						if (splashscreen.duration < 0)
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