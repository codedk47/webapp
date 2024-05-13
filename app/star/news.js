addEventListener('DOMContentLoaded', event =>
{
	const video = document.createElement('video');
	video.setAttribute('playsinline', 'true');
	document.querySelectorAll('div.videos>a>figure,div.playleft>a>figure,div.playright>a>figure').forEach(element =>
	{
		element.onmouseleave = event => element.removeChild(video);
		element.onmouseenter = element.ontouchstart = event =>
		{
			video.poster = element.firstElementChild.src;
			video.muted = true;
			video.autoplay = true;
			video.src = element.dataset.preview;
			element.appendChild(video);
		};
	});
});