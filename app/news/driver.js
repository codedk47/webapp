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
	return loader(path, {method: body ? 'POST' : 'GET', headers: self.apphead,
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
window.addEventListener('DOMContentLoaded', async function()
{
	if (top !== self)
	{
		self.apphead = {};
		const
		origin = top.document.querySelector('iframe').dataset.entry,
		video = document.querySelector('video');
		if (self.Hls && video)
		{
			//video.disablePictureInPicture = true;
			loader(`${video.dataset.src}/cover`, null, 'application/octet-stream').then(blob => video.poster = URL.createObjectURL(blob));
			if (Hls.isSupported())
			{
				const hls = new Hls;
				hls.attachMedia(video);
				hls.loadSource(`${video.dataset.src}/play`);
				//console.log(video.dataset.src);
			}
			else
			{
				const hls = document.createElement('source');
				hls.type = 'application/x-mpegURL';
				hls.src = `${video.dataset.src}/play.m3u8`;
				video.appendChild(hls);
			}
			window.addEventListener("orientationchange", () =>
			{
				if (screen.orientation.angle)
				{
					//竖屏
				}
				else
				{
					//横屏
				}
			});
		}
		//console.log(origin);
		//self.apphead = {};
		//console.log('app loaded');
		self.onmessage = event => 
		{
			self.onmessage = null;
			Object.assign(self.apphead, event.data);
			self.init && self.init();
			self.init = null;
			const viewport = new IntersectionObserver(entries =>
			{
				entries.forEach(entry =>
				{
					if (entry.isIntersecting)
					{
						if (entry.target === lazy)
						{
							loader(`${origin}${lazy.dataset.lazy},page:${++lazy.dataset.page}`, {headers : self.apphead}, 'text/plain').then(data =>
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
					if (target.tagName === 'A' && target.hasAttribute('href') && /^javascript:|^blob:/.test(target.getAttribute('href')) === false)
					{
						event.preventDefault();
						router(target.getAttribute('href'));
						break;
					}
				}
			});
		};
		return top.postMessage(null);
	}
	const
	logs = JSON.parse(localStorage.getItem('logs')) || [],
	frame = document.querySelector('iframe'),
	entry = frame.dataset.entry,
	headers = {'Content-Type': 'application/data-stream'},
	initreq = Object.assign({'Account-Init': 0}, headers);
	function log(query)
	{
		if (typeof query !== 'string')
		{
			query = typeof query === 'number'
				? (logs.length ? logs.splice(-1 + query)[0] : '')
				: logs[logs.length];
		}
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
	function render(data)
	{
		frame.contentDocument.removeChild(frame.contentDocument.documentElement);
		frame.contentDocument.write(data);
		frame.contentDocument.close();
	}
	if (location.hash.substring(1))
	{
		initreq['Unit-Code'] = headers['Unit-Code'] = location.hash.substring(1, 5);
	}
	if (location.hash.substring(5))
	{
		// await loader(`${entry}?api/user`, {headers}, 'text/plain').then(result =>
		// {
		// 	console.log(result)
		// });
	}
	if (localStorage.getItem('account') === null)
	{
		await loader(`${entry}?api/register`, {headers}, 'application/json').then(result =>
		{
			localStorage.setItem('account', result.data.signature);
			initreq['Account-Init'] = 1;
		});
	}
	if (localStorage.getItem('account') === null)
	{
		return console.log('Unauthorized');
	}
	initreq.Authorization = headers.Authorization = `Bearer ${localStorage.getItem('account')}`;

	if (frame.dataset.query.length === 0)
	{
		frame.dataset.query = logs[logs.length - 1] ?? '';
	}
	// let load;
	// frame.addEventListener('transitionend', event =>
	// {
	// 	if (frame.style.opacity == 0)
	// 	{
	// 		load.then(render);
	// 	}
	// });
	history.pushState(null, null, `${location.origin}${location.pathname}`);
	history.back();
	history.forward();
	window.addEventListener('popstate', () => history.go(1));
	window.addEventListener('message', event =>
	{
		if (event.data)
		{
			//frame.style.opacity = 0;
			loader(log(event.data.path), {headers, method: event.data.body
				? 'POST' : 'GET', body: event.data.body}, 'text/plain').then(render);
		}
		else
		{
			//frame.style.opacity = 1;
			frame.contentWindow.postMessage(headers);
		}
	});
	loader(log(frame.dataset.query), {headers: initreq}, 'text/plain').then(render);
});