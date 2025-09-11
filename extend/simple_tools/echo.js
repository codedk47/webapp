const tools =
{
	write_clipboard(content)
	{
		navigator.clipboard.writeText(content).then(() => alert('已拷贝！'));
	},

	base64(form)
	{
		form.result.value = $[`base64_${['en', 'de'][form.method.value]}code`](form.content.value);
		return false;
	},
	hash(form)
	{
		form.result.value = $.hash(form.method.value, form.content.value);
		return false;
	},
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
	generate_password(form)
	{
		console.log(form);
		return false;
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
	}
};