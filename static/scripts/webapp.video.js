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
}

webapp-video>section{
	position: absolute;
	inset: 0;
	display: flex;
	flex-direction: column;
	z-index: 1;
	transition: opacity .4s;
	background: linear-gradient(transparent 90%, rgba(0, 0, 0, 0.4));

}
webapp-video>section>input[type=range]{
	appearance: none;
	outline: none;
	margin: 0 1rem;
	overflow: hidden;
	transition: background .4s;
	background-color: rgba(255, 255, 255, .4);
	border-radius: .5rem;
	
}
webapp-video>section>input[type=range]:hover{
	background-color: rgba(255, 255, 255, .8)
}
webapp-video>section>input[type=range]::-webkit-slider-thumb{
	appearance: none;
	outline: none;
	width:.1rem;

	background-color: rgba(0, 0, 0, .2);
	transition: background .4s;
	height: .4rem;

	box-shadow: 1000rem 0rem 0 1000rem rgba(0,0,0,.2);
}
webapp-video>section>input[type=range]:hover::-webkit-slider-thumb{
	background-color: rgba(0, 0, 0, .4);
}

webapp-video>section>div.playpause,
webapp-video>section>div.functions>span{
	flex-grow: 1;
}
webapp-video>section>div.functions{
	display: flex;
	padding: .4rem;
}
webapp-video>section>div.functions>details>summary{
	color: white;
	padding: 0 4px;
	line-height: 32px;
	display: flex;
	list-style: none;
}
webapp-video>section>div.functions>details>summary>a{
	display: none;
}
webapp-video>section>div.functions>details[open]>summary>a{
	cursor: pointer;
	display: inline-block;
	padding: 0 .4rem;
}

webapp-video>section>div.functions>svg,
webapp-video>section>div.functions>details:not([open])>summary,
webapp-video>section>div.functions>details>summary>a{
	cursor: pointer;
	border: 1px solid transparent;
}
webapp-video>section>div.functions>svg:hover,
webapp-video>section>div.functions>details:not([open])>summary:hover,
webapp-video>section>div.functions>details[open]>summary>a:hover{
	border: 1px solid rgba(0, 0, 0, .6);
	background-color: rgba(0, 0, 0, .4);
	border-radius: .2rem;
}


webapp-video>section>div.loading
{
	background-color: rgba(0, 0, 0, .6);
	background-image: linear-gradient(-45deg,
	rgba(255, 255, 255, 0.15) 25%,
	transparent 25%,
	transparent 50%,
	rgba(255, 255, 255, 0.15) 50%,
	rgba(255, 255, 255, 0.15) 75%,
	transparent 75%,
	transparent);
	background-size: 1rem 1rem;
	height: .6rem;
	transition: opacity .6s;
	animation: 1s linear 0s infinite normal none running webapp-video-loading;
}
@keyframes webapp-video-loading {
	0% {background-position: 1rem 0px;}
	100% {background-position: 0px 0px;}
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
}`;
customElements.define('webapp-video', class extends HTMLElement
{
	#video = document.createElement('video');
	#controls = document.createElement('section');

	//#progress = document.createElement('input');
	#playm3u8;
	#fadeout;
	constructor()
	{
		super();
		this.#video.setAttribute('width', '100%');
		this.#video.setAttribute('height', '100%');
		this.#video.setAttribute('playsinline', true);
		this.#video.setAttribute('disablepictureinpicture', true);
		this.#video.textContent = `Sorry, your browser doesn't support embedded videos.`;
		this.#video.controlsList = 'nodownload';

		this.#controls.addEventListener('mousemove', () =>{

			if (this.#video.paused)
			{
				console.log('mousemove')
				this.#controls.style.opacity = 1;
				clearTimeout(this.#fadeout);
				this.#fadeout = setTimeout(() => this.#controls.style.opacity = 0, 2000);
			}

		});
		this.#controls.addEventListener('mouseleave', () =>{
			console.log('mouseleave')
			this.#controls.style.opacity = 0;
		});


		const playpause = this.#controls.appendChild(document.createElement('div'));
		playpause.addEventListener('dblclick', () => this.fullscreen());
		playpause.addEventListener('click', () => this.pp());
		playpause.className = 'playpause';

		const progress = this.#controls.appendChild(document.createElement('input'));
		progress.type = 'range';
		progress.max = 1;
		progress.step = 0.0001;
		progress.value = 0;


		//progress.onmousedown = progress.ontouchstart = () => this.pause();

		progress.addEventListener('mousedown', ()=>{
			this.pause();
		})
		progress.addEventListener('mouseup', ()=>{
			console.log('mouseup', progress.value);
			requestAnimationFrame(()=>{
				this.#video.currentTime = this.#video.duration * progress.value;
			})
			
			
			
		});


		// progress.addEventListener('change', () =>
		// {
		// 	this.#video.currentTime = this.#video.duration * progress.value;
		// 	// this.paused && this.play();
		// });


		const functions = this.#controls.appendChild(document.createElement('div'));
		const playing = this.#svg('');
		playing.addEventListener('click', () => this.pp());



		const speedrate = document.createElement('details').appendChild(document.createElement('summary'));
		
		speedrate.textContent = '1x';
		[0.25, 0.5, 0.75, 1, 1.25, 1.5, 1.75, 2, 3, 4].forEach(rate =>
		{
			const value = speedrate.appendChild(document.createElement('a'));
			value.dataset.rate = rate;
			value.textContent = Math.floor(rate) === rate ? `${rate}x` : rate;
			value.onclick = () => this.#video.playbackRate = parseFloat(value.dataset.rate);

		});

		const fullscreen = this.#svg('m 10,16 2,0 0,-4 4,0 0,-2 L 10,10 l 0,6 0,0 z',
			'm 20,10 0,2 4,0 0,4 2,0 L 26,10 l -6,0 0,0 z',
			'm 24,24 -4,0 0,2 L 26,26 l 0,-6 -2,0 0,4 0,0 z',
			'M 12,20 10,20 10,26 l 6,0 0,-2 -4,0 0,-4 0,0 z');
		fullscreen.addEventListener('click', () => this.fullscreen());

		functions.append(playing, speedrate.parentNode, document.createElement('span'), fullscreen);
		functions.className = 'functions';
		
		
		const loading = this.#controls.appendChild(document.createElement('div'));
		this.#video.addEventListener('waiting', () => loading.style.opacity = 1);
		this.#video.addEventListener('playing', () => loading.style.opacity = 0);
		loading.className = 'loading';
		
		this.#video.addEventListener('pause', () =>
		{
			this.#controls.style.opacity = 1;
			playing.firstChild.setAttribute('d', 'M 12,26 18.5,22 18.5,14 12,10 z M 18.5,22 25,18 25,18 18.5,14 z');
		});
		this.#video.addEventListener('play', () =>
		{
			this.#controls.style.opacity = 0;
	

			playing.firstChild.setAttribute('d', 'M 12,26 16,26 16,10 12,10 z M 21,26 25,26 25,10 21,10 z');
	
		});

		this.#video.addEventListener('timeupdate', () =>
		{
			progress.value = this.#video.currentTime / this.#video.duration
		});


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
	fullscreen()
	{
		alert(1)
		if (this.requestFullscreen)
		{
			document.fullscreenElement === this ? document.exitFullscreen() : this.requestFullscreen();
		}
		else
		{
			this.#video.requestFullscreen();
		}
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
		//this.#video.controls = this.hasAttribute('controls');
		this.append(this.#controls, this.#video);
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