document.head.appendChild(document.createElement('style')).textContent = `
webapp-slideshows{
	display: block;
	position: relative;
	overflow: hidden;
	color: transparent;
}
webapp-slideshows>div:first-child{
	width: 300%;
	display: flex;
	position: absolute;
	top: 0;
	bottom: 0;
}
webapp-slideshows>div:first-child>a{
	width: 100%;
	display: block;
	background: linear-gradient(to bottom right, silver, transparent);
	background-repeat: no-repeat;
	background-position: center;
	background-size: cover;
}`;
customElements.define('webapp-slideshows', class extends HTMLElement
{
	#slide = document.createElement('div');
	#anchor = [
		document.createElement('a'),
		document.createElement('a'),
		document.createElement('a')
	];
	#contents = [];
	#slider;
	#index = 0;
	constructor()
	{
		super();
		this.#slide.style.left = '-100%';
		this.#slide.append(...this.#anchor.map(anchor =>
		{
			anchor.onclick = event =>
			{
				event.stopPropagation();
				return this.#index < this.#contents.length && this.onclick
					? this.onclick.call(this.#contents[this.#index], event)
					: event.isTrusted;
			};
			return anchor;
		}));
		let transitioning = false;
		const position = {
			offset: 0,
			start: 0,
			move: 0,
			left: '-100%'
		};
		this.#slide.addEventListener('touchstart', event =>
		{
			if (transitioning) return;
			position.offset = this.#slide.offsetLeft;
			position.start = event.clientX || event.touches[0].clientX;
			position.left = this.#slide.style.left;
		});
		this.#slide.addEventListener('touchmove', event =>
		{
			event.preventDefault();
			if (transitioning) return;
			position.move = event.clientX || event.touches[0].clientX;
			this.#slide.style.left = `${position.offset + position.move - position.start}px`;
		});
		this.#slide.addEventListener('touchend', this.#slider = offset =>
		{
			if (transitioning) return;
			let [left, direction, clicked] = typeof offset === 'boolean'
				? [this.#slide.style.left, offset ? -101 : 101, this.#slide.style.left !== '-100%']
				: [position.left, position.start - position.move, this.#slide.style.left === position.left];
			if (clicked)
			{
				return;
			}
			if (this.#contents.length && Math.abs(direction) > 100)
			{
				if (direction < 0)
				{
					if (--this.#index < 0)
					{
						this.#index = this.#contents.length - 1;
					}
					left = '0px';
				}
				else
				{
					if (++this.#index >= this.#contents.length)
					{
						this.#index = 0;
					}
					left = '-200%';
				}
			}
			if (this.#slide.style.left !== left)
			{
				transitioning = true;
				this.#slide.style.transition = 'left .4s ease';
				this.#slide.style.left = left;
			}
		});
		this.#slide.addEventListener('transitionend', () =>
		{
			this.#slide.style.transition = null;
			if (this.#slide.style.left !== '-100%')
			{
				this.#setcontent(...this.#slide.style.left === '0px'
					? [this.#slide.insertBefore(this.#slide.lastElementChild, this.#slide.firstElementChild),
						this.#contents[this.#index - 1 < 0 ? this.#contents.length - 1 : this.#index - 1]]
					: [this.#slide.appendChild(this.#slide.firstElementChild),
						this.#contents[this.#index + 1 >= this.#contents.length ? 0 : this.#index + 1]]);
				this.#slide.style.left = '-100%';
			}
			transitioning = false;
		});
	}
	#setcontent(node, content)
	{
		node.style.backgroundImage = `url(${content.picture})`;
		node.href = 'support' in content ? content.support : 'javascript:;';
	}
	addition(content)
	{
		switch (this.#contents.length)
		{
			case 0: this.#setcontent(this.#anchor[1], content);
			case 1: this.#setcontent(this.#anchor[2], content);
			case 2: this.#setcontent(this.#anchor[0], content);
		}
		this.#contents[this.#contents.length] = content;
	}
	slide(leftorright)
	{
		this.#slider(Boolean(leftorright));
	}
	connectedCallback()
	{
		this.appendChild(this.#slide);
		requestAnimationFrame(() =>
		{
			alert(this.lastChild.nodeValue);
			const contents = JSON.parse(this.lastChild.nodeValue);
			//this.removeChild(this.lastChild);
			Array.isArray(contents) && contents.forEach(content => this.addition(content));
			setInterval(this.#slider, this.dataset.duration * 1000 || 4000, this.hasAttribute('reverse'));
		});
	}
});