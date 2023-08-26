uploader.mapfile = new Map;
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
uploader.change_nickname = (button) =>
{
	top.framer.loader(button.dataset.action, {
		method: 'PATCH',
		body: button.previousElementSibling.value
	}).then(data => data.result ? top.framer(top.document.querySelector('iframe[data-load]').dataset.load) : alert('用户昵称修改失败！'));
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
};
uploader.uploading = table =>
{
	const
	input = table.querySelector('input[type=file]'),
	submit = table.querySelector('button'),
	tbody = table.querySelector('tbody'),
	text = submit.textContent;

	if (input.files.length < 1)
	{
		return alert('至少需要选择 1 个文件');
	}
	submit.disabled = input.disabled = true;
	submit.onclick = null;
	submit.textContent = '正在上传请保持页面不要关闭，如果遇到网络中断，请重新选择上传即可断点续传。';
	uploader.mapfile.clear();
	while (tbody.childNodes[1])
	{
		tbody.childNodes[1].remove();
	}
	uploader(input.dataset.uploadurl, input.files, file =>
	{
		const tr = document.createElement('tr');
		uploader.mapfile.set(file, tr);
		tr.append(...['hash', 'size', 'type', 'name'].map(field =>
		{
			const td = document.createElement('td');
			td.textContent = file[field];
			return td;
		}));
		// const progress = document.createElement('progress');
		// progress.style.display = 'block';
		// progress.style.width = '100%';
		// progress.value = 0;
		const progress = tr.appendChild(document.createElement('td'));
		tbody.appendChild(tr);
		return value => progress.textContent = `${(value * 100).toFixed(2)} %`;
	}).then(results =>
	{
		const success = results.filter(file => file.status === 'fulfilled');
		setTimeout(() => {
			results.length === success.length ? alert('所有上传处理完毕！')
				: alert(success.length ? '部分上传完毕，请检查最后状态！' : '所有上传处理失败！');
			submit.disabled = input.disabled = false;
			submit.onclick = () => uploader.uploading(table);
			submit.textContent = text;
		}, 1000);
	});
};
uploader.video_patch = (action, data = null) =>
{
	top.framer.loader(action, {
		method: 'PATCH',
		body: data || null
	}).then(data => data.result ? top.framer(top.document.querySelector('iframe[data-load]').dataset.load) : alert('修改失败！'));
}
uploader.change_video_user = select =>
{
	top.framer.loader(select.dataset.action, {
		method: 'PATCH',
		body: select.value
	}).then(data => data.result ? top.framer(top.document.querySelector('iframe[data-load]').dataset.load) : alert('分配用户失败！'));
};
uploader.upload_image = (input, preview) =>
{
	if (input.disabled || input.files.length < 1) return;
	input.disabled = true;
	const reader = new FileReader;
	reader.onload = event =>
	{
		const
		buffer = new Uint8Array(event.target.result),
		key = input.dataset.key.match(/.{2}/g).map(value => parseInt(value, 16));
		top.framer.loader(input.dataset.uploadurl, {
			method: 'PATCH',
			headers: {'Mask-Key': input.dataset.key},
			body: buffer.map((byte, i) => byte ^ key[i % 8])
		}).then(json =>
		{
			input.disabled = false;
			if (json.result)
			{
				if (preview)
				{
					preview.style.backgroundImage = `url(${URL.createObjectURL(input.files[0])})`;
				}
			}
			else
			{
				alert('上传失败！')
			}
		});
	};
	reader.readAsArrayBuffer(input.files[0]);
};
uploader.form_value = form =>
{
	let data = JSON.stringify(Object.fromEntries(new FormData(form))), hash = 5381n, buffer = [];
	for (let unicode of data)
	{
		const value = unicode.codePointAt(0);
		value < 128
			? buffer[buffer.length] = value
			: buffer.push(...(value < 2048
				? [value >> 6 | 192, value & 63 | 128]
				: [value >> 12 | 224, value >> 6 & 63 | 128, value & 63 | 128]));
		hash = (hash & 0xfffffffffffffffn) + ((hash & 0x1ffffffffffffffn) << 5n) + BigInt(value);
	}
	data = hash.toString(16).padStart(16, 0);
	hash = data.match(/.{2}/g).map(value => parseInt(value, 16));
	framer.loader(top.document.querySelector('iframe[importance="high"]').dataset.load, {
		method: form.method.toUpperCase(),
		headers: {'Mask-Key': data},
		body: Uint8Array.from(buffer.map((byte, i) => byte ^ hash[i % 8]))
	}).then(json => {
		if (Array.isArray(json.errors) && json.errors.length)
		{
			alert(json.errors.join('\n'));
		}
		if (json.hasOwnProperty('dialog'))
		{
			alert(json.dialog);
		}
		if (json.hasOwnProperty('goto'))
		{
			framer(json.goto);
		}
		console.log(json);
	});
	return false;
};