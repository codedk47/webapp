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
		case 'text/plain': return blob.text();
		default: return URL.createObjectURL(blob);
	}
}

if (globalThis.window)
{
	loader.worker = (function()
	{
		let id = 0;
		const
		byte = 16,
		code = '0123456789ABCDEFGHIJKLMNOPQRSTUV',
		promise = new Map,
		backer = new Worker(document.currentScript.src);
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
		function upload(resource, files, progress)
		{
			let size = 0, sent = 0;
			return Promise.allSettled(Array.from(files).map(file => new Promise(async (resolve, reject) =>
			{
				size += file.size;
				let
				hash = 5381n,
				reader = file.stream().getReader(),
				offset = 0,
				key = Array(byte).fill(parseInt(file.size / byte, 10)).map((v, k) => v * k),
				i = 0;
				do
				{
					let {done, value} = await reader.read();
					if (done)
					{
						//key = hash.toString(16).padStart(16, 0).match(/.{2}/g).map(value => parseInt(value, 16));
						break;
					}
					while (i < byte && offset + value.length > key[i])
					{
						//console.log( i, ' - ', value[key[i] - offset], ' - ', String.fromCharCode(value[key[i] - offset]) );
						hash = (hash & 0xfffffffffffffffn) + ((hash & 0x1ffffffffffffffn) << 5n) + BigInt(value[key[i++] - offset]);
					}
					offset += value.length;
				} while (true);
				const response = await fetch(resource, {
					method: 'POST',
					headers: {'Content-Type': 'application/json'},
					body: JSON.stringify({
						hash: Array.from(Array(12)).map((v, i) => code[hash >> BigInt(i) * 5n & 31n]).join(''),
						size: file.size,
						mime: file.type,
						...(i = file.name.lastIndexOf('.')) !== -1
							? {type: file.name.substring(i + 1), name: file.name.substring(0, i)}
							: {type: '', name: file.name}
					})
				});

				if (response.ok)
				{
					resource = await response.text();
					key = hash.toString(16).padStart(16, 0).match(/.{2}/g).map(value => parseInt(value, 16));
					console.log();
return;
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
							backer.postMessage({id, src, options: {
								method: 'POST',
								headers: {'Mask-Key' : key.map(value => value.toString(16).padStart(2, 0)).join('')},
								body: value.buffer}}, [value.buffer]);
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
						backer.postMessage({id, src, options : {
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
						backer.postMessage({id, src, options : {
							method: 'POST',
							body: value.buffer
						}}, [value.buffer]);
					}).then(() => reader.read().then(uploaddata)).catch(reject);
				});
				//console.log(src)
		

				return;
				a.then(async response =>
				{
					if (response.ok === false) return reject();
					const src = await response.text(), reader = file.stream().getReader();
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

							// backer.postMessage({id, src, options : {
							// 	method: 'POST',
							// 	body: value.buffer
							// }}, [value.buffer]);
						}).then(() => reader.read().then(uploaddata)).catch(reject);
					});
				}).catch(reject);
			})));
		}
		return async (src, options, progress) => options instanceof FileList
			? upload(src, options, progress || (value => value))
			: new Promise((resolve, reject) => promise.set(++id, [resolve, reject]) && backer.postMessage({id, src, options}));
	}());
}
else
{
	let count = 0;
	const queue = [];
	function worker(data)
	{
		loader(data.src, data.options)
			.then(content => self.postMessage({id: data.id, is: 0, content}))
			.catch(error => self.postMessage({id: data.id, is: 1, content: error}))
			.finally(() =>
			{
				--count;
				// console.log(count);
				if (queue.length)
				{
					worker(queue.shift());
				}
				
			});
	}
	self.onmessage = event =>
	{
		if (++count < 6)
		{
			worker(event.data);
		}
		else
		{
			queue[queue.length] = event.data;
		}
	};
}