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
			if (0 && this.#require && this.#video.currentTime > 30)
			{
				this.#video.pause();
			}
		});


		if (window.MediaSource && window.Hls)
		{
			//alert('MediaSource')
			this.#model = new window.Hls;
			this.#open = (url) =>
			{
				this.#model.config.autoStartLoad = this.autoplay;
				this.#model.loadSource(this.#playurl = url);
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
				this.#open = (url) =>
				{
					
					this.#playurl = url;
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
			this.#open(`${this.#load}/${this.#model instanceof HTMLVideoElement ? 'play.m3u8' : 'play'}`);
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
	#callback;
	#template;
	#height;
	#load;
	#page = 1;
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
			})
			this.#shift = false;
			// .then(index => {
			// 	this.#shift = false;
			// 	console.log(this.#slide.childNodes[index].resume())
			// });
		}
	}
	connectedCallback()
	{
		this.appendChild(this.#slide);
		this.#callback = window[this.dataset.callback];
		requestAnimationFrame(() =>
		{
			this.#template = this.querySelector('template');
			this.#height = this.offsetHeight;
			this.#load = this.dataset.load;
			this.loaddata();
		});
	}
});
function wplayload(path)
{
	// console.log( /URI="(?!http:\/\/)[^"]+"/.test('#EXT-X-KEY:METHOD=AES-128,URI="keycode",IV=0xc59ffe73bd982d39619e12a51e484194') )
	// console.log( '#EXT-X-KEY:METHOD=AES-128,URI="keycode",IV=0xc59ffe73bd982d39619e12a51e484194' )

	//
	//console.log( '#EXT-X-KEY:METHOD=AES-128,URI="keycode",IV=0xc59ffe73bd982d39619e12a51e484194'.match(/URI="([^"]+)"/)[1] );


	loader(`${path}/play`, null, 'text/plain').then(a=>
	{
		const s = a.split('\n');
		for (let i = 0; i < s.length; ++i)
		{
			switch (true)
			{
				case s[i].startsWith('#EXT-X-KEY'):
					if (/URI="(?!http:\/\/)[^"]+"/.test(s[i]))
					{
						s[i] = s[i].replace(/URI="([^"]+)"/, `URI="${path}/$1"`);
					}
					break;
				case s[i].startsWith('#EXTINF'):
					if (/^(?!http:\/\/)/.test(s[++i]))
					{
						s[i] = `${path}/${s[i]}`;
					}
					break;
			}
		}


		const aa = s.join('\n');
		

		console.log(aa);



		const vv = document.body.appendChild( document.createElement('video') );

		vv.controls = true;
		vv.src = URL.createObjectURL(new Blob([aa], {type: 'application/vnd.apple.mpegurl'}));


		// a.split("\n").forEach(p=>{

		// 	// switch (p.substring(0, 10))
		// 	// {
		// 	// 	case '#EXT-X-KEY':

		// 	// }
		// 	// if (p.substring(0, 10) === '#EXT-X-KEY')
		// 	// {
		// 	// 	console.log('#EXT-X-KEY')
		// 	// }

		// 	console.log(p)
		// })



		
	})
}