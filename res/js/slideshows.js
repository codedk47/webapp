document.head.appendChild(document.createElement('style')).textContent = `
webapp-slideshows{
	display: block;
	position: relative;
	overflow: hidden;

	min-height: 12rem;
}
webapp-slideshows>div:first-child{
	width: 300%;
	position: absolute;
	top:0; bottom:0;
	overflow: hidden;
	display: flex;
}
webapp-slideshows>div:first-child>a{
	width: 100%;
	display: block;
	
}

webapp-slideshows>div:first-child>a:nth-child(1){background:red;}
webapp-slideshows>div:first-child>a:nth-child(2){background:green;}
webapp-slideshows>div:first-child>a:nth-child(3){background:blue;}
`;

customElements.define('webapp-slideshows', class extends HTMLElement
{
	#slide = document.createElement('div');
	#slides = [
		document.createElement('a'),
		document.createElement('a'),
		document.createElement('a')
	];
	#contents = [];

	#slider;
	#duration;
	#index;

	#onclick = event => event.isTrusted;
	constructor()
	{
		super();
		this.#slide.style.left = '-100%';
		this.#slide.append(...this.#slides.map(anchor => (anchor.onclick = event => this.#onclick(event), anchor)));


		let transitioning = false, currentindex = 0, beforeindex = 0;
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
			if (transitioning) return;
			event.preventDefault();
			position.move = event.clientX || event.touches[0].clientX;
			this.#slide.style.left = `${position.offset + position.move - position.start}px`;
		});
		this.#slide.addEventListener('touchend', this.#slider = offset =>
		{
			if (transitioning) return;
			let [left, direction, clicked] = typeof offset === 'boolean'
				? [this.#slide.style.left, offset ? -101 : 101, false]
				: [position.left, position.start - position.move, this.#slide.style.left === position.left];
			if (clicked)
			{
				return;
			}
			if (this.#contents.length && Math.abs(direction) > 100)
			{
				beforeindex = currentindex;

				if (direction < 0)
				{
					if (--currentindex < 0)
					{
						currentindex = this.#contents.length - 1;
					}
					left = '0px';
				}
				else
				{
					if (++currentindex >= this.#contents.length)
					{
						currentindex = 0;
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
				console.log(this.#slide.style.left)
				this.#slide.style.left = '-100%';
				this.#setcontent(...this.#slide.style.left === '0px'
					? [this.#slide.insertBefore(this.#slide.lastElementChild, this.#slide.firstElementChild),
						this.#contents[currentindex - 1 < 0 ? this.#contents.length - 1 : currentindex - 1]]
					: [this.#slide.appendChild(this.#slide.firstElementChild),
						this.#contents[currentindex + 1 >= this.#contents.length ? 0 : currentindex + 1]]);
				
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
			case 0: this.#setcontent(this.#slides[1], content);
			case 1: this.#setcontent(this.#slides[2], content);
			case 2: this.#setcontent(this.#slides[0], content);
		}
		this.#contents[this.#contents.length] = content;
	}
	connectedCallback()
	{
		this.#duration = parseInt(this.dataset.duration, 10) || 4;
		this.appendChild(this.#slide);
		requestAnimationFrame(() =>
		{
			const contents = JSON.parse(this.lastChild.nodeValue);
			Array.isArray(contents) && contents.forEach(content => this.addition(content));
			// setInterval(() => {
				
			// }, parseInt(this.dataset.duration, 10) || 4);
		
		});
	}
});