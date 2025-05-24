masker.urlencode = data => encodeURIComponent(data).replace(/%20|[\!'\(\)\*\+\/@~]/g, escape =>
	({'%20': '+', '!': '%21', "'": '%27', '(': '%28', ')': '%29', '*': '%2A', '+': '%2B', '/': '%2F', '@': '%40', '~': '%7E'}[escape]));
masker.canplay = video =>
{
	console.log(video);
	document.querySelector('aside').style.height = `${Math.trunc(this.height * video.scalewidth)}px`;
	if (video.horizontal)
	{
		video.parentNode.style.cssText = 'position:sticky;top:0;z-index:9';
	}
	
};
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

	// document.querySelectorAll('a[href][data-key]').forEach(a => {

	// 	a.onclick = function()
	// 	{
	// 		console.log(a);
	// 		return false;
	// 	}
		
	// })


});