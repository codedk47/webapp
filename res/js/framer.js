window.addEventListener('DOMContentLoaded', async event =>
{
	const
	headers = {},
	frame = document.querySelector('iframe'),
	sandbox = document.createElement('iframe'),
	entry = frame.dataset.entry || location.href,
	framer = url => postMessage(url);

	window.framer = framer;
	sandbox.width = sandbox.height = '100%';
	sandbox.style.cssText = 'background:white;position:fixed;border:none;overflow:hidden;display:none';
	document.body.appendChild(sandbox);


	framer.open = function(url = 'about:blank')
	{
		sandbox.style.display = 'block';
		sandbox.src = url;
		return sandbox;
	};
	framer.close = function()
	{
		sandbox.style.display = 'none';
		sandbox.src = 'about:blank';
		return sandbox;
	};

	
	//Authorization: 'Bearer asdasdasd'
	window.addEventListener('message', event =>
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
						postMessage(target.href);
						event.preventDefault();
						break;
					}
				}

				// let target = event.target;
				// console.log(target.tagName === 'A'
				// && target.hasAttribute('href')
				// && target.hasAttribute('target'))
				// event.preventDefault();
			};
		});



		
	});

	if ('splashscreen' in frame.dataset)
	{
		const splashscreen = JSON.parse(frame.dataset.splashscreen);
		console.log( splashscreen );


		framer.open();
		loader(splashscreen.image, {mask: splashscreen.mask}).then(blob =>
		{
			sandbox.style.background = `white url(${blob}) no-repeat center/cover`;
			const
			draw = sandbox.contentDocument,
			into = draw.createElement('span'),
			body = draw.body;
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
			body.appendChild(into);
			(function()
			{
				if (splashscreen.duration > 0)
				{
					into.innerText = `${splashscreen.duration.toString().padStart(2, 0)} s`;
					setTimeout(arguments.callee, 1000, --splashscreen.duration);
				}
				else
				{
					if (splashscreen.auto)
					{
						framer.close();
					}
					else
					{
						into.innerText = 'Into';
					}
				}
			})();
			draw.onclick = event =>
			{
				if (event.target === into)
				{
					if (splashscreen.duration === 0)
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
		});
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