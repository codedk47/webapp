then(function(){

});


window.addEventListener('DOMContentLoaded', async event =>
{

    const player = document.querySelector('webapp-video');
    if (player)
    {
        player.play('/2302/MDSE00014224/play', 1000 << 16 | 1);
        player.finish(asd=>{

            framer.dialog.show('接下来往后看。。。');
        });

        
    }
});