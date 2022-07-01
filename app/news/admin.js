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
function upres(e)
{
	const progress = Array.from(e.getElementsByTagName('progress'));
	xhr.open(e.method, e.action);
	xhr.setRequestHeader('Authorization', `Bearer ${e.dataset.auth}`);
	xhr.upload.onprogress = event => event.lengthComputable && progress.forEach(e => e.value = event.loaded / event.total);
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