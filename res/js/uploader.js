async function uploader(resource, files, pending = file => null)
{
	const
	byte = 16,
	code = '0123456789ABCDEFGHIJKLMNOPQRSTUV',
	mask = (key, buffer) => buffer.map((byte, i) => byte ^ key[i % 8]).buffer;
	//mask = (key, buffer) => buffer.map((byte, i) => key[i % 8] = byte ^ key[i % 8]).buffer;
	return Promise.allSettled(Array.from(files).map(file => new Promise(async (resolve, reject) =>
	{
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
				hash = (hash & 0x3fffffffffffffffn) + BigInt(file.size) & 0x3fffffffffffffffn;
				break;
			}
			while (i < byte && offset + value.length > key[i])
			{
				hash = (hash & 0xfffffffffffffffn)
					+ ((hash & 0x1ffffffffffffffn) << 5n)
					+ (new DataView(value.buffer, key[i++] - offset).getBigUint64(0, true) & 0xfffffffffffffffn);
			}
			offset += value.length;
		} while (true);
		key = hash.toString(16).padStart(16, 0);
		i = {
			hash: Array.from(Array(12)).map((v, i) => code[hash >> BigInt(i) * 5n & 31n]).join(''),
			size: file.size,
			mime: file.type.substring(0, 255),
			...(i = file.name.lastIndexOf('.')) !== -1
				? {name: file.name.substring(0, i), type: file.name.substring(i + 1)}
				: {name: file.name, type: ''}
		};
		const progress = pending({...i}) || Boolean, response = await fetch(resource, {
			method: 'POST',
			headers: {'Mask-Key': key},
			body: mask(key.match(/.{2}/g).map(value => parseInt(value, 16)), Uint8Array.from(Array.from(JSON.stringify(i)).reduce((result, value) =>
			{
				const code = value.codePointAt(0);
				code < 128
					? result[result.length] = code
					: result.push(...(code < 2048
						? [code >> 6 | 192, code & 63 | 128]
						: [code >> 12 | 224, code >> 6 & 63 | 128, code & 63 | 128]));
				return result;
			}, [])))
		});
		if (response.ok)
		{
			resource = await response.json();
			offset = 0;
			reader = file.stream().getReader();
			do
			{
				let {done, value} = await reader.read();
				if (done)
				{
					return resolve(file);
				}
				offset += value.length;
				if (offset > resource.offset)
				{
					i = value.slice(resource.offset + value.length - offset);
					await fetch(resource.uploadurl, {
						method: 'POST',
						headers: {'Mask-Key': key},
						body: mask(key.match(/.{2}/g).map(value => parseInt(value, 16)), i)
					});
					resource.offset += i.length;
				}
				sent += value.length;
				progress(file.size, value.length);
			} while (true);
		}
		return reject(file);
	})));
}