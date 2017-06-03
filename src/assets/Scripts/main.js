/**
 * Created by howard on 01/06/2017.
 */

require.config({
    paths : {
        'jquery' : 'Utils/jquery',
        'validate' : 'Utils/validate'
    },
    shim:{
        validate:['jquery']
    }
});

require(['railtour'], function(railtour) {
    railtour.init();
})