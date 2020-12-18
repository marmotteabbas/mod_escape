define(['jquery','mod_escape/leaflet'], function($, L) {
    var mymap;
    return {
        init: function() {

            if ($("input[name=location]").val() !== "") {
                var coord = $("input[name=location]").val().replace('[', '').replace(']', '');
                var split = coord.split(",");
            } else {
                var split = [45.17235628126675,5.767478942871095];
            }

            mymap = L.map('mapid').setView([split[0], split[1]], 13);
            L.tileLayer('https://api.mapbox.com/styles/v1/{id}/tiles/{z}/{x}/{y}?'+
                    'access_token=pk.eyJ1IjoibWFwYm94IiwiYSI6ImNpejY4NXVycTA2emYycXBndHRqcmZ3N3gifQ.rJcFIG214AriISLbB6B5aw', {
                maxZoom: 18,
                attribution: 'Map data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, ' +
                'Imagery © <a href="https://www.mapbox.com/">Mapbox</a>',
                id: 'mapbox/streets-v11',
                tileSize: 512,
                zoomOffset: -1
            }).addTo(mymap);

            if ($("input[name=location]").val() !== "") {
                L.marker([split[0], split[1]]).addTo(mymap).bindPopup("Stage Place").openPopup();
                $("input[name='location']").val("["+split[0]+","+split[1]+"]");
            }

            mymap.on('click', this.onMapClick);
        },onMapClick: function(e) {
            $(".leaflet-marker-icon").remove();
            $(".leaflet-popup").remove();
            $(".leaflet-shadow-pane").remove();

            L.marker([e.latlng.lat, e.latlng.lng]).addTo(mymap).bindPopup("Stage Place").openPopup();
            $("input[name='location']").val("["+e.latlng.lat+","+e.latlng.lng+"]");
        }
    };
});