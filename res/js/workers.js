if (globalThis.window)
{
	window[document.currentScript.dataset.name || 'worker'] = (function()
	{
		let id = 0;
		const promise = new Map, worker = new Worker(document.currentScript.src);
		worker.onmessage = event =>
		{
			//console.log(event);
			const callback = promise.get(event.data.id);
			if (callback)
			{
				promise.delete(event.data.id);
				callback[event.data.is](event.data.content);
			}
		};
		return (url, type = 'application/octet-stream', options = {cache: 'no-store'}) => new Promise((resolve, reject) =>
		{
			promise.set(++id, [resolve, reject]);
			worker.postMessage({id, url, type, options});
		});
	}());
}
else
{
	let count = 0;
	const controller = new AbortController, queue = [], limit = 4;
	async function load(url, type, options)
	{
		const
		response = await fetch(url, {...options, signal: controller.signal}),
		
		reader = response.body.getReader(),
		key = new Uint8Array(8),
		buffer = [];


		//console.log(response);

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
			default: return URL.createObjectURL(blob);
		}
	}
	function work(data)
	{
		load(data.url, data.type, data.option)
			.then(content => self.postMessage({id: data.id, is: 0, content}))
			.catch(error => self.postMessage({id: data.id, is: 1, content: error}))
			.finally(() =>
			{
				
				--count;
				if (queue.length)
				{
					console.log(queue);
					work(queue.shift());
				}
				
			});
	}
	
	self.onmessage = event =>
	{
		if (event.data === 'abort')
		{
			controller.abort();
			queue.length = count = 0;
		}
		else
		{
			if (count++ < limit)
			{
				work(event.data);
			}
			else
			{
				queue[queue.length] = event.data;
			}
		}
	};
}