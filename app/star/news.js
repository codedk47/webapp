function adulted(event)
{
	if (event)
	{
		document.querySelectorAll('img').forEach(img => img.style.filter = 'blur(var(--webapp-gapitem))');
	}
	else
	{
		document.cookie="adult=yes";
		document.querySelectorAll('img').forEach(img => img.style.filter = null);
	}
}

addEventListener('DOMContentLoaded', event =>
{
	document.querySelectorAll('body>dialog').forEach(dialog => dialog.showModal());

	const video = document.createElement('video');
	video.setAttribute('playsinline', 'true');
	video.setAttribute('disablepictureinpicture', 'true');
	video.setAttribute('muted', 'true');
	video.autoplay = true;
	video.loop = true;
	let before;

	document.ontouchstart = event =>
	{
		for (let p = event.target; p !== document; p = p.parentNode)
		{
			if (before !== p && p.tagName === 'A' && p.dataset.preview)
			{
				before = p;
				video.src = p.dataset.preview;
				p.firstElementChild.appendChild(video);
				break;
			}
		}
	};
	document.querySelectorAll('div.videos>a,div.playleft>a,div.playright>a').forEach(element =>
	{
		element.onmouseenter = event => document.ontouchstart(event);
	});

});