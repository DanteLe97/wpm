/* js public
-- coder LTP MAC 
----------------------------------*/
var MAC = MAC || {};
(function($){
    // USE STRICT
    "use strict";
    var $window = $(window);
    var $document = $(document);
    MAC.documentOnReady = {
        init: function(){
            MAC.documentOnReady.linkMacMenuServices();
            MAC.documentOnReady.scrollBarTable();
        },
        linkMacMenuServices: function() {
            function initializeSmoothScroll() {
                var links = document.querySelectorAll("nav a");
                links.forEach(function(link) {
                    link.addEventListener("click", function(event) {
                        handleLinkClick(event, this.hash);
                    });
                    link.addEventListener("touchstart", function(event) {
                        handleLinkClick(event, this.hash);
                    });
                });
            }
            function handleLinkClick(event, targetId) {
                if (targetId) {
                    var anchor = document.querySelector(targetId);
                    if (anchor) {
                        event.preventDefault();
                        runScroll(targetId, 800);
                    }
                }
            }
            function runScroll(targetIdPage, time) {
                var headerWrap = document.getElementById("jet-theme-core-header");
                var header = headerWrap.querySelector(".jet-sticky-section");
                //var header = document.getElementById("jet-theme-core-header");
                var headerHeight = header ? header.offsetHeight : 0;
                var anchor = document.querySelector(targetIdPage);
                if (anchor) {
                    var style = window.getComputedStyle(anchor);
                    var marginTop = parseInt(style.marginTop) || 40;
                    var scrollPosition = anchor.getBoundingClientRect().top + window.pageYOffset - headerHeight - marginTop;
                    var currentPosition = window.pageYOffset;
                    var distance = scrollPosition - currentPosition;
                    var duration = time;
                    var start = null;

                    function smoothScroll(timestamp) {
                        if (!start) start = timestamp;
                        var progress = timestamp - start;
                        var r = progress / duration;
                        var move = currentPosition + distance * r;
                        window.scrollTo(0, move);
                        if (progress < duration) {
                            window.requestAnimationFrame(smoothScroll);
                        } else {
                            window.scrollTo(0, scrollPosition);
                        }
                    }
                    window.requestAnimationFrame(smoothScroll);
                }
            }
            function handlePageLoad() {
                var targetPageId = window.location.hash;
                if (targetPageId) {
                    runScroll(targetPageId, 0);
                }
                initializeSmoothScroll();
            }
            document.addEventListener("DOMContentLoaded", function() {
                window.addEventListener('hashchange', handlePageLoad);
                var jsMenuDefault = document.querySelectorAll(".mac-menu-services-js-default");
                if(jsMenuDefault.length > 0) {
                    handlePageLoad();
                }
            });
            window.addEventListener("load", function() {
                var jsMenuDefault = document.querySelectorAll(".mac-menu-services-js-default");
                if(jsMenuDefault.length > 0) {
                    handlePageLoad();
                    window.addEventListener("resize", function() {
                        handlePageLoad();
                    });
                }
            });
        },
        scrollBarTable: function() {
            function checkTableOverflow() {
                document.querySelectorAll(".module-category").forEach(category => {
                    const tables = category.querySelectorAll("table");
                
                    tables.forEach(table => {
                        const container = table.parentElement;
                        if (table.scrollWidth > container.clientWidth) {
                            table.classList.add("table-scroll-active");
                        } else {
                            table.classList.remove("table-scroll-active");
                        }
                    });
                });
            }
            checkTableOverflow();
            window.addEventListener("resize", checkTableOverflow);
        },
    };
    MAC.documentOnLoad = {
        init: function() {
        }
    };

    $document.ready( MAC.documentOnReady.init );
    $window.on('load', MAC.documentOnLoad.init );
})(jQuery);





