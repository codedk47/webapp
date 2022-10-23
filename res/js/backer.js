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
		function upload(url, files, progress)
		{
			let size = 0, sent = 0;
			const code = '0123456789ABCDEFGHIJKLMNOPQRSTUV';
			return Promise.allSettled(Array.from(files).map(file => new Promise(async (resolve, reject) =>
			{
				size += file.size;
				let hash = 5381n, reader = file.stream().getReader();
				
				// const
				// key = await reader.read().then(async function time33({done, value})
				// {
				// 	if (done) return hash.toString(16).padStart(16, 0).match(/.{2}/g).map(value => parseInt(value, 16));
				// 	for (let i = 0; i < value.length; hash = (hash & 0xfffffffffffffffn) + ((hash & 0x1ffffffffffffffn) << 5n) + BigInt(value[i++]));
				// 	return await reader.read().then(time33);
				// });

				await reader.read().then(async function time33({done, value})
				{
					if (done) return;
					for (let i = 0; i < value.length; ++i)
					{
						hash = (hash & 0xfffffffffffffffn) + ((hash & 0x1ffffffffffffffn) << 5n) + BigInt(value[i++]);
					}
					await reader.read().then(time33);
				});



				console.log(hash);
				return;
				
				const response = await fetch(url, {
					method: 'POST',
					headers: {'Content-Type': 'application/json'},
					body: JSON.stringify({key,
						hash: Array.from(Array(10)).map((v, i) => code[hash >> BigInt(i) * 5n & 31n]).join(''),
						size: file.size,
						mime: file.type,
						...(i = file.name.lastIndexOf('.')) !== -1
							? {type: file.name.substring(i + 1), name: file.name.substring(0, i)}
							: {type: '', name: file.name}
					})
				});
				console.log(hash);
				return;
				if (response.ok)
				{
					url = await response.text();
					reader = file.stream().getReader();
					let read = 0;
					do
					{
						let {done, value} = await reader.read();
						if (done) return resolve(file);
						if (await new Promise((resolve, reject) =>
						{
							promise.set(++id, [resolve, reject]);
							console.log(1)
							for (let i = 0; i < value.length; ++i)
							{
								value[i] = value[i] ^ key[read++ % 8];
							}
							backer.postMessage({id, url, options: {method: 'POST', body: value.buffer}}, [value.buffer]);
						}).then(() =>
						{
							
							sent += value.length;
							progress(sent / size);
							return true;
						})) continue;
					} while (false);
				}
				return reject(file);



				while (({done, value} = await reader.read()).done === false)
				{
					send += value.length;
					progress(send / size);
					await new Promise((resolve, reject) =>
					{
						promise.set(++id, [resolve, reject]);
						for (let i = 0; i < value.length; ++i)
						{
							value[i] = value[i] ^ key[i % 8];
						}
						backer.postMessage({id, url, options : {
							method: 'POST',
							body: value.buffer
						}}, [value.buffer]);
					});
				}
				resolve(file);


				reader.read().then(function uploaddata({done, value})
				{
					if (done) return resolve(file);
					send += value.length;
					progress(send / size);
					new Promise((resolve, reject) =>
					{
						promise.set(++id, [resolve, reject]);
						for (let i = 0; i < value.length; ++i)
						{
							value[i] = value[i] ^ key[i % 8];
						}
						backer.postMessage({id, url, options : {
							method: 'POST',
							body: value.buffer
						}}, [value.buffer]);
					}).then(() => reader.read().then(uploaddata)).catch(reject);
				});
				//console.log(url)
		

				return;
				a.then(async response =>
				{
					if (response.ok === false) return reject();
					const url = await response.text(), reader = file.stream().getReader();
					reader.read().then(function uploaddata({done, value})
					{
						if (done) return resolve(file);
						send += value.length;
						progress(send / size);


						console.log( Math.random().toString(16) );


						new Promise((resolve, reject) =>
						{
							promise.set(++id, [resolve, reject]);

							for (let i = 0; i < value.length; ++i)
							{
								value[i] = i;
								//console.log(value[i])
							}


							console.log(value.buffer)
							//resolve();

							// backer.postMessage({id, url, options : {
							// 	method: 'POST',
							// 	body: value.buffer
							// }}, [value.buffer]);
						}).then(() => reader.read().then(uploaddata)).catch(reject);
					});
				}).catch(reject);
			})));
		}
		return (url, options, progress) => options instanceof FileList
			? upload(url, options, progress || (value => value))
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
		load(data.url, data.options)
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