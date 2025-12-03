define([
    'Magento_Customer/js/form/form',
    'Magento_Ui/js/modal/confirm',
    'jquery'
], function (FormComponent, confirm, $) {
    'use strict';

    return FormComponent.extend({
        initialize: function () {
            this._super();
            this.confirmUrl = this.source.get('data.links.confirmUrl');
            return this;
        },

        forceConfirm: function () {
            var url = this.confirmUrl;
            confirm({
                title: $.mage.__('Confirm Account'),
                content: $.mage.__('Are you sure you want to manually confirm this customer account?'),
                actions: {
                    confirm: function () {
                        $.ajax({
                            url: url,
                            type: 'POST',
                            dataType: 'json',
                            showLoader: true,
                            success: function (response) {
                                if (response.success) {
                                    window.location.reload();
                                } else {
                                    alert(response.message);
                                }
                            },
                            error: function () {
                                alert($.mage.__('An error occurred while confirming the account.'));
                            }
                        });
                    }
                }
            });
        }
    });
});
