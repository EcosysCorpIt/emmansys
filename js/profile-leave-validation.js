jQuery(document).ready(function($) {
    var today = ems_profile_leave_data.today;
    var ajax_url = ems_profile_leave_data.ajax_url;
    var nonce = ems_profile_leave_data.nonce;
    var ajax_action = ems_profile_leave_data.action;

    // Determine the current form context (profile or dashboard)
    var $currentForm = null;
    if ($(ems_profile_leave_data.form_id_profile).length) {
        $currentForm = $(ems_profile_leave_data.form_id_profile);
    } else if ($(ems_profile_leave_data.form_id_dashboard).length) {
        $currentForm = $(ems_profile_leave_data.form_id_dashboard);
    }

    if (!$currentForm) {
        // console.error("EmManSys: Could not find profile or dashboard leave form.");
        return; // Exit if form not found
    }

    var $profileStartDate = $currentForm.find('.ems-leave-start-date'); // Use class selector
    var $profileEndDate = $currentForm.find('.ems-leave-end-date');   // Use class selector

    function validateProfileLeaveDates() {
        var startDate = $profileStartDate.val(); // Should be YYYY-MM-DD
        var endDate = $profileEndDate.val();     // Should be YYYY-MM-DD

        // Set min attribute for start date to today
        if (!$profileStartDate.attr('min') || $profileStartDate.attr('min') < today) {
            $profileStartDate.attr('min', today);
        }
         // If jQuery UI Datepicker is attached to start date, update its minDate
        if ($.fn.datepicker && $profileStartDate.hasClass('hasDatepicker')) {
            try {
                $profileStartDate.datepicker("option", "minDate", today);
            } catch (e) {
                // console.warn("EmManSys: Error setting minDate on start datepicker.", e);
            }
        }

        // If start date is in the past (e.g. due to browser autofill or if min attribute failed), reset it
        if (startDate && startDate < today) {
            $profileStartDate.val(today);
            startDate = today; // Update for subsequent logic
        }
        
        // Set min attribute for end date based on start date or today
        var minEndDate = startDate ? startDate : today;
        $profileEndDate.attr('min', minEndDate);

        // If jQuery UI Datepicker is attached to end date, update its minDate
        if ($.fn.datepicker && $profileEndDate.hasClass('hasDatepicker')) {
             try {
                $profileEndDate.datepicker("option", "minDate", minEndDate);
            } catch (e) {
                // console.warn("EmManSys: Error setting minDate on end datepicker.", e);
            }
        }

        // If end date is already set and is before new start date (or today if start date is empty), clear/reset it
        if (endDate && endDate < minEndDate) {
            $profileEndDate.val(minEndDate); // Or $profileEndDate.val(''); to clear
        }
    }

    if ($profileStartDate.length && $profileEndDate.length) {
        validateProfileLeaveDates(); // Initial validation

        $profileStartDate.on('change input', function() { // Use 'input' for immediate feedback on some browsers
            validateProfileLeaveDates();
        });

        $profileEndDate.on('change input', function() {
            // Simple re-check: if end date is before start date after change
            var startDateVal = $profileStartDate.val();
            var endDateVal = $(this).val();
            if (startDateVal && endDateVal && endDateVal < startDateVal) {
                $(this).val(startDateVal); // Reset to start date, or clear
            }
        });
    }

    $currentForm.on('submit', function(e) {
        e.preventDefault(); 

        // Re-target within the current form for safety, using classes
        var $form = $(this);
        var startDate = $form.find('.ems-leave-start-date').val();
        var endDate = $form.find('.ems-leave-end-date').val();
        var leaveType = $form.find('select[name="ems_profile_leave_type"]').val();
        var leaveDuration = $form.find('select[name="ems_profile_leave_duration"]').val();
        var leaveReason = $form.find('textarea[name="ems_profile_leave_reason_field"]').val();

        // Client-side validation (re-check before AJAX)
        if (!leaveType || !startDate || !endDate || !leaveDuration || !leaveReason) {
            alert(ems_profile_leave_data.error_all_fields_required);
            return false;
        }
        if (startDate < today) { // Should be caught by PHP and min attribute, but good to double check
            alert(ems_profile_leave_data.error_start_date_past);
            $form.find('.ems-leave-start-date').val(today); // Correct it
            validateProfileLeaveDates(); // Re-apply constraints
            return false;
        }
        if (endDate < startDate) {
            alert(ems_profile_leave_data.error_end_date_invalid);
            $form.find('.ems-leave-end-date').val(startDate); // Correct it
            validateProfileLeaveDates(); // Re-apply constraints
            return false;
        }

        var formData = $(this).serialize(); 
        formData += '&action=' + ajax_action; 
        formData += '&security=' + nonce;    

        $form.find('.notice').remove();
        var $submitButton = $form.find('input[type="submit"]');
        var originalButtonText = $submitButton.val();
        $submitButton.val(ems_profile_leave_data.submitting_text || 'Submitting...').prop('disabled', true); // Add submitting_text to localization

        $.ajax({
            url: ajax_url,
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $form.prepend('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>');
                    $form[0].reset(); 
                } else {
                    $form.prepend('<div class="notice notice-error is-dismissible"><p>' + response.data.message + '</p></div>');
                }
            },
            error: function() {
                $form.prepend('<div class="notice notice-error is-dismissible"><p>' + ems_profile_leave_data.error_message_general + '</p></div>');
            },
            complete: function() {
                 $submitButton.val(originalButtonText).prop('disabled', false);
                 validateProfileLeaveDates(); // Re-apply min dates after reset
            }
        });
    });
});
