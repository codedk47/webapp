// function fetchpic(img)
// {
// 	fetch(img.dataset.url).then(response => response.arrayBuffer()).then(unpack);
// }
window.addEventListener('DOMContentLoaded', function()
{
	if (top !== self)
	{
		// document.querySelectorAll('img[data-src]').forEach(function(img)
		// {
		// 	fetch('http://192.168.0.155/cover1.bin').then(response => response.arrayBuffer()).then(unpack)
		// 	.then(data => img.src = URL.createObjectURL(new Blob([data.buffer], {type: 'application/octet-stream'})));
		// });
		return document.addEventListener('click', function(event)
		{
			for (let target = event.target; target.parentNode; target = target.parentNode)
			{
				if (target.tagName === 'A')
				{
					event.preventDefault();
					top.postMessage(target.getAttribute('href'));
					break;
				}
			}
		});
	}

	const ifa = document.querySelector('iframe'), source = ifa.dataset.app, options = {
		headers: {
			'Authorization': 'Bearer 1231231',
			'Content-Type': 'binary'
		}
	};
	

	window.addEventListener('message', function(event)
	{

		fetch(source + event.data, options).then(response => response.arrayBuffer()).then(unpack).then(render);
	});


	function render(data)
	{
		ifa.contentDocument.removeChild(ifa.contentDocument.documentElement);
		ifa.contentDocument.write((new TextDecoder('utf-8')).decode(data));
		ifa.contentDocument.close();
	}
	fetch(source, options).then(response => response.arrayBuffer()).then(unpack).then(render);
});
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
