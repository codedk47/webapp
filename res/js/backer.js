if (globalThis.window)
{
	window[document.currentScript.dataset.name || 'backer'] = (function()
	{
		let id = 0;
		const promise = new Map, backer = new Worker(document.currentScript.src);
		backer.onmessage = event =>
		{
			//console.log(event);
			const callback = promise.get(event.data.id);
			if (callback)
			{
				promise.delete(event.data.id);
				callback[event.data.is](event.data.content);
			}
		};
		return (url, type = 'application/octet-stream', options = {cache: 'no-store'}) => typeof type === 'string'
			? new Promise((resolve, reject) => promise.set(++id, [resolve, reject]) && backer.postMessage({id, url, type, options}))
			: Promise.allSettled(Array.from(type).map(file => new Promise((resolve, reject) =>
			{
				const reader = file.stream().getReader();
				reader.read().then(function uploaddata({done, value})
				{
					if (done) return resolve(file);
					new Promise((resolve, reject) =>
					{
						promise.set(++id, [resolve, reject]);
						backer.postMessage({id, url, type: 'application/octet-stream', options, buffer: value.buffer}, [value.buffer]);
					}).then(()=> reader.read().then(uploaddata)).catch(reject);
				});
			})));
	}());
}
else
{
	let count = 0;
	const controller = new AbortController, queue = [], limit = 4;
	async function load(url, type, options)
	{
		//application/octet-stream
		//application/octet-masker
		const
		option = {...options, signal: controller.signal}, response = await fetch(url, option), type = response.headers.get('content-type');
		if (option.mask ||  === 'application/octet-masker')
		{
			let blob;
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
			blob = new Blob(buffer, {type});
		}
		else
		{
			blob = await response.blob();
		}
		
		switch (option.type || response.headers.get('content-type'))
		{
			case 'application/json': return JSON.parse(await blob.text());
			case 'text/plain': return blob.text();
			default: return URL.createObjectURL(blob);
		}
	}
	function work(data)
	{
		load(data.url, data.type, data.buffer ? {
			...data.options,
			method: 'POST',
			body: data.buffer
		} : data.options)
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