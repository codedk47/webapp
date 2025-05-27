masker.urlencode = data => encodeURIComponent(data).replace(/%20|[\!'\(\)\*\+\/@~]/g, escape =>
	({'%20': '+', '!': '%21', "'": '%27', '(': '%28', ')': '%29', '*': '%2A', '+': '%2B', '/': '%2F', '@': '%40', '~': '%7E'}[escape]));
masker.init((data) =>
{
	console.log(data)

});
masker.then(() =>
{
	document.querySelectorAll('blockquote[data-lazy]').forEach(element => masker.viewport(element).then(function lazy(element)
	{
		fetch(`${element.dataset.lazy}${element.dataset.page++}`).then(response => response.text()).then(content =>
		{
			if (content.startsWith('<'))
			{
				const template = document.createElement('template');
				template.innerHTML = content;
				element.previousElementSibling.appendChild(template.content);
				masker.viewport(element).then(lazy);
			}
			else
			{
				element.textContent = '没有更多的内容了';
			}
		});
	}));
});
masker.auth = element =>
{
	if (element.style.pointerEvents !== 'none')
	{
		const account = Object.fromEntries(new FormData(element).entries()), fieldset = element.querySelectorAll('fieldset');
		element.style.pointerEvents = 'none';
		fieldset.forEach(field => field.disabled = true);
		element.oninput = () => Object.keys(account).forEach(field => element[field].setCustomValidity(''));
		fetch(element.action, {headers: {'Sign-In': encodeURI(JSON.stringify(account))}}).then(response => response.json()).then(authorize =>
		{
			typeof authorize[element.dataset.storage] === 'string'
				? masker.token(authorize[element.dataset.storage]).then(() => location.reload())
				: (element[authorize.error.field].setCustomValidity(authorize.error.message), requestAnimationFrame(() => element.reportValidity()));
		}).finally(() => fieldset.forEach(field => field.disabled = false), element.style.pointerEvents = null);
	}
	return false;
};
masker.video_resizer = video =>
{
	//console.log(video);
	video.parentNode.dataset.type = video.horizontal ? 'h' : 'v';
};

