function fireEvent(element, event) {
    if (document.createEventObject) {
        // dispatch for IE
        var evt = document.createEventObject();
        return element.fireEvent('on' + event, evt)
    }
    else {
        // dispatch for firefox + others
        var evt = document.createEvent("HTMLEvents");
        evt.initEvent(event, true, true); // event type,bubbling,cancelable
        return !element.dispatchEvent(evt);
    }
}

Event.observe(document, 'dom:loaded', function () {
    if ($('billing-address-select')) {
        document.getElementById('billing-address-select').value = '';
        fireEvent(document.getElementById('billing-address-select'), 'change');
    }

    $('ssn_click').observe('click', function (event) {
        // Check button is disabled
        if ($(this).hasClassName('disabled')) {
            return;
        }

        var url = MAGENTO_BASE_URL + 'payex/getaddr';
        var ssn = $$('[name="socialSecurityNumber"]')[0].value;
        var country_code = $$('[name="check_country"]')[0].value;
        var postcode = $$('[name="check_postcode"]')[0].value;

        // Check PayEx SSN Form is exists
        if (typeof window.PAYEX_SSN_FORM === 'undefined') {
            return false;
        }

        if (window.PAYEX_SSN_FORM.validator.validate()) {
            $(this).addClassName('disabled');
            var self = this;
            var request = new Ajax.Request(
                url, {
                    method: 'post',
                    parameters: {ssn: ssn, country_code: country_code, postcode: postcode},
                    onSuccess: function (response) {
                        $(self).removeClassName('disabled');
                        var json = response.responseText.evalJSON();
                        if (!json.success) {
                            if ($('social_security_number_form') != undefined) {
                                // Use HTML placeholder to show message
                                $('ssn-error').update(json.message);
                                $('ssn-error-placeholder').show();
                            } else {
                                // Use popup message as failback
                                alert(json.message);
                            }

                            return;
                        }

                        // Hide error placeholder
                        if ($('social_security_number_form') != undefined) {
                            $('social_security_number_form').hide();
                        }

                        // Set Form Fields
                        if ($('billing:firstname')) $('billing:firstname').setValue(json.first_name);
                        if ($('billing:lastname')) $('billing:lastname').setValue(json.last_name);
                        if ($('billing:company')) $('billing:company').setValue('');
                        if ($('billing:street1')) $('billing:street1').setValue(json.address_1);
                        if ($('billing:street2')) {
                            //WHEN payex gives us a space -> validation will crash. So sanitize json input
                            // replace(/^\s+/,"") === ltrim()
                            $('billing:street2').setValue(json.address_2.replace(/^\s+/,""));
                        }
                        if ($('billing:city')) $('billing:city').setValue(json.city);
                        if ($('billing:region')) $('billing:region').setValue('');
                        if ($('billing:postcode')) $('billing:postcode').setValue(json.postcode);
                        if ($('billing:country_id')) $('billing:country_id').setValue(json.country);
                    }
                }
            );
        }
    });
});
