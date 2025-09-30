"use strict";
const webapp = import('./webkit.js').then(function({default: $}, undefined)
{
	const dialog = $.dialog(true);
	$.dialog.message = (context, classname = 'webapp') => dialog.open(() =>
		dialog.draw(Object.assign({classname, accept: 'OK'}, $.is_entries(context) ? context : {content: context})));
	$.dialog.warning = context => $.dialog.message(context, 'webapp-warning');
	$.dialog.confirm = (context, classname = 'webapp') => dialog.open(() =>
		dialog.draw(Object.assign({classname, accept: 'Accept', cancel: 'Cancel'}, $.is_entries(context) ? context : {content: context})));
	$.dialog.prompt = context => dialog.open(() =>
	{
		while ($.is_string(context))
		{
			let fieldset;
			if (context.startsWith('<') || context.startsWith('#'))
			{
				context = context.startsWith('<')
					? $.element.template(context)
					: $(document.querySelector(`template${context}`).content.cloneNode(true));
				context.find('form').exists(node =>
				{
					node.target.onsubmit = event => !dialog.resolve(event.target);
					node.find('button:is([value=close],[type=reset])').exists(node => node.on('click', () => dialog.close()));
				});
				break;
			}
			const [name, type, ...value] = context.split(':');
			context = $.element.create('form', {class: 'webapp', method: 'dialog', autocomplete: 'off'});
			context.target.onsubmit = event => !dialog.close(event.target.input.type === 'file' ? (event.target.input.files.length
				? new Blob([event.target.input.files[0]], {type: event.target.input.files[0].type || 'application/octet-stream'})
				: undefined) : event.target.input.value);
	

			fieldset = $.element.create('fieldset');
			fieldset.append('legend', name);
			fieldset.append('input', {name: 'input', type: type || 'text', value: value.join(':')}).target.select();
			context.append(fieldset);

			fieldset = $.element.create('fieldset');
			fieldset.append('button', {0: 'Submit', type: 'submit'});
			fieldset.append('button', {0: 'Cancel', type: 'button'}).on('click', () => dialog.close());
			context.append(fieldset);
			break;
		}
		dialog.draw({classname: 'webapp'}).append(context);
	});
	$.dialog.hint = content =>
	{
		const dialog = $.dialog();
		dialog.open(() =>
		{
			dialog.draw({classname: 'webapp-hint', content});
			dialog.once('transitionend', () => dialog.remove());
			setTimeout(() => dialog.target.style.opacity = 0, 600);
		});
	};
	$.copytoclipboard = (content, success = 'Copied!') => navigator.clipboard
		.writeText(content).then($.is_string(success) ? () => $.dialog.hint(success) : success);

	// $.save_content_as = (text, name) =>
	// {
	// 	const a = document.createElement('a');
	// 	a.href = URL.createObjectURL(new Blob([text], {type: 'text/plain'}));
	// 	a.download = name;
	// 	a.click();
	// };


	async function before(context)
	{
		let body;
		if ($.is_object(context) && $.is_array(context.errors) && context.errors.length)
		{
			await $.dialog.warning({title: 'Errors', content: context.errors.join('\n')});
		}
		for (let method of ['message', 'warning', 'confirm', 'prompt'])
		{
			body = method in context ? await $.dialog[method](context[method]) : null;
			if (body === undefined)
			{
				return Promise.reject(body);
			}
		}
		switch (true)
		{
			case $.is_numeric(body): return body.toString(10);
			case $.is_bool(body): return body.toString();
			default: return body;
		}
	}
	async function after(response)
	{
		if (!response.ok)
		{
			return $.dialog.warning({title: response.status, content: response.statusText});
		}
		const text = await response.text();
		try
		{
			const context = JSON.parse(text);
			await before(context).then(async body => 'continue' in context && await action(context.continue, body), demission => demission);
			if (typeof context.redirect === 'string')
			{
				return location.replace(context.redirect);
			}
			if ('refresh' in context)
			{
				typeof context.refresh === 'string' ? location.assign(context.refresh) : location.reload();
			}
		}
		catch (error)
		{
			$.dialog.warning({title: 'Error', content: text.trim()});
		}
	}
	async function action(context, body)
	{
		const options = {};
		let callback = after, url;
		do
		{
			if ($.is_a(body, HTMLFormElement))
			{
				const fieldset = body.querySelectorAll('fieldset');
				body = $.formdata(body);
				fieldset.forEach(fieldset => fieldset.disabled = true);
				callback = response =>
				{
					fieldset.forEach(fieldset => fieldset.disabled = false);
					dialog.close();
					after(response);
				};
				break;
			}
			if ($.is_object(body))
			{
				body = $.json_encode(body);
			}
		} while (false);
		if ($.is_a(context, Element))
		{
			if (context.tagName === 'FORM')
			{
				url = context.action;
				options.method = context.method;
				options.body = body === null ? $.formdata(context) : body;
			}
			else
			{
				url = context.href || context.dataset.action;
				options.method = context.dataset.method;
				options.body = body === null ? context.dataset.body || null : body;
			}
		}
		else
		{
			url = context.action;
			options.method = context.method;
			options.body = context.body === null ? body : $.is_object(context.body) ? $.json_encode(context.body) : context.body;
		}
		options.method ||= options.body === null ? 'GET' : 'POST';
		return fetch(url, options).then(callback);
	}
	$.action = element => !before(element.dataset).then(body => action(element, body), demission => demission);

	$.delete_cookie_reload = name => location.reload($.cookie.delete(name));

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