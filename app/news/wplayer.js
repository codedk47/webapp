customElements.define('webapp-video', class extends HTMLElement
{
	#video;
	#model;
	#open;
	#close;
	#require;
	#playurl;
	#playtime = 0;
	constructor()
	{
		super();
		this.#video = document.createElement('video');
		this.#video.setAttribute('width', '100%');
		this.#video.setAttribute('autoplay', true);
		this.#video.setAttribute('playsinline', true);
		this.#video.setAttribute('disablepictureinpicture', true);
		this.#video.controls = true;
		
		
		if (window.MediaSource && window.Hls)
		{
			//this.#require = this.dataset.require;
			
			this.#model = window.hls instanceof window.Hls ? window.hls : window.hls = new window.Hls;
			this.#open = (url) =>
			{
				this.#model.loadSource(url);
				this.#model.attachMedia(this.#video);
				// return new Promise((resolve, reject) =>
				// {
				// 	this.#model.attachMedia(this.#video);
				// 	this.#model.once(window.Hls.Events.MANIFEST_PARSED, () => resolve(this));
				// 	this.#model.once(window.Hls.Events.MEDIA_ATTACHED, () => this.#model.loadSource(url));
				// });
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
				this.#open = (url) =>
				{
					
					this.#video.src = url;
				};
				this.#close = () =>
				{
					this.#video.pause();
					this.#open('');
					this.#video.load();
				};
			}
			else
			{
				this.#open = (url) => console.log(`cant open ${url}`);
				this.#close = () => null;
			}
		}
		
	}
	open(url)
	{
		this.#open(this.#playurl = url);
	}
	suspend()
	{
		this.#close();
	}
	resume()
	{
		this.#open(this.#playurl);

		// this.#video.onloadedmetadata = ()=>{
		// 	alert(1)
		// };



		this.currentTime = this.#playtime;
		this.#video.play();
	}
	connectedCallback()
	{
		this.appendChild(this.#video);
		if (this.dataset.load)
		{
		
			loader(`${this.dataset.load}/cover`, null, 'application/octet-stream').then(blob => this.#video.poster = URL.createObjectURL(blob));
			this.#playurl = `${this.dataset.load}/${this.#model instanceof HTMLVideoElement ? 'play.m3u8' : 'play'}`;
			if (this.preload !== 'none')
			{
				this.open(this.#playurl);
			}
		}
	}
	disconnectedCallback()
	{
		this.#close();
	}
});
document.head.appendChild(document.createElement('style')).textContent = `
webapp-slide{
	height: 90%;
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
	#load;
	#page = 1;
	#template;
	#height;
	#slide;

	//#control;
	
	
	

	#index = 0;
	#shift = true;
	#position = {

	};
	constructor()
	{
		super();
		this.#load = this.dataset.load;
		this.#slide = document.createElement('div');
		this.#slide.style.top = '0px';
		this.#slide.addEventListener('transitionend', () =>
		{
			this.#slide.classList.remove('shifting');
			this.#shift = false;
		});
		this.addEventListener('touchstart', event =>
		{
			
			if (this.#shift) return;
			this.#position.offset = this.#slide.offsetTop;
			this.#position.start = event.clientY || event.touches[0].clientY;
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
			console.log(this.#shift)
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
					}
					else
					{
						// this.#shift = true;
						// this.#loaddata();
					}
				}
			}
			
			const top = this.#index * this.#height;
			

			console.log(parseInt(this.#slide.style.top) );


			if (parseInt(this.#slide.style.top) === -top) return;
			this.#shift = true;
			this.#slide.classList.add('shifting');
			this.#slide.style.top = `-${top}px`;

			this.#slide.childNodes[this.#index].resume();

		});

		
	}
	#createvideo(data)
	{
		const
		video = document.createElement('webapp-video');
		video.style.height = `${this.#height}px`;
		video.preload = 'none';
		//video.controls = true;
		video.dataset.load = data.path;

		video.appendChild(document.importNode(this.#template.content, true));


		this.#slide.appendChild(video);
	}
	#loaddata()
	{
		loader(`${this.#load}${this.#page++}`, {headers: {'Content-Type': 'application/data-stream'}}, 'application/json').then(result =>
		{
			result.data.data.forEach(data =>
			{
				this.#createvideo(data);
			});
			this.#shift = false;
			return this.#index;
		}).then(index => {
			
			console.log(this.#slide.childNodes[index].resume())
		});
	}
	connectedCallback()
	{
		this.appendChild(this.#slide);
		requestAnimationFrame(() =>
		{
			this.#template = this.firstElementChild;
			this.#height = this.offsetHeight;
			this.#load && this.#loaddata();
		});
	}
});