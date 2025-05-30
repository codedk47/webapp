"use strict";
const webapp = import('./webkit.js').then(function({default: $})
{
	const dialog = $.dialog(true);
	//dialog.target.className = 'webapp';


	$.extend('element',
	{
		async action(url)
		{
			const dataset = this.target.dataset;
			'message' in dataset && await dialog.message(dataset.message);
			return ('confirm' in dataset ? dialog.confirm(dataset.confirm) : Promise.resolve(true)).then(confirm => confirm
				? this.fetch().then(response => response.text()).then(async text =>
				{
					
					try
					{
						const json = JSON.parse(text);
						if (typeof json.message === 'string')
						{
							await dialog.message(json.message);
						}
						typeof json.redirect === 'string' && location.replace(json.redirect);
						if ('refresh' in json)
						{
							typeof json.refresh === 'string' ? location.assign(json.refresh) : location.reload();
						}
					}
					catch (error)
					{
						dialog.message(text);
					}
				}) : Promise.resolve(confirm));
		}
	});

	$.previewimage = (input, element) => element.src = URL.createObjectURL(input.files[0]);

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