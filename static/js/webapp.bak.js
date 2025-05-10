"use strict";//em...
window.wa = import('./webkit.js').then(({default: wa})=>
{
	const
	dialog = new wa.vn.dialog(true).call((dialog)=> Object.assign(dialog,
	{
		header: dialog.element('header'),
		section: dialog.element('section'),
		pre: wa.vn.element('pre'),//.style({maxWidth: '642px', maxHeight: '210px', overflow: 'auto'}),
		input: dialog.input().style({width: '100%'}).remove(),
		footer: dialog.element('footer'),
		cancel: dialog.button('Cancel').on('click', ()=> dialog.close('cancel')).remove(),
		accept: dialog.button('Accept').on('click', ()=> dialog.close('accept')).remove(),
		ok: dialog.button('OK').on('click', ()=> dialog.close('ok')).remove(),
		classic(title, option, content, value)
		{
			dialog.header.content(title);
			if (option)
			{
				dialog.footer.insert(dialog.cancel);
				dialog.footer.insert(dialog.accept);
			}
			else
			{
				dialog.classes('danger');
				dialog.footer.insert(dialog.ok);
			}
			if (wa.is_scalar(content))
			{
				dialog.section.insert(dialog.pre.content(content));
				if (wa.is_scalar(value))
				{
					dialog.section.insert(dialog.input.value(value));
				}
			}
			return dialog.section;
		},
		clear(...params)
		{
			dialog.header.content('#');
			dialog.section.remove('*');
			dialog.footer.remove('*');
			dialog.removes();
			return params;
		},
		progress: wa.vn.element('progress').style({width: '333px'}),
		onprogress(event)
		{
			dialog.progress.target.value = event.total ? event.loaded / event.total : 1;
		},
		uploading(vn)
		{
			vn.ajax.upload.onprogress = dialog.onprogress;
			dialog.header.content('Uploading...');
			dialog.section.insert(dialog.progress.value(1));
			dialog.footer.insert(dialog.cancel);
		},
		uploads(vn, status)
		{
			if (dialog.target.open)
			{
				dialog.close(status);
			}
			else
			{
				if (status === 'cancel')
				{
					vn.ajax.abort();
				}
				vn.ajax.upload.onprogress = null;
				dialog.progress.target.value = 0;
				dialog.clear();
			}
		}
	})),
	response = (vn)=>
	{
		console.log(vn)
		const response = wa.json_decode(vn.response);
		if (wa.is_entries(response) === false)
		{
			return wa.warning(vn.response, 'Errors');
		}
		if (wa.is_array(response.errors) && response.errors.length)
		{
			return wa.warning(response.errors.join('\n'));
		}
		
		
		
	},
	checked = ([value, vn])=>
	{
		if (value || wa.is_string(value))
		{
			

			dialog.open(dialog.uploading, vn).finally(()=> dialog.uploads(vn, 'cancel'));

			vn.send('{a:123}asd').finally(()=> dialog.uploads(vn, 'accept')).then(response);
			
			
			
		
		}
	},
	request = (vn)=>
	{
		const {dialog1, confirm, prompt, value} = vn.data();
		if (confirm || prompt)
		{
			(confirm ? wa.confirm(confirm, vn) : wa.prompt(prompt, value, vn)).then(checked);
		}
		else
		{
			// if (dialog)
			// {
			// 	if (wa.is_function(wa.dialog[dialog]))
			// 	{
			// 		wa.dialog(wa.dialog[dialog]).then((choice)=>
			// 		{
			// 			if (choice === 'accept')
			// 			{
			// 				vn.send().then(response);
			// 			}
			// 		});
			// 	}
			// 	else
			// 	{
			// 		wa.warning(`wa.dialog.${dialog} is not a function`);
			// 	}
			// }
			// else
			// {
			// 	vn.send().then(response);
			// }
		}
		vn.event('exit');
		return false;
	};


	


	wa.all('[data-status]').forEach((vn)=> vn.on(vn.data('status'), request))


	wa.vn.is('wa-input', class wainput extends HTMLElement
	{
		static stylesheet = wa.stylesheet('\
		:host{\
			position: relative;\
			display: inline;\
			contain: content;\
		}\
		input{\
			padding: 4px;\
		}\
		ul{\
			position: absolute;\
			left: 0;\
			top: 26px;\
			border: 1px solid #ccc;\
			box-shadow: 0 6px 12px #aaa;\
			padding: 4px 0;\
			margin: 0;\
			border-radius: 4px;\
		}\
		ul>li{\
			padding: 6px;\
			list-style: none;\
		}\
		ul>li:hover{\
			background-color: silver;\
		}\
		');
		static templete = wa.vn.from([
			['slot', {name: 'header'}],
			['slot', {name: 'section'}],
			['slot', {name: 'footer'}]
		]);
		// static get observedAttributes()
		// {
		// 	return ['type', 'name'];
		// }
		constructor()
		{
			const
				host = new wa.vn(super()),
				root = host.shadow,
				input = root.input(host.attr());
				wainput.stylesheet.connect(root);


			if (input.attr('type') === 'autocomplete')
			{
				input.attr({type: 'search', action: null, autocomplete: 'off'});
				const ul = host.shadow.element('ul');
				input.target.oninput = (aa)=>
				{
					ul.remove('*');
					for (let i=0;i<5;++i)
					{
						ul.element('li').content('dasd'+aa.target.value);
					}
				};
			}

			// console.log( wainput.templete )
			// 	console.log( input.attr() )
		
			

		}
		oninput(a)
		{
			
		}
		// attributeChangedCallback(name, oldval, newval)
		// {
		// 	console.log(name, oldval, newval);
		// }
	});

	wa.vn.is('wa-select', class waSelect extends HTMLElement
	{
		constructor()
		{
			const ff = new wa.vn(super());
			const sw = ff.shadow;
			ff.child('#comment').observe('CharacterData', ()=>{


				console.log(a.target.nodeValue)

			})

			ff.style({width: '100px', height: '100px', display: 'block'})
	
			// console.log( ff.child('#text').on('DOMCharacterDataModified', ()=>{
			// 	console.log(123)
			// }) )
		}
	})

	Object.assign(wa.vn,
	{
		sselect_response(details)
		{
			if (wa.is_entries(details.response.data))
			{
				const summary = details.get('summary>div');
				summary.remove('LABEL');
				summary.remove('INPUT');
				for (let [key, value] of Object.entries(details.response.data))
				{
					summary.sselect_option(key, value);
				}
			}
		},
		sselect_oninput(input)
		{
			const details = input.parent('details');
			details.request('GET', `${details.data('url')}${wa.urlencode(input.value())}`).then(wa.vn.sselect_response);
		},
		mselect_response(details)
		{
			if (wa.is_entries(details.response.data))
			{
				const summary = details.get('div');
				for (let input of summary.all('label>input[type=checkbox]:not(:checked)'))
				{
					input.parent('label').remove();
				}
				for (let [key, value] of Object.entries(details.response.data))
				{
					if (summary.has(`label>input[type=checkbox][value="${key}"]:checked`) === false)
					{
						summary.mselect_option(key, value);
					}
				}
			}
		},
		mselect_oninput(input)
		{
			const details = input.parent('details');
			details.request('GET', `${details.data('url')}${wa.urlencode(input.value())}`).then(wa.vn.mselect_response);
		}
	});

	Object.assign(wa.vn.prototype,
	{
		sselect_value()
		{
			return this.has('summary>div>input[type=radio]:checked')
				? this.get('summary>div>input[type=radio]:checked').value() : null;
		},
		sselect_checked(value)
		{
			if (wa.is_scalar(value) && this.has(`input[value="${value}"]`))
			{
				this.get(`input[value="${value}"]`).checked(true);
			}
		},
		sselect_option(value, content)
		{
			const input = this.input('radio').attr({name: this.target.parentNode.parentNode.dataset.name, value});
			this.element('label').attr('for', input.id).content(content);
		},
		sselect(name, options, value)
		{
			const
				details = this.element('details').attr('class', 'sselect').data('name', name),
				summary = details.element('summary').element('div');
			if (wa.is_entries(options))
			{
				for (let [value, content] of Object.entries(options))
				{
					summary.sselect_option(value, content);
				}
				summary.sselect_checked(value);
			}
			else
			{
				if (wa.is_string(options))
				{
					details.ajax.accept('json');
					details.request('GET', options).then(wa.vn.sselect_response).then(()=> summary.sselect_checked(value));
				}
			}
			return details;
		},
		sselect_input(name, options, value)
		{
			const details = this.sselect(name, options, value);
			details.ajax.accept('json');
			details.get('summary>div').insert(wa.vn.element('div'), 'first').input('search')
				.on('input', wa.vn.sselect_oninput)
				.on('focus', (vn)=> vn.evented('input'));
			return details;
		},
		mselect_value()
		{
			return this.all('div>label>input[type=checkbox]:checked').map((vn)=> vn.value());
		},
		mselect_checked(values)
		{
			if (wa.is_array(values))
			{
				for (let input of this.all(values.map((value)=> `label>input[value="${value}"]`)))
				{
					input.checked(true);
				}
			}
		},
		mselect_option(value, content)
		{
			const label = this.element('label');
			label.input('checkbox').attr({name: this.target.parentNode.dataset.name, value});
			label.text(content);
		},
		mselect(name, options, values)
		{
			const
				details = this.element('details').attr('class', 'mselect').data('name', `${name}[]`),
				summary = details.element('summary').attr('placeholder', 'Multiple Selection').insert(wa.vn.element('div'), 'after');
			if (wa.is_entries(options))
			{
				for (let [value, content] of Object.entries(options))
				{
					summary.mselect_option(value, content);
				}
				summary.mselect_checked(values);
			}
			else
			{
				if (wa.is_string(options))
				{
					details.ajax.accept('json');
					details.request('GET', options).then(wa.vn.mselect_response).then(()=> summary.mselect_checked(values));
				}
			}
			return details;
		},
		mselect_input(name, options, value)
		{
			const details = this.mselect(name, options, value);
			details.ajax.accept('json');
			details.get('div').insert(wa.vn.element('div'), 'first').input('search')
				.on('input', wa.vn.mselect_oninput)
				.on('focus', (vn)=> vn.evented('input'));
			return details;
		},
	});

	return Object.assign(wa,
	{
		dialog:		(scheme, ...params)=> dialog.open(dialog.call, scheme, ...params).then((retval)=> dialog.clear(retval, ...params)),
		warning:	(content, title = 'Warning')=> dialog.open(dialog.classic, title, false, content).then(dialog.clear),
		confirm:	(content, ...params)=> dialog.open(dialog.classic, 'Confirm', true, content).then((retval)=> dialog.clear(retval === 'accept', ...params)),
		prompt:		(content, value = '', ...params)=> dialog.open(dialog.classic, 'Prompt', true, content, value).then((retval)=> dialog.clear(retval === 'accept' ? dialog.input.value() : null, ...params)),
		callback:	(callbacks)=>
		{
			
		}
	});
});