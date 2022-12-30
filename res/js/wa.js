function g(p,a)
{
	location.assign(((o,q)=>Object.keys(o).reduce((p,k)=>o[k]===null?p:`${p},${k}:${o[k]}`,q))
		(...typeof(p)==="string"?[{},p]:[
			Object.assign(Object.fromEntries(Array.from(location.search.matchAll(/\,(\w+)(?:\:([\%\+\-\.\/\=\w]*))?/g)).map(v=>v.slice(1))),p),
			a||location.search.replace(/\,.+/,"")]));
}
function urlencode(data)
{
	return encodeURIComponent(data).replace(/%20|[\!'\(\)\*\+\/@~]/g, (escape)=> ({
		'%20': '+',
		'!': '%21',
		"'": '%27',
		'(': '%28',
		')': '%29',
		'*': '%2A',
		'+': '%2B',
		'/': '%2F',
		'@': '%40',
		'~': '%7E'}[escape]));
}




document.addEventListener('DOMContentLoaded', event =>
{
	async function dialog(context, resolve, reject)
	{
		const
		dialog = document.createElement('dialog'),
		footer = document.createElement('footer'),
		form = document.createElement('form'),
		confirm = document.createElement('button'),
		cancel = document.createElement('button');
		confirm.innerHTML = 'OK';
		cancel.innerHTML = 'Cancel';
		footer.appendChild(confirm);
		if (typeof context === 'string' && resolve)
		{
			footer.appendChild(cancel);
		}
		return new Promise((resolve, reject) =>
		{
			//dialog.appendChild(document.createElement('header')).innerHTML = 'dialog';
			confirm.onclick = () => Boolean(dialog.close(resolve(form)));
			dialog.onclose = cancel.onclick = () => reject(form);
			dialog.appendChild(form).onsubmit = confirm.onclick;
			if (typeof context === 'string')
			{
				form.appendChild(document.createElement('pre')).innerText = context;
			}
			else
			{
				form.method = context.method || 'post';
				//form.enctype = context.enctype || 'application/x-www-form-urlencoded';
				context.hasOwnProperty('action') && form.setAttribute('action', context.action);
				for (let name in context.fields)
				{
					let field;
					switch (context.fields[name])
					{
						case 'textarea':
							field = document.createElement('textarea');
							break;
						default:
							field = document.createElement('input');
							field.type = context.fields[name];
					}
					field.name = name;
					form.appendChild(document.createElement('fieldset')).appendChild(field);
				}
				footer.appendChild(cancel);
			}
			dialog.appendChild(footer);
			document.body.appendChild(dialog).showModal();
		}).then(resolve, reject || Boolean).finally(() => dialog.remove());
	}
	async function interact(element)
	{
		//dialog({method: 'get', action: '?', fields: {name: 'text'}}).then(bind);

		//redirect: 'manual'
		//return;
	
		//alert(element.enctype)

		//console.log( JSON.stringify(Object.fromEntries(new FormData(element))) );
		//return;

		const
		response = element.tagName === 'FORM'
			? await fetch(element.action, {method: element.getAttribute('method'), body: new URLSearchParams(new FormData(element))})
			: await fetch(element.href || element.dataset.src, {method: element.dataset.method || 'get'}),
		data = await response.text();

		//alert(response.redirect());
		//console.log( response );
		//return;
		


		try
		{
			const json = JSON.parse(data);
			if (Array.isArray(json.errors) && json.errors.length)
			{
				await dialog(json.errors.join('\n'));
			}
			if (json.hasOwnProperty('dialog'))
			{
				typeof json.dialog === 'string'
					? await dialog(json.dialog)
					: await dialog(json.dialog, interact);
			}
			if (json.hasOwnProperty('goto'))
			{
				//console.log(json.goto)
				typeof json.goto === 'string'
					? location.assign(json.goto)
					: location.reload();
			}
		}
		catch (error)
		{
			dialog(data);
		}
	}



	document.querySelectorAll('[data-bind]').forEach(element => element[`on${element.dataset.bind}`] = event =>
	{
		

		if (element.tagName === 'FORM')
		{
			interact(element);
		}
		else
		{
			if (element.dataset.dialog)
			{
				try
				{
					dialog({
						method: element.dataset.method || 'post',
						//enctype: element.dataset.enctype || 'application/x-www-form-urlencoded',
						action: element.href || element.dataset.src,
						fields: JSON.parse(element.dataset.dialog)
					}, interact);
				}
				catch
				{
					dialog(element.dataset.dialog, () => interact(element));
				}
			}
			else
			{
				interact(element);
			}
		}
		return false;
	});
});