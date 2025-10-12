"use strict";
const webapp = import('../modules/webkit.mjs').then(function({default: $}, undefined)
{
	const xhr = $.xhr, dialog = $.dialog(true),
	message = context => dialog.open(() =>
		dialog.draw(Object.assign({class: 'webapp', accept: 'OK'}, $.is_entries(context) ? context : {content: context}))),
	confirm = context => dialog.open(() =>
		dialog.draw(Object.assign({class: 'webapp', accept: 'Accept', cancel: 'Cancel'}, $.is_entries(context) ? context : {content: context}))),
	prompt = context => dialog.open(() =>
	{
		const draw = {class: 'webapp'};
		while ($.is_string(context))
		{
			let fieldset;
			if (context.startsWith('<') || context.startsWith('#'))
			{
				context = context.startsWith('<')
					? $.element.template(context)
					: $(document.querySelector(`template${context}`).content.cloneNode(true));
				context.find('form').exists(node => node.target.onsubmit = event => !dialog.resolve(event.target));
				draw.cancel = 'Close';
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
		dialog.draw(draw).append(context);
	});
	async function pending(context)
	{
		let retval;
		if ($.is_object(context) && $.is_array(context.errors) && context.errors.length)
		{
			await message({class: 'webapp-warning', title: 'Errors', content: context.errors.join('\n')});
		}
		for (let method of ['message', 'confirm', 'prompt'])
		{
			retval = method in context ? await $.dialog[method](context[method]) : null;
			if (retval === undefined)
			{
				return Promise.reject(retval);
			}
		}
		switch (true)
		{
			case $.is_numeric(retval): return retval.toString(10);
			case $.is_bool(retval): return retval.toString();
			default: return retval;
		}
	}
	async function finish()
	{
		if (xhr.status < 200 || xhr.status > 299)
		{
			return message({class: 'webapp-warning', title: xhr.status, content: xhr.statusText});
		}
		try
		{
			const context = JSON.parse(xhr.responseText);
			await pending(context).then(async retval => 'continue' in context && await action(context.continue, retval), demission => demission);
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
			message({class: 'webapp-warning', title: error.message, content: xhr.responseText.trim()});
		}
	}
	async function action(context, retval)
	{
		let callback = finish, method, url, body, fieldset;
		if ($.is_element(context))
		{
			switch (context.tagName)
			{
				case 'FORM':
					xhr.progress = context.querySelector('progress');
					method = context.getAttribute('method');
					url = context.action;
					body = $.formdata(context);
					fieldset = context.querySelectorAll('fieldset');
					fieldset.forEach(fieldset => fieldset.disabled = true);
					callback = () => finish(dialog.close(fieldset.forEach(fieldset => fieldset.disabled = false)));
					break;
				case 'A':
					url = context.href;
				case 'INPUT':
				case 'SELECT':
				case 'TEXTAREA':
					body = context.value;
				default:
					method = context.dataset.method;
					url === undefined && (url = context.dataset.action);
					body === undefined && (body = context.dataset.body);
			}
		}
		else
		{
			url = context.action;
			method = context.method;
			if (context.body !== null)
			{
				body = $.is_object(context.body) ? $.json_encode(context.body) : context.body;
			}
		}
		while (body === undefined)
		{
			if ($.is_a(retval, HTMLFormElement))
			{
				xhr.progress = retval.querySelector('progress');
				body = $.formdata(retval);
				fieldset = retval.querySelectorAll('fieldset');
				fieldset.forEach(fieldset => fieldset.disabled = true);
				callback = () => finish(dialog.close(fieldset.forEach(fieldset => fieldset.disabled = false)));
				break;
			}
			if ($.is_object(retval))
			{
				body = $.json_encode(retval);
				break;
			}
			body = retval;
		}
		return xhr.request(method || (body === null ? 'GET' : 'POST'), url, body).then(callback);
	}
	Object.assign($.dialog, {message, confirm, prompt, hint(content)
	{
		const dialog = $.dialog();
		dialog.open(() =>
		{
			dialog.draw({class: 'webapp-hint', content});
			dialog.once('transitionend', () => dialog.remove());
			setTimeout(() => dialog.target.style.opacity = 0, 600);
		});
	}});
	return globalThis.$ = Object.assign($, {
		action(element)
		{
			return !pending(element.dataset).then(retval => action(element, retval), demission => demission);
		},
		at(p, a)
		{
			location.assign(((o,q)=>Object.keys(o).reduce((p,k)=>o[k]===null?p:`${p},${k}:${o[k]}`,q))
			(...typeof(p)==="string"?[{},p]:[
				Object.assign(Object.fromEntries(Array.from(location.search.matchAll(/\,(\w+)(?:\:([\%\+\-\.\/\=\w]*))?/g)).map(v=>v.slice(1))),p),
				a||location.search.replace(/\,.+/,"")]))
		},
		sign_in(form, authorize)
		{
			if (form.style.pointerEvents !== 'none')
			{
				const data = $.formdata(form), fieldset = form.querySelectorAll('fieldset');
				form.style.pointerEvents = 'none';
				fieldset.forEach(field => field.disabled = true);
				form.oninput = () => data.keys().forEach(field => form[field].setCustomValidity(''));
				fetch(form.action, {headers: {'Sign-In': data.toString()}}).then(response => response.json()).then(authorize || (authorize =>
					authorize.signature
						? $.cookie.refresh(form.dataset.storage, authorize.signature)
						: (form[authorize.error.field].setCustomValidity(authorize.error.message),
							requestAnimationFrame(() => form.reportValidity())))).finally(() =>
					(fieldset.forEach(field => field.disabled = false), form.style.pointerEvents = null));
			}
			return false;
		},
		copytoclipboard(content, success = 'Copied!')
		{
			$.copy(content).then($.is_string(success) ? () => $.dialog.hint(success) : success);
		},
		// save_content_as(text, name)
		// {
		// 	const a = document.createElement('a');
		// 	a.href = URL.createObjectURL(new Blob([text], {type: 'text/plain'}));
		// 	a.download = name;
		// 	a.click();
		// },
		previewimage(input, element)
		{
			element.src = input.files.length ? URL.createObjectURL(input.files[0]) : null;
		}
	});
});
addEventListener('DOMContentLoaded', () =>
{
	document.addEventListener('mouseup', event => document.querySelectorAll('details.popup[open]').forEach(details =>
	{
		for (let element = event.target.parentNode; element; element = element.parentNode)
		{
			if (element === details)
			{
				return;
			}
		}
		details.open = false;
	}));
	webapp.then(window.webapp);
});