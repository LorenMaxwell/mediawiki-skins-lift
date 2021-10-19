$('#switchWatch').on('change', function() {

    /* See: https://www.mediawiki.org/wiki/API:Watch#MediaWiki_JS */
    /*
    	watch.js
    
    	MediaWiki API Demos
    	Demo of `Watch` module: Add a page to your watchlist
    
    	MIT License
    */
    
    var params = {
    		action: 'watch',
    		unwatch: !$(this).is(':checked'),
    		titles: mw.config.values.wgPageName
    	},
    	api = new mw.Api()

    api.postWithToken( 'watch', params )
    
    $(this).blur()

})