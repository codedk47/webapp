function content_to_buffer(contents, hash = null)
{
	const
		buffer = Uint8Array.from(encodeURIComponent(contents).match(/%[0-F]{2}|[^%]/g)
			.map(a => a.startsWith('%') ? parseInt(a.substring(1), 16) : a.codePointAt(0))),
		key = /^[0-f]{16}$/.test(hash) ? hash.match(/.{2}/g).map(value => parseInt(value, 16)) : [];
	if (key.length === 8)
	{
		for (let i = 0; i < buffer.length; ++i)
		{
			buffer[i] ^= key[i % 8];
		}
	}
	return buffer;
}
function buffer_to_content(bytes, hash = null)
{
	const key = /^[0-f]{16}$/.test(hash) ? hash.match(/.{2}/g).map(value => parseInt(value, 16)) : [];
	return (new TextDecoder).decode(key.length === 8 ? bytes.map((byte, i) => byte ^ key[i % 8]) : bytes);
}
function g(p,a)
{
	location.assign(((o,q)=>Object.keys(o).reduce((p,k)=>o[k]===null?p:`${p},${k}:${o[k]}`,q))
		(...typeof(p)==="string"?[{},p]:[
			Object.assign(Object.fromEntries(Array.from(location.search.matchAll(/\,(\w+)(?:\:([\%\+\-\.\/\=\w]*))?/g)).map(v=>v.slice(1))),p),
			a||location.search.replace(/\,.+/,"")]));
}

function urlencode(data)
{
	return encodeURIComponent(data).replace(/%20|[\!'\(\)\*\+\/@~]/g, (escape)=> ({
		'%20': '+',
		'!': '%21',
		"'": '%27',
		'(': '%28',
		')': '%29',
		'*': '%2A',
		'+': '%2B',
		'/': '%2F',
		'@': '%40',
		'~': '%7E'}[escape]));
}

function url64_encode(data)
{
	const
	key = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ_abcdefghijklmnopqrstuvwxyz-', 
	bin = unescape(encodeURIComponent(data));
	let buffer = '';
	for (let i = 0, length = bin.length; i < length;)
	{
		let value = bin.charCodeAt(i++) << 16;
		buffer += key.charAt(value >> 18 & 63);
		if (i < length)
		{
			value |= bin.charCodeAt(i++) << 8;
			buffer += key.charAt(value >> 12 & 63);
			if (i < length)
			{
				value |= bin.charCodeAt(i++);
				buffer += key.charAt(value >> 6 & 63);
				buffer += key.charAt(value & 63);
				continue;
			}
			buffer += key.charAt(value >> 6 & 63);
			break;
		}
		buffer += key.charAt(value >> 12 & 63);
		break;
	}
	console.log(buffer)
	return buffer;
}

function view_video(data, preview)
{
	let dialog = document.querySelector('dialog.play');
	if (!dialog)
	{
		dialog = document.createElement('dialog');
		dialog.className = 'play';
		const video = document.createElement('webapp-video');
		video.setAttributeNode(document.createAttribute('autoplay'));
		video.setAttributeNode(document.createAttribute('controls'));
		video.mask = true;
		video.on('canplay', () => {
			play.style.width = `${parseInt(video.width * 0.7)}px`;
			play.style.height = `${parseInt(video.height * 0.7)}px`;
		});
		dialog.onclick = event => event.target === dialog && dialog.close();
		dialog.onclose = () => {try {video.pause();} catch {}};
		dialog.appendChild(video);
		document.body.appendChild(dialog);
	}
	const play = dialog.querySelector('webapp-video');
	play.poster(data.cover);
	play.m3u8(data.playm3u8, preview);
	dialog.showModal();
}

