const resorigin = document.currentScript.dataset.origin;

function g(p,a)
{
	location.assign(((o,q)=>Object.keys(o).reduce((p,k)=>o[k]===null?p:`${p},${k}:${o[k]}`,q))
		(...typeof(p)==="string"?[{},p]:[
			Object.assign(Object.fromEntries(Array.from(location.search.matchAll(/\,(\w+)(?:\:([\%\+\-\.\/\=\w]*))?/g)).map(v=>v.slice(1))),p),
			a||location.search.replace(/\,.+/,"")]));
}
function r(p,a)
{
	const url = top.document.querySelector('iframe[importance="high"]').dataset.load;
	top.framer(((o,q)=>Object.keys(o).reduce((p,k)=>o[k]===null?p:`${p},${k}:${o[k]}`,q))
		(...typeof(p)==="string"?[{},p]:[
			Object.assign(Object.fromEntries(Array.from(url.matchAll(/\,(\w+)(?:\:([\%\+\-\.\/\=\w]*))?/g)).map(v=>v.slice(1))),p),
			a||url.replace(/\,.+/,"")]));
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
		video.loaded(() => {
			play.style.width = `${parseInt(video.width * 0.7)}px`;
			play.style.height = `${parseInt(video.height * 0.7)}px`;
		});
		dialog.onclick = event => event.target === dialog && dialog.close();
		dialog.onclose = () => {try {video.suspend();} catch {}};
		dialog.appendChild(video);
		document.body.appendChild(dialog);
	}
	const play = dialog.querySelector('webapp-video');
	play.style.width = '1px';
	play.style.height = '1px';
	play.poster(`${resorigin}${data.cover}`).then(blob => {
		const cover = new Image;
		cover.src = blob;
		cover.onload = () => {
			play.style.width = `${parseInt(cover.width * 0.7)}px`;
			play.style.height = `${parseInt(cover.height * 0.7)}px`;
			play.m3u8(`${resorigin}${data.playm3u8}`, preview);
		};
	});
	dialog.showModal();
}

function video_value(form)
{
	const formdata = new FormData(form), data = Object.fromEntries(formdata), tags = [];
	if (formdata.get('tag'))
	{
		tags[tags.length] = formdata.get('tag');
		tags.push(...formdata.getAll(`t${formdata.get('tag')}[]`));
		if (tags.length > 5)
		{
			alert('标签数量不得超过5个！');
			return false;
		}
	}
	data.tags = tags.join(',');
	const raw = JSON.stringify(data), buffer = [];
	let hash = 5381n;
	for (let unicode of raw)
	{
		const value = unicode.codePointAt(0);
		value < 128
			? buffer[buffer.length] = value
			: buffer.push(...(value < 2048
				? [value >> 6 | 192, value & 63 | 128]
				: [value >> 12 | 224, value >> 6 & 63 | 128, value & 63 | 128]));

		hash = (hash & 0xfffffffffffffffn) + ((hash & 0x1ffffffffffffffn) << 5n) + BigInt(value);
	}
	const key = hash.toString(16).padStart(16, 0);
	hash = key.match(/.{2}/g).map(value => parseInt(value, 16));
	fetch (form.action, {
		method: 'PATCH',
		headers: {'Mask-Key': key},
		body: Uint8Array.from(buffer.map((byte, i) => byte ^ hash[i % 8])).buffer
	}).then(r => r.json()).then(json => {
		if (json.errors.length)
		{
			return alert(json.errors.join('\n'));
		}
		if (json.hasOwnProperty('goto'))
		{
			if (top.window === self.window)
			{
				typeof json.goto === 'string' ? location.assign(json.goto) : location.reload();
			}
			else
			{
				typeof json.goto === 'string' ? top.framer(json.goto) : top.location.reload();
			}
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
		top.fetch(input.dataset.uploadurl, {
			method: 'PATCH',
			headers: {'Mask-Key': input.dataset.key},
			body: buffer.map((byte, i) => byte ^ key[i % 8])
		}).then(r => r.json()).then(json =>
		{
			//alert(json.dialog);
			preview.style.backgroundImage = `url(${URL.createObjectURL(input.files[0])})`;
			preview.textContent = '';
			input.disabled = false;
		})
	};
	reader.readAsArrayBuffer(input.files[0]);
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



	document.querySelectorAll('div[data-cover]').forEach(element =>
	{
		top.loader(`${resorigin}${element.dataset.cover}`, {mask: true}).then(blob =>
		{
			element.style.backgroundImage = `url(${blob})`;
		});
	});

});