"use strict";
const webapp = import('./webkit.js').then(function({default: $})
{
	const dialog = $.dialog(true);
	//dialog.target.className = 'webapp';

	async function asd()
	{

	}


	$.extend('element',
	{
		action()
		{
			const dataset = this.target.dataset;

			if ('confirm' in dataset)
			{
				//confirm(dataset.confirm)

				dialog.message(dataset.confirm).then(a => {

					
					console.log('close', a)
				})

				dialog.prompt({
					'asdwd' : {type: 'number', min: 10, max: 20}
				}).then(a => {

					
					console.log('close', a)
				})


				dialog.confirm(dataset.confirm).then(a => {

					
					console.log('close', a)
				})
			}
			

			// this.fetch().then(response => response.text()).then(body => {
			// 	try {
			// 		const json = JSON.parse(body);


			// 	} catch (error) {
					
			// 		alert(body)
			// 	}

				

				
			// });
			
			




			//console.log(this.target, this.target.tagName, url)
			return false;
		}
	});

	return globalThis.$ = $;
});
addEventListener('DOMContentLoaded', () => webapp.then(window.webapp));