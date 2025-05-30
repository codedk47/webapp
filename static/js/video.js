//document.head.appendChild(document.createElement('script')).src = '/webapp/static/js/hls.min.js';
document.head.appendChild(document.createElement('style')).textContent = `
webapp-video{
	display: block;
	position: relative;
}
webapp-video>video{
	display: block;
	object-fit: cover;
}
webapp-video>a{
	position: absolute;
	inset: 0;
	background-color: black;
}
webapp-video>a>img{
	width: 100%;
	height: 100%;
	object-fit: cover;
}
webapp-video>mark{
	position: absolute;
	right: .4rem;
	bottom: .4rem;
	border: 1px solid white;
	color: white;
	background-color: rgba(0, 0, 0, .8);
	font-family: consolas, monospace;
	padding: .4rem 1rem;
	border-radius: .4rem;
}
/*
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
*/
webapp-videos{
	display: block;
	position: relative;
	overflow: hidden;
}
webapp-videos>div{
	width: 100%;
	height: 100%;
	position: absolute;
}
webapp-videos>div>webapp-video{
	height: 100%;
}`;
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
		this.#video.addEventListener('loadedmetadata', event =>
		{
			this.onresize && this.onresize(event);
			// const autoheight = event =>
			// {
			// 	this.#video.setAttribute('height', this.hasAttribute('autoheight') ? Math.trunc(this.height * this.scalewidth) : '100%');
			// 	this.onresize && this.onresize(event);
			// }
			// autoheight(event);
			// addEventListener('resize', autoheight)
		});
		this.#video.addEventListener('loadeddata', event => new Promise(resolve =>
			this.#video.paused ? this.#video.onplay = resolve : resolve(event)).then(event => {
			this.dataset.log && this.dataset.key && navigator.sendBeacon(this.dataset.log, this.dataset.key);
			this.watch && this.watch(event);
		}));

		this.#video.addEventListener('canplay', event => this.oncanplay && this.oncanplay(event));

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
				? data => this.#video.src = Array.isArray(data) ? `data:application/vnd.apple.mpegurl;base64,${btoa(data.join(''))}` : data
				: data => console.error(data);
		}
	}
	get video()
	{
		return this.#video;
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
	m3u8(resource, duration)
	{
		if (duration)
		{
			fetch(resource).then(response => response.text()).then(data =>
			{
				const basepath = resource.split('/').slice(0, -1).join('/'), buffer = data.match(/#[^#]+/g);
				for (let i = 0, t = 0; i < buffer.length; ++i)
				{
					switch (true)
					{
						case buffer[i].startsWith('#EXT-X-KEY'):
							buffer[i] = buffer[i].replace(/URI="((?!https?\:)[^"]+)"/i, `URI="${basepath}/$1"`);
							break;
						case buffer[i].startsWith('#EXTINF'):
							let m = buffer[i].match(/(#EXTINF:(\d+(?:\.\d+)?)\,\s*)([^\s]+\s)/);
							if (m)
							{
								t += parseFloat(m[2]);
								if (/^(?!https?\:)/i.test(m[3]))
								{
									buffer[i] = `${m[1]}${basepath}/${m[3]}`;
								}
							}
					}
					if (t > duration)
					{
						buffer.splice(i, buffer.length - i - 1);
						break;
					}
				}
				this.#playm3u8(buffer);
			});
		}
		else
		{
			this.#playm3u8(resource);
		}
	}
	splashscreen(element)
	{
		if (this.querySelector('mark')) return;
		const skip = this.appendChild(document.createElement('mark')), timer = () =>
		{
			if (element.dataset.duration > 0)
			{
				skip.textContent = `${element.dataset.duration--} ${element.dataset.unit}`;
				setTimeout(timer, 1000);
			}
			else
			{
				skip.textContent = element.dataset.skip || 'Skip';
				skip.onclick = () =>
				{
					while (this.lastChild !== this.#video)
					{
						this.removeChild(this.lastChild);
					}
					this.play();
				};
			}
		};
		timer();
	}
	connectedCallback()
	{
		if (this.#video.isConnected) return;
		if ('poster' in this.dataset)
		{
			this.poster(this.dataset.poster);
			delete this.dataset.poster;
		}
		if ('m3u8' in this.dataset)
		{
			this.m3u8(this.dataset.m3u8, this.dataset.duration);
			delete this.dataset.m3u8;
		}
		this.#video.loop = this.hasAttribute('loop');
		this.#video.muted = this.hasAttribute('muted');
		this.#video.autoplay = this.hasAttribute('autoplay');
		this.#video.controls = this.hasAttribute('controls');
		this.appendChild(this.#video);
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
	#fetch = true;
	#page = -1;
	constructor()
	{
		super();
		let transitioning = false, currentindex = 0, beforeindex = 0;
		const position = {
			offset: 0,
			start: 0,
			move: 0,
			top: 0
		}
		this.#slide.style.top = '0px';
		this.#slide.addEventListener('touchstart', event =>
		{
			//console.log('touchstart');
			if (transitioning) return;
			position.offset = this.#slide.offsetTop;
			position.start = event.clientY || event.touches[0].clientY;
			position.top = this.#slide.style.top;
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
			if (transitioning) return;
			let [top, direction, clicked] = typeof offset === 'boolean'
				? [this.#slide.style.top, offset ? -101 : 101, false]
				: [position.top, position.start - position.move, this.#slide.style.top === position.top];
			if (clicked)
			{
				//console.log(offset);
				if (this.#active && this.#active.video === offset.target && globalThis.MediaSource && globalThis.Hls)
				{
					this.#active.pp();
				}
				return;
			}
			beforeindex = currentindex;
			if (Math.abs(direction) > 100)
			{
				if (direction < 0)
				{
					if (--currentindex < 0)
					{
						currentindex = beforeindex;
					}
					else
					{
						//currentindex < 3 && this.fetch(true);
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
				this.#onchange();
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
	#setvideo(video, content)
	{
		typeof content.poster === 'string' && video.poster(content.poster);
		typeof content.m3u8 === 'string' && video.m3u8(content.m3u8);
	}
	#onchange()
	{
		this.onchange && this.onchange();
	}
	fetch()
	{
		if ('fetch' in this.dataset && this.#fetch)
		{
			this.#fetch = false;
			//console.log('fetching...');
			fetch(`${this.dataset.fetch}${'page' in this.dataset ? this.dataset.page++ : ''}`).then(response => response.json()).then(data =>
			{
				if (data.length)
				{
					data.forEach(video =>
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
								this.#onchange();
							}
						}
						this.#contents[this.#contents.length] = video;
					});
					this.#fetch = true;
				}
			});
		}
	}
	slide(upordown)
	{
		this.#slider(Boolean(upordown));
	}
	connectedCallback()
	{
		if (this.#slide.isConnected) return;
		this.#page = parseInt(this.dataset.page, 10);
		this.#videos.forEach(video =>
		{
			video.setAttributeNode(document.createAttribute('loop'));
			//video.setAttributeNode(document.createAttribute('autoplay'));
			video.setAttributeNode(document.createAttribute('controls'));

			// this.hasAttribute('loop') && video.setAttributeNode(document.createAttribute('loop'));
			this.hasAttribute('muted') && video.setAttributeNode(document.createAttribute('muted'));
			// this.hasAttribute('autoplay') && video.setAttributeNode(document.createAttribute('autoplay'));
			// this.hasAttribute('controls') && video.setAttributeNode(document.createAttribute('controls'));

			video.watch = () => 'log' in this.dataset
				&& typeof this.#current.key === 'string'
				&& navigator.sendBeacon(this.dataset.log, this.#current.key);
	
		});
		this.appendChild(this.#slide);
		this.fetch();
	}
});