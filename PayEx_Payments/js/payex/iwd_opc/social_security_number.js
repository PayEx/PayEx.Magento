// @codingStandardsIgnoreFile
jQuery(document).ready(function($) {
    $(document).on('click', '#ssn_click', function(e) {
        if ($(this).hasClass('disabled')) {
            return false;
        }

        var url = MAGENTO_BASE_URL + 'payex/getaddr';
        var ssn = $('[name="socialSecurityNumber"]').first().val();
        var country_code = $('#billing\\:country_id').val();
        var postcode = $('#billing\\:postcode').val();

        // Validate
        if (ssn.length === 0) {
            alert('Please enter Social security number.');
            return;
        }

        $(this).addClass('disabled');

        var self = this;
        $.ajax({
            url: url,
            type: 'POST',
            data: {ssn: ssn, country_code: country_code, postcode: postcode},
            dataType: 'json',
            success: function(json) {
                $(self).removeClass('disabled');
                if (!json.success) {
                    alert(json.message);
                    return;
                }

                // Set Form Fields
                if ($('#billing\\:firstname').length) $('#billing\\:firstname').val(json.first_name);
                if ($('#billing\\:lastname').length) $('#billing\\:lastname').val(json.last_name);
                if ($('#billing\\:company').length) $('#billing\\:company').val('');
                if ($('#billing\\:street1').length) $('#billing\\:street1').val(json.address_1);
                if ($('#billing\\:street2').length) $('#billing\\:street2').val($.trim(json.address_2));
                if ($('#billing\\:city').length) $('#billing\\:city').val(json.city);
                if ($('#billing\\:region').length) $('#billing\\:region').val('');
                if ($('#billing\\:postcode').length) $('#billing\\:postcode').val(json.postcode);
                if ($('#billing\\:country_id').length) $('#billing\\:country_id').val(json.country);
            }
        });
    });
});
