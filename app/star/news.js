addEventListener('DOMContentLoaded', event =>
{
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