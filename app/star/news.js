addEventListener('DOMContentLoaded', event =>
{
	let video = null;

	document.querySelectorAll('div.videos>a>figure,div.playleft>a>figure,div.playright>a>figure').forEach(element =>
	{
		element.onmouseleave = event =>
		{
			if (video && video.isConnected)
			{
				element.removeChild(video);
			}
		};
		element.onmouseenter = element.parentNode.ontouchstart = event =>
		{
			element.onmouseleave();
			video = document.createElement('video');
			video.setAttribute('playsinline', 'true');
			video.setAttribute('disablepictureinpicture', 'true');
			video.setAttribute('muted', 'true');
			video.autoplay = true;
			video.poster = element.firstElementChild.src;
			video.src = element.dataset.preview;
			element.appendChild(video);
			// video.style.opacity = 1;
			// element.appendChild(video);
			// video.oncanplay = () => {
			// 	video.style.opacity = null;
				
			// }
		};
		// element.parentNode.onclick = event =>
		// {
		// 	if (event.target.tagName !== 'VIDEO')
		// 	{
		// 		event.preventDefault();
		// 		element.onmouseenter()
		// 	}
		// };
	});
});