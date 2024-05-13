addEventListener('DOMContentLoaded', event =>
{
	const video = document.createElement('video');
	document.querySelectorAll('div.videos>a>figure,div.playleft>a>figure,div.playright>a>figure').forEach(element =>
	{
		element.onmouseleave = element.parentNode.onblur = event => element.removeChild(video);
		element.onmouseenter = element.parentNode.onfocus = event =>
		{
			video.poster = element.firstElementChild.src;
			video.muted = true;
			video.autoplay = true;
			video.src = element.dataset.preview;
			element.appendChild(video);
		};
	});
});