window.onload = function() {
    if (window.jQuery) {
        initBindings();
    } else {
        // jQuery is not loaded
        console.log('no jQuery found, please load jQuery externally or use typoscript constant plugin.tx_pbsocial.settings.load-jquery = 1');
    }
};


function documentReady(){

    jQuery('a.likes,a.comments,a.plus,a.replies').unbind('click').click(function(){
        window.open(this.href,'_blank','width=1200,height=800');
        return false;
    });

    //
    // ANY CLICK REDIRECT TO SOURCE OBJECT PAGE
    //
    jQuery('.pb-list-item .image, .pb-list-item .icon, .pb-list-item img, .pb-list-item .text').unbind('click').click(function(e){
        var _Url = jQuery(this).closest('.pb-list-item').data('url');
        window.open(_Url,'_blank','width=1200,height=800');
        return false;
    });
}

function initBindings(){
    documentReady();
}

//// HELPER FUNCTIONS ////
function createScript(src){
    var script = document.createElement('SCRIPT');
    script.src = src;
    script.type = 'text/javascript';
    document.getElementsByTagName('head')[0].appendChild(script);
}

