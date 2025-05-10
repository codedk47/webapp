//document.head.appendChild(document.createElement('script')).src = '/webapp/res/js/hls.min.js';
document.head.appendChild(document.createElement('style')).textContent = `
webapp-video{
	display: block;
	position: relative;
}
webapp-video>video{
	display: block;
	object-fit: cover;
}
webapp-video>aside{
	position: absolute;
	inset: 0;
	display: flex;
	flex-direction: column;
	z-index: 1;
}
webapp-video>aside>figure,
webapp-video>aside>div>span{
	flex-grow: 1;
}
webapp-video>aside>div{
	display: flex;
	background-color: rgba(0, 0, 0, .6);
	align-items: center;
	color: white;
}
webapp-video>aside>div>details>summary{
	display: flex;
	list-style: none;
}
webapp-video>aside>div>details>summary>input{
	display: none;
}
webapp-video>aside>div>details[open]>summary>input{
	display: inline-block;
}
webapp-video>aside>div>time::before{
	content: attr(datetime)
}
webapp-video>aside>div>time::after{
	content: attr(data-duration)
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
	#controls = document.createElement('aside');
	#loading = document.createElement('figure');
	#progress = document.createElement('input');
	#actions = document.createElement('div');
	#playpause = this.#svg('M 12,26 18.5,22 18.5,14 12,10 z M 18.5,22 25,18 25,18 18.5,14 z');
	//#volume = this.#svg('M8,21 L12,21 L17,26 L17,10 L12,15 L8,15 L8,21 Z M19,14 L19,22 C20.48,21.32 21.5,19.77 21.5,18 C21.5,16.26 20.48,14.74 19,14 ZM19,11.29 C21.89,12.15 24,14.83 24,18 C24,21.17 21.89,23.85 19,24.71 L19,26.77 C23.01,25.86 26,22.28 26,18 C26,13.72 23.01,10.14 19,9.23 L19,11.29 Z');
	
	#volume = document.createElement('details').appendChild(document.createElement('summary'));
	#speaker = document.createElement('input');
	#currenttime = document.createElement('time');
	#fullscreen = this.#svg('m 10,16 2,0 0,-4 4,0 0,-2 L 10,10 l 0,6 0,0 z',
		'm 20,10 0,2 4,0 0,4 2,0 L 26,10 l -6,0 0,0 z',
		'm 24,24 -4,0 0,2 L 26,26 l 0,-6 -2,0 0,4 0,0 z',
		'M 12,20 10,20 10,26 l 6,0 0,-2 -4,0 0,-4 0,0 z');
	
	#playm3u8;
	#format = time => [parseInt(time / 3600), parseInt((time % 3600) / 60), parseInt(time % 60)].map(value => String(value).padStart(2, 0)).join(':');
	#svg(...path)
	{
		const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
		svg.setAttribute('width', 32);
		svg.setAttribute('height', 32);
		svg.setAttribute('viewBox', '0 0 36 36');
		svg.setAttribute('fill', 'white');
		path.forEach(d => svg.appendChild(document.createElementNS('http://www.w3.org/2000/svg', 'path')).setAttribute('d', d));
		return svg;
	}
	

	constructor()
	{
		super();
		this.#video.setAttribute('width', '100%');
		this.#video.setAttribute('height', '100%');
		this.#video.setAttribute('playsinline', true);
		this.#video.setAttribute('disablepictureinpicture', true);
		this.#video.textContent = `Sorry, your browser doesn't support embedded videos.`;
		this.#video.controlsList = 'nodownload';



		this.#volume.appendChild(this.#svg('M8,21 L12,21 L17,26 L17,10 L12,15 L8,15 L8,21 Z M19,14 L19,22 C20.48,21.32 21.5,19.77 21.5,18 C21.5,16.26 20.48,14.74 19,14 ZM19,11.29 C21.89,12.15 24,14.83 24,18 C24,21.17 21.89,23.85 19,24.71 L19,26.77 C23.01,25.86 26,22.28 26,18 C26,13.72 23.01,10.14 19,9.23 L19,11.29 Z'));
		this.#volume.appendChild(this.#speaker);
		this.#speaker.type = this.#progress.type = 'range';
		this.#speaker.max = this.#progress.max = 1;
		this.#speaker.step = this.#progress.step = 0.0001;
		this.#progress.value = 0;
		this.#speaker.value = 1;

		this.#playpause.onclick = e => {
			this.pp();
		}
		


		this.#fullscreen.onclick = () => this.#video.requestFullscreen();
	
		this.#speaker.oninput = event =>
		{
			event.stopPropagation();
			this.#video.volume = this.#speaker.value;
		};
		this.#currenttime.setAttribute('datetime', '00:00:00');
		this.#currenttime.dataset.duration = '--:--:--';
		this.#currenttime.textContent = ' / ';
		this.#video.ondurationchange = () => {
			this.#currenttime.dataset.duration = this.#format(this.#video.duration);

		}
		this.#actions.append(this.#playpause, this.#volume.parentNode, this.#currenttime, document.createElement('span'), this.#fullscreen);

		this.#controls.append(this.#loading, this.#progress, this.#actions);
		this.#controls.addEventListener('click', e =>{
			console.log(e);
		})

		//progress.onmousedown = progress.ontouchstart = () => this.pause();
		this.#progress.addEventListener('change', () =>
		{
			this.#video.currentTime = this.#video.duration * this.#progress.value;
			this.paused && this.play();
		});

		this.#video.addEventListener('timeupdate', () =>
		{
			this.#progress.value = this.#video.currentTime / this.#video.duration;
			this.#currenttime.setAttribute('datetime', this.#format(this.#video.currentTime));
		});

		// const functions = this.#controls.appendChild(this.#functions);
		

		this.#video.addEventListener('canplay', event =>
		{
			this.oncanplay && this.oncanplay(this.#video);
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
	// log(callback)
	// {
	// 	//日志触发回调（类型）
	// 	return typeof callback === 'string' ? navigator.sendBeacon(callback) : callback('watch');
	// }
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
	controls(a)
	{
		// if (!this.#controls)
		// {
		// 	this.#controls = document.createElement('div');

		// 	this.#controls.innerHTML = 'asdasd';

		// 	this.insertBefore(this.#controls, this.firstElementChild);

		// }
		this.insertBefore(this.#controls, this.firstElementChild);
		//console.log(this.#controls, a);
	}
	connectedCallback()
	{
		if (this.#video.isConnected) return;
		this.#video.loop = this.hasAttribute('loop');
		this.#video.muted = this.hasAttribute('muted');
		this.#video.autoplay = this.hasAttribute('autoplay');
		//this.#video.controls = this.hasAttribute('controls');
		this.appendChild(this.#video);
		this.controls(this.getAttribute('controls'));

		// if ('splashscreen' in this.dataset)
		// {

		// }
		if ('poster' in this.dataset)
		{
			this.poster(this.dataset.poster);
			delete this.dataset.poster;
		}
		if ('m3u8' in this.dataset)
		{
			this.m3u8(this.dataset.m3u8, this.dataset.preview);
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
				this.onchange && this.onchange();
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
								this.onchange && this.onchange();
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

		});
		this.appendChild(this.#slide);
		this.fetch();
	}
});