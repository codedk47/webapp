uploader.auth = element =>
{
	const
	data = Array.from(JSON.stringify(Object.fromEntries(new FormData(element)))).reduce((result, value) =>
	{
		const code = value.codePointAt(0);
		code < 128
			? result[result.length] = code
			: result.push(...(code < 2048
				? [code >> 6 | 192, code & 63 | 128]
				: [code >> 12 | 224, code >> 6 & 63 | 128, code & 63 | 128]));
		return result;
	}, []),
	key = data.reduce((result, value) => (result & 0xfffffffffffffffn) + ((result & 0x1ffffffffffffffn) << 5n) + BigInt(value), 5381n).toString(16).padStart(16, 0),
	keys = key.match(/.{2}/g).map(value => parseInt(value, 16));
	loader(`${top.location.href}/auth`, {
		'method': 'POST',
		'headers': {'Mask-Key': key},
		'body': Uint8Array.from(data.map((byte, i) => byte ^ keys[i % 8])).buffer
	}).then(auth => auth.token ? top.framer.authorization(auth.token) : alert(auth.errors ? auth.errors.join('\n') : '未知错误'));
	return false;
};
uploader.uploadlist = (files, tbody) =>
{
	while (tbody.childNodes[1])
	{
		tbody.childNodes[1].remove();
	}
	Array.from(files).forEach(file =>
	{
		const tr = document.createElement('tr');
		tr.appendChild(document.createElement('td'));
		tr.append(...['size', 'type', 'name'].map(field =>
		{
			const td = document.createElement('td');
			td.textContent = file[field];
			return td;
		}));
		tbody.appendChild(tr);
	});
}
uploader.uploading = table =>
{
	const
	input = table.querySelector('input[type=file]'),
	submit = table.querySelector('button'),
	tbody = table.querySelector('tbody'),
	text = submit.textContent;

	if (input.files.length < 1 || input.files.length > 5)
	{
		return alert('至少需要选择 1 - 5 个之间');
	}
	submit.disabled = input.disabled = true;
	submit.onclick = null;
	submit.textContent = '正在上传请保持页面不要关闭，如果遇到网络中断，请重新选择上传即可断点续传。';
	while (tbody.childNodes[1])
	{
		tbody.childNodes[1].remove();
	}
	uploader(input.dataset.uploadurl, input.files, file =>
	{
		const tr = document.createElement('tr');
		tr.append(...['hash', 'size', 'type', 'name'].map(field =>
		{
			const td = document.createElement('td');
			td.textContent = file[field];
			return td;
		}));
		
		const progress = document.createElement('progress');




		progress.value = 0;


		tr.appendChild(document.createElement('td')).appendChild(progress);
		tbody.appendChild(tr);
		return progress;
	});
};