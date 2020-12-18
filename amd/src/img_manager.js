define(['jquery'], function($) {/* eslint-disable no-console */
    var dont_block = true;
    var compteur = 0;
    function urlExists(url, callback) {
        fetch(url, { method: 'head' })
        .then(function(status) {
          callback(status.ok);
        });
    }
    return {
        init: function(rootpath) {
        /*    $(".form-control").prop( "disabled", true);
            $("#id_title").prop( "disabled", false);*/
            $(".fp-content").bind('DOMSubtreeModified', function() {
                    var url = $(".realpreview").attr("src");
                    urlExists(url, function(exists) {
                        if (exists && dont_block) {
                          // console.log($("input[name=mediafile]").val()+""+url); 
                            dont_block = false;

                            $.ajax({
                                method: "POST",
                                url: rootpath+"/mod/escape/get_url_image_ajax.php",
                                data: { themid: $("input[name=mediafile]").val() }
                            })
                                .done(function(url) {
                                    var tmpImg = new Image();
                                    tmpImg.src=url;

                                    $(tmpImg).one('load',function(){
                                        var orgWidth = tmpImg.width;
                                        var orgHeight = tmpImg.height;
                                        if (orgWidth >600) {
                                            var ratio = 600 / orgWidth;
                                            orgHeight = orgHeight * ratio;
                                            orgWidth = 600;
                                        }

                                        $("#image_container")
                                        .html("<div class='col-md-3'></div>"+
                                          "<div class='col-md-9 form-inline felement'>"+
                                              '<svg id="svg_viewer" class="svg_viewer" height="'+orgHeight+'" width="'+
                                              orgWidth+'" '+
                                              'onclick="require(\'mod_escape/img_manager\').coordsandintro(event)">'+
                                                  '<image xlink:href="'+url+'" x="0" y="0"/ style="width: '+orgWidth+'px;">'+
                                              "</svg>"+
                                        "</div>"+
                                        "<div class='col-md-3'></div>"+
                                        "<div class='col-md-9 form-inline felement'>"+
                                          "<span style='color: blue;cursor: pointer;' id='erase'>Erase All</span>"+
                                        "</div>"
                                                );

                                        $("#id_contents_editor")
                                        .html('<svg id="svg_viewer" class="svg_viewer" height="'+orgHeight+
                                        '" width="'+orgWidth+'" '+
                                              'onclick="require(\'mod_escape/img_manager\').coordsandintro(event)">'+
                                                  '<image xlink:href="'+url+'" x="0" y="0"/ style="width: '+orgWidth+'px;">'+
                                              "</svg>");

                                        $('#id_contents_editoreditable').html($('#svg_viewer')[0].outerHTML);

                                        $( "#erase" ).click(function() {
                                                require('mod_escape/img_manager').eraseall();
                                            });
                                    });
                            });
                        }
                    });
           });

          $("#id_introduction").on("input paste", function() {
            $('#id_contents_editoreditable').html("<span id='intro_text_clicking_pix'>"+
                    $("#id_introduction").val()+"</span><br />"+$('#svg_viewer')[0].outerHTML);
            $("#id_contents_editor").html("<span id='intro_text_clicking_pix'>"+
                    $("#id_introduction").val()+"</span><br />"+$('#svg_viewer')[0].outerHTML);
            $("#editor_atto_content_wrap").html("<span id='intro_text_clicking_pix'>"+
                   $("#id_introduction").val()+"</span><br />"+$('#svg_viewer')[0].outerHTML);
          });

           if($("#id_contents_editor").text().includes('svg')){
               $("#image_container")
                                        .html("<div class='col-md-3'></div>"+
                                          "<div class='col-md-9 form-inline felement'>"+$("#id_contents_editor").text()+
                                        "</div>"+
                                        "<div class='col-md-3'></div>"+
                                        "<div class='col-md-9 form-inline felement'>"+
                                          "<span style='color: blue;cursor: pointer;' id='erase'>Erase All</span>"+
                                        "</div>"
                                                );
                $('#id_contents_editoreditable').html($('#svg_viewer')[0].outerHTML);
                $( "#erase" ).click(function() {
                    require('mod_escape/img_manager').eraseall();
               });
           }

           if($("#svg_viewer").children().length-1 > 0) {
               compteur = $("#svg_viewer").children().length-1;
           }

           //reset intro clean
           $("#id_introduction").val( $("#image_container #intro_text_clicking_pix").html());
           $("#image_container #intro_text_clicking_pix").remove();
        },
        coordsandintro: function(event) {
            require('mod_escape/img_manager').coords(event);
            $('#id_contents_editoreditable').html("<span id='intro_text_clicking_pix'>"+
                    $("#id_introduction").val()+"</span><br />"+$('#svg_viewer')[0].outerHTML);
            $("#id_contents_editor").html("<span id='intro_text_clicking_pix'>"+
                    $("#id_introduction").val()+"</span><br />"+$('#svg_viewer')[0].outerHTML);
            $("#editor_atto_content_wrap").html("<span id='intro_text_clicking_pix'>"+
                   $("#id_introduction").val()+"</span><br />"+$('#svg_viewer')[0].outerHTML);
        },
        coords: function(event) {
            if (compteur < 5) {
                var x = event.clientX;
                var y = event.clientY;

                var svg_viewer = document.getElementById('svg_viewer');
                var positions = require('mod_escape/img_manager').elementPosition(svg_viewer);

                x = x - positions.viewportX;
                y = y - positions.viewportY;

                svg_viewer.innerHTML = svg_viewer.innerHTML +
                        '<circle cx="'+x+'" cy="'+y+'" r="10" stroke="#4b6c0b" fill="#9dcc41" class="puce"></circle>';

                $('#id_contents_editoreditable').html($('#svg_viewer')[0].outerHTML);
                $("#id_contents_editor").html($('#svg_viewer')[0].outerHTML);
                $("#editor_atto_content_wrap").html($('#svg_viewer')[0].outerHTML);
                $("#id_answer_editor_"+compteur).val("("+x+","+y+")");
                compteur++;
            }
        },
        elementPosition : function(a) {
            var b = a.getBoundingClientRect();
            return {
              clientX: a.offsetLeft,
              clientY: a.offsetTop,
              viewportX: (b.x || b.left),
              viewportY: (b.y || b.top)
            };
          },
          eraseall : function() {
                $( ".puce" ).remove();
                for (var i = 0; i <= compteur; i++) {
                    $("#id_answer_editor_"+i).val("");
                }
                compteur=0;
                var svg_viewer = document.getElementById('svg_viewer');
                $("#id_contents_editor").html(svg_viewer.innerHTML);
          }
    };
});