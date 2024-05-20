addEventListener('DOMContentLoaded', event =>
{
	const video = document.createElement('video');
	video.setAttribute('playsinline', 'true');
	video.setAttribute('disablepictureinpicture', 'true');
	video.setAttribute('muted', 'true');
	video.autoplay = true;
	video.loop = true;


	document.ontouchstart = event =>
	{
		for (let p = event.target; p !== document; p = p.parentNode)
		{
			if (p.tagName === 'A' && p.dataset.preview)
			{
				video.src = p.dataset.preview;
				p.firstElementChild.appendChild(video);
			}
		}
	};


	return;
	

	document.querySelectorAll('div.videos>a,div.playleft>a,div.playright>a').forEach(element =>
	{
		element.onmouseleave = event =>
		{
			
			// if (video && video.isConnected)
			// {
			// 	video.remove();
			// 	video = null;
			// }
		};
		element.onmouseenter = event =>
		{
			console.log(element)
			// video = document.createElement('video');
			// video.setAttribute('playsinline', 'true');
			// video.setAttribute('disablepictureinpicture', 'true');
			// video.setAttribute('muted', 'true');
			// video.autoplay = true;
			// video.poster = element.firstElementChild.src;
			// video.src = element.dataset.preview;
			// element.appendChild(video);
			// video.style.opacity = 1;
			// element.appendChild(video);
			// video.oncanplay = () => {
			// 	video.style.opacity = null;
				
			// }
		};
		element.ontouchstart
		// element.parentNode.ontouchstart = () =>
		// {
		// 	element.onmouseleave();
		// 	element.onmouseenter();
		// };
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