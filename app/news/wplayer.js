customElements.define('webapp-video', class extends HTMLElement
{
	//#resolve;
	//#promise = new Promise(resolve => this.#resolve = resolve);
	#video = document.createElement('video');
	#model;
	#open;
	#suspend;
	#resume;
	#close;
	
	#load;
	#require;
	#playurl;
	#playtime = 0;
	autoplay;
	controls = false;
	constructor()
	{
		super();
		this.#video.setAttribute('width', '100%');
		this.#video.setAttribute('playsinline', true);
		this.#video.setAttribute('disablepictureinpicture', true);
		this.#video.muted = true;
		this.#video.autoplay = true;
		this.#video.controls = true;
		this.#video.controlsList = 'nodownload';
		
		this.#video.addEventListener('timeupdate', () =>
		{
			if (this.#require && this.#video.currentTime > 10 && this.display && 0)
			{
				this.#video.currentTime = 10;
				this.#video.pause();
				this.display.show();
				//this.#suspend();
			}
		});


		if (window.MediaSource && window.Hls)
		{
			//alert('MediaSource')
			this.#model = new window.Hls;
			this.#open = (data) =>
			{
				this.#playurl = URL.createObjectURL(new Blob([data], {type: 'application/x-mpegURL'}));

				
				this.#model.config.autoStartLoad = this.autoplay;
				this.#model.loadSource(this.#playurl);
				this.#model.attachMedia(this.#video);



				// this.#playurl = 
				// return new Promise((resolve, reject) =>
				// {
				// 	this.#model.attachMedia(this.#video);
				// 	this.#model.once(window.Hls.Events.MANIFEST_PARSED, () => resolve(this.#model));
				// 	this.#model.once(window.Hls.Events.MEDIA_ATTACHED, () => this.#model.loadSource(url));
				// });
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
			if (this.#video.canPlayType('application/vnd.apple.mpegurl')
				|| this.#video.canPlayType('application/x-mpegURL')) {
				this.#model = this.#video;
				this.#video.addEventListener('click', () =>
				{
					this.#video.paused ? this.#video.play() : this.#video.pause();
				});
				this.#video.addEventListener('canplay', () =>
				{
					this.#video.currentTime = this.#playtime;
		
				});
				this.#open = (data) =>
				{
					
					
					this.#playurl = `data:application/vnd.apple.mpegurl;base64,${btoa(data)}`;
					if (this.autoplay)
					{
						this.#video.src = this.#playurl;
					}
					
					// this.#video.src = url;
					// this.#video.addEventListener('loadedmetadata', ()=>{
					// 	//this.#video.pause();
					// 	alert(1)
					// })
					
				};
				this.#suspend = () =>
				{
					this.#video.load(this.#video.src = '');
				};
				this.#resume = () =>
				{
					this.#video.src = this.#playurl;
					
				};
				this.#close = this.#suspend;
			}
			else
			{
				this.#open = (url) => console.log(`cant open ${url}`);
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
	connectedCallback()
	{
		this.autoplay = this.hasAttribute('autoplay');
		this.controls = this.hasAttribute('controls');
		this.appendChild(this.#video);
		if (this.#load = this.dataset.load)
		{
			this.#require = this.dataset.require;
			loader(`${this.#load}/cover`, null, 'application/octet-stream').then(blob => this.#video.poster = URL.createObjectURL(blob));
			loader(`${this.#load}/play`, null, 'text/plain').then(data =>
			{
				const m3u8 = data.split('\n');
				for (let i = 0; i < m3u8.length; ++i)
				{
					switch (true)
					{
						case m3u8[i].startsWith('#EXT-X-KEY'):
							if (/URI="(?!http:\/\/)[^"]+"/.test(m3u8[i]))
							{
								m3u8[i] = m3u8[i].replace(/URI="([^"]+)"/, `URI="${this.#load}/$1"`);
							}
							break;
						case m3u8[i].startsWith('#EXTINF'):
							if (/^(?!http:\/\/)/.test(m3u8[++i]))
							{
								m3u8[i] = `${this.#load}/${m3u8[i]}`;
							}
							break;
					}
				}
				this.#open(m3u8.join('\n'));
			});
		}
	}
	disconnectedCallback()
	{
		this.#close();
	}
});
document.head.appendChild(document.createElement('style')).textContent = `
webapp-slide{
	height: 100%;
	display: block;
	position: relative;
	overflow: hidden;
}
webapp-slide>div{
	position: absolute;
}
webapp-slide>div.shifting{
	transition: top .4s;
}
webapp-slide>div>webapp-video{
	display: block;
	position: relative;
}
webapp-slide>div>webapp-video>video{
	height: 100%;
	object-fit: fill;
}`;

customElements.define('webapp-slide', class extends HTMLElement
{
	#template;
	#callback;
	#require;
	#height;
	#load;
	#page;
	#slide = document.createElement('div');
	#index = 0;
	#shift = true;
	#position;
	constructor()
	{
		super();
		this.#slide.style.top = '0px';
		this.#slide.addEventListener('transitionend', () =>
		{
			this.#slide.classList.remove('shifting');
			this.#shift = false;
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
							this.loaddata();
						}
					}
				}
			}
			const top = this.#index * this.#height;
			if (parseInt(this.#slide.style.top) === -top) return;
			this.#shift = true;
			this.#slide.classList.add('shifting');
			this.#slide.style.top = `-${top}px`;
			if (this.#index === index) return;
			this.#slide.childNodes[index].suspend();
			this.#slide.childNodes[this.#index].resume();
		});
	}
	loaddata()
	{
		if (this.#load)
		{
			loader(`${this.#load}${this.#page++}`, {headers: {'Content-Type': 'application/data-stream'}}, 'application/json').then(result =>
			{
				result.data.data.forEach(data =>
				{
					const
					video = document.createElement('webapp-video');
					video.controls = true;
					video.autoplay = false;
					//video.controls = 'nodownload nofullscreen';
					video.style.height = `${this.#height}px`;
					video.dataset.load = data.path;
					video.dataset.require = data.require;
					if (data.require && this.#require)
					{
						video.display = this.#require;
					
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

				return this.#index;
			}).then(index => {
				this.#shift = false;
				this.#slide.childNodes[index].resume();
			});
		}
	}
	connectedCallback()
	{
		this.appendChild(this.#slide);
		this.#callback = window[this.dataset.callback];
		if (this.dataset.display)
		{
			this.#require = document.querySelector(this.dataset.display);
		}
		requestAnimationFrame(() =>
		{
			this.#template = this.querySelector('template');
			this.#height = this.offsetHeight;
			this.#load = this.dataset.load;
			this.#page = this.dataset.page || 1;
			this.loaddata();
		});
	}
});