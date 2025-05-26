jQuery(document).ready(function($) {
    var today = ems_profile_leave_data.today;
    var ajax_url = ems_profile_leave_data.ajax_url;
    var nonce = ems_profile_leave_data.nonce;
    var ajax_action = ems_profile_leave_data.action;

    // --- Existing date validation logic from your previous JS file ---
    var profileStartDate = '#ems_profile_start_date';
    var profileEndDate = '#ems_profile_end_date';

    function validateProfileLeaveDates() {
        var startDate = $(profileStartDate).val();
        var endDate = $(profileEndDate).val();

        if (!$(profileStartDate).attr('min')) {
            $(profileStartDate).attr('min', today);
        }
        if (startDate) {
            $(profileEndDate).attr('min', startDate);
            if (endDate && endDate < startDate) {
                $(profileEndDate).val('');
            }
        } else {
             $(profileEndDate).attr('min', today);
        }
        if (startDate && startDate < today) {
            $(profileStartDate).val(today);
        }
    }

    if ($(profileStartDate).length && $(profileEndDate).length) {
        validateProfileLeaveDates();
        $(profileStartDate).on('change', validateProfileLeaveDates);
        $(profileEndDate).on('change', function() {
            var startDateVal = $(profileStartDate).val();
            var endDateVal = $(this).val();
            if (startDateVal && endDateVal && endDateVal < startDateVal) {
                $(this).val(startDateVal);
            }
        });
    }
    // --- End of existing date validation logic ---

    $('#ems-profile-leave-form').on('submit', function(e) {
        e.preventDefault(); // Prevent traditional form submission

        // Client-side validation (re-check before AJAX)
        var startDate = $(profileStartDate).val();
        var endDate = $(profileEndDate).val();
        var leaveType = $('#ems_profile_leave_type').val();
        var leaveDuration = $('#ems_profile_leave_duration').val();
        var leaveReason = $('#ems_profile_leave_reason_field').val();

        if (!leaveType || !startDate || !endDate || !leaveDuration || !leaveReason) {
            alert(ems_profile_leave_data.error_all_fields_required);
            return false;
        }
        if (startDate < today) {
            alert(ems_profile_leave_data.error_start_date_past);
            return false;
        }
        if (endDate < startDate) {
            alert(ems_profile_leave_data.error_end_date_invalid);
            return false;
        }

        var formData = $(this).serialize(); // Collect form data
        formData += '&action=' + ajax_action; // Add our AJAX action
        formData += '&security=' + nonce;     // Add our nonce

        // Clear previous messages
        $('#ems-profile-leave-form .notice').remove();
        var $submitButton = $(this).find('input[type="submit"]');
        var originalButtonText = $submitButton.val();
        $submitButton.val('Submitting...').prop('disabled', true);


        $.ajax({
            url: ajax_url,
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#ems-profile-leave-form').prepend('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>');
                    $('#ems-profile-leave-form')[0].reset(); // Reset the form
                    // Optionally: Refresh the leave history list via another AJAX call or if response contains updated HTML
                    // For now, just reset form and show message. User can refresh to see history.
                } else {
                    $('#ems-profile-leave-form').prepend('<div class="notice notice-error is-dismissible"><p>' + response.data.message + '</p></div>');
                }
            },
            error: function() {
                $('#ems-profile-leave-form').prepend('<div class="notice notice-error is-dismissible"><p>' + ems_profile_leave_data.error_message_general + '</p></div>');
            },
            complete: function() {
                 $submitButton.val(originalButtonText).prop('disabled', false);
                 validateProfileLeaveDates(); // Re-apply min dates after reset
            }
        });
    });
});