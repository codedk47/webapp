async function loader(resource, options = {})
{
	const response = await fetch(resource, options);
	let type = response.headers.get('content-type') || 'application/octet-stream', blob;
	if (options.mask || type.startsWith('@'))
	{
		const reader = response.body.getReader(), key = new Uint8Array(8), buffer = [];
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
		type = options.type || type.startsWith('@') ? type.substring(1) : type;
		blob = new Blob(buffer, {type});
	}
	else
	{
		blob = await response.blob();
	}

	switch (options.type || type.split(';')[0])
	{
		case 'application/json': return JSON.parse(await blob.text());
		case 'text/modify': return [response.url, await blob.text(), Object.fromEntries(response.headers.entries())];
		case 'text/plain': return blob.text();
		default: return URL.createObjectURL(blob);
	}
}

if (self.window)
{
	loader.worker = (function()
	{
		let id = 0;
		const promise = new Map, worker = new Worker(document.currentScript.src);
		worker.onmessage = event =>
		{
			//alert(event.data)
			//console.log(event);
			const callback = promise.get(event.data.id);
			if (callback)
			{
				promise.delete(event.data.id);
				callback[event.data.is](event.data.content);
			}
		};
		function upload(resource, files, progress)
		{

		}
		return async (resource, options, progress) => options instanceof FileList
			? upload(resource, options, progress || (value => value))
			: new Promise((resolve, reject) => promise.set(++id, [resolve, reject]) && worker.postMessage({id, resource, options}));
	}());
}
else
{
	//fix safari can't set variable bug
	self.count = 0;
	self.queue = [];
	function worker(data)
	{
		loader(data.resource, data.options)
			.then(content => self.postMessage({id: data.id, is: 0, content}))
			.catch(error => self.postMessage({id: data.id, is: 1, content: error}))
			.finally(() =>
			{
				--self.count;
				if (self.queue.length)
				{
					worker(self.queue.shift());
				}
			});
	}
	self.onmessage = event =>
	{
		if (++self.count < 6)
		{
			worker(event.data);
		}
		else
		{
			//self.queue.push(event.data);
			self.queue[self.queue.length] = event.data;
		}
	};
}