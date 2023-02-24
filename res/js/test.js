function asd()
{
	const video = document.querySelector('webapp-video');
	if (video)
	{
		video.m3u8('/2302/MDSE00015434/play', 1000 << 16 | 1);
	}
}

self.onmessage = event =>
{
	//alert('eeeeeeeeeeeeeee');
	const video = document.querySelector('webapp-video1');
	if (video)
	{

		// video.mask = false;
		// video.m3u8('https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8');

		//video.m3u8('/2302/MDSE00015434/play', 1000 << 16 | 1);

		// video.loaded(function(){
		// 	video.loaded(null);
		// 	setTimeout(()=>{
		// 		video.m3u8('/2302/MDSE00015434/play');

		// 	}, 5000)
		// });

	}
};