define(['jquery'], function($) {/* eslint-disable no-console*/
    return {
        init: function() {
        $("circle").click(function() {
            $("button:contains("+$(this).attr("cx")+","+$(this).attr("cy")+")").click();
        });
        }
    };
});