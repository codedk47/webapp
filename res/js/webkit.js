/*
该库为PQ分离浏览器库，为了PQ核心函库数可以独立运行在其他JS平台上
请与PQ核心库一起使用，由ZERONETA编写PQ库的扩展在WebApp骨架上使用
*/
import pq from './pq.js';
export default new pq((window, undefined)=>
{
	const//Web APIs
	{
		Array,
		atob,
		btoa,
		CSSStyleSheet,
		clearInterval,
		clearTimeout,
		crypto,
		customElements,
		document,
		Event,
		FormData,
		frames,
		getComputedStyle,
		HTMLElement,
		Image,
		location,
		Map,
		Notification,
		navigator,
		Proxy,
		Set,
		String,
		setInterval,
		setTimeout,
		TextDecoder,
		TextEncoder,
		Uint32Array,
		Uint8Array,
		WebSocket,
		XMLHttpRequest
	} = window,
	fromCodePoint = String.fromCodePoint,
	ajax = pq.mapping(()=> pq.http),
	observers = new Map,
	listeners = new Map,
	events = new Map;

	// new window.MutationObserver((records)=>
	// {
	// 	for (let record of records)
	// 	{
	// 		let observer = observers.get(record.target);
	// 		if (observer && (observer = observer.get(record.type)))
	// 		{
	// 			console.log(record)
	// 		}
	// 	}
	// }).observe(document.documentElement, {characterData: true, childList: true, subtree: true});

	class element
	{
		static id = 0;
		static get =		(syntax, context = document) => new element(context.querySelector(syntax));
		static name =		(target)=>
		{
			switch (target.nodeType)
			{
				case 1:		return target.tagName.toLowerCase();
				case 2:		return `@${target.nodeName}`;
				case 7:		return `?${target.nodeName.toLowerCase()}`;
				default:	return target.nodeName;
			}
		}
		static create =	(tagname)=> new element(document.createElement(tagname));
		constructor(target = document)
		{
			this.#target = target;
		}
		#target = null;
		get target()
		{
			return this.#target;
		}
		get name()
		{
			return element.name(this.target);
		}
		get id()
		{
			if (this.target.hasAttribute('id') === false)
			{
				this.target.id = `idx${++element.id}`;
			}
			return this.target.id;
		}
		get connected()
		{
			return this.target.isConnected;
		}
		get text()
		{
			return this.target.textContent;
		}
		text(content)
		{
			this.target.textContent = content;
			return this;
		}
		on(type, listener)
		{
			this.target.addEventListener(type, listener);
			return this;
		}
		off(type, listener)
		{
			this.target.removeEventListener(type, listener);
			return this;
		}
		once(type, listener)
		{
			this.target.addEventListener(type, listener, {once: true});
			return this;
		}
		event(type, bubbles, cancelable, composed)
		{
			return this.target.dispatchEvent(new Event(type, {bubbles, cancelable, composed}));
		}
		fetch(data)
		{
			let url;
			const options = {};
			if (this.name === 'form')
			{
				url = this.target.action;
				options.method = this.target.method;
				options.body = new formdata(this.target);
			}
			else
			{
				url = this.target.href || this.target.dataset.action;
				options.method = this.target.dataset.method || 'get';
				options.body = data;
			}
			return fetch(url, options);
		}
		remove(nodename)
		{
			if (pq.is_string(nodename))
			{
				if (nodename === '*')
				{
					while (this.target.firstChild)
					{
						this.target.removeChild(this.target.lastChild);
					}
				}
				else
				{
					for (let target of this.target.childNodes)
					{
						if (element.name(target) === nodename)
						{
							this.target.removeChild(target);
						}
					}
				}
			}
			else
			{
				this.target.remove();
			}
			return this;
		}
		append(...elements)
		{
			for (let i in elements)
			{
				this.target.appendChild(elements[i].target);
			}
		}
	}
	class dialog extends element
	{
		#retval;
		#header = element.create('header');
		#section = element.create('section');
		#footer = element.create('footer');

		
		#cancel = element.create('button');
		#accept = element.create('button');

		constructor(modal)
		{
			let valve = true;
			super(document.createElement('dialog'));
			
			this.#cancel.on('click', () => this.close(false));
			this.#accept.on('click', () => this.close(true));



			this.on('close', async event =>
			{
				//this.remove('*');
				if (valve === false && event.isTrusted)
				{
					this.remove();
					await Array.prototype.shift.call(this)(this.#retval);
					valve = true;
				}
				if (valve && this.target.open === false && this.length)
				{
					valve = false;
					const [scheme, params] = Array.prototype.shift.call(this);
					if (pq.is_function(scheme))
					{
						this.remove('*');
						await scheme.apply(this, params);
					}
					if (document.body)
					{
						document.body.appendChild(this.target)[modal ? 'showModal': 'show']();
						//this.target.returnValue = '';
					}
					else
					{
						valve = Array.prototype.shift.call(this);
					}
				}
			});
		}
		// clear()
		// {

		// 	return this.remove('*');
		// }
		draw(title, cancel, accept)
		{
			this.append(this.#header.text(title), this.#section);
			this.#footer.remove('*');
	
			cancel && this.#footer.append(this.#cancel.text(cancel));
			accept && this.#footer.append(this.#accept.text(accept));

			if (this.#footer.target.childElementCount)
			{
				this.append(this.#footer);
			}


			return this.#section;
		}
		open(scheme, ...params)
		{
			this.#retval = null;
			return pq.promise(resolve =>
			{
				Array.prototype.push.call(this, [scheme, params], resolve);
				this.event('close');
			});
		}
		close(value)
		{
			this.#retval = value;
			this.target.close();
			return this;
		}


		message(content, title = 'Message', button = 'OK')
		{
			return this.open(() => {
				this.draw(title, button).text(content);

				
			})
		}
		async confirm(content, title = 'Confirm', cancel = 'Cancel', accept = 'Accept')
		{
			return this.open(() => {
				this.draw(title, cancel, accept).text(content);

				
			})
		}
		async prompt(formdata)
		{
			return this.open(() => {
				let fieldset;
				const form = element.create('form');
				

				for (const [name, attributes] of Object.entries(formdata))
				{
					const field = element.create('input');
					fieldset = element.create('fieldset');
					//field.name = name;

					//field.setattr(attributes);


					field.type = attributes.type;

					//console.log(name, attributes);


					

					form.append(fieldset.append(field));
				}
				fieldset.append

				
				form.append
				console.log(this.target);

				this.append(form);
			})

		}

	};
	class formdata extends FormData
	{
	}

	class http extends XMLHttpRequest
	{
		evented(download, upload)
		{
			if (pq.is_entries(download))
			{
				for (let [type, listener] of Object.entries(download))
				{
					switch (type)
					{
						case 'onabort':
						case 'onloadend':
						case 'onloadstart':
						case 'onprogress':
						case 'onreadystatechange':
						case 'ontimeout':
							this[type] = listener;
					}
				}
			}
			if (pq.is_entries(upload))
			{
				for (let [type, listener] of Object.entries(upload))
				{
					switch (type)
					{
						case 'onabort':
						case 'onerror':
						case 'onload':
						case 'onloadend':
						case 'onloadstart':
						case 'onprogress':
						case 'ontimeout':
							this.upload[type] = listener;
					}
				}
			}
			return this;
		}
		accept(type)
		{
			this.responseType = type;
			return this;
		}
		request(method, url, data = null)
		{
			this.open(method, url, true);
			if (pq.is_entries(data) && pq.is_formdata(data) === false)
			{
				this.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
				data = pq.http_build_query(data);
			}
			return pq.promise((resolve, reject)=>
			{
				this.onload = resolve;
				this.onerror = reject;
				this.send(data);
			}).then((event)=> event.target);
		}
	}

	class websocket extends WebSocket
	{
	}

	return new Proxy(Object.assign(Object.defineProperties(pq,
	{
		href: {get() {return location.href;}, set(url) {location.href = url;}},
		http: {get() {return new http;}}
	}),
	{
		extend: 			(classname, prototype) => Object.assign({element, dialog, formdata, websocket}[classname].prototype, prototype),
		clipboard:			navigator.clipboard,
		session:			window.sessionStorage,
		storage:			window.localStorage,
		// utf8_decode:		(data)=> new TextDecoder('utf-8').decode(Uint8Array.from(data, (byte)=>byte.codePointAt(0))),
		// utf8_encode:		(data)=> fromCodePoint(...new TextEncoder().encode(data)),
		// base64_decode:		(data)=> pq.utf8_decode(atob(data)),
		// base64_encode:		(data)=> btoa(pq.utf8_encode(data)),
		// is_vn:				(object)=> pq.is_a(object, vn),
		is_element:			(object)=> pq.is_a(object, HTMLElement),
		is_formdata:		(object)=> pq.gettype(object) === 'FormData',
		is_window:			(object)=> object === window || pq.in_array(object, frames, true),
		random_bytes:		(length)=> crypto.getRandomValues(new pq.struct(length)).latin1,
		random_int:			(min, max)=> crypto.getRandomValues(new Uint32Array(1))[0] % (max - min) + min,
		setcookie:			(name, value = '', expire, path, domain, secure)=>
		{
			document.cookie = [`${pq.urlencode(name)}=${pq.urlencode(value)}`,
				pq.is_int(expire) ? `;expires=${pq.date_create(expire).toUTCString()}` : '',
				pq.is_string(path) ? `;path=${path}` : '',
				pq.is_string(domain) ? `;domain=${domain}` : '',
				secure ? ';secure' : ''].join('');
			return true;
		},
		getcookie:			(name)=>
		{
			const find = ` ${pq.urlencode(name)}=`, cookie = ` ${document.cookie};`, offset = cookie.indexOf(find) + find.length;
			return offset > find.length ? pq.urldecode(cookie.substring(offset, cookie.indexOf(';', offset))) : '';
		},
		getcookies:			()=> document.cookie.split(';').reduce((cookies, item)=>
		{
			const offset = item.indexOf('=');
			cookies[pq.urldecode(item.substring(0, offset).trimLeft())] = pq.urldecode(item.substring(offset + 1));
			return cookies;
		}, {}),
		deferred:			Object.assign((callback, delay = 0, ...params)=> setTimeout(callback, delay, ...params), {cancel(id) {clearTimeout(id);}}),
		interval:			Object.assign((callback, delay = 0, ...params)=> setInterval(callback, delay, ...params), {cancel(id) {clearInterval(id);}}),
		reload:				(forced = false)=> location.reload(forced),
		// pq.assign =				(url)=> location.assign(url);
		// pq.replace =			(url)=> location.replace(url);
		openwindow:			(...params)=> window.open(...params),
		notification:		(title, options)=> new Notification(title, options),
		loadimg:			(url)=> pq.promise((resolve, reject)=>
		{
			const image = new Image;
			image.onload = resolve;
			image.onerror = reject;
			image.src = url;
		}).then((event)=> new element(event.target)),
		formdata:			(element) => new formdata(element),
		websocket:			(url)=> pq.promise((resolve, reject)=>
		{
			const ws = new websocket(url);
			ws.onopen = resolve;
			ws.onerror = reject;
		}).then((event)=> event.target),
		dialog:				(modal) => new dialog(modal),
		//cd:					[listeners, events, ajax]//缓存的数据，只供观察
	}),
	{
		apply(target, ...[, [any]])
		{


			
			return target.is_string(any) ? element.get(any) : new element(any);
		}
	});
});