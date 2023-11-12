masker.splashscreen = () =>
{
	const header = document.querySelector('header'), duration = document.body.dataset.duration * 1;
	document.onclick = () => location.href = document.body.dataset.acturl;
	setTimeout(function timer(duration)
	{
		if (duration)
		{
			header.textContent = `${String(--duration).padStart(2, 0)} s`;
			setTimeout(timer, 1000, duration);
		}
		else
		{
			header.textContent = 'Skip';
			header.onclick = () => postMessage('close');
			document.body.dataset.autoskip && header.onclick();
		}
	}, 1000, duration);
};

masker.revert_account = input =>
{
	if (input.files.length)
	{
		const codeReader = new ZXingBrowser.BrowserQRCodeReader();
		codeReader.decodeFromImageUrl(URL.createObjectURL(input.files[0])).then(result =>
		{
			masker.authorization(result.text).then(() => location.replace(location.href));
		});
	}
};

masker.create_account = form =>
{
	if (form.style.pointerEvents !== 'none')
	{
		form.style.pointerEvents = 'none';
		masker(form.action, {method: 'POST', body: new FormData(form)}).then(response => response.json()).then(json =>
		{
			json.token = 'Is-tV4Pao1rehhGxG1R2Ju4dDMsrgQwB8T1FHdP1uW9FnEX28NSM1DcO3GPipdKl03O';
			masker.authorization(json.token).then(() => location.replace(location.href));
		}).finally(() => form.style.pointerEvents = null);
	}
	return false;
};


masker.canplay = video =>
{
	console.log(video);
};