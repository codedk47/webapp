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
function getimg(img)
{
	loader('http://192.168.0.155/cover1.bin', null, 'application/octet-stream').then(blob => img.src = URL.createObjectURL(blob));
	//loader( img.dataset.src, null, 'application/octet-stream').then(blob => img.src = URL.createObjectURL(blob));
}
function aaa(data)
{
	console.log(data)
}
function driver(path, body)
{
	top.postMessage({path, body: body ? (typeof body === 'string' ? body : JSON.stringify(body)) : null});
	return false;
}
window.addEventListener('DOMContentLoaded', async function()
{
	if (top !== self)
	{
		console.log('app loaded');
		const viewport = new IntersectionObserver(entries =>
		{
			entries.forEach(entry =>
			{
				if (entry.isIntersecting)
				{
					if (entry.target === lazy)
					{
						loader(`http://192.168.0.119/${lazy.dataset.lazy},page:${++lazy.dataset.page}`, {headers: {'Content-Type': 'application/data-stream'}}, 'text/plain').then(data =>
						{
							if (data)
							{
								const renderer = document.createElement('template');
								renderer.innerHTML = data;
								renderer.content.querySelectorAll('img[data-src]').forEach(img => viewport.observe(img));
								lazy.parentNode.insertBefore(renderer.content, lazy);
								console.log(lazy.dataset.page)
							}
							else
							{
								viewport.unobserve(lazy.parentNode.removeChild(lazy));
								console.log('delete')
							}
							
						});
					}
					else
					{
						viewport.unobserve(entry.target);
						getimg(entry.target);
					}
					
				}
			});
		}), lazy = document.querySelector('[data-lazy]');
		lazy && viewport.observe(lazy);
		document.querySelectorAll('img[data-src]').forEach(img => viewport.observe(img));
		document.querySelectorAll('[class~="back"]').forEach(back => back.onclick = () => driver(-1));
		return document.addEventListener('click', event =>
		{
			for (let target = event.target; target.parentNode; target = target.parentNode)
			{
				if (target.tagName === 'A' && target.hasAttribute('href') && /^javascript:/.test(target.getAttribute('href')) === false)
				{
					event.preventDefault();
					driver(target.getAttribute('href'));
					break;
				}
			}
		});
	}
	const
	historys = [],
	ifa = document.querySelector('iframe'),
	source = historys[historys.length] = ifa.dataset.app,
	headers = {
		'Content-Type': 'application/data-stream'
	}, initreq = Object.assign({'Account-Init': 0}, headers);
	if (location.hash.substring(1))
	{
		initreq['Unit-Code'] = headers['Unit-Code'] = location.hash.substring(1, 5);
	}
	if (location.hash.substring(5))
	{
		await loader(`${source}?api/user`, {headers}, 'text/plain').then(result =>
		{
			console.log(result)
		});
	}
	if (window.localStorage.getItem('account') === null)
	{
		await loader(`${source}?api/user`, {headers}, 'application/json').then(result =>
		{
			window.localStorage.setItem('account', result.data);
			initreq['Account-Init'] = 1;
		});
	}
	if (window.localStorage.getItem('account') === null)
	{
		return console.log('Unauthorized');
	}
	initreq.Authorization = headers.Authorization = `Bearer ${window.localStorage.getItem('account')}`;


	console.log( headers, initreq, location )


	function render(data)
	{
		ifa.contentDocument.removeChild(ifa.contentDocument.documentElement);
		ifa.contentDocument.write(data);
		ifa.contentDocument.close();
	}

	history.pushState(null, document.title, location.href);
	history.back();
	history.forward();
	window.onpopstate = () => history.go(1);
	window.addEventListener('message', event =>
	{
		const url = typeof event.data.path === 'string' ? source + event.data.path : (
			typeof event.data.path === 'number'
				? historys[Math.max(0, Math.min(9, historys.length - 1 + event.data.path))]
				: historys[historys.length - 1]);
		if (historys.length > 9)
		{
			historys.shift();
		}
		loader(historys[historys.length] = url, {headers,
			method: event.data.body ? 'POST' : 'GET', body: event.data.body}, 'text/plain').then(render);
	});
	loader(source, {headers: initreq}, 'text/plain').then(render);
});


/*
function unpack(data)
{
	const key = new Uint8Array(data.slice(0, 8));
	const buffer = new Uint8Array(data.slice(8));
	for (let i = 0; i < buffer.length; ++i)
	{
		buffer[i] = buffer[i] ^ key[i % 8];
	}
	return buffer;
}
function request(method, url, body = null)
{
	return new Promise(function(resolve, reject)
	{
		const xhr = new XMLHttpRequest;
		xhr.open(method, url);
		xhr.responseType = 'arraybuffer';
		xhr.onload = () => resolve(JSON.parse(/json/.test(xhr.getResponseHeader('Content-Type'))
			? (new TextDecoder('utf-8')).decode(new Uint8Array(xhr.response))
			: unpack(xhr.response)));
		xhr.onerror = () => reject(xhr);
		xhr.send(body);
	});
}
*/