/*
该库为PQ分离浏览器库，为了PQ核心函库数可以独立运行在其他JS平台上
请与PQ核心库一起使用，由ZERONETA编写PQ库的扩展在WA骨架上使用
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

	new window.MutationObserver((records)=>
	{
		for (let record of records)
		{
			let observer = observers.get(record.target);
			if (observer && (observer = observer.get(record.type)))
			{
				console.log(record)
			}
		}
	}).observe(document.documentElement, {characterData: true, childList: true, subtree: true});


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

	class ss extends CSSStyleSheet
	{
		constructor(data)
		{
			super();
			if (pq.is_string(data))
			{
				this.replaceSync(data);
			}
		}
		connect(vn)
		{
			vn.target.adoptedStyleSheets = [...vn.target.adoptedStyleSheets, this];
			return this;
		}
		disconnect(vn)
		{
			const stylesheets = vn.target.adoptedStyleSheets.slice(), offset = stylesheets.indexOf(this);
			if (offset !== -1)
			{
				stylesheets.splice(offset, 1);
				vn.target.adoptedStyleSheets = stylesheets;
			}
			return this;
		}
	}

	class vn
	{
		static id = 0;
		static get fragment(){return new vn(document.createDocumentFragment());}
		static cdata =		(data)=> new vn(document.createCDATASection(data));//HTML并不支持CDATA
		static comment =	(data)=> new vn(document.createComment(data));
		static element =	(tagname)=> new vn(document.createElement(tagname));
		static pi =			(p, i)=> new vn(document.createProcessingInstruction(p, i));
		static text =		(data = null)=> new vn(document.createTextNode(data));
		static get =		(syntax, context = document)=> new vn(context.querySelector(syntax));
		static all =		(syntax, context = document)=> Array.from(context.querySelectorAll(syntax), (element)=> new vn(element));
		static from =		(create, assign) => vn.fragment.from(create, assign);
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
		static walk =		(target, direction, nodename = '*', skip = 0)=>
		{
			let i = 0;
			while (target = target[direction])
			{
				if ((nodename === '*' || vn.name(target) === nodename) && skip === i++)
				{
					return new vn(target);
				}
			}
			return null;
		};
		static event =
		{
			prevent(event)
			{
				event.preventDefault();
			},
			stop(event)
			{
				event.stopPropagation();
			},
			exit(event)
			{
				event.isImmediatePropagationStopped = true;
				event.stopImmediatePropagation();
			}
		};
		static evented =	(event)=>
		{
			let evented = listeners.get(event.currentTarget);
			if (evented && (evented = evented.get(event.type)))
			{
				const target = new vn(event.currentTarget);
				events.set(event.currentTarget, event);
				for (let listener of evented)
				{
					if (listener(target) === false)
					{
						vn.event.prevent(event);
					}
					if (event.isImmediatePropagationStopped)
					{
						break;
					}
				}
				events.delete(event.currentTarget);
			}
		}
		static is(tagname, extend)
		{
			customElements.define(tagname, extend);
		}
		static dialog =		class extends vn
		{
			constructor(modal)
			{
				let valve = true;
				super(document.createElement('dialog'));
				this.on('close', async ()=>
				{
					if (valve === false && this.event('isTrusted'))
					{
						this.remove();
						await Array.prototype.shift.call(this)(this.target.returnValue);
						valve = true;
					}
					if (valve && this.target.open === false && this.length)
					{
						valve = false;
						const [scheme, params] = Array.prototype.shift.call(this);
						if (pq.is_function(scheme))
						{
							await scheme.apply(this, params);
						}
						if (document.body)
						{
							document.body.appendChild(this.target)[modal ? 'showModal': 'show']();
							this.target.returnValue = '';
						}
						else
						{
							valve = Array.prototype.shift.call(this);
						}
					}
				});
			}
			open(scheme, ...params)
			{
				return pq.promise((resolve)=>
				{
					Array.prototype.push.call(this, [scheme, params], resolve);
					this.evented('close');
				});
			}
			close(value)
			{
				this.target.close(value);
				return this;
			}
		};
		target = null;
		constructor(target = document)
		{
			this.target = target;
		}
		clone(deep = true)
		{
			return new vn(this.target.cloneNode(deep));
		}
		//创建节点
		cdata(data)
		{
			return this.insert(vn.cdata(data));
		}
		comment(data)
		{
			return this.insert(vn.comment(data));
		}
		element(tagname)
		{
			return this.insert(vn.element(tagname));
		}
		pi(p, i)
		{
			return this.insert(vn.pi(p, i));
		}
		text(data)
		{
			return this.insert(vn.text(data));
		}
		//移动操作
		insert(vn, position)
		{
			switch (position)
			{
				case 'after'://插入到当前节点之后
					this.target.parentNode.insertBefore(vn.target, this.target.nextSibling);
					break;
				case 'before'://插入到当前节点之前
					this.target.parentNode.insertBefore(vn.target, this.target);
					break;
				case 'first'://插入到当前节点下开头
					this.target.insertBefore(vn.target, this.target.firstChild);
					break;
				default://插入到当前节点下末尾
					this.target.appendChild(vn.target);
			}
			return vn;
		}
		moveto(node, position)
		{
			return (pq.is_string(node) ? vn.get(node) : node).insert(this, position);
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
						if (vn.name(target) === nodename)
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
		// append(...vn)
		// {
		// 	for (let item of vn)
		// 	{
		// 		this.target.appendChild(item.target);
		// 	}
		// 	return this;
		// }
		//查找操作
		parent(nodename, skip)
		{
			return vn.walk(this.target, 'parentNode', nodename.toUpperCase(), skip);
		}
		prev(nodename, skip)
		{
			return vn.walk(this.target, 'previousSibling', nodename, skip);
		}
		next(nodename, skip)
		{
			return vn.walk(this.target, 'nextSibling', nodename, skip);
		}
		child(nodename = '*', skip = 0)
		{
			let i = 0;
			for (let target of this.target.childNodes)
			{
				if ((nodename === '*' || vn.name(target) === nodename) && skip === i++)
				{
					return new vn(target);
				}
			}
			return null;
		}
		has(node)
		{
			return pq.is_string(node) ? this.target.querySelector(node) !== null : this.target.contains(node.target);
		}
		get(syntax)
		{
			return vn.get(syntax, this.target);
		}
		all(syntax)
		{
			return vn.all(syntax, this.target);
		}
		//属性操作
		attr(mixed, value)
		{
			if (pq.is_string(mixed))
			{
				if (value === undefined)
				{
					return this.target[mixed] === undefined ? null : this.target[mixed];
				}
				this.target.setAttribute(mixed, value);
			}
			else
			{
				if (pq.is_entries(mixed) === false)
				{
					return pq.array_column(this.target.attributes, 'value', 'name');
				}
				for (let [name, data] of Object.entries(mixed))
				{
					pq.is_defined(data)
						? this.target.setAttribute(name, data)
						: this.target.removeAttribute(name);
				}
			}
			return this;
		}
		data(mixed, value)
		{
			if (pq.is_string(mixed))
			{
				if (value === undefined)
				{
					return this.target.getAttribute(`data-${mixed}`);
				}
				this.target.setAttribute(`data-${mixed}`, value);
			}
			else
			{
				const dataset = this.target.dataset;
				if (pq.is_entries(mixed) === false)
				{
					return dataset;
				}
				for (let [name, data] of Object.entries(mixed))
				{
					dataset[name] = data;
				}
			}
			return this;
		}
		toggle(name = 'hidden')
		{
			return this.target.toggleAttribute(name);
		}
		hidden(value)
		{
			if (pq.is_bool(value))
			{
				this.target.hidden = value;
				return this;
			}
			return this.target.hidden;
		}
		checked(value)
		{
			if (pq.is_bool(value))
			{
				this.target.checked = value;
				return this;
			}
			return this.target.checked;
		}
		classed(name)
		{
			return this.target.classList.contains(name);
		}
		classes(...names)
		{
			this.target.classList.add(...names);
			return this;
		}
		removes(...names)
		{
			if (names.length)
			{
				this.target.classList.remove(...names);
			}
			else
			{
				this.target.className = '';
			}
			return this;
		}
		toggles(name, force)
		{
			this.target.classList.toggle(name, force);
			return this;
		}
		replaces(oldclass, newclass)
		{
			this.target.classList.replace(oldclass, newclass);
			return this;
		}
		style(data)
		{
			if (pq.is_entries(data))
			{
				const style = this.target.style;
				for (let [name, value] of Object.entries(data))
				{
					if (pq.is_scalar(value))
					{
						style[name] = value;
					}
					else
					{
						style.removeProperty(name.replace(/[A-Z]/, '-$&').toLowerCase());
					}
				}
				return this;
			}
			return getComputedStyle(this.target)[data];
		}
		visibility(value)
		{
			const style = this.target.style;
			if (pq.is_bool(value) ? value : style.getPropertyValue('visibility') !== 'hidden')
			{
				style.setProperty('visibility', 'hidden', 'important');
			}
			else
			{
				style.removeProperty('visibility');
			}
			return this;
		}
		never(value)
		{
			const style = this.target.style;
			if (pq.is_bool(value) ? value : style.getPropertyValue('pointer-events') !== 'none')
			{
				style.setProperty('pointer-events', 'none', 'important');
			}
			else
			{
				style.removeProperty('pointer-events');
			}
			return this;
		}
		//事件操作
		observe(type, handle)
		{
			
			return this;
		}
		event(property)
		{
			const event = events.get(this.target);
			if (pq.is_defined(property))
			{
				if (pq.is_function(vn.event[property]))
				{
					vn.event[property](event);
					return this;
				}
				return event[property];
			}
			return event;
		}
		on(type, listener)
		{
			if (pq.is_string(type) && pq.is_function(listener))
			{
				const events = listeners.get(this.target);
				if (events === undefined)
				{
					this.target[`on${type}`] = vn.evented;
					listeners.set(this.target, new Map([[type, new Set([listener])]]));
				}
				else
				{
					if (events.has(type) === false)
					{
						this.target[`on${type}`] = vn.evented;
						events.set(type, new Set([listener]));
					}
					else
					{
						events.get(type).add(listener);
					}
				}
			}
			return this;
		}
		off(type, listener)
		{
			let events;
			if (events = listeners.get(this.target))
			{
				if (pq.is_string(type))
				{
					if (events = events.get(type))
					{
						if (pq.is_function(listener))
						{
							events.delete(listener);
						}
						if (events.size === 0)
						{
							this.target[`on${type}`] = null;
							listeners.get(this.target).delete(type);
						}
					}
				}
				else
				{
					for (let event of listeners.get(this.target).keys())
					{
						this.target[`on${event}`] = null;
					}
					listeners.delete(this.target);
				}
			}
			return this;
		}
		once(type, listener)
		{
			const callback = ()=> listener(this.off(type, callback));
			return this.on(type, callback);
		}
		evented(type, bubbles, cancelable, composed)
		{
			return this.target.dispatchEvent(new Event(type, {bubbles, cancelable, composed}));
		}
		ondrop(callback)
		{
			this.target.ondragover = vn.event.prevent;
			return this.on('drop', callback);
		}
		//一些经常创建的元素
		a(name, href = 'javascript:;')
		{
			return this.element('a').content(name).attr(pq.is_string(href) ? {href} : Object.assign({href: 'javascript:;'}, href));
		}
		input(type = 'text')
		{
			return this.element('input').attr(pq.is_string(type) ? {type} : Object.assign({type: 'text'}, type));
		}
		button(name, type = 'button')
		{
			return this.element('button').content(name).attr(pq.is_string(type) ? {type} : Object.assign({type: 'text'}, type));
		}
		//万恶的根源
		content(data)
		{
			if (data === undefined)
			{
				return this.target.textContent;
			}
			this.target.textContent = data;
			return this;
		}
		html(data)
		{
			if (data === undefined)
			{
				return this.target.innerHTML;
			}
			this.target.innerHTML = data
			return this;
		}
		value(data)
		{
			if (data === undefined)
			{
				return this.target.value;
			}
			this.target.value = data
			return this;
		}
		//我们终于度过上面3个函数的调用危险期了不是么
		call(scheme, ...params)
		{
			return scheme.call(this, this, ...params);
		}
		from(create, assign)
		{
			for (let node of create)
			{
				if (pq.is_string(node))
				{
					this.text(node);
					continue;
				}
				const [tagname, mixed, ...params] = node, element = this.element(tagname);
				// if (pq.is_function(mixed))
				// {
				// 	mixed(element);
				// 	element.from(params, assign);
				// 	continue;
				// }
				if (pq.is_entries(mixed) && pq.is_array(mixed) === false)
				{
					for (let [name, value] of Object.entries(mixed))
					{
						switch (name)
						{
							case 'class':
								element.target.className = value;
								break;
							case 'style':
								if (pq.is_string(value))
								{
									element.target.style.cssText = value;
									break;
								}
								element.style(value);
								break;
							case 'id':
								if (pq.is_defined(assign))
								{
									assign[value] = element;
									break;
								}
							default:
								if (name.substring(0, 2) === 'on')
								{
									element.on(name.substring(2), value);
									break;
								}
								element.target.setAttribute(name, value);
						}
					}
					element.from(params, assign);
					continue;
				}
				element.from([mixed, ...params], assign);
			}
			return this;
		}
		request(method, url, data)
		{
			const ajax = this.ajax;
			ajax.onabort = ()=>
			{
				ajax.onabort = null;
				this.never(false);
			};
			this.never(true);
			return ajax.request(method, url, data).finally(ajax.onabort).then(()=> this);
		}
		send(data)
		{
			let method = this.target.dataset.method, url;
			switch (this.name)
			{
				case 'A':
					url = this.target.href;
					break;
				case 'INPUT':
					if (data === undefined)
					{
						data = this.target.value;
					}
				case 'BUTTON':
					method = this.target.formMethod;
					url = this.target.formAction;
					break;
				case 'FORM':
					method = this.target.method;
					url = this.target.action;
					if (data === undefined)
					{
						data = new FormData(this.target);
					}
					break;
				case 'SELECT':
				case 'TEXTAREA':
					if (data === undefined)
					{
						data = this.target.value;
					}
				default:
					url = this.target.dataset.url;
			}
			if (method === undefined)
			{
				method = 'GET';
			}
			return this.request(method, url, data);
		}
		get response()
		{
			return this.ajax.response;
		}
		get ajax()
		{
			return ajax.pull(this.target);
		}
		get name()
		{
			return vn.name(this.target);
		}
		get rect()
		{
			return this.target.getBoundingClientRect();
		}
		get connected()
		{
			return this.target.isConnected;
		}
		get id()
		{
			if (this.target.hasAttribute('id') === false)
			{
				this.target.id = `idv${++vn.id}`;
			}
			return this.target.id;
		}
		get shadow()
		{
			return new vn(this.target.shadowRoot || this.target.attachShadow({mode: 'open'}));
		}
		get [Symbol.toStringTag]()
		{
			return pq.gettype(this.target);
		}
		*[Symbol.iterator]()
		{
			for (let target of Array.from(this.target.childNodes))
			{
				yield new vn(target);
			}
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
		ss,
		vn,
		get:				vn.get,
		all:				vn.all,
		stylesheet:			(data)=> new ss(data),
		clipboard:			navigator.clipboard,
		session:			window.sessionStorage,
		storage:			window.localStorage,
		// utf8_decode:		(data)=> new TextDecoder('utf-8').decode(Uint8Array.from(data, (byte)=>byte.codePointAt(0))),
		// utf8_encode:		(data)=> fromCodePoint(...new TextEncoder().encode(data)),
		// base64_decode:		(data)=> pq.utf8_decode(atob(data)),
		// base64_encode:		(data)=> btoa(pq.utf8_encode(data)),
		is_vn:				(object)=> pq.is_a(object, vn),
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
		}).then((event)=> new vn(event.target)),
		websocket:			(url)=> pq.promise((resolve, reject)=>
		{
			const ws = new websocket(url);
			ws.onopen = resolve;
			ws.onerror = reject;
		}).then((event)=> event.target),
		cd:					[listeners, events, ajax]//缓存的数据，只供观察
	}),
	{
		apply(target, ...[, [any]])
		{
			return target.is_string(any) ? vn.get(any) : new vn(any);
		}
	});
});