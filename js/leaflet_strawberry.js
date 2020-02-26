(function ($, Drupal, drupalSettings, L) {

    'use strict';

    Drupal.behaviors.format_strawberryfield_leaflet_initiate = {
        attach: function(context, settings) {
            $('.strawberry-leaflet-item[data-iiif-infojson]').once('attache_leaflet')
                .each(function (index, value) {
                    // Get the node uuid for this element
                    var element_id = $(this).attr("id");
                    // Check if we got some data passed via Drupal settings.
                    if (typeof(drupalSettings.format_strawberryfield.leaflet[element_id]) != 'undefined') {

                        $(this).height(drupalSettings.format_strawberryfield.leaflet[element_id]['height']);
                        $(this).width(drupalSettings.format_strawberryfield.leaflet[element_id]['width']);
                        // Defines our basic options for leaflet GEOJSON

                        // initialize the map
                        var map = L.map('map').setView([42.35, -71.08], 13);

                        var geojsonLayer = L.geoJson.ajax(drupalSettings.format_strawberryfield.leaflet[element_id]['geojsonurl']);

                        // load a tile layer
                        L.tileLayer('http://tiles.mapc.org/basemap/{z}/{x}/{y}.png',
                            {
                                attribution: 'Tiles by <a href="http://mapc.org">MAPC</a>, Data by <a href="http://mass.gov/mgis">MassGIS</a>',
                                maxZoom: 17,
                                minZoom: 9
                            }).addTo(map);

                        function popUp(f,l){
                            var out = [];
                            if (f.properties){
                                for(key in f.properties){
                                    out.push(key+": "+f.properties[key]);
                                }
                                l.bindPopup(out.join("<br />"));
                            }
                        }
                       // var jsonTest = new L.GeoJSON.AJAX(["colleges.geojson","counties.geojson"],{onEachFeature:popUp}).addTo(m);


                        var $firstgeojson = [drupalSettings.format_strawberryfield.leaflet[element_id]['geojsonurl']];
                        var $allgeojsons = $firstgeojson.concat(drupalSettings.format_strawberryfield.leaflet[element_id]['geojsonother']);
                        var $secondgeojson = drupalSettings.format_strawberryfield.leaflet[element_id]['geojsonother'].find(x=>x!==undefined);

                        if (Array.isArray($allgeojsons) && $allgeojsons.length && typeof($secondgeojson) != 'undefined') {

                            $allgeojsons.forEach(geojsonURL => {
                                // TODO Provider should be passed by metadata at
                                // \Drupal\format_strawberryfield\Plugin\Field\FieldFormatter\StrawberryleafletFormatter::viewElements
                                // Deal with this for Beta3
                                geojsonLayer.addUrl("geojsonURL");//we now have 2 layers
                            })
                        }
                        //@TODO add an extra geojsons key with every other one so people can select the others.
                        // load a tile layer
                        L.tileLayer('http://tiles.mapc.org/basemap/{z}/{x}/{y}.png',
                            {
                                attribution: 'Tiles by <a href="http://mapc.org">MAPC</a>, Data by <a href="http://mass.gov/mgis">MassGIS</a>',
                                maxZoom: 17,
                                minZoom: 9
                            }).addTo(map);
                        geojsonLayer.addTo(map);
                        console.log('initializing leaflet 1.6.0')
                    }
                })}}
})(jQuery, Drupal, drupalSettings, window.L);