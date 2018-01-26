(function() {
    var db_hash_elem = document.getElementById(window.location.hash.substring(1));
    var remove_lazyload_check = false;

    function scrollAction() {
        if (window.location.hash) {
            // Start at top of page
            window.scrollTo(0, 0);

            // After a short delay, display the element and scroll to it
            jQuery(function($) {
                setTimeout(function() {
                    $(window.location.hash).css('display', window.db_location_hash_style);
                    et_pb_smooth_scroll($(window.location.hash), false, 800);
                }, 700);
            });
            event.target.removeEventListener(event.type, arguments.callee);
        }
    }

    function scroll() {
        window.addEventListener('PreloaderDestroyed', scrollAction);
        window.preloader_event_registered = true;
    }
    if (document.readyState == 'complete') {
        scroll();
    } else {
        document.addEventListener('readystatechange', function() {
            if (!db_hash_elem) {
                return;
            }
            if (document.readyState !== "loading") {
                window.db_location_hash_style = db_hash_elem.style.display;
                db_hash_elem.style.display = 'none';
            }
            if (document.readyState === "complete") {
                scroll();
            }
        });
    }
})();