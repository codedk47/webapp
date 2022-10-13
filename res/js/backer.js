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
		return (url, options = {cache: 'no-store'}) => options instanceof FileList
			? Promise.allSettled(Array.from(options).map(file => new Promise(async (resolve, reject) =>
			{
				let hash = 5381n;
				const stream = file.stream(), reader = stream.getReader();
				await reader.read().then(function time33({done, value})
				{
					if (done) return reader.releaseLock();
					for (let i = 0; i < value.length; ++i)
					{
						hash = (hash & 0xfffffffffffffffn) + ((hash & 0x1ffffffffffffffn) << 5n) + BigInt(value[i]);
					}
					reader.read().then(time33);
				});
				await reader.cancel();
				fetch(url, {
					method: 'POST',
					headers: {'Content-Type': 'application/json'},
					body: JSON.stringify({
						//hash: hash.toString(16).padStart(16, 0),
						size: file.size,
						type: file.type,
						name: file.name})
				}).then(response => response.text()).then(url =>
				{
					console.log(url)
					const reader = stream.getReader();
					reader.read().then(function uploaddata({done, value})
					{
						console.log(11111);
						if (done) return resolve(file);
						new Promise((resolve, reject) =>
						{
							promise.set(++id, [resolve, reject]);
							backer.postMessage({id, url, options, buffer: value.buffer}, [value.buffer]);
						}).then(() => reader.read().then(uploaddata)).catch(reject);
					});
				}).catch(reject);
			})))
			: new Promise((resolve, reject) => promise.set(++id, [resolve, reject]) && backer.postMessage({id, url, options}));
	}());
}
else
{
	const controller = new AbortController, queue = [];
	async function load(url, options)
	{
		//application/octet-stream
		//application/octet-masker
		const
		option = {...options, signal: controller.signal},
		response = await fetch(url, option),
		type = response.headers.get('content-type');
		let blob;
		if (option.mask || type === 'application/octet-masker')
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
			blob = new Blob(buffer, {type});
		}
		else
		{
			blob = await response.blob();
		}

		switch (option.type || type)
		{
			case 'application/json': return JSON.parse(await blob.text());
			case 'text/plain': return blob.text();
			default: return URL.createObjectURL(blob);
		}
	}
	function work(data)
	{
		load(data.url, data.buffer ? {
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
	let count = 0;
	self.onmessage = event =>
	{
		if (event.data === 'abort')
		{
			controller.abort();
			queue.length = count = 0;
		}
		else
		{
			if (++count < 6)
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