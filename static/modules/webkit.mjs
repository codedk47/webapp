/*
该库为PQ分离浏览器库，为了PQ核心函库数可以独立运行在其他JS平台上
请与PQ核心库一起使用，由ZERONETA编写PQ库的扩展在WebApp骨架上使用
*/
import pq from './pq.min.mjs';
export default new pq((window, undefined)=>
{
	const//Web APIs
	{
		Array,
		atob,
		btoa,
		cookieStore,
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
	fromCodePoint = String.fromCodePoint, cookie = new Proxy({
		asd: 123,
		refresh(name)
		{
			cookie
		}
	}, {
		// set(target, thisArg, argumentsList)
		// {
		// 	console.log(target, thisArg, argumentsList)
		// },
		get(target, property, receiver)
		{
			return (asd) => console.log(asd);
			console.log(target, property)
			
			//cookieStore.get(property).then(a => console.log(a.value))


			// const find = ` ${pq.urlencode(property)}=`, cookie = ` ${document.cookie};`, offset = cookie.indexOf(find) + find.length;

			// console.log(find, "\n", cookie, "\n", offset, "\n",
			
			// cookie.indexOf(';', offset),
			// "\n",
			// pq.urldecode(cookie.substring(offset, cookie.indexOf(';', offset))) , [offset, find.length])
			
			// return offset > find.length ? pq.urldecode(cookie.substring(offset, cookie.indexOf(';', offset))) : '';

		}
	});
	
	const aaa = {

		refresh(name)
		{
			//arguments.length ? cookieStore[arguments.length > 1 ? 'set' : 'delete'](...arguments) : Promise.resolve()
			location.reload(arguments.length > 1 ? cookie.set(...arguments) : pq.is_string(name) && cookie.delete(name));
		},
		set(name, value = '', expire, path, domain, secure)
		{
			return cookieStore.set(...arguments);
			document.cookie = [`${pq.urlencode(name)}=${pq.urlencode(value)}`,
				pq.is_int(expire) ? `;expires=${pq.date_create(expire).toUTCString()}` : '',
				pq.is_string(path) ? `;path=${path}` : '',
				pq.is_string(domain) ? `;domain=${domain}` : '',
				secure ? ';secure' : ''].join('');
		},
		// set : (...params) => cookieStore.set(...params),
		// delete : name => cookieStore.delete(name),
		delete(name)
		{
			return cookieStore.delete(name);
			document.cookie = `${pq.urlencode(name)}=0;expires=0`;
		},
		get(name)
		{
			const find = ` ${pq.urlencode(name)}=`, cookie = ` ${document.cookie};`, offset = cookie.indexOf(find) + find.length;
			return offset > find.length ? pq.urldecode(cookie.substring(offset, cookie.indexOf(';', offset))) : '';
		},
		all()
		{
			return Object.fromEntries(cookie);
		},
		*[Symbol.iterator]()
		{
			for (let item of document.cookie.split(';'))
			{
				const offset = item.indexOf('=');
				yield [pq.urldecode(item.substring(0, offset).trimLeft()), pq.urldecode(item.substring(offset + 1))];
			}
		}
	};
	// ,
	// ajax = pq.mapping(()=> pq.http),
	// observers = new Map,
	// listeners = new Map,
	// events = new Map;

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
		static get(selector, context = document)
		{
			return new element(context.querySelector(selector));
		}
		static name(target)
		{
			switch (target.nodeType)
			{
				case 1:		return target.tagName.toLowerCase();
				case 2:		return `@${target.nodeName}`;
				case 7:		return `?${target.nodeName.toLowerCase()}`;
				default:	return target.nodeName;
			}
		}
		static create(tagname, attribute)
		{
			const node = new element(document.createElement(tagname));
			attribute && node.setattr(pq.is_string(attribute) ? {0: attribute} : attribute);
			return node;
		}
		static template(html)
		{
			const template = document.createElement('template');
			template.innerHTML = html;
			return new element(template.content);
		}
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
			return this.target.hasAttribute('id') ? this.target.id : this.target.id = `wkid${++element.id}`;
		}
		get connected()
		{
			return this.target.isConnected;
		}
		get text()
		{
			return this.target.textContent;
		}
		exists(callback)
		{
			this.target && callback(this);
		}
		clone()
		{
			return new element(this.target.cloneNode(true));
		}
		find(selector)
		{
			return element.get(selector, this.target);
		}
		setattr(name, value)
		{
			for (let [k, v] of pq.is_string(name) ? [[name, value]] : Object.entries(name))
			{
				k === '0' ? this.text(v) : pq.is_scalar(v)
					? this.target.setAttribute(k, v) : this.target.setAttributeNode(document.createAttribute(k));
			}
			return this;
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
		fetch(body)
		{
			let url;
			const options = {};
			if (this.name === 'form')
			{
				url = this.target.action;
				options.method = this.target.getAttribute('method');
				options.body = body || pq.is_string(body) ? body : new formdata(this.target);
			}
			else
			{
				url = this.target.href || this.target.dataset.action;
				options.method = this.target.dataset.method || (body || pq.is_string(body) ? 'POST' : 'GET');
				options.body = body;
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
		append(node, attribute)
		{
			if (pq.is_string(node))
			{
				node = element.create(node, attribute);
			}
			else
			{
				attribute && node.setattr(attribute);
			}
			this.target.appendChild(node.target);
			return node;
		}


		// disabled(on = true)
		// {

		// }
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
			this.#accept.on('click', () => this.close(null));
			this.#cancel.on('click', () => this.close(undefined));
			this.on('close', async event =>
			{
				
				//this.remove('*');
				if (valve === false && event.isTrusted)
				{
					//console.log(valve, event.isTrusted , '--------------\n')
					this.remove();
					await this.resolve(this.#retval);
					valve = true;
				}
				
				if (valve && this.target.open === false && this.length)
				{
					valve = false;
					const [scheme, params] = Array.prototype.shift.call(this);
					if (pq.is_function(scheme))
					{
						this.remove('*');
						//console.log('!!--------------\n')
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
		get retval()
		{
			return this.#retval;
		}
		draw(context)
		{
			pq.is_scalar(context.class) ? this.target.className = context.class : this.target.removeAttribute('class');
			pq.is_scalar(context.title) && this.append(this.#header.text(context.title));
			this.append(pq.is_scalar(context.content) ? this.#section.text(context.content) : this.#section.remove('*'));
			this.#footer.remove('*');
			pq.is_scalar(context.accept) && this.#footer.append(this.#accept.text(context.accept));
			pq.is_scalar(context.cancel) && this.#footer.append(this.#cancel.text(context.cancel));
			this.#footer.target.childElementCount && this.append(this.#footer);
			return this.#section;
		}
		open(scheme, ...params)
		{
			this.#retval = undefined;
			return pq.promise(resolve =>
			{
				Array.prototype.push.call(this, [scheme, params], resolve);
				this.event('close');
			});
		}
		async resolve(value)
		{
			return this.length && this[0].toString().includes('[native code]')
				? Array.prototype.shift.call(this)(value) : Promise.resolve(value);
		}
		close(value)
		{
			this.#retval = value;
			this.target.close();
			return this;
		}
	};
	class formdata extends FormData
	{
		// constructor(context)
		// {
		// 	pq.is_a(context, HTMLFormElement) ? super(context) : super();
		// 	if (pq.is_object(context))
		// 	{
		// 		for (let [name, value] of Object.entries(context))
		// 		{
		// 			this.append(name, value);
		// 			console.log(name, value)
		// 		}
		// 	}
		// }
	}

	class xhr extends XMLHttpRequest
	{
		// on(event, listener, upload = false)
		// {

		// }
		// evented(download, upload)
		// {
		// 	if (pq.is_entries(download))
		// 	{
		// 		for (let [type, listener] of Object.entries(download))
		// 		{
		// 			switch (type)
		// 			{
		// 				case 'onabort':
		// 				case 'onloadend':
		// 				case 'onloadstart':
		// 				case 'onprogress':
		// 				case 'onreadystatechange':
		// 				case 'ontimeout':
		// 					this[type] = listener;
		// 			}
		// 		}
		// 	}
		// 	if (pq.is_entries(upload))
		// 	{
		// 		for (let [type, listener] of Object.entries(upload))
		// 		{
		// 			switch (type)
		// 			{
		// 				case 'onabort':
		// 				case 'onerror':
		// 				case 'onload':
		// 				case 'onloadend':
		// 				case 'onloadstart':
		// 				case 'onprogress':
		// 				case 'ontimeout':
		// 					this.upload[type] = listener;
		// 			}
		// 		}
		// 	}
		// 	return this;
		// }
		accept(type)
		{
			this.responseType = type;
			return this;
		}
		request(method, url, body = null, type = null)
		{
			this.open(method, url, true);
			type && this.setRequestHeader('Content-Type', type);
			// if (pq.is_entries(body) && pq.is_formdata(body) === false)
			// {
			// 	this.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
			// 	body = pq.http_build_query(body);
			// }
			return pq.promise((resolve, reject) =>
			{
				this.onload = resolve;
				this.onerror = reject;
				this.send(body);
			}).then(event => event.target);
		}
	}

	class websocket extends WebSocket
	{
	}


	return new Proxy(Object.assign(Object.defineProperties(pq,
	{
		//href: {get() {return location.href;}, set(url) {location.href = url;}},
		xhr: {get() {return new xhr;}}
	}),
	{
		cookie,
		element,
		extend: 			(classname, prototype) => Object.assign({element, dialog, formdata, websocket}[classname].prototype, prototype),
		
		// clipboard:			navigator.clipboard,
		// session:			window.sessionStorage,
		// storage:			window.localStorage,
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
		setcookie: 			(...values) => !cookie.set(...values),
		deferred:			Object.assign((callback, delay = 0, ...params)=> setTimeout(callback, delay, ...params), {cancel(id) {clearTimeout(id);}}),
		interval:			Object.assign((callback, delay = 0, ...params)=> setInterval(callback, delay, ...params), {cancel(id) {clearInterval(id);}}),
		reload:				(forced = false)=> location.reload(forced),
		// pq.assign =				(url)=> location.assign(url);
		// pq.replace =			(url)=> location.replace(url);
		// openwindow:			(...params)=> window.open(...params),
		// notification:		(title, options)=> new Notification(title, options),
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