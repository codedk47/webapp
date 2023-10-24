//document.head.appendChild(document.createElement('script')).src = '/webapp/res/js/hls.min.js';
document.head.appendChild(document.createElement('style')).textContent = `
webapp-video{
	display: block;
	position: relative;
}
webapp-video>div.control{

	
	position: absolute;
	left: 0;
	right: 0;
	bottom: 0;
}

webapp-video>div.control>input[type=range]{
	width: 100%;
}
webapp-video>div.control>div{
	display: flex;
	background: red;
	height: 1rem;
}



webapp-video>video{
	display: block;
	object-fit: cover;
}
webapp-videos{
	display: block;
	height: 300px;
	position: relative;
	overflow: hidden;
	border: 1px red solid;
}
webapp-videos>div{
	width: 100%;
	height: 100%;
	position: absolute;
}
webapp-videos>div>webapp-video{
	height: 100%;
}

`;
customElements.define('webapp-video', class extends HTMLElement
{
	#video = document.createElement('video');
	// #controls = document.createElement('div');
	// #progress = document.createElement('input');
	// #functions = document.createElement('div');

	
	#playm3u8;
	constructor()
	{
		super();
		this.#video.setAttribute('width', '100%');
		this.#video.setAttribute('height', '100%');
		this.#video.setAttribute('playsinline', true);
		this.#video.setAttribute('disablepictureinpicture', true);
		this.#video.textContent = `Sorry, your browser doesn't support embedded videos.`;
		this.#video.controlsList = 'nodownload';

		// const progress = this.#controls.appendChild(this.#progress);
		// progress.type = 'range';
		// progress.max = 1;
		// progress.step = 0.0001;
		// progress.value = 0;

		// progress.onmousedown = progress.ontouchstart = () => this.pause();
		// progress.addEventListener('change', () =>
		// {
		// 	this.#video.currentTime = this.#video.duration * progress.value;
		// 	this.paused && this.play();
		// });

		// this.#video.addEventListener('timeupdate', () =>
		// {
		// 	progress.value = this.#video.currentTime / this.#video.duration
		// });

		// const functions = this.#controls.appendChild(this.#functions);
		

		this.#video.addEventListener('canplay', event =>
		{
			this.#video.setAttribute('height', this.hasAttribute('autoheight')
				? Math.trunc(event.target.videoHeight * this.scalewidth)
				: '100%');
		});

		if (globalThis.MediaSource && globalThis.Hls)
		{
			const hls = new globalThis.Hls;
			hls.attachMedia(this.#video);
			this.#playm3u8 = data => hls.loadSource(Array.isArray(data)
				? URL.createObjectURL(new Blob(data, {type: 'application/x-mpegURL'})) : data);
		}
		else
		{
			this.#playm3u8 = this.#video.canPlayType('application/vnd.apple.mpegurl') || this.#video.canPlayType('application/x-mpegURL')
				? data => this.#video.src = Array.isArray(data) ? `data:application/vnd.apple.mpegurl;base64,${btoa(data.join('\n'))}` : data
				: data => console.error(data);
		}
	}
	get paused()
	{
		return this.#video.paused;
	}
	get width()
	{
		return this.#video.videoWidth;
	}
	get height()
	{
		return this.#video.videoHeight;
	}
	get scalewidth()
	{
		return this.offsetWidth / this.width;
	}
	get horizontal()
	{
		return this.width > this.height;
	}
	play()
	{
		this.#video.play();
	}
	pause()
	{
		this.#video.pause();
	}
	pp()
	{
		this.paused ? this.play() : this.pause();
	}
	poster(resource)
	{
		this.#video.poster = resource;
		return this;
	}
	on(event, callback)
	{
		this.#video[`on${event}`] = callback;
	}
	m3u8(resource, preview)
	{
		return new Promise(async resolve =>
		{
			this.#video.addEventListener('canplay', () => resolve(this), {once: true});
			if (resource.startsWith('?/') || preview)
			{
				const
				[previewstart, previewend] = Number.isInteger(preview *= 1)
					? [preview >> 16 & 0xffff, (preview >> 16 & 0xffff) + (preview & 0xffff)]
					: [0, 0xffff],
				response = await fetch(resource),
				basepath = response.url.split('/').slice(0, -1).join('/'),
				rowdata = (await response.text()).match(/#[^#]+/g),
				buffer = [];
				for (let duration = 0, i = 0; i < rowdata.length; ++i)
				{
					if (rowdata[i].startsWith('#EXTINF'))
					{
						if (duration > -1)
						{
							const pattern = rowdata[i].match(/#EXTINF:(\d+(?:\.\d+)?)\,\s*([^#]+)/);
							duration += parseFloat(pattern[1]);
							if (duration > previewstart)
							{
								buffer[buffer.length] = /^(?!https?:\/\/)/i.test(pattern[2])
									? pattern[0].replace(pattern[2], `${basepath}/${pattern[2]}`) : pattern[0];
								if (duration > previewend)
								{
									duration = -1;
								}
							}
						}
					}
					else
					{
						buffer[buffer.length] = rowdata[i].startsWith('#EXT-X-KEY')
							? rowdata[i].replace(/URI="([^"]+)"/, `URI="${basepath}/$1"`)
							: rowdata[i];
					}
				}
				this.#playm3u8(buffer);
			}
			else
			{
				this.#playm3u8(resource);
			}
		});
	}
	connectedCallback()
	{
		this.#video.loop = this.hasAttribute('loop');
		this.#video.muted = this.hasAttribute('muted');
		this.#video.autoplay = this.hasAttribute('autoplay');
		this.#video.controls = this.hasAttribute('controls');
		this.appendChild(this.#video);

		
		if ('poster' in this.dataset)
		{
			this.poster(this.dataset.poster);
			delete this.dataset.poster;
		}
		if ('m3u8' in this.dataset)
		{
			this.m3u8(this.dataset.m3u8, this.dataset.preview).then(window[this.dataset.canplay]);
			delete this.dataset.m3u8;
		}
	}
});
customElements.define('webapp-videos', class extends HTMLElement
{
	#slide = document.createElement('div');
	#videos = [
		document.createElement('webapp-video'),
		document.createElement('webapp-video'),
		document.createElement('webapp-video')
	];
	#contents = [];
	#slider;
	#active = null;
	#passive = null;
	#current = null;
	#before = null;
	#index = -1;
	#events = {change: Boolean}

	#fetch = true;
	constructor()
	{
		super();
		let transitioning = false, currentindex = 0, beforeindex = 0;
		const position = {
			offsettop: 0,
			offset: 0,
			start: 0,
			move: 0
		}
		this.#slide.style.top = '0px';
		this.#slide.addEventListener('touchstart', event =>
		{
			//console.log('touchstart');
			if (transitioning) return;
			position.offsettop = this.#slide.style.top;
			position.offset = this.#slide.offsetTop;
			position.start = event.clientY || event.touches[0].clientY;
		});
		this.#slide.addEventListener('touchmove', event =>
		{
			//console.log('touchmove');
			if (transitioning) return;
			event.preventDefault();
			position.move = event.clientY || event.touches[0].clientY;
			this.#slide.style.top = `${position.offset + position.move - position.start}px`;
		});
		this.#slide.addEventListener('touchend', this.#slider = offset =>
		{
			//console.log('touchend');
			if (transitioning) return;
			let [top, direction, clicked] = typeof offset === 'boolean'
				? [this.#slide.style.top, offset ? -101 : 101, false]
				: [position.offsettop, position.start - position.move, this.#slide.style.top === position.offsettop];
			if (clicked)
			{
				console.log('clicked');
				if (this.#active)
				{
					this.#active.pp();
				}
				return;
			}
			if (Math.abs(direction) > 100)
			{
				beforeindex = currentindex;
				if (direction < 0)
				{
					if (--currentindex < 0)
					{
						currentindex = beforeindex;
					}
					else
					{
						this.#index = currentindex;
						this.#before = this.#contents[beforeindex];
						this.#current = this.#contents[currentindex];
						[top, this.#active, this.#passive] = top === '-200%'
							? ['-100%', this.#slide.childNodes[1], this.#slide.childNodes[2]]
							: ['0px', this.#slide.childNodes[0], this.#slide.childNodes[1]];
					}
				}
				else
				{
					this.#contents.length - currentindex < 4 && this.fetch();
					if (++currentindex < this.#contents.length)
					{
						this.#index = currentindex;
						this.#before = this.#contents[beforeindex];
						this.#current = this.#contents[currentindex];
						[top, this.#active, this.#passive] = top === '0px'
							? ['-100%', this.#slide.childNodes[1], this.#slide.childNodes[0]]
							: ['-200%', this.#slide.childNodes[2], this.#slide.childNodes[1]];
					}
					else
					{
						currentindex = beforeindex;
					}
				}
			}
			if (this.#slide.style.top !== top)
			{
				transitioning = true;
				this.#slide.style.transition = 'top .4s ease';
				this.#slide.style.top = top;
			}
		});
		this.#slide.addEventListener('transitionend', () =>
		{
			this.#slide.style.transition = null;
			if (currentindex !== beforeindex)
			{
				this.#passive.pause();
				this.#active.play();
				this.#change();
				if (currentindex < beforeindex)
				{
					if (currentindex && this.#contents.length - beforeindex > 1)
					{
						this.#slide.style.top = '-100%';
						this.#setvideo(this.#slide.insertBefore(this.#slide.lastElementChild, this.#slide.firstElementChild), this.#contents[currentindex - 1]);
					}
				}
				else
				{
					if (currentindex > 1 && this.#contents.length - currentindex > 1)
					{
						this.#slide.style.top = '-100%';
						this.#setvideo(this.#slide.appendChild(this.#slide.firstElementChild), this.#contents[currentindex + 1]);
					}
				}
			}
			transitioning = false;
		});
	}
	get active(){return this.#active;}
	get passive(){return this.#passive;}
	get current(){return this.#current;}
	get before(){return this.#before;}
	get index(){return this.#index;}
	#change()
	{
		this.#events.change(this);
		// console.log({
		// 	active: this.active,
		// 	current: this.current,
		// 	passive: this.passive,
		// 	before: this.before,
		// 	index: this.index
		// });
	}
	#setvideo(video, content)
	{
		if ('poster' in video)
		{
			video.poster(content.poster);
		}
		if ('m3u8' in content)
		{
			content.canplay = video.m3u8(content.m3u8, content.preview);
		}
	}
	fetch()
	{
		if ('fetch' in this.dataset && this.#fetch)
		{
			this.#fetch = false;
			//console.log('fetching...');
			fetch(`${this.dataset.fetch}${'page' in this.dataset ? this.dataset.page++ : ''}`).then(response => response.json()).then(data => data.forEach(video =>
			{
				if (this.#contents.length < 3)
				{
					this.#setvideo(this.#slide.appendChild(this.#videos[this.#contents.length]), video);
					if (this.#contents.length === 0)
					{
						this.#active = this.#videos[0];
						this.#active.play();
						this.#current = video;
						this.#index = 0;
						this.#change();
					}
				}
				this.#contents[this.#contents.length] = video;
			})).then(() => this.#fetch = true);
		}
	}
	slide(upordown)
	{
		this.#slider(Boolean(upordown));
	}
	connectedCallback()
	{
		this.#videos.forEach(video =>
		{
			video.setAttributeNode(document.createAttribute('loop'));
			//video.setAttributeNode(document.createAttribute('autoplay'));
			video.setAttributeNode(document.createAttribute('controls'));

			// this.hasAttribute('loop') && video.setAttributeNode(document.createAttribute('loop'));
			// this.hasAttribute('muted') && video.setAttributeNode(document.createAttribute('muted'));
			// this.hasAttribute('autoplay') && video.setAttributeNode(document.createAttribute('autoplay'));
			// this.hasAttribute('controls') && video.setAttributeNode(document.createAttribute('controls'));

		});
		if ('change' in this.dataset && this.dataset.change in window)
		{
			this.#events.change = window[this.dataset.change];
		}
		
		
		this.appendChild(this.#slide);
		this.fetch();
	}
});
function vtest(v)
{
	console.log(v.active, v.current);
	v.current.canplay.then(t => console.log(t));
}