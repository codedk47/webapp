const tools =
{
	save_content_as(text, name)
	{
		const a = document.createElement('a');
		a.href = URL.createObjectURL(new Blob([text], {type: 'text/plain'}));
		a.download = name;
		a.click();
	},
	submit(form, calltype, callback)
	{
		fetch(form.action, {method: 'POST', body: new FormData(form)}).then(response =>
		{
			switch (calltype)
			{
				case 'blob': return response.blob();
				case 'text': return response.text();
				default: response
			}
		}).then(callback);
		return false;
	},
	//----------------OpenSSL----------------
	openssl_submit(form)
	{
		return tools.submit(form, 'text', text => form.querySelector('a[download]').href =
			URL.createObjectURL(new Blob([form.content.value = text], {type: 'text/plain'})));
	},
	openssl_zeroneta(form)
	{
		return tools.submit(form, 'text', text =>  {
			const [key, cer] = text.split('\n\n');
			form.querySelector('a[download="user.key"]').href = URL.createObjectURL(new Blob([form.key.value = key.trim()], {type: 'text/plain'}));
			form.querySelector('a[download="user.cer"]').href = URL.createObjectURL(new Blob([form.cer.value = cer.trim()], {type: 'text/plain'}));
		});
	},
	//---------------QRCode----------------
	qrcode_create(form)
	{
		return tools.submit(form, 'blob', blob => form.result.src = URL.createObjectURL(blob));
	},
	qrcode_reader(form, reader)
	{
		if (form.qrcode.files.length)
		{
			form.qrcode.files[0].size < 16777216
				? reader.decodeFromImageUrl(URL.createObjectURL(form.qrcode.files[0])).then(
					result => form.result.textContent = result.text,
					() => alert('读取二维码内容失败，请检文件是图片格式二维码！'))
				: alert('文件内容过大请输入小于16M图片');
		}
		else
		{
			form.result.textContent = '';
		}
	},
	//----------------Misc----------------
	websocket(form)
	{
		if (!form.socket.disabled)
		{
			form.socket.disabled = true;
			const
				websocket = new WebSocket(form.socket.value),
				connect = form.querySelector('button[type=submit]'),
				send = form.querySelector('button[type=button]');
			function echo(ctx)
			{
				const [who, msg] = typeof ctx === 'string' ? ['客户端', ctx] : ['服务端', ctx.data];
				form.echo.appendChild(document.createTextNode(`--------${who}:${$.date('Y-m-d\\Th:i:s')}--------\n${msg}\n\n`));
				form.echo.scrollTop = form.echo.scrollHeight;
			}
			websocket.onerror = () =>
			{
				form.echo.textContent = '';
				form.socket.disabled = false;
				echo('连接失败，请检查地址是否正确！');
			};
			websocket.onopen = () =>
			{
				websocket.onclose = () =>
				{
					echo('与服务器失去连接！');
					form.socket.disabled = false;
					form.send.disabled = true;
					connect.textContent = '开始连接';
					connect.type = 'submit';
					connect.onclick = null;
				};
				form.echo.textContent = '';
				form.send.disabled = false;
				connect.textContent = '断开连接';
				connect.type = 'button';
				connect.onclick = () => websocket.close();
				send.onclick = () =>
				{
					websocket.send(form.send.value);
					form.send.value = null;
				};
			};
			websocket.onmessage = event => echo(event);
		}
		return false;
	},
	base64(form)
	{
		form.result.value = $[`base64_${['en', 'de'][form.method.value]}code`](form.content.value);
		return false;
	},
	php_hash(form)
	{
		return tools.submit(form, 'text', text => form.result.value = text);
	},
	js_hash(form)
	{
		form.result.value = $.hash(form.algos.value, form.content.value);
		return false;
	},





	generate_password(form)
	{
		const charset = form.charset.value.split(''), passwords = [];
		for (let i = 0; i < form.count.value; ++i)
		{
			const password = [];
			while (password.length < form.length.value)
			{
				password[password.length] = charset[$.random_int(0, charset.length)];
			}
			passwords[passwords.length] = password.join('');
		}
		form.result.value = passwords.join('\n');
		return false;
	},
	generate_uuid(form)
	{
		form.result.value = Array.from(Array(parseInt(form.count.value))).map(() =>
			$.md5($.random_bytes(16)).replace(/^([0-f]{8})([0-f]{4})([0-f]{4})([0-f]{4})([0-f]{12})/i, '$1-$2-$3-$4-$5')).join('\n');
		return false;
	},


	// apple_mobile_webclip(form)
	// {
	
	// 	return form.icon.files.length && form.icon.files[0].size < 65535 ? tools.submit(form, 'text', text => {

	// 		console.log(text);
	// 	}) : !!alert('图标文件必须且小于60K');
	// }
};