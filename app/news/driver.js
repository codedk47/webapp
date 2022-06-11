function fapp(src)
{
	const fapp = document.querySelector('iframe');
	// fapp.contentWindow.addEventListener('DOMContentLoaded', function()
	// {
	// 	console.log('load')
	// 	fapp.contentWindow.addEventListener('click', function(event)
	// 	{
	// 		const target = event.target || event.srcElement;
	// 		if (target.tagName === 'A')
	// 		{
	// 			event.preventDefault();
	// 			URL.revokeObjectURL(fapp.src);//静态方法用来释放一个之前已经存在的、通过调用 URL.createObjectURL() 创建的 URL 对象
	// 			fetch(src + target.getAttribute('href')).then(response => response.blob()).then(function(data)
	// 			{
	// 				fapp.src = URL.createObjectURL(data);
	// 				//console.log(data);
	// 				//fapp.contentWindow.document.write(html);
	// 				// fapp.contentWindow.document.close();
	// 				// console.log(html)
	// 			})
	// 		}
	// 	});
	// }, true);
	function loaded()
	{
		console.log(122);
		fapp.contentWindow.addEventListener('click', function(event)
		{
			console.log(1);
		});
	}
	fapp.onclick = a => console.log(a);
	fetch(src).then(response => response.blob()).then(function(data)
	{
		const aa = fapp.contentWindow.document;
		fapp.src = URL.createObjectURL(data);
		fapp.contentWindow.addEventListener('DOMContentLoaded', function(){
			console.log(123);
		});
		//setInterval(()=>console.log(aa === fapp.contentWindow.document), 1)
		//while (aa === fapp.contentWindow.document);
		
		//return fapp.contentWindow.document;
	});

	
}
function unpack(data, type)
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
function init()
{
	document.querySelectorAll('img[data-src]')
}