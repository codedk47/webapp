const dialog = render => new Promise(resolve =>
{
	const dialog = document.createElement('dialog');
	document.body.appendChild(dialog);
	render(dialog, context =>
	{
		resolve(context);
		dialog.close();
		document.body.removeChild(dialog);
	});
	dialog.showModal();
});