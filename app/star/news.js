addEventListener('DOMContentLoaded', event =>
{
	const video = document.createElement('video');
	video.setAttribute('playsinline', 'true');
	video.setAttribute('disablepictureinpicture', 'true');
	video.setAttribute('muted', 'true');
	video.autoplay = true;

	document.querySelectorAll('div.videos>a>figure,div.playleft>a>figure,div.playright>a>figure').forEach(element =>
	{
		element.onmouseleave = event => element.removeChild(video);
		element.onmouseenter = element.parentNode.ontouchstart = event =>
		{
			video.poster = element.firstElementChild.src;
			
			
			video.src = element.dataset.preview;
			element.appendChild(video);
		};
	});
});