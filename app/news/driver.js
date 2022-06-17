const apphead = {};
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
	return loader(path, {method: body ? 'POST' : 'GET', headers: apphead,
		body: body ? (typeof body === 'string' ? body : JSON.stringify(body)) : null}, type || 'application/json');
}
function router(path, body)
{
	top.postMessage({path, body: body ? (typeof body === 'string' ? body : JSON.stringify(body)) : null});
	return false;
}
window.addEventListener('DOMContentLoaded', async function()
{
	if (top !== self)
	{
		//console.log('app loaded');
		self.onmessage = event => 
		{
			self.onmessage = null;
			Object.assign(apphead, event.data);
			const viewport = new IntersectionObserver(entries =>
			{
				entries.forEach(entry =>
				{
					if (entry.isIntersecting)
					{
						if (entry.target === lazy)
						{
							loader(`http://192.168.0.119/${lazy.dataset.lazy},page:${++lazy.dataset.page}`, {headers: apphead}, 'text/plain').then(data =>
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
									viewport.unobserve(lazy.parentNode.removeChild(lazy));
									//console.log('delete')
								}
							});
						}
						else
						{
							viewport.unobserve(entry.target);
							loader('http://192.168.0.155/cover1.bin', null, 'application/octet-stream')
							//loader(entry.target.dataset.src, {headers: apphead}, 'application/octet-stream')
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
					if (target.tagName === 'A' && target.hasAttribute('href') && /^javascript:|^blob:/.test(target.getAttribute('href')) === false)
					{
						event.preventDefault();
						router(target.getAttribute('href'));
						break;
					}
				}
			});
			document.querySelectorAll('[class~="back"]').forEach(back => back.onclick = () => router(-1));
		};
		return top.postMessage(null);
	}
	const
	historys = [],
	ifa = document.querySelector('iframe'),
	source = historys[historys.length] = ifa.dataset.app,
	headers = {'Content-Type': 'application/data-stream'},
	initreq = Object.assign({'Account-Init': 0}, headers);
	function render(data)
	{
		ifa.contentDocument.removeChild(ifa.contentDocument.documentElement);
		ifa.contentDocument.write(data);
		ifa.contentDocument.close();
	}
	if (location.hash.substring(1))
	{
		initreq['Unit-Code'] = headers['Unit-Code'] = location.hash.substring(1, 5);
	}
	if (location.hash.substring(5))
	{
		// await loader(`${source}?api/user`, {headers}, 'text/plain').then(result =>
		// {
		// 	console.log(result)
		// });
	}
	if (window.localStorage.getItem('account') === null)
	{
		await loader(`${source}?api/register`, {headers}, 'application/json').then(result =>
		{
			window.localStorage.setItem('account', result.data.signature);
			initreq['Account-Init'] = 1;
		});
	}
	if (window.localStorage.getItem('account') === null)
	{
		return console.log('Unauthorized');
	}
	initreq.Authorization = headers.Authorization = `Bearer ${window.localStorage.getItem('account')}`;
	history.pushState(null, null, location.href);
	history.back();
	history.forward();
	window.addEventListener('popstate', () => history.go(1));
	window.addEventListener('message', event =>
	{
		if (event.data)
		{
			const url = typeof event.data.path === 'string' ? historys[historys.length] = source + event.data.path : (
				typeof event.data.path === 'number'
					? historys.splice(Math.max(0, Math.min(9, historys.length - 1 + event.data.path)), 1)
					: historys[historys.length - 1]);
			if (historys.length > 9)
			{
				historys.shift();
			}
			loader(url, {headers,
				method: event.data.body ? 'POST' : 'GET', body: event.data.body}, 'text/plain').then(render);
		}
		ifa.contentWindow.postMessage(headers);
	});
	loader(source, {headers: initreq}, 'text/plain').then(render);
});