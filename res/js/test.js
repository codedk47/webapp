then(function(){
	console.log( framer.asd );
	// console.log(framer.asd)
	const player = document.querySelector('webapp-video');
	if (player)
	{

	    player.play('/2302/MDSE00015434/play', 1000 << 16 | 1);

		player.oncanplay

		const qq = ['/2302/MDSE00015528/play', '/2302/MDSE00015531/play', '/2302/MDSE00015503/play'];


		player.finish(asd=>{
			//framer.dialog.show('接下来往后看。。。');
			if (qq.length)
			{
				player.play(qq.shift(), 1000 << 16 | 1);
			}
		});

		
	}
});