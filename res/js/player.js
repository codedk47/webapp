document.head.appendChild(document.createElement('style')).textContent = `
webapp-video{
	display: block;
	position: relative;
}
webapp-videos{
	height: 100%;
	display: block;
	position: relative;
	overflow: hidden;
}
webapp-videos>div{
	position: absolute;
	width:100%;
	height:100%;
}
webapp-videos>div.shifting{
	transition: top .4s;
}
webapp-videos>div>webapp-video{
	height: 100%;
}
webapp-videos>div>webapp-video>video{
	background-color: black;
	object-fit: contain;
}
webapp-videos>div>webapp-video>div.pp{
	position: absolute;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	background: url(${top.location.origin}/webapp/res/ps/pause-button.png) center center / 40% no-repeat;
	visibility: hidden;
}`;
customElements.define('webapp-video', class extends HTMLElement
{
	#loader = 'framer' in top ? top.framer.source : loader;
	#video = document.createElement('video');
	#model;
	#playdata;
	#playm3u8;
	#suspend;
	#resume;
	#close;

	//#controls = document.createElement('div');
	#controls;

	#loading = false;
	#loaded;
	constructor()
	{
		super();
		this.#video.setAttribute('width', '100%');
		this.#video.setAttribute('height', '100%');
		this.#video.setAttribute('playsinline', true);
		this.#video.setAttribute('disablepictureinpicture', true);
		this.#video.textContent = `Sorry, your browser doesn't support embedded videos.`;
		this.#video.controlsList = 'nodownload';
		this.#video.style.display = 'block';

		this.#video.oncanplay = event =>
		{
			if (this.#loading)
			{
				this.#loading = false;
				this.#loaded && this.#loaded(this);

			// this.#video.height = this.hasAttribute('autoheight')
			// 	? event.target.videoHeight * (this.offsetWidth / event.target.videoWidth)
			// 	: '100%';
				this.#video.setAttribute('height', this.hasAttribute('autoheight')
					? Math.trunc(event.target.videoHeight * this.scalewidth)
					: '100%');

				const pp = this.querySelector('div.pp');
				if (pp)
				{
					pp.style.visibility = this.#video.paused ? 'visible' : 'hidden';
				}
			}
		}

		if (globalThis.MediaSource && globalThis.Hls)
		{
			this.#playm3u8 = data =>
			{
				if (this.#model)
				{
					this.#model.destroy();
				}
				this.#model = new globalThis.Hls;
				this.#loading = true;
				this.#playdata = Array.isArray(data) ? URL.createObjectURL(new Blob(data, {type: 'application/x-mpegURL'})) : data;
				this.#model.config.autoStartLoad = this.#video.autoplay;
				this.#model.loadSource(this.#playdata);
				this.#model.attachMedia(this.#video);

			};
			this.#suspend = () =>
			{
				this.#model.stopLoad();
				this.#video.pause();
			};
			this.#resume = () =>
			{
				this.#model.startLoad();
				this.#video.play();
			};
			this.#close = () =>
			{
				this.#model.detachMedia(this.#video);
			};
		}
		else
		{
			if (this.#video.canPlayType('application/vnd.apple.mpegurl') || this.#video.canPlayType('application/x-mpegURL'))
			{
				// this.#model = this.#video;
// 				const template = document.createElement('template');
// 				template.innerHTML = `<svg width="40%" height="40%" viewBox="0 0 24 24" style="position:absolute;top:0;left:0;right:0;bottom:10%;margin:auto;">
// <path d="M 12,2 C 17.52,2 22,6.48 22,12 22,17.52 17.52,22 12,22 6.48,22 2,17.52 2,12 2,6.48 6.48,2 12,2 Z" fill="black" opacity="0.6"></path>
// <path d="m 9.5,7.5 v 9 l 7,-4.5 z" fill="white"></path>
// </svg>`;
// 				this.#controls = template.content.firstChild;
// 				this.#controls.onclick = () => this.#video.play();
// 				this.#video.onpause = () => this.#controls.style.visibility = 'visible';
// 				this.#video.onplay = () => this.#controls.style.visibility = 'hidden';
// 				this.#video.onclick = () => this.#video.paused || this.#video.pause();

				this.#playm3u8 = data =>
				{
					this.#loading = true;
					this.#playdata = Array.isArray(data) ? `data:application/vnd.apple.mpegurl;base64,${btoa(data.join('\n'))}` : data;
					if (this.#video.autoplay)
					{
						this.#video.src = this.#playdata;
					}
				};
				this.#suspend = () =>
				{
					this.#video.load(this.#video.src = '');
				};
				this.#resume = () =>
				{
					this.#video.src = this.#playdata;
				};
				this.#close = this.#suspend;
			}
			else
			{
				this.#playm3u8 = data => console.error(data);
				this.#close = this.#resume = this.#suspend = () => null;
			}
		}
	}
	suspend()
	{
		this.#suspend();
	}
	resume()
	{
		this.#resume();
	}
	play_pause()
	{
		const pp = this.querySelector('div.pp');
		if (this.#video.paused)
		{
			this.#video.play();
			pp.style.visibility = 'hidden';
		}
		else
		{
			this.#video.pause();
			pp.style.visibility = 'visible';
		}
	}
	close()
	{
		this.#close();
	}
	async poster(resource)
	{
		return this.#loader(resource, {mask: this.mask}).then(blob => this.#video.poster = blob);
	}
	m3u8(resource, preview)
	{
		this.mask ? this.#loader(resource, {mask: true, type: 'text/modify'}).then(([url, data]) =>
		{
			const buffer = [], rowdata = data.match(/#[^#]+/g), resource = url.substring(0, url.lastIndexOf('/')),
			[previewstart, previewend] = Number.isInteger(preview *= 1)
				? [preview >> 16 & 0xffff, (preview >> 16 & 0xffff) + (preview & 0xffff)]
				: [0, 0xffff];
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
								? pattern[0].replace(pattern[2], `${resource}/${pattern[2]}`) : pattern[0];
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
						? rowdata[i].replace(/URI="([^"]+)"/, `URI="${resource}/$1"`)
						: rowdata[i];
				}
			}
			this.#playm3u8(buffer);
		}) : this.#playm3u8(resource);
		return this;
	}
	connectedCallback()
	{
		// this.style.display = 'block';
		// this.style.position = 'relative';
		this.#video.loop = this.hasAttribute('loop');
		this.#video.muted = this.hasAttribute('muted');
		this.#video.autoplay = this.hasAttribute('autoplay');
		this.#video.controls = this.hasAttribute('controls');

		this.appendChild(this.#video);
		//this.#controls && this.appendChild(this.#controls);

		if (this.dataset.fit)
		{
			this.#video.style.objectFit = this.dataset.fit;
		}
		this.dataset.poster && this.poster(this.dataset.poster);
		this.dataset.m3u8 && this.m3u8(this.dataset.m3u8, this.dataset.preview);
	}
	disconnectedCallback()
	{
		this.#close();
	}
	set mask(value)
	{
		value ? this.setAttributeNode(document.createAttribute('data-mask')) : this.removeAttribute('data-mask');
	}
	get mask()
	{
		return this.hasAttribute('data-mask');
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
	loaded(init)
	{
		this.#loaded = init;
	}
	finish(then)
	{
		this.#video.onended = () => then(this);
		//return this;
	}
	// interval(call)
	// {
	// 	// this.#video.ontimeupdate = event =>
	// 	// {

	// 	// 	console.log(event)
	// 	// };
	// }
});
customElements.define('webapp-videos', class extends HTMLElement
{
	#loader = 'framer' in top ? top.framer.loader : loader;
	#slide = document.createElement('div');
	#shift = true;
	#index = 0;
	#each;



	#template;
	#callback;
	#require;
	#height;
	#load;
	#page;
	

	#position;
	#change;
	constructor()
	{
		super();
		this.#slide.style.top = '0px';
		this.#slide.addEventListener('transitionend', () =>
		{
			this.#slide.classList.remove('shifting');
			this.#shift = false;
		});
		this.addEventListener('click', event =>
		{
			if (this.#slide.childNodes.length > this.#index)
			{
				this.#slide.childNodes[this.#index].play_pause();
			}
		});
		this.addEventListener('touchstart', event =>
		{
			if (this.#shift) return;
			this.#position = {
				offset: this.#slide.offsetTop,
				start: event.clientY || event.touches[0].clientY
			};
		});
		this.addEventListener('touchmove', event =>
		{
			event.preventDefault();
			if (this.#shift) return;
			this.#position.move = event.clientY || event.touches[0].clientY;
			this.#slide.style.top = `${this.#position.offset + this.#position.move - this.#position.start}px`;
		});
		this.addEventListener('touchend', event =>
		{
			if (this.#shift) return;
			const direction = this.#position.start - this.#position.move, index = this.#index;
			if (Math.abs(direction) > 100)
			{
				if (direction < 0)
				{
					if (this.#index)
					{
						--this.#index;
					}
				}
				if (direction > 0)
				{
					if (this.#index < this.#slide.children.length - 1)
					{
						++this.#index;
						if (this.#slide.children.length - this.#index === 1)
						{
							this.#loading();
						}
					}
				}
			}
			const top = this.#index * this.offsetHeight;
			if (parseInt(this.#slide.style.top) === -top) return;
			this.#shift = true;
			this.#slide.classList.add('shifting');
			this.#slide.style.top = `-${top}px`;
			if (this.#index === index) return;

			this.#slide.childNodes[index].close();
			this.#slide.childNodes[this.#index].m3u8(this.#slide.childNodes[this.#index].dataset.playm3u8,
				this.#slide.childNodes[this.#index].dataset.preview);
			this.#change && this.#change(this.#slide.childNodes[this.#index]);

			this.#slide.childNodes[this.#index].querySelector('div.pp').style.visibility =
				this.#slide.childNodes[this.#index].querySelector('video').paused ? 'visible' : 'hidden';
			//this.#slide.childNodes[index].suspend();
			//this.#slide.childNodes[this.#index].resume();
		});
	}
	loaddata()
	{
		if (this.#load)
		{
			loader(`${this.#load}${this.#page++}`, {headers: {'Content-Type': 'application/data-stream'}}, 'application/json').then(result =>
			{
				result.data.data.forEach((data, index) =>
				{
					const
					video = document.createElement('webapp-video');
					video.controls = true;
					video.autoplay = false;
					//video.controls = 'nodownload nofullscreen';
					video.style.height = `${this.#height}px`;
					video.dataset.load = data.path;
					video.dataset.require = data.require;

					this.hasAttribute('controls') && video.setAttribute('controls', true);
		
					if (data.require && this.#require)
					{
						video.display = this.#require;
					
					}
					if (index === 0 && this.#index === 0)
					{
						video.setAttribute('autoplay', true);
						this.#change && this.#change(video);
					}
					
					
					
					
					//video.preload = 'none';
					//video.controls = true;

					//console.log(this.#template.cloneNode(true));
					video.appendChild(document.importNode(this.#template.content, true));
					this.#slide.appendChild(video);
					if (this.#callback)
					{
						this.#callback.call(video, data);
						
					}
				});
				
				this.#shift = false;
			});
		}
	}
	
	connectedCallback()
	{
		this.appendChild(this.#slide);
		this.#loading(this.#each = window[this.dataset.each]).then(() =>
		{
			if (this.#slide.childNodes.length)
			{
				this.#shift = false;
				this.#slide.firstChild.m3u8(this.#slide.firstChild.dataset.playm3u8, this.#slide.firstChild.dataset.preview);

				this.#slide.firstChild.querySelector('div.pp').style.visibility =
					this.#slide.firstChild.querySelector('video').paused ? 'visible' : 'hidden';

				//this.#slide.firstChild.firstChild.setAttributeNode(document.createAttribute('autoplay'));
				//this.#slide.firstChild.m3u8(this.#slide.firstChild.dataset.a);
				//this.#slide.firstChild.resume();
				// this.#slide.firstChild.oncanplay = () =>
				// {
				// 	console.log(123);
				// };
				//console.log(this.#slide.firstChild.resume())
			
			}
		});
	}
	#loading()
	{
		return this.#loader(`${this.dataset.url}${this.dataset.page++}`, {mask: this.hasAttribute('data-mask')}).then(data => data.forEach(data =>
		{
			const video = document.createElement('webapp-video');
			video.setAttributeNode(document.createAttribute('autoplay'));
			//video.setAttributeNode(document.createAttribute('controls'));
			const div = video.appendChild(document.createElement('div'));
			div.className = 'pp';
			this.#slide.appendChild(video);
			this.#each.call(video, data);
		}));
	}
	change(then)
	{
		this.#change = then;
	}
});
