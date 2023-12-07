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
masker.json = (element, callback) => masker(...element instanceof HTMLFormElement
	? [element.action, {method: element.method.toUpperCase(), body: new FormData(element)}]
	: [element instanceof HTMLAnchorElement ? element.href : element]).then(response => response.json()).then(callback || (async data => {
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
masker.init(() => masker.json('?home/init', data =>
{
	requestAnimationFrame(function closed()
	{
		if (document.querySelector('iframe'))
		{
			requestAnimationFrame(closed);
		}
		else
		{
			data.popup && masker.dialog(data.popup.picture, data.popup.title, data.popup.support);
			data.notice && masker.dialog(data.notice.content, data.notice.title);
		}
	});
}));
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

masker.canplay = video =>
{
	console.log(video);
};
masker.shortchanged = videos =>
{
	const
		video = videos.active.video,
		div = videos.active.querySelector('div') || videos.active.appendChild(document.createElement('div')),
		strong = div.querySelector('strong') || div.appendChild(document.createElement('strong')),
		mark = div.querySelector('mark') || div.appendChild(document.createElement('mark'));


	video.removeAttribute('height');
	div.className = 'videoinfo';
	

	strong.textContent = videos.current.name;
	mark.innerHTML = '';
	for (const [taghash, tagname] of Object.entries(videos.current.tags))
	{
		const anchor = mark.appendChild(document.createElement('a'));
		anchor.href = `?home/asd,tag:${taghash}`;
		anchor.textContent = `#${tagname}`;
	}



	//div.textContent = '123123';


	//const strong = videos.active.querySelector('strong') || videos.active.appendChild(document.createElement('strong'));
	//strong.textContent = videos.current.name;

	console.log(videos.active, videos.current);
};
masker.confirm = (content, context) => new Promise((resolve, reject) => confirm(content) ? resolve(context) : reject(context));
masker.prompt = (title, value) => new Promise((resolve, reject) => (value = prompt(title, value)) === null ? reject() : resolve(value));
masker.clear = action => masker.confirm('清除后不可恢复！').then(() => masker.json(`?home/my-clear,action:${action}`));
masker.change = (element, field, value) => masker.prompt(title, value).then(value => {

	masker.json()

})
masker.favorite = anchor =>
{
	anchor.onclick = () => false;
	masker.json(anchor.href, data =>
	{
		if (data.result)
		{
			const childs = anchor.childNodes;
			if (data.result > 0)
			{
				childs[0].style = 'display:none';
				childs[1].style = 'display:block';
				childs[2].textContent = '已收藏';
			}
			else
			{
				childs[0].style = 'display:block';
				childs[1].style = 'display:none';
				childs[2].textContent = '收藏';
			}
		}
	}).finally(() => anchor.onclick = () => masker.favorite(anchor));
	return false;
}



masker.invite = anchor => masker.prompt(anchor).then(value => masker.json(anchor.href + value));

masker.nickname = anchor => {

	prompt('请输入花名：', anchor.textContent);
	return false;
}