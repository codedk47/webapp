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


	$.authsignin = (element, callback) =>
	{
		if (element.style.pointerEvents !== 'none')
		{
			const
				account = Object.fromEntries(new FormData(element).entries()),
				fieldset = element.querySelectorAll('fieldset');
			element.style.pointerEvents = 'none';
			fieldset.forEach(field => field.disabled = true);
			element.oninput = () => Object.keys(account).forEach(field => element[field].setCustomValidity(''));
			fetch(element.action, {headers: {'Sign-In': encodeURI(JSON.stringify(account))}}).then(response => response.json()).then(callback)
				.finally(() => fieldset.forEach(field => field.disabled = false), element.style.pointerEvents = null);
		}
		return false;
	};
	$.at = (p, a) => location.assign(((o,q)=>Object.keys(o).reduce((p,k)=>o[k]===null?p:`${p},${k}:${o[k]}`,q))
		(...typeof(p)==="string"?[{},p]:[
			Object.assign(Object.fromEntries(Array.from(location.search.matchAll(/\,(\w+)(?:\:([\%\+\-\.\/\=\w]*))?/g)).map(v=>v.slice(1))),p),
			a||location.search.replace(/\,.+/,"")]));

	return globalThis.$ = $;
});
addEventListener('DOMContentLoaded', () => webapp.then(window.webapp));