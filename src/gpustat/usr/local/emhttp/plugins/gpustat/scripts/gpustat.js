const gpustat_status = () => {
    $.getJSON('/plugins/gpustat/gpustatus.php', (data) => {
        if(data) {
            // Nvidia Slider Bars
            $('.gpu-memclockbar').removeAttr('style').css('width', data["memclock"] / data["memclockmax"] * 100 + "%");
            $('.gpu-gpuclockbar').removeAttr('style').css('width', data["clock"] / data["clockmax"] * 100 + "%");
            $('.gpu-utilbar').removeAttr('style').css('width', data["util"]);
            $('.gpu-memutilbar').removeAttr('style').css('width', data["memutil"]);
            $('.gpu-encutilbar').removeAttr('style').css('width', data["encutil"]);
            $('.gpu-decutilbar').removeAttr('style').css('width', data["decutil"]);
            $('.gpu-fanbar').removeAttr('style').css('width', data["fan"]);
            $('.gpu-powerbar').removeAttr('style').css('width', parseInt(data["power"].replace("W","") / data["powermax"] * 100) + "%");
            $('.gpu-rxutilbar').removeAttr('style').css('width', parseInt(data["rxutil"] / data["pciemax"] * 100) + "%");
            $('.gpu-txutilbar').removeAttr('style').css('width', parseInt(data["txutil"] / data["pciemax"] * 100) + "%");

            // Intel Slider Bars
            $('.gpu-3drenderbar').removeAttr('style').css('width', data["3drender"]);
            $('.gpu-blitterbar').removeAttr('style').css('width', data["blitter"]);
            $('.gpu-videobar').removeAttr('style').css('width', data["video"]);
            $('.gpu-videnhbar').removeAttr('style').css('width', data["videnh"]);
            $('.gpu-powerutilbar').removeAttr('style').css('width', data["powerutil"]);

            // App Using Hardware
            if (data["plexusing"]) {
                $('.gpu-img-span-plex').css('display', "inline");
            } else {
                $('.gpu-img-span-plex').css('display', "none");
            }
            if (data["jellyusing"]) {
                $('.gpu-img-span-jelly').css('display', "inline");
            } else {
                $('.gpu-img-span-jelly').css('display', "none");
            }
            if (data["embyusing"]) {
                $('.gpu-img-span-emby').css('display', "inline");
            } else {
                $('.gpu-img-span-emby').css('display', "none");
            }

            $.each(data, function (key, data) {
                $('.gpu-'+key).html(data);
            })
        }
    });
};

const gpustat_dash = () => {
    // append data from the table into the correct one
    $('#db-box1').append($('.dash_gpustat').html());

    // reload toggle to get the correct state
    toggleView('dash_gpustat_toggle', true);

    // reload sorting to get the stored data (cookie)
    sortTable($('#db-box1'),$.cookie('db-box1'));
}