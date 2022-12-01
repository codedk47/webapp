const xhr = new XMLHttpRequest;
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
function mrp(e)
{
	if (e.parentNode.parentNode.childNodes.length <= 20)
	{
		e.parentNode.parentNode.appendChild(document.createElement('dd')).appendChild(e.nextElementSibling.cloneNode(true));
	}
	else
	{
		alert('目前只支持同时20个上传任务');
	}
}
function mupres(e)
{
	const xhr = new XMLHttpRequest, progress = Array.from(e.parentNode.getElementsByTagName('progress'));
	xhr.open(e.method, e.action);
	xhr.setRequestHeader('Authorization', `Bearer ${e.dataset.auth}`);
	xhr.upload.onprogress = event => event.lengthComputable && progress.forEach(e => e.value = event.loaded / event.total);
	xhr.responseType = 'json';
	xhr.onload = () => {
		if (Object.keys(xhr.response.errors).length)
		{
			alert(Object.values(xhr.response.errors).join("\n"));
			progress.value = 0;
		}
		else
		{
			if (xhr.response.goto)
			{
				e.parentNode.parentNode.remove();
			}
		}
	};
	xhr.send(new FormData(e));
	return e.parentNode.open = false;
}
function upres(e)
{
	const progress = Array.from(e.getElementsByTagName('progress'));
	xhr.open(e.method, e.action);
	xhr.setRequestHeader('Authorization', `Bearer ${e.dataset.auth}`);
	xhr.upload.onprogress = event => event.lengthComputable && progress.forEach(e => e.value = event.loaded / event.total);
	if (e.dataset.back == 'html')
	{
		xhr.onload = () =>
		{
			alert(xhr.status);
			location.reload();
		};
	}
	else
	{
		xhr.responseType = 'json';
		xhr.onload = () => {
			if (Object.keys(xhr.response.errors).length)
			{
				alert(Object.values(xhr.response.errors).join("\n"));
			}
			else
			{
				if (xhr.response.goto)
				{
					location.href = xhr.response.goto;
				}
			}
			console.log(xhr.response)
		};
	}
	xhr.send(new FormData(e));
	return false;
}
function anchor(a)
{
	xhr.open(a.dataset.method || 'GET', a.href);
	if (a.dataset.auth)
	{
		xhr.setRequestHeader('Authorization', `Bearer ${a.dataset.auth}`);
	}
	xhr.responseType = 'json';
	xhr.onload = () => {
		if (Object.keys(xhr.response.errors).length)
		{
			alert(Object.values(xhr.response.errors).join("\n"));
		}
		else
		{
			if (xhr.response.goto)
			{
				location.href = xhr.response.goto;
			}
		}
		console.log(xhr.response)
	};
	xhr.send(a.dataset.body);
	return false;
}
async function loader(source, options, type)
{
	const
	response = await fetch(source, options || null),
	reader = response.body.getReader(),
	key = new Uint8Array(8),
	buffer = [];
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
		default: return blob;
	}
}
function wschatinit(list, form)
{
	const
	ws = new WebSocket(form.dataset.ws),
	chatlog = form.firstElementChild,
	userids = {};
	list.onclick = event =>
	{
		if (event.target.tagName === 'DD')
		{
			form.to.value = event.target.dataset.id;
		}
	};
	function log(msg)
	{
		while (chatlog.childNodes.length > 200)
		{
			chatlog.removeChild(chatlog.firstElementChild);
		}
		const log = chatlog.appendChild(document.createElement('p'));

		if (typeof msg === 'string')
		{
			log.textContent = msg;
		}
		else
		{
			const desc = document.createElement('div'), cto = document.createElement('a');
			desc.appendChild(document.createElement('span')).textContent = msg.time;
			cto.textContent = `${msg.uid}(${msg.id})`;
			cto.href = 'javascript:;';
			cto.onclick = () =>
			{
				form.to.value = msg.id;
				navigator.clipboard.writeText(msg.uid);
			};

			desc.appendChild(cto);
			desc.appendChild(document.createElement('span')).textContent = `@${msg.sto}`;

			log.appendChild(desc);
			log.appendChild(document.createTextNode(msg.msg));

		}
		chatlog.scrollTop = chatlog.scrollHeight;
	}
	ws.onerror = () => log('Lost chat server connect...');
	ws.onopen = () =>
	{
		log('With chat server connected!');
		form.onsubmit = () =>
		{
			ws.send(`${form.to.value ? form.to.value : '*'} ${form.message.value}`);
			form.message.value = '';
			return false;
		};
		ws.send('* users');
		setInterval(() => ws.send('* users'), 2000);
	};
	ws.onmessage = event =>
	{
		if (event.data)
		{
			const [recv, data] = event.data.split(' ', 2);
			if (recv === 'users')
			{
				const users = data.split(',');
				list.innerHTML = '';
				list.appendChild(document.createElement('dt')).textContent = `当前在线${users.length}人`;
				return users.forEach(id =>
				{
					const user = document.createElement('dd');
					user.dataset.id = id;
					user.textContent = userids[id] ? `${userids[id]}(${id})` : `Socket(${id})`;
					list.appendChild(user);
				});
			}
			const msg = JSON.parse(event.data);
			if (msg.msg)
			{
				if (msg.msg === '加入聊天频道!')
				{
					userids[msg.id] = msg.uid;
				}
				else
				{
					log(msg);
				}
			}
		}
	};
}