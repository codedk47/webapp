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
	return new Blob(buffer, {type});
}
function driver(path, method, body, type)
{
	top.postMessage({path, method: method || 'get', body, type});
	return false;
}
window.addEventListener('DOMContentLoaded', async function()
{
	if (top !== self)
	{
		// document.querySelectorAll('img[data-src]').forEach(function(img)
		// {
		// 	loader('http://192.168.0.155/cover1.bin', null, 'application/octet-stream').then(blob => img.src = URL.createObjectURL(blob));
		// });
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



//			'Authorization': 'Bearer 1231231',

	const
	ifa = document.querySelector('iframe'),
	source = ifa.dataset.app,
	headers = {
		'Content-Type': 'application/data-stream',
		'Unit-Code': /^\w{4}$/.test(location.search.substring(1, 5)) ? location.search.substring(1, 5) : ''
	}, initreq = Object.assign({'Account-Init': 0}, headers);
	if (window.localStorage.getItem('account') === null)
	{
		await loader(`${source}?api/user`, {headers}).then(blob => blob.text()).then(data =>
		{
			window.localStorage.setItem('account', JSON.parse(data).data);
			initreq['Account-Init'] = 1;
		});
	}
	if (window.localStorage.getItem('account') === null)
	{
		return console.log('Unauthorized');
	}
	initreq.Authorization = headers.Authorization = `Bearer ${window.localStorage.getItem('account')}`;


	console.log( headers, initreq )


	function render(data)
	{
		ifa.contentDocument.removeChild(ifa.contentDocument.documentElement);
		ifa.contentDocument.write(data);
		ifa.contentDocument.close();
	}
	window.addEventListener('message', event =>
	{
		console.log(event.data)
		loader(source + event.data.path, {headers}).then(blob => blob.text()).then(render);
	});
	loader(source, {headers: initreq}).then(blob => blob.text()).then(render);
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