addEventListener('DOMContentLoaded', async event =>
{
	const
	headers = {},
	frame = document.querySelector('iframe'),
	sandbox = document.createElement('iframe'),
	framer = url => postMessage(url);

	addEventListener('message', event =>
	{
		loader(event.data, {headers}).then(data =>
		{
			frame.contentDocument.open();
			frame.contentDocument.write(data);
			frame.contentDocument.close();
			frame.contentDocument.onclick = event =>
			{
				for (let target = event.target; target.parentNode; target = target.parentNode)
				{
					if (target.tagName === 'A'
						&& target.hasAttribute('href')
						&& target.hasAttribute('target') === false
						&& /^javascript:|^blob:/.test(target.getAttribute('href')) === false) {
						event.preventDefault();
						framer(target.href);
						break;
					}
				}
			};
		});
	});

	event.currentTarget.framer = framer;
	sandbox.width = sandbox.height = '100%';
	sandbox.style.cssText = 'background:white;position:fixed;border:none;overflow:hidden;display:none';
	document.body.appendChild(sandbox);

	framer.open = (url = 'about:blank') => new Promise(resolve =>
	{
		sandbox.src = url;
		sandbox.style.display = 'block';
		sandbox.onload = () => resolve(sandbox.contentWindow);
	});
	framer.close = () =>
	{
		sandbox.style.display = 'none';
		sandbox.src = 'about:blank';
	};
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
			this.#dialog.showModal();
		}
		close()
		{
			this.#dialog.close();
		}
	};



	if ('splashscreen' in frame.dataset)
	{
		// console.log({
		// 	autoskip: true,
		// 	duration: 1,
		// 	mask: true,
		// 	picture: "https://domain/picture",
		// 	support: "https://domain/support"
		// });
		const splashscreen = JSON.parse(frame.dataset.splashscreen);
		framer.open().then(window => loader(splashscreen.picture, {mask: splashscreen.mask}).then(blob =>
		{
			sandbox.style.background = `white url(${blob}) no-repeat center/cover`;
			const
			draw = window.document,
			into = draw.createElement('span'),
			skip = setInterval((function()
			{
				if (splashscreen.duration > 0)
				{
					into.innerText = `${String(splashscreen.duration--).padStart(2, 0)} s`;
					//setTimeout(arguments.callee, 1000);
				}
				else
				{
					clearInterval(skip);
					if (splashscreen.autoskip)
					{
						framer.close();
					}
					else
					{
						into.innerText = 'Into';
					}
				}
				return arguments.callee;
			})(), 1000);
			into.style.cssText = [
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
			draw.onclick = event =>
			{
				if (event.target === into)
				{
					if (splashscreen.duration < 0)
					{
						draw.onclick = null;
						sandbox.style.background = 'white';
						framer.close();
					}
				}
				else
				{
					if ('support' in splashscreen)
					{
						open(splashscreen.support);
						framer.close();
					}
				}
			};
			draw.body.appendChild(into);
		}));
	}


	if ('authorization' in frame.dataset)
	{
		while (!(localStorage.getItem('token') && await loader(`${frame.dataset.authorization}/${localStorage.getItem('token')}`).token))
		{
			localStorage.setItem('token', await new Promise(resolve =>
			{
				framer(`${frame.dataset.authorization}`);
				framer.authorization = resolve;
			}));
		}
		delete framer.authorization;
		headers.Authorization = `Bearer ${localStorage.getItem('token')}`;
	}

	if ('query' in frame.dataset)
	{
		framer(frame.dataset.query);
	}
});