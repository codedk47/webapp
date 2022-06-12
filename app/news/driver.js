window.addEventListener('DOMContentLoaded', function()
{
	if (top !== self)
	{
		console.log('app loaded')
		return window.addEventListener('click', function(event)
		{
			if (event.target.tagName === 'A')
			{
				event.preventDefault();
				top.postMessage(event.target.href);
			}
		});
	}
	const fapp = document.querySelector('iframe'), options = {
		headers: {
			'Authorization': 'Bearer 1231231',
			'Content-Type': 'binary'
		}
	};



	// console.log('main loaded')
	// window.addEventListener('message', function(event)
	// {
	// 	alert(event.data)
	// 	console.log(event)
	// });



	// fetch(fapp.dataset.src, options).then(response => response.arrayBuffer()).then(unpack)
	// .then(data => fapp.src = `data:text/html;base64,${btoa(String.fromCharCode(...data))}`);
})


function fapp(source)
{
	// window.addEventListener('message', function(event)
	// {
	// 	console.log(event)
	// });



	return;
	const fapp = document.querySelector('iframe'), options = {
		headers: {
			'Authorization': 'Bearer 1231231',
			'Content-Type': 'binary'
		}
	};
	function listen(event)
	{
		console.log(event)
		const target = event.target || event.srcElement;
		if (target.tagName === 'A')
		{
			event.preventDefault();
			//URL.revokeObjectURL(fapp.src);//静态方法用来释放一个之前已经存在的、通过调用 URL.createObjectURL() 创建的 URL 对象
			fetch(source + target.getAttribute('href'), options).then(response => response.arrayBuffer()).then(unpack).then(loader);
		}
	}
	function detect(document)
	{
		console.log('loaded');
		if (document === fapp.contentDocument)
		{
			return setTimeout(detect, 0, document);
		}
		//console.log('gggggggggggggggggggg');
		fapp.contentWindow.addEventListener('click', listen);
		
	}

	function loader(data)
	{
		//setTimeout(detect, 0, fapp.contentDocument);
		//fapp.src = URL.createObjectURL(new Blob([data.buffer], {type: 'text/html; charset=utf-8'}));
		fapp.src = `data:text/html;base64,${btoa(String.fromCharCode(...data))}`;
		// fapp.contentWindow.addEventListener('DOMContentLoaded', function(){
		// 	console.log(111111111111)
		// });
	}
	fetch(source, options).then(response => response.arrayBuffer()).then(unpack).then(loader);
}
function unpack(data)
{
	const key = new Uint8Array(data.slice(0, 8));
	const buffer = new Uint8Array(data.slice(8));
	for (let i = 0; i < buffer.length; ++i)
	{
		buffer[i] = buffer[i] ^ key[i % 8];
	}
	return buffer;
}

function aa(data, type)
{
	const key = new Uint8Array(data.slice(0, 8));
	const buffer = new Uint8Array(data.slice(8));
	for (let i = 0; i < buffer.length; ++i)
	{
		buffer[i] = buffer[i] ^ key[i % 8];
	}
	return typeof type === 'string'
		? URL.createObjectURL(new Blob([buffer.buffer], {type}))
		: (new TextDecoder('utf-8')).decode(buffer);
}
function fetchpic(img)
{
	fetch(img.dataset.url).then(response => response.arrayBuffer()).then(function(data)
	{
		img.src = unpack(data, true);
	});
}
function initdata()
{
	//document.querySelectorAll('img[data-src]')
	// document.querySelectorAll('meta').forEach(function(img)
	// {
	// 	console.log(img)
	// });
}








function request(method, url, body = null)
{
	return new Promise(function(resolve, reject)
	{
		const xhr = new XMLHttpRequest;
		xhr.open(method, url);
		xhr.responseType = 'arraybuffer';
		xhr.onload = () => resolve(JSON.parse(/json/.test(xhr.getResponseHeader('Content-Type'))
			? (new TextDecoder('utf-8')).decode(new Uint8Array(xhr.response))
			: unpack(xhr.response)));
		xhr.onerror = () => reject(xhr);
		xhr.send(body);
	});
}
