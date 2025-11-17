define([
    'jquery'
], function ($) {
    'use strict';

    return function (target) {
        $.widget('mage.mediaUploader', target, {
            _create: function () {
                this.options.allowedExt = ['jpeg', 'jpg', 'png', 'gif', 'webp', 'avif', 'svg'];
                this._super();
            }
        });

        return $.mage.mediaUploader;
    };
});
