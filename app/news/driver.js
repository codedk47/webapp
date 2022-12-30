async function loader(source, options, type)
{
	const
	response = await fetch(source, options || null),
	reader = response.body.getReader(),
	key = new Uint8Array(8),
	buffer = [];
	for (let read, len = 0, offset = 0;;)
	{
		read = await reader.read();
		if (read.done)
		{
			break;
		}
		if (/^text/i.test(response.headers.get('content-type')))
		{
			buffer[buffer.length] = read.value;
			continue;
		}
		if (len < 8)
		{
			//console.log('keyload...')
			let i = 0;
			while (i < read.value.length)
			{
				key[len++] = read.value[i++];
				//console.log('keyload-' + i)
				if (len > 7)
				{
					//console.log('keyloaded over')
					break;
				}
			}
			if (len < 8)
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
			read.value[i] = read.value[i] ^ key[offset++ % 8];
		}
		buffer[buffer.length] = read.value;
	}
	//console.log('payload finish')
	const blob = new Blob(buffer, {type});
	switch (type)
	{
		case 'application/json': return JSON.parse(await blob.text());
		case 'text/plain': return blob.text();
		default: return blob;
	}
}
async function caller(path, body, type)
{
	return loader(self.entry + path, {method: body ? 'POST' : 'GET', headers: self.apphead,
		body: body ? (typeof body === 'string' ? body : JSON.stringify(body)) : null}, type || 'application/json');
}
function router(path, body)
{
	top.postMessage({path, body: body ? (typeof body === 'string' ? body : JSON.stringify(body)) : null});
	return false;
}
// function screen()
// {
// 	html2canvas(document.querySelector('iframe').contentDocument.body).then(canvas => {
// 		canvas.toBlob(blob => {
// 			const anchor = document.createElement('a');
// 			anchor.href = URL.createObjectURL(blob);
// 			anchor.download = 'screen.png';
// 			anchor.click();
// 		}, 'image/png');
// 	});
// }
async function register(random, answer)
{
	return caller('?api/register', {random, answer}, 'application/json').then(result =>
	{
		if (result.code === 200 && result.data.signature)
		{
			top.resolve(result.data);
		}
		return result;
	});
}
window.addEventListener('DOMContentLoaded', async function()
{
	if (top !== self)
	{
		self.apphead = {};
		self.entry = top.document.querySelector('iframe').dataset.entry;
		
		self.onmessage = event => 
		{
			self.onmessage = null;
			Object.assign(self.apphead, event.data);
			const viewport = new IntersectionObserver(entries =>
			{
				entries.forEach(entry =>
				{
					if (entry.isIntersecting)
					{
						if (entry.target === lazy)
						{
							loader(`${self.entry}${lazy.dataset.lazy},page:${++lazy.dataset.page}`, {headers : self.apphead}, 'text/plain').then(data =>
							{
								if (data)
								{
									const renderer = document.createElement('template');
									renderer.innerHTML = data;
									renderer.content.querySelectorAll('img[data-src]').forEach(img => viewport.observe(img));
									lazy.parentNode.insertBefore(renderer.content, lazy);
									//console.log(lazy.dataset.page)
								}
								else
								{
									const finished = document.createElement('div');
									finished.innerHTML = '已加载全部内容';
									finished.style.cssText = 'text-align: center;padding: 10px 0;color: #7a7a74';
									lazy.parentNode.parentNode.insertBefore(finished, lazy.parentNode.parentNode.nextElementSibling);
									viewport.unobserve(lazy.parentNode.removeChild(lazy));
									//console.log('delete')
								}
							});
						}
						else
						{
							viewport.unobserve(entry.target);
							loader(entry.target.dataset.src, null, 'application/octet-stream')
								.then(blob => entry.target.src = URL.createObjectURL(blob));
						}
					}
				});
			}), lazy = document.querySelector('[data-lazy]');
			lazy && viewport.observe(lazy);
			document.querySelectorAll('img[data-src]').forEach(img => viewport.observe(img));
			document.addEventListener('click', event =>
			{
				for (let target = event.target; target.parentNode; target = target.parentNode)
				{
					if (target.tagName === 'A'
						&& target.hasAttribute('href')
						&& (target.hasAttribute('target') === false || target.getAttribute('target') === '')
						&& /^javascript:|^blob:/.test(target.getAttribute('href')) === false) {
						router(target.getAttribute('href'));
						event.preventDefault();
						break;
					}
				}
			});
			self.init && self.init();
		};
		return top.postMessage(null);
	}
	const
	logs = JSON.parse(localStorage.getItem('logs')) || [],
	frame = document.querySelector('iframe'),
	entry = frame.dataset.entry,
	headers = {'Content-Type': 'application/data-stream'},
	initreq = Object.assign({'Account-Init': 0}, headers),
	revertsign = location.hash.substring(5);
	function log(query)
	{
		if (typeof query !== 'string')
		{
			query = typeof query === 'number'
				? (logs.length ? logs.splice(-1 + query)[0] : '')
				: logs[logs.length];
		}
		//const path = query.split(',', 1)[0];
		if (logs.length === 0 || logs[logs.length - 1] !== query)
		{
			logs[logs.length] = query;
			if (logs.length > 10)
			{
				logs.splice(1, 1);
			}
			localStorage.setItem('logs', JSON.stringify(logs));
		}
		return entry + query;
	}
	top.entergame = function()
	{
		loader(`${entry}?game-enter`, {headers: Object.assign({Authorization:
			`Bearer ${localStorage.getItem('account')}`}, headers)}, 'text/plain').then(data => {

			frame.style.display = 'none';
			const game = document.createElement('iframe'), close = document.createElement('span');
			game.setAttribute('frameborder', 0);
			game.width = game.height = '100%';
			game.src = data;

			//close.textContent = '离开游戏';
			close.textContent = '❌';
			close.onclick = () =>
			{
				if (confirm('离开游戏？'))
				{
					frame.style.display = null;
					document.body.removeChild(close);
					document.body.removeChild(game);
				}
			}
			close.style.cssText = 'position:fixed;top:14rem;right:.6rem;font-size:1.5rem';
		
			document.body.appendChild(close);
			document.body.appendChild(game);
			//alert(data)
		});
	}


	// frame.addEventListener('transitionend', event =>
	// {
	// 	alert(1)
	// });
	function render(data)
	{
		frame.onload = () =>
		{
			frame.onload = () =>
			{
				if (frame.contentDocument.body.childNodes.length === 0)
				{
					//alert(frame.contentDocument.body.childNodes.length)
					//logs.splice(0);
					loader(log(frame.dataset.query), {headers: initreq}, 'text/plain').then(render);
				}
			};
			frame.contentDocument.open();
			frame.contentDocument.write(data);
			frame.contentDocument.close();
		}
		frame.style.visibility = 'hidden';
		frame.src = 'about:blank';

		// frame.style.opacity = 0;
		// frame.ontransitionend = () =>
		// {
		// 	frame.ontransitionend = null;
		// 	frame.onload = () =>
		// 	{
		// 		frame.onload = null;
		// 		//frame.contentDocument.removeChild(frame.contentDocument.documentElement);
		// 		frame.contentDocument.open();
		// 		frame.contentDocument.write(data);
		// 		frame.contentDocument.close();
		// 	}
		// 	frame.src = 'about:blank';
		// 	//frame.contentWindow.location.reload();//iOS Safari 闪屏
		// };
	}
	if (location.hash.substring(1))
	{
		initreq['Unit-Code'] = headers['Unit-Code'] = location.hash.substring(1, 5);
	}
	if (frame.dataset.query.length === 0)
	{
		frame.dataset.query = window.name ? (logs[logs.length - 1] || '') : '?home/home';
	}
	window.addEventListener('message', event =>
	{
		if (event.data)
		{
			loader(log(event.data.path), {headers, method: event.data.body
				? 'POST' : 'GET', body: event.data.body}, 'text/plain').then(render);
		}
		else
		{
			//frame.style.opacity = 1;
			frame.style.visibility = 'visible';
			frame.contentWindow.postMessage(headers);
		}
	});
	new Promise(resolve =>
	{
		if (window.name) return resolve();
		loader(`${entry}?api/screen`, {headers}, 'application/json').then(screen =>
		{
			if (screen.data === null) return resolve(window.name = 'app');
			const fdoc = frame.contentDocument,
			fdiv = fdoc.createElement('div'),
			ftxt = fdoc.createTextNode('??'),
			ftimer = seconds =>
			{
				if (seconds > -1)
				{
					ftxt.nodeValue = `${seconds}s`;
					ftimer.tid = setTimeout(ftimer, 1000, --seconds);
				}
				else
				{
					fdoc.onclick = null;
					resolve(window.name = 'app');
				}
			};
			//fdiv.textContent = '正在进入';
			fdiv.appendChild(ftxt);
			fdoc.onclick = event =>
			{
				if (event.target !== fdiv)
				{
					fdoc.onclick = null;
					clearTimeout(ftimer.tid);
					if (screen.data.target)
					{
						window.open(`${entry}${screen.data.query}`);
					}
					else
					{
						frame.dataset.query = screen.data.query
					}
					resolve(window.name = 'app');
				}
			};
			fdoc.head.appendChild(fdoc.createElement('style')).textContent = `
			body,div::before{
				background-position: center;
				background-size: cover;
				background-attachment: fixed;
				background-repeat: no-repeat;
			}
			div::before{
				content: '';
				position: absolute;
				top: 0;
				left: 0;
				right: 0;
				bottom: 0;
				filter: blur(1rem);
				z-index: -1;
				margin: -2rem;
			}
			div{
				width: 4rem;
				position: fixed;
				top: 1rem;
				right: 0;
				
				margin: 0 auto;
				padding: 1rem .4rem;
				box-shadow: 0 .5rem 2rem rgb(27, 31, 35);
				border-radius: .6rem 0 0 .6rem;
				overflow: hidden;
				z-index: 1;
				font-family: consolas, monospace;
				font-weight: bold;
				font-size: 1.4rem;
				text-align: center;
				text-shadow: black 1px 1px;
				letter-spacing: .4rem;
				color: white;
				cursor: pointer;
			}`;
			loader(screen.data.src, null, 'application/octet-stream').then(blob =>
			{
				fdoc.styleSheets[0].insertRule(`body,div::before{background-image:url(${URL.createObjectURL(blob)})}`, 0);
				fdoc.body.appendChild(fdiv);
				ftimer(screen.data.seconds);
			});
		});
	}).then(() => new Promise(async resolve =>
	{
		let unauthorized = true;
		if (revertsign)
		{
			await loader(`${entry}?api/user`, {headers: Object.assign({Authorization:
				`Bearer ${revertsign}`}, headers)}, 'application/json').then(account => {
				if (account.data.uid)
				{
					unauthorized = false;
					localStorage.setItem('account', revertsign);
					console.log('revert', account);
				}
			});
		}
		if (unauthorized && localStorage.getItem('account'))
		{
			await loader(`${entry}?api/user`, {headers: Object.assign({Authorization:
				`Bearer ${localStorage.getItem('account')}`}, headers)}, 'application/json').then(account => {
				if (account.data.uid)
				{
					unauthorized = false;
					console.log('sign', account);
				}
				else
				{
					localStorage.removeItem('account');
				}
			});
		}
		if (unauthorized)
		{
			await loader(`${entry}?api/register`, {headers, method: 'POST', body: '{"random":"random","answer":"answer"}'}, 'application/json').then(result =>
			{
				if (result.code === 200 && result.data.signature)
				{
					localStorage.setItem('account', result.data.signature);
				}
			});

			// await loader(`${entry}?register`, {headers}, 'text/plain').then(render).then(() => new Promise(async resolve => top.resolve = resolve)).then(account =>
			// {
			// 	console.log('register', account);
			// 	localStorage.setItem('account', account.signature);
			// });
		}
		if (localStorage.getItem('account'))
		{
			document.cookie = `account=${localStorage.getItem('account')}`;
			initreq.Authorization = headers.Authorization = `Bearer ${localStorage.getItem('account')}`;
			initreq['Account-Init'] = 1;
			history.pushState(null, null, `${location.origin}${location.pathname}`);
			// history.back();
			// history.forward();
			//window.addEventListener('popstate', event => console.log(event));
			top.init && top.init(localStorage.getItem('account'));
			resolve();
		}
		else
		{
			alert('Unauthorized');
		}
	})).then(() => loader(log(frame.dataset.query), {headers: initreq}, 'text/plain').then(render));
});