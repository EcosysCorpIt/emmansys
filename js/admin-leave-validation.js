jQuery(document).ready(function($) {
    // Function to validate dates
    function validateLeaveDates(startDateField, endDateField) {
        var startDate = $(startDateField).val();
        var endDate = $(endDateField).val();
        var today = ems_leave_data.today; // Get today's date from localized script

        // Set min attribute for start date to today if not already set by PHP (should be)
        if (!$(startDateField).attr('min')) {
            $(startDateField).attr('min', today);
        }

        // Set min attribute for end date to today or start date
        if (startDate) {
            $(endDateField).attr('min', startDate);
             // If end date is already set and is before new start date, clear it or alert user
            if (endDate && endDate < startDate) {
                $(endDateField).val(''); // Clear it
                // alert('End date cannot be earlier than the start date.'); // Optional: alert user
            }
        } else {
             $(endDateField).attr('min', today); // If no start date, min is today
        }

        // Additional check: Start date cannot be in the past
        if (startDate && startDate < today) {
            // alert('Start date cannot be in the past.'); // Optional: alert user
            $(startDateField).val(today); // Reset to today, or handle error differently
        }
    }

    // Apply to admin form (Leave Request CPT edit screen)
    // Note: The IDs might be different if you changed them in render_leave_request_details_meta_box
    var adminStartDate = '#ems_leave_start_date';
    var adminEndDate = '#ems_leave_end_date';

    if ($(adminStartDate).length && $(adminEndDate).length) {
        validateLeaveDates(adminStartDate, adminEndDate); // Initial validation

        $(adminStartDate).on('change', function() {
            validateLeaveDates(adminStartDate, adminEndDate);
        });
        $(adminEndDate).on('change', function() {
             // Simple check: if end date is before start date after change
            var startDateVal = $(adminStartDate).val();
            var endDateVal = $(this).val();
            if (startDateVal && endDateVal && endDateVal < startDateVal) {
                // alert('End date cannot be earlier than the start date.');
                $(this).val(startDateVal); // Reset to start date, or clear
            }
        });
    }

    // Apply to profile form
    var profileStartDate = '#ems_profile_start_date';
    var profileEndDate = '#ems_profile_end_date';

    if ($(profileStartDate).length && $(profileEndDate).length) {
        validateLeaveDates(profileStartDate, profileEndDate); // Initial validation

        $(profileStartDate).on('change', function() {
            validateLeaveDates(profileStartDate, profileEndDate);
        });
         $(profileEndDate).on('change', function() {
            var startDateVal = $(profileStartDate).val();
            var endDateVal = $(this).val();
            if (startDateVal && endDateVal && endDateVal < startDateVal) {
                // alert('End date cannot be earlier than the start date.');
                $(this).val(startDateVal); 
            }
        });
    }

    // Prevent form submission if dates are invalid (optional, more robust client-side)
    $('form').on('submit', function(e){
        var startDateField, endDateField;
        if ($(this).attr('id') === 'ems-profile-leave-form') { // Profile form
            startDateField = profileStartDate;
            endDateField = profileEndDate;
        } else if ($(this).attr('id') === 'post' && $('body').hasClass('post-type-leave_request')) { // Admin CPT form
             startDateField = adminStartDate;
             endDateField = adminEndDate;
        } else {
            return; // Not our form
        }

        if ($(startDateField).length && $(endDateField).length) {
            var startDate = $(startDateField).val();
            var endDate = $(endDateField).val();
            var today = ems_leave_data.today;

            if (startDate && startDate < today) {
                alert( (typeof wp !== 'undefined' && wp.i18n && wp.i18n.__) ? wp.i18n.__('Start date cannot be in the past.', 'emmansys') : 'Start date cannot be in the past.' );
                e.preventDefault();
                return false;
            }
            if (startDate && endDate && endDate < startDate) {
                 alert( (typeof wp !== 'undefined' && wp.i18n && wp.i18n.__) ? wp.i18n.__('End date cannot be earlier than the start date.', 'emmansys') : 'End date cannot be earlier than the start date.' );
                e.preventDefault();
                return false;
            }
        }
    });

});