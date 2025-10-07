function slideshow(element, duration = 4)
{
	const
	slidebox = document.createElement('div'),
	slideloop = [
		document.createElement('a'),
		document.createElement('a'),
		document.createElement('a')
	],
	indexbox = document.createElement('div'),
	position = {
		offset: 0,
		start: 0,
		move: 0
	}, contents = [];
	

	element.style.position = 'relative';
	element.style.overflow = 'hidden';

	let transitioning = false, currentindex = 0, beforeindex = 0, leftoffset, direction;
	function indexchange()
	{
		indexbox.childNodes[beforeindex].style.backgroundColor = 'black';
		indexbox.childNodes[beforeindex].style.borderColor = 'white';


		indexbox.childNodes[currentindex].style.backgroundColor = 'white';
		indexbox.childNodes[currentindex].style.borderColor = 'black';
	}
	function setcontent(index, picture, support, id)
	{
		const node = typeof index === 'number' ? slideloop[index] : index;
		node.style.backgroundImage = `url(${picture})`;
		node.href = support;
		node.dataset.id = id;
	}

	duration *= 1000;
	let beforetime = +new Date;
	requestAnimationFrame(function slider()
	{
		const nowtime = +new Date;
		if (nowtime - beforetime > duration)
		{
			beforetime = nowtime;
			if (transitioning === false && contents.length)
			{
				beforeindex = currentindex;
				if (++currentindex >= contents.length)
				{
					currentindex = 0;
				}
				direction = 1;
				slidebox.style.transition = 'left .4s ease';
				slidebox.style.left = leftoffset = '-200%';
				transitioning = true;
			}
		}
		setTimeout(() => requestAnimationFrame(slider), 1000);
	});

	slidebox.style.cssText = 'width:300%;position:absolute;top:0;bottom:0;overflow:hidden;display:flex;left:-100%';
	slidebox.addEventListener('transitionend', () =>
	{
		slidebox.style.transition = null;
		//console.log(currentindex);
		
		if (leftoffset !== '-100%')
		{
			beforetime = +new Date;
			slidebox.style.left = leftoffset = '-100%';
			if (direction < 0)
			{
				const content = contents[currentindex - 1 < 0 ? contents.length - 1 : currentindex - 1];
				setcontent(slidebox.insertBefore(slidebox.lastElementChild, slidebox.firstElementChild),
					content.picture, content.support, content.id);
			}
			else
			{
				const content = contents[currentindex + 1 >= contents.length ? 0 : currentindex + 1];
				setcontent(slidebox.appendChild(slidebox.firstElementChild),
					content.picture, content.support, content.id);
			}
			indexchange();
		}
		transitioning = false;
	});
	slidebox.addEventListener('touchstart', event =>
	{
		if (transitioning) return;
		position.offset = slidebox.offsetLeft;
		position.start = event.clientX || event.touches[0].clientX;
	});
	slidebox.addEventListener('touchmove', event =>
	{
		if (transitioning) return;
		event.preventDefault();
		position.move = event.clientX || event.touches[0].clientX;
		slidebox.style.left = `${position.offset + position.move - position.start}px`;
	});
	slidebox.addEventListener('touchend', () =>
	{
		if (transitioning) return;
		leftoffset = '-100%';
		beforeindex = currentindex;
		direction = position.start - position.move;
		if (contents.length && Math.abs(direction) > 100)
		{
			if (direction < 0)
			{
				if (--currentindex < 0)
				{
					currentindex = contents.length - 1;
				}
				leftoffset = 0;
			}
			if (direction > 0)
			{
				if (++currentindex >= contents.length)
				{
					currentindex = 0;
				}
				leftoffset = '-200%';
			}
		}
		if (slidebox.style.left !== '-100%')
		{
			transitioning = true;
		}
		slidebox.style.transition = 'left .4s ease';
		slidebox.style.left = leftoffset;
	});


	slidebox.append(...slideloop.map((node, i) =>
	{
		node.style.width = '100%';
		node.style.backgroundPosition = 'center';
		node.style.backgroundRepeat = 'no-repeat';
		node.style.backgroundSize = '100% 100%';
		// node.style.backgroundColor = ['red', 'blue', 'green'][i];
		// node.textContent = ['red', 'blue', 'green'][i];
		return node;
	}));

	indexbox.style.cssText = 'position:absolute;bottom:0;left:0;right:0;display:flex;justify-content:center;gap:1rem;padding:1rem';
	element.append(slidebox, indexbox);
	function addition(picture, support, id)
	{
		const span = document.createElement('span');
		span.style.cssText = 'width:.5rem;height:.5rem;display:inline-block;background-color:black;border:.1rem solid white;border-radius:50%;opacity:0.8';
		indexbox.appendChild(span);
		if (contents.length < 3)
		{
			switch (contents.length)
			{
				case 0: setcontent(1, picture, support, id); indexchange();
				case 1: setcontent(2, picture, support, id);
				case 2: setcontent(0, picture, support, id);
			}
		}
		contents[contents.length] = {picture, support, id};
	}

	return (additions, loader) =>
	{
		loader
			? loader(additions.picture, {mask: additions.mask}).then(blob => addition(blob, additions.support, additions.id))
			: addition(additions.picture, additions.support, additions.id);
	};
}