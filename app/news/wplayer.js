customElements.define('webapp-video', class extends HTMLVideoElement
{
	#model;
	#open;
	#close;
	#playurl;
	#playtime;
	constructor()
	{
		super();
		this.playsinline = true;
		this.disablepictureinpicture = true;
		if (window.MediaSource && window.Hls)
		{
			
			this.#model = window.hls instanceof window.Hls ? window.hls : window.hls = new window.Hls;
			this.#open = (url) =>
			{
				this.#model.loadSource(url);
				this.#model.attachMedia(this);
				// return new Promise((resolve, reject) =>
				// {
				// 	this.#model.attachMedia(this.#video);
				// 	this.#model.once(window.Hls.Events.MANIFEST_PARSED, () => resolve(this));
				// 	this.#model.once(window.Hls.Events.MEDIA_ATTACHED, () => this.#model.loadSource(url));
				// });
			};
			this.#close = () =>
			{
				this.#model.detachMedia(this);
			};
		}
		else
		{
			if (this.canPlayType('application/vnd.apple.mpegurl')
				|| this.canPlayType('application/x-mpegURL')) {
				this.#model = this;
				this.#open = (url) =>
				{
					this.src = url;
				};
				this.#close = () =>
				{
					this.pause();
					this.#open('');
					this.load();
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
		this.play();
	}
	connectedCallback()
	{
		const open = this.getAttribute('open');
		if (open)
		{
			loader(`${open}/cover`, null, 'application/octet-stream').then(blob => this.poster = URL.createObjectURL(blob));
			this.#playurl = `${open}/${this.#model instanceof HTMLVideoElement ? 'play.m3u8' : 'play'}`;
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
}, {extends: 'video'});
/*
customElements.define('webapp-video', class extends HTMLElement
{
	#style;
	#video;
	#bar;
	#range;
	#togglescreen;
	#togglemuted;


	#model;
	#open;
	#close;
	#playtime = 0;
	#playurl;
	constructor()
	{
		super();
		this.#style = document.createElement('style');
		this.#style.textContent = `
			:host{
				background: white;
				display: block;
				overflow: hidden;
				position: relative;
				border: 1px solid black;
			}
			:host>video{

			}
			:host>div{
				display: flex;
			}
			:host>div>input[type=range]{
				
				width: 100%;
				margin: 0;
			}

		`;

		this.#video = document.createElement('video');
		this.#video.setAttribute('autoplay', true);
		this.#video.setAttribute('playsinline', true);
		this.#video.setAttribute('controlslist', 'nodownload');
		
		this.#video.setAttribute('disablepictureinpicture', true);
		this.#video.setAttribute('disableRemotePlayback', true);
		this.#video.setAttribute('width', '100%');
		this.#video.setAttribute('height', '100%');
	

		if (window.MediaSource && window.Hls)
		{
			
			this.#model = window.hls instanceof window.Hls ? window.hls : window.hls = new window.Hls;
			this.#open = (url) =>
			{
				// this.#model.loadSource(url);
				// this.#model.attachMedia(this.#video);
				return new Promise((resolve, reject) =>
				{
					this.#model.attachMedia(this.#video);
					this.#model.once(window.Hls.Events.MANIFEST_PARSED, () => resolve(this));
					this.#model.once(window.Hls.Events.MEDIA_ATTACHED, () => this.#model.loadSource(url));
				});
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
					alert(url)
					this.#model.src = url;
				};
				this.#close = () =>
				{
					this.#model.pause();
					this.#open('');
					this.#model.load();
				};
			}
			else
			{
				this.#open = (url) => console.log(`cant open ${url}`);
				this.#close = () => null;
			}
		}

		this.#bar = document.createElement('div');

		this.#togglemuted = document.createElement('button');
		this.#togglemuted.textContent = 'muted';
		this.#togglemuted.addEventListener('click', () => {
			this.#video.muted = !this.#video.muted;
		});

		this.#range = document.createElement('input');
		this.#range.type = 'range';
		this.#range.step = 'any';
		this.#range.value = 0;
		this.#range.min = 0;
		this.#range.max = 1;


		this.#togglescreen = document.createElement('button');
		this.#togglescreen.textContent = 'full';
		this.#togglescreen.addEventListener('click', () => {
			this.#video.requestFullscreen();
		});

		this.#bar.append(this.#togglemuted, this.#range, this.#togglescreen);


		this.#video.addEventListener('contextmenu', event => event.preventDefault());
		this.#video.addEventListener('timeupdate', () =>
		{
			this.#range.value = (this.#playtime = this.#video.currentTime) / this.#video.duration;
		});
		//this.#video.addEventListener('loadedmetadata', () => this.#playable = true);
		this.#video.addEventListener('click', () =>
		{
			if (this.#video.duration) this.#video.paused ? this.#video.play() : this.#video.pause();
		});
		this.#range.addEventListener('mousedown', () =>
		{
			if (this.#video.duration) this.#video.pause();
		});
		this.#range.addEventListener('mouseup', () =>
		{
			if (this.#video.duration) this.#video.play();
		});
		this.#range.addEventListener('change', () =>
		{
			if (this.#video.duration) this.#video.currentTime = this.#video.duration * this.#range.value;
		});
		this.attachShadow({mode: 'closed'}).append(this.#style, this.#video, this.#bar);
	}
	poster(url)
	{
		this.#video.poster = url;
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



		this.#video.currentTime = this.#playtime;
		this.#video.play();
	}
// 下面4个方法为常用生命周期
	connectedCallback()
	{
		const open = this.getAttribute('open'), preload = this.getAttribute('preload');
		if (open)
		{
			loader(`${open}/cover`, null, 'application/octet-stream').then(blob => this.poster(URL.createObjectURL(blob)));
			this.#playurl = `${open}/${this.#model instanceof HTMLElement ? 'play.m3u8' : 'play'}`;
			if (preload !== 'none')
			{
				this.open(this.#playurl);
			}
		}
		//console.log('自定义元素加入页面');
		// 执行渲染更新
		//this._updateRendering();
	}

  disconnectedCallback() {
    // 本例子该生命周期未使用，占位示意
    console.log('自定义元素从页面移除');
  }
  adoptedCallback() {
    // 本例子该生命周期未使用，占位示意
    console.log('自定义元素转移到新页面');
  }

  attributeChangedCallback(name, oldValue, newValue) {
    console.log('自定义元素属性发生变化');
    // this._rows = newValue;
    // // 执行渲染更新
    // this._updateRendering();
  }

  // 设置直接get/set rows属性的方法
  get rows() {

    return this._rows;
  }
  set rows(v) {
    this.setAttribute('rows', v);
  }

  _updateRendering() {
    // 根据变化的属性，改变组件的UI
    // var shadow = this.shadowRoot;
    // var childNodes = shadow.childNodes;
    // var rows = this._rows;
    // for (var i = 0; i < childNodes.length; i++) {
    //   if (childNodes[i].nodeName === 'STYLE') {
    //     childNodes[i].textContent = `div {
    //       display: -webkit-box;
    //       -webkit-line-clamp: ${rows};
    //       -webkit-box-orient: vertical;
    //       overflow: hidden;
    //     }`;
    //   }
    // }
  }

});
*/