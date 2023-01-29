window.addEventListener('DOMContentLoaded', async event =>
{
	const
	frame = document.querySelector('iframe'),
	href = frame.dataset.entry || location.href;


	



	if (frame.dataset.query)
	{
		backer(`${href}${frame.dataset.query}`, {mask: true, type: 'text/plain'}).then(data => {

			frame.contentDocument.open();
			frame.contentDocument.write(data);
			frame.contentDocument.close();
		});
	}


	
	
});