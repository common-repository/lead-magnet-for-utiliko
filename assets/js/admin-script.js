jQuery(document).ready(function ($) {
    var crmConnected = false;
    var accessToken = '';
    var fieldMappings = {};
    var selectedFormId;
    var fetchedFormId = '';

    function fetchAccessTokenFromWordPress() {
        $.ajax({
            url: lead_magnet_for_utiliko_admin_object.ajax_url,
            type: 'POST',
            data: {
                action: 'get_crm_access_token_ajax',
                nonce: lead_magnet_for_utiliko_admin_object.nonce
            },
            success: function (response) {
                console.log('AJAX success response:', response);
                if (response.success) {
                    console.log('Initial AccessToken:', response.data.access_token);
                    accessToken = response.data.access_token;
                    fetchedFormId = response.data.form_id;
                    crmConnected = true;
                    console.log('CRM Access Token:', accessToken);
                    //  enableFormSubmission();
                } else {
                    console.error('Failed to fetch CRM Access Token:', response.data.message);
                    crmConnected = false;
                    disableFormSubmission();
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX request failed:', status, error);
                console.log('Response:', xhr.responseText);
                crmConnected = false;
                disableFormSubmission();
            }
        });
    }

    $('#crmForm').on('submit', function (event) {
        event.preventDefault();
        var crmUrl = $('#input_data').val();
        if (crmUrl) {
            var newWindow = window.open(crmUrl, '_blank');
            var pollTimer = window.setInterval(function () {
                try {
                    var currentUrl = newWindow.document.URL;
                    if (currentUrl.includes("access_token")) {
                        window.clearInterval(pollTimer);
                        var urlParams = new URLSearchParams(new URL(currentUrl).search);
                        var accessToken = urlParams.get('access_token');
                        newWindow.close();

                        if (accessToken) {
                            saveAccessTokenAndUrl(crmUrl, accessToken);
                        } else {
                            alert('Access token not found in the URL');
                        }
                    }
                } catch (e) {
                }
            }, 1000);
        } else {
            alert('Please enter a valid CRM URL');
        }
    });

    function saveAccessTokenAndUrl(crmUrl, accessToken) {
        $.ajax({
            type: 'POST',
            url: lead_magnet_for_utiliko_admin_object.ajax_url,
            data: {
                action: 'lead_magnet_save_crm_url',
                crm_url: crmUrl,
                access_token: accessToken,
                nonce: lead_magnet_for_utiliko_admin_object.nonce
            },
            success: function (response) {
                if (response.success) {
                    alert('URL and access token saved successfully');
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function (xhr, status, error) {
                console.error('Failed to save URL and access token: ' + error);
            }
        });
    }

    function enableFormSubmission() {
        $(document).on('click', '.wpforms-submit', function (e) {
            if (!crmConnected) { return; }
            var $form = $(this).closest('form');
            var formId = $form.find('input[name="wpforms[id]"]').val();
            var formData = $form.serializeArray();
            fetchFormMappings(formId, formData, $form);
        });
    }

    function disableFormSubmission() {
        $(document).off('click', '.wpforms-submit');
    }

    function sendToCRM(crmData, $form) {
        if (!accessToken) {
            console.error('CRM Access Token not found. Unable to send data to CRM.');
            return;
        }

        $.ajax({
            url: 'https://api.utiliko.io/api/v4/WordpressIntegrations/createLeadByAccessToken',
            type: 'POST',
            headers: {
                'Authorization': 'Bearer ' + accessToken,
                'Content-Type': 'application/json'
            },
            data: JSON.stringify(crmData),
            success: function (response) {
                console.log('Data sent to CRM successfully:', response);
                alert('Data sent to CRM successfully!');
                $form.submit();
            },
            error: function (xhr, status, error) {
                console.error('Error sending data to CRM:', status, error);
                alert('Failed to send data to CRM.');
            }
        });
    }

    $(document).on('click', '.wpforms-submit', function (e) {
        e.preventDefault();
        var $form = $(this).closest('form');
        $form.attr('novalidate', 'novalidate');
        var formId = $form.data('formid');
        fetchedFormId = $form.data('formid');
        var formDataArray = $form.serializeArray();
        var formData = {};
        formDataArray.forEach(function (item) {
            var match = item.name.match(/wpforms\[fields\]\[(\d+)\]/);
            if (match) {
                var fieldId = match[1];
                var optionValue = getOptionValueForFieldId(fieldId);
                if (optionValue) {
                    formData[optionValue] = item.value;
                }
            } else {
                formData[item.name] = item.value;
            }
        });

        formData.accessToken = accessToken;
        console.log('Form data after processing:', formData);

        if (Object.keys(formData).length === 0) {
            alert('Form data is empty. Please check field mappings and form inputs.');
            return;
        }

        var crmUrl = `https://api.utiliko.io/api/v4/WordpressIntegrations/createLeadByAccessToken`;

        $.ajax({
            url: crmUrl,
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(formData),
            success: function (response) {
                if (response.data) {
                    // alert('Leads created successfully! Thank You!');
                    $form.each(function() {
                        this.reset();
                    });
                    window.location.href = 'https://www.utiliko.io/thank-you/';
                } else {
                    console.error('Error:', response.data.message);
                    alert('Something Went Wrong. Please Contact To Admin..!!!');
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX Error:', status, error);
            }
        });
    });

    function fetchFieldMappings() {
        $.ajax({
            url: lead_magnet_for_utiliko_admin_object.ajax_url,
            type: 'POST',
            data: {
                action: 'get_field_mappings',
                nonce: lead_magnet_for_utiliko_admin_object.nonce
            },
            success: function (response) {
                if (response.success) {
                    fieldMappings = response.data.mappings;
                    console.log('Field Mappings:', fieldMappings);
                } else {
                    console.error('Failed to fetch field mappings:', response.data.message);
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX Error:', xhr.status, xhr.statusText, xhr.responseText);
            }
        });
    }

    function getOptionValueForFieldId(fieldId) {
        var $form = $(this).closest('form');
        var getFormId = $form.data('formid');
        return fieldMappings[fetchedFormId][fieldId] || null;
    }

    $('#wpforms_select').change(function () {
        selectedFormId = $(this).val();
        fetchFormFields(selectedFormId);
    });

    window.fetchFormFields = function (formId) {
        if (!formId) return;

        $.ajax({
            url: lead_magnet_for_utiliko_admin_object.ajax_url,
            type: 'POST',
            data: {
                action: 'fetch_wpform_fields',
                form_id: formId,
                nonce: lead_magnet_for_utiliko_admin_object.nonce
            },
            success: function (response) {
                if (response.success) {
                    $('#inside').html(response.data.html);
                } else {
                    $('#inside').html('<p>' + response.data.message + '</p>');
                }
            },
            error: function (xhr, status, error) {
                $('#inside').html('<p>An error occurred while fetching form fields.</p>');
            }
        });
    };

    $('#save-mapping').off('click').on('click', function () {
        var formId = $('#wpforms_select').val();
        var formName = $('#wpforms_select option:selected').text();
        var mappings = {};
        $('#inside .form-field-mapping select').each(function () {
            var fieldId = $(this).attr('id').replace('field_mapping_', '');
            var fieldValue = $(this).val();
            mappings[fieldId] = fieldValue;
        });

        $.ajax({
            url: lead_magnet_for_utiliko_admin_object.ajax_url,
            type: 'POST',
            data: {
                action: 'saveField_mappings',
                form_id: formId,
                form_name: formName,
                mappings: mappings,
                nonce: lead_magnet_for_utiliko_admin_object.nonce
            },
            success: function (response) {
                if (response.success) {
                    alert('Mappings saved successfully!');
                } else {
                    alert('Failed to save mappings: ' + response.data.message);
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX Error:', status, error);
                alert('Failed to save mappings.');
            }
        });
    });

    fetchAccessTokenFromWordPress();
    fetchFieldMappings();
    
    $('#refresh-token').click(function () {
        fetchAccessTokenFromWordPress();
    });


});