function video_value(form)
{
	const formdata = new FormData(form), body = Object.fromEntries(formdata), tags = [];
	body.tags = formdata.getAll('tags[]');
	delete body['tags[]'];
	masker(form.action, {method: 'PATCH', body}).then(response => response.json()).then(json =>
	{
		if (json.errors.length)
		{
			return alert(json.errors.join('\n'));
		}
		if (json.hasOwnProperty('goto'))
		{
			typeof json.goto === 'string' ? location.assign(json.goto) : location.reload();
		}
	});
	return false;
}
function video_cover(input, preview)
{
	if (input.files.length < 1) return alert('请选择一个封面图片！');
	if (input.disabled) return;
	input.disabled = true;
	const reader = new FileReader;
	reader.onload = event =>
	{
		const
		buffer = new Uint8Array(event.target.result),
		key = input.dataset.key.match(/.{2}/g).map(value => parseInt(value, 16));
		fetch(input.dataset.uploadurl, {
			method: 'PATCH',
			headers: {'Mask-Key': input.dataset.key},
			body: buffer.map((byte, i) => byte ^ key[i % 8])
		}).then(r => r.json()).then(json =>
		{
			//console.log(json);
			preview.style.backgroundImage = `url(${URL.createObjectURL(input.files[0])})`;
			preview.textContent = '';
			input.disabled = false;
		})
	};
	reader.readAsArrayBuffer(input.files[0]);
}
function video_cover(input, preview)
{
	if (input.files.length < 1) return alert('请选择一个封面图片！');
	if (input.disabled) return;
	input.disabled = true;
	const reader = new FileReader;
	reader.onload = event =>
	{
		const
		buffer = new Uint8Array(event.target.result),
		key = input.dataset.key.match(/.{2}/g).map(value => parseInt(value, 16));
		fetch(input.dataset.uploadurl, {
			method: 'PATCH',
			headers: {'Mask-Key': input.dataset.key},
			body: buffer.map((byte, i) => byte ^ key[i % 8])
		}).then(r => r.json()).then(json =>
		{
			//console.log(json);
			preview.style.backgroundImage = `url(${URL.createObjectURL(input.files[0])})`;
			preview.textContent = '';
			input.disabled = false;
		})
	};
	reader.readAsArrayBuffer(input.files[0]);
}
function upload_image(input, callback)
{
	if (input.files.length < 1) return alert('请选择一个文件 ！');
	if (input.files[0].size > 2000000) return alert('文件内容太大 ！');
	if (input.disabled) return;
	input.disabled = true;
	const reader = new FileReader;
	reader.onload = event =>
	{
		const
			code = '0123456789ABCDEFGHIJKLMNOPQRSTUV',
			buffer = new Uint8Array(event.target.result),
			hash = buffer.reduce((hash, byte) => (hash & 0xfffffffffffffffn) + ((hash & 0x1ffffffffffffffn) << 5n) + BigInt(byte), 5381n),
			key = hash.toString(16).padStart(16, 0),
			keys = key.match(/.{2}/g).map(value => parseInt(value, 16));
		for (let i = 0; i < buffer.length; ++i)
		{
			buffer[i] ^= keys[i % 8];
		}
		fetch(input.dataset.uploadurl, {
			method: 'PATCH',
			headers: {'Mask-Key': key},
			body: buffer
		}).then(response => response.json()).then(json =>
		{
			input.disabled = false;
			Array.isArray(json.errors) && json.errors.length && alert(json.errors.join('\n'));
			if (callback)
			{
				callback(Array.from(Array(12)).map((v, i) => code[hash >> BigInt(11 - i) * 5n & 31n]).join(''));
			}
			else
			{
				json.hasOwnProperty('dialog') && alert(json.dialog);
				if (json.hasOwnProperty('goto'))
				{
					location.assign(json.goto);
				}
			}
		});
	};
	reader.readAsArrayBuffer(input.files[0]);
}
function search_comment(input, select)
{
	//console.log(input.dataset.type, input.value);
	fetch(`${input.dataset.action},type:${input.dataset.type}`, {
		method: 'POST',
		body: input.value
	}).then(response => response.json()).then(data => {
		select.innerHTML = '';
		const ul = document.createElement('ul');
		for (const [hash, title] of Object.entries(data.comments))
		{
			const
			select_li = document.createElement('li'),
			select_label = document.createElement('label'),
			select_input = document.createElement('input');

			select_input.name = 'phash';
			select_input.type = 'radio';
			select_input.value = hash;
			select_label.appendChild(select_input);
			select_label.appendChild(document.createTextNode(title));
			select_li.appendChild(select_label);
			ul.appendChild(select_li);
		}
		select.appendChild(ul);
	});
}
function admin_comment(form)
{
	fetch(form.action, {
		method: 'POST',
		body: new FormData(form)
	}).then(response => response.json()).then(json => {
		if (json.hasOwnProperty('goto'))
		{
			location.assign(json.goto);
		}
	});
	return false;
}
function admin_comment_image(hash)
{
	document.querySelector('textarea[name=images]').value += hash;
}
function search_videos(input, ul)
{

	//console.log(localStorage.getItem('comment_userid'), input.value)
	fetch(`${input.dataset.action},userid:${localStorage.getItem('comment_userid')}`, {
		method: 'POST',
		body: input.value
	}).then(response => response.json()).then(json => {
		ul.querySelectorAll('li>label>input').forEach(node => node.checked || ul.removeChild(node.parentNode.parentNode));
		for (const [hash, title] of Object.entries(json.videos))
		{
			const
			select_li = document.createElement('li'),
			select_label = document.createElement('label'),
			select_input = document.createElement('input');
			select_input.name = 'videos[]';
			select_input.type = 'checkbox';
			select_input.value = hash;
			select_label.appendChild(select_input);
			select_label.appendChild(document.createTextNode(title));
			select_li.appendChild(select_label);
			ul.appendChild(select_li);
		}
	});
	
}
document.addEventListener('DOMContentLoaded', event =>
{
	async function dialog(context, resolve, reject)
	{
		const
		dialog = document.createElement('dialog'),
		footer = document.createElement('footer'),
		form = document.createElement('form'),
		confirm = document.createElement('button'),
		cancel = document.createElement('button');
		confirm.innerHTML = 'OK';
		cancel.innerHTML = 'Cancel';
		footer.appendChild(confirm);
		if (typeof context === 'string' && resolve)
		{
			footer.appendChild(cancel);
		}
		return new Promise((resolve, reject) =>
		{
			//dialog.appendChild(document.createElement('header')).innerHTML = 'dialog';
			confirm.onclick = () => Boolean(dialog.close(resolve(form)));
			dialog.onclose = cancel.onclick = () => reject(form);
			dialog.appendChild(form).onsubmit = confirm.onclick;
			if (typeof context === 'string')
			{
				form.appendChild(document.createElement('pre')).innerText = context;
			}
			else
			{
				form.method = context.method || 'post';
				//form.enctype = context.enctype || 'application/x-www-form-urlencoded';
				context.hasOwnProperty('action') && form.setAttribute('action', context.action);
				for (let name in context.fields)
				{
					let field;
					switch (context.fields[name])
					{
						case 'textarea':
							field = document.createElement('textarea');
							break;
						default:
							field = document.createElement('input');
							field.type = context.fields[name];
					}
					field.name = name;
					form.appendChild(document.createElement('fieldset')).appendChild(field);
				}
				footer.appendChild(cancel);
			}
			dialog.appendChild(footer);
			document.body.appendChild(dialog).showModal();
		}).then(resolve, reject || Boolean).finally(() => dialog.remove());
	}
	async function interact(element)
	{
		//dialog({method: 'get', action: '?', fields: {name: 'text'}}).then(bind);

		//redirect: 'manual'
		//return;
	
		//alert(element.enctype)

		//console.log( JSON.stringify(Object.fromEntries(new FormData(element))) );
		//return;

		const
		response = element.tagName === 'FORM'
			? await fetch(element.action, {method: element.getAttribute('method').toUpperCase(), body: new URLSearchParams(new FormData(element))})
			: await fetch(element.href || element.dataset.src, {method: (element.dataset.method || 'get').toUpperCase()}),
		data = await response.text();

		//alert(response.redirect());
		//console.log( response );
		//return;
		


		try
		{
			const json = JSON.parse(data);
			if (Array.isArray(json.errors) && json.errors.length)
			{
				await dialog(json.errors.join('\n'));
			}
			if (json.hasOwnProperty('dialog'))
			{
				typeof json.dialog === 'string'
					? await dialog(json.dialog)
					: await dialog(json.dialog, interact);
			}
			if (json.hasOwnProperty('goto'))
			{
				//console.log(json.goto)
				typeof json.goto === 'string'
					? location.assign(json.goto)
					: location.reload();
			}
		}
		catch (error)
		{
			dialog(data);
		}
	}



	document.querySelectorAll('[data-bind]').forEach(element => element[`on${element.dataset.bind}`] = event =>
	{
		

		if (element.tagName === 'FORM')
		{
			interact(element);
		}
		else
		{
			if (element.dataset.dialog)
			{
				try
				{
					dialog({
						method: element.dataset.method || 'post',
						//enctype: element.dataset.enctype || 'application/x-www-form-urlencoded',
						action: element.href || element.dataset.src,
						fields: JSON.parse(element.dataset.dialog)
					}, interact);
				}
				catch
				{
					dialog(element.dataset.dialog, () => interact(element));
				}
			}
			else
			{
				interact(element);
			}
		}
		return false;
	});


});