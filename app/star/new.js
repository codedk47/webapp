const
    promise = new Promise(resolve => addEventListener('DOMContentLoaded', event => resolve())),
    masker = resource => fetch(resource);
masker.then = callback => promise.then(callback);


masker.urlencode = (data) => encodeURIComponent(data).replace(/%20|[\!'\(\)\*\+\/@~]/g, (escape)=> ({
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
masker.assign = (p, a) => location.assign(((o,q)=>Object.keys(o).reduce((p,k)=>o[k]===null?p:`${p},${k}:${o[k]}`,q))
	(...typeof(p)==="string"?[{},p]:[
		Object.assign(Object.fromEntries(Array.from(location.search.matchAll(/\,(\w+)(?:\:([\%\+\-\.\/\=\w]*))?/g)).map(v=>v.slice(1))),p),
		a||location.search.replace(/\,.+/,"")]));
masker.viewport = (function()
{
	const observes = new Map, viewport = new IntersectionObserver(entries =>
	{
		entries.forEach(entry =>
		{
			if (entry.isIntersecting && observes.has(entry.target))
			{
				viewport.unobserve(entry.target);
				observes.get(entry.target).resolve(entry.target);
				observes.delete(entry.target);
			}
		});
	});
	return async element =>
	{
		const pending = observes.get(element) || {};
		return pending.promise || (observes.promise = new Promise(resolve =>
		{
			pending.resolve = resolve;
			observes.set(element, pending);
			viewport.observe(element);
		}));
	};
}());
masker.dialog = (context, title, url) => new Promise(resolve =>
{
	const dialog = document.createElement('dialog');
	if (context instanceof HTMLElement)
	{
		dialog.appendChild(context);
	}
	else
	{
		dialog.appendChild(document.createElement('strong')).textContent = title;
		if (typeof url === 'string')
		{
			const anchor = document.createElement('a'), image = anchor.appendChild(new Image);
			anchor.onclick = () => location.href = anchor.href;
			anchor.href = url;
			image.fetchpriority = 'high';
			image.src = context;
			dialog.appendChild(anchor);
		}
		else
		{
			dialog.appendChild(document.createElement('pre')).textContent = context;
		}
		const strong = document.createElement('strong');
		strong.textContent = '关 闭';
		strong.onclick = () => resolve(document.body.removeChild(dialog));
		dialog.appendChild(strong);
		dialog.onclick = event => event.target === dialog && strong.onclick();
	}
	document.body.appendChild(dialog).showModal();
});
masker.json = (context, callback) => masker(...Array.isArray(context) ? context : (context instanceof HTMLFormElement
	? [context.action, {method: context.method.toUpperCase(), body: new FormData(context)}]
	: [context instanceof HTMLAnchorElement ? context.href : context])).then(response => response.json()).then(callback || (async data => {
	data.hasOwnProperty('debug') && console.log(data);
	if (Array.isArray(data.errors) && data.errors.length)
	{
		await masker.dialog(data.errors.join('\n'), '错误信息');
	}
	if (data.hasOwnProperty('dialog'))
	{
		await masker.dialog(data.dialog, '对话框');
	}
	if (typeof data.goto === 'string')
	{
		location.href = data.goto;
	}
	if (typeof data.reload === 'number')
	{
		setTimeout(() => location.reload(), data.reload * 1000);
	}
}));
masker.submit = form =>
{
	if (form.style.pointerEvents !== 'none')
	{
		form.style.pointerEvents = 'none';
		masker.json(form).finally(() => form.style.pointerEvents = null);
	}
	return false;
};
masker.then(() =>
{
	const videos = document.querySelector('webapp-videos');
	if (videos)
	{
		const btn = document.querySelector('div.float');
		btn.firstChild.onclick = () => videos.slide(true);
		btn.lastChild.onclick = () => videos.slide(false);

		document.onwheel = document.onkeydown = event =>
		{
			if (event.deltaY || event.key === 'ArrowUp' || event.key === 'ArrowDown')
			{
				videos.slide(event.deltaY ? event.deltaY < 0 : event.key === 'ArrowUp');
			}
		};
	}

	document.querySelectorAll('blockquote[data-lazy]').forEach(element => masker.viewport(element).then(function lazy(element)
	{
		fetch(`${element.dataset.lazy}${element.dataset.page++}`).then(response => response.text()).then(content =>
		{
			if (content.startsWith('<'))
			{
				const template = document.createElement('template');
				template.innerHTML = content;
				element.previousElementSibling.appendChild(template.content);
				masker.viewport(element).then(lazy);
			}
			else
			{
				element.textContent = '没有更多的内容了';
			}
		});
	}));


});
// masker.init(() => masker.json('?home/init', data =>
// {
// 	requestAnimationFrame(function closed()
// 	{
// 		if (document.querySelector('iframe'))
// 		{
// 			requestAnimationFrame(closed);
// 		}
// 		else
// 		{
// 			data.popup && masker.dialog(data.popup.picture, data.popup.title, data.popup.support);
// 			data.notice && masker.dialog(data.notice.content, data.notice.title);
// 		}
// 	});
// }));
masker.splashscreen = () =>
{
	const header = document.querySelector('header'), duration = document.body.dataset.duration * 1;
	document.onclick = () => location.href = document.body.dataset.support;
	setTimeout(function timer(duration)
	{
		if (duration)
		{
			header.textContent = `${String(--duration).padStart(2, 0)} s`;
			setTimeout(timer, 1000, duration);
		}
		else
		{
			header.textContent = 'Skip';
			header.onclick = () => postMessage('close');
			document.body.dataset.autoskip && header.onclick();
		}
	}, 1000, duration);
};
masker.revert_account = input =>
{
	if (input.files.length)
	{
		const codeReader = new ZXingBrowser.BrowserQRCodeReader();
		codeReader.decodeFromImageUrl(URL.createObjectURL(input.files[0])).then(result =>
		{
			masker.authorization(result.text).then(() => location.replace(location.href));
		});
	}
};
masker.create_account = form =>
{
	if (form.style.pointerEvents !== 'none')
	{
		form.style.pointerEvents = 'none';
		masker.json(form, data => data.token
			? masker.authorization(data.token).then(() => location.replace(location.href))
			: alert('服务器繁忙，请稍后重试！')).finally(() => form.style.pointerEvents = null);
	}
	return false;
};
masker.delete_account = anchor =>
{
	masker.authorization(null).then(() => location.href = anchor.href);
	return false;
};
masker.log = (anchor, callback) =>
{
	anchor.onclick = () => false;
	masker.json([anchor.href, {method: 'POST', body: anchor.dataset.body}], data =>
	{
		if (data.result)
		{
			const childs = anchor.childNodes;
			[childs[0].style.display, childs[1].style.display] = [childs[1].style.display, childs[0].style.display];
			if (anchor.dataset.toggle)
			{
				[anchor.dataset.value, anchor.dataset.toggle] = [childs[2].textContent = anchor.dataset.toggle, anchor.dataset.value];
			}
			callback && callback();
		}
	}).finally(() => anchor.onclick = () => masker.log(anchor, callback));
	return false;
};
masker.lognews = anchor =>
{
	masker.json(typeof anchor === 'string' ? anchor : anchor.href, data => location.href = data.support);
	return false;
};
masker.selectorigin = select => select.value && location.reload(sessionStorage.setItem('origin', select.value));
masker.canplay = video =>
{
	const selectorigin = document.querySelector('div.useraction>select');
	if (selectorigin)
	{
		selectorigin.firstChild.value || selectorigin.removeChild(selectorigin.firstChild);
		selectorigin.value = sessionStorage.getItem('origin');
	}
	video.firstElementChild.style.objectFit = 'contain';
};
masker.shortchanged = videos =>
{
	videos.current.watched || masker.json(['?home/log,type:watch', {method: 'POST', body: videos.current.hash}]);
	if (videos.active.querySelector('div.videoinfo') === null)
	{
		videos.active.appendChild(videos.querySelector('template').content.cloneNode(true));
		//videos.active.querySelectorAll('div.videolink>a').forEach(element => element.onclick = () => masker.log(element));
		//videos.active.video.removeAttribute('height');
		//videos.active.firstElementChild.removeAttribute('controls');
		// videos.active.firstElementChild.controlslist = 'nodownload nofullscreen';
		// videos.active.firstElementChild.disablepictureinpicture = true;
	}

	const videoinfo = videos.active.querySelector('div.videoinfo');
	videoinfo.innerHTML = videos.current.name;
	for (const [taghash, tagname] of Object.entries(videos.current.tags))
	{
		const anchor = videoinfo.appendChild(document.createElement('a'));
		//anchor.href = `?home/asd,tag:${taghash}`;
		anchor.textContent = `#${tagname}`;
	}
	videos.active.querySelector('div.videolink>img').src = videos.current.poster;
	videos.active.querySelectorAll('div.videolink>a').forEach(element =>
	{
		element.dataset.body = videos.current.hash;
		element.onclick = () => masker.log(element, () => videos.current[element.dataset.log] = !videos.current[element.dataset.log]);
		if (videos.current[element.dataset.log])
		{
			element.childNodes[0].style.display = 'none';
			element.childNodes[1].style.display = 'block';
		}
		else
		{
			element.childNodes[0].style.display = 'block';
			element.childNodes[1].style.display = 'none';
		}
	});
};
masker.confirm = (content, context) => new Promise((resolve, reject) => confirm(content) ? resolve(context) : reject(context));
masker.prompt = (title, value) => new Promise((resolve, reject) => (value = prompt(title, value)) === null ? reject() : resolve(value));
masker.clear = action => masker.confirm('清除后不可恢复！').then(() => masker.json(`?home/my-clear,action:${action}`));
masker.change = (element, field, value) => masker.prompt(title, value).then(value => {

	masker.json()

})

// masker.favorite = anchor =>
// {
// 	anchor.onclick = () => false;
// 	masker.json(`${anchor.href},hash:${anchor.dataset.hash}`, data =>
// 	{
// 		if (data.result)
// 		{
// 			const childs = anchor.childNodes;
// 			if (data.result > 0)
// 			{
// 				childs[0].style = 'display:none';
// 				childs[1].style = 'display:block';
// 				if (childs[2])
// 				{
// 					childs[2].textContent = '已收藏';
// 				}
// 			}
// 			else
// 			{
// 				childs[0].style = 'display:block';
// 				childs[1].style = 'display:none';
// 				if (childs[2])
// 				{
// 					childs[2].textContent = '收藏';
// 				}
// 			}
// 		}
// 	}).finally(() => anchor.onclick = () => masker.favorite(anchor));
// 	return false;
// }




masker.nickname = anchor => {

	prompt('请输入花名：', anchor.textContent);
	return false;
}


masker.signup = form =>
{
	const body = new FormData(form), fieldset = form.querySelectorAll('fieldset');
	form.style.pointerEvents = 'none';
	fieldset.forEach(field => field.disabled = true);
	fetch(form.action, {method: form.method, body}).then(r => r.text()).then(d =>
	{
		alert(d);
	}).finally(() =>
	{
		fieldset.forEach(field => field.disabled = false);
		form.style.pointerEvents = null;
	});
	return false;
};
masker.signin = form =>
{
	localStorage.setItem('user', form['signature'].value);
	document.cookie = `user=${form['signature'].value}`;
	location.href = '?new/my';
	return false;
};