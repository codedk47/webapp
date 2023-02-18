then(function(){
	console.log( framer.asd );
	// console.log(framer.asd)
	const video = document.querySelector('webapp-video');
	if (video)
	{

		//video.m3u8('https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8');

		video.m3u8('/2302/MDSE00015434/play', 1000 << 16 | 600);

		video.loaded(function(){


			console.log(video.horizontal)

		});

		const qq = ['/2302/MDSE00015528/play', '/2302/MDSE00015531/play', '/2302/MDSE00015503/play'];


		video.finish(asd=>{
			//framer.dialog.show('接下来往后看。。。');
			if (qq.length)
			{
				video.m3u8(qq.shift(), 1000 << 16 | 1);
			}
		});

		
	}
});