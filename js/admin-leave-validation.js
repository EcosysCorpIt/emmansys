jQuery(document).ready(function($) {
    var adminStartDateField = '#ems_leave_start_date'; // As defined in render_leave_request_details_meta_box
    var adminEndDateField = '#ems_leave_end_date';     // As defined in render_leave_request_details_meta_box

    // Function to validate dates
    function validateLeaveDates() {
        var startDate = $(adminStartDateField).val(); // Should be YYYY-MM-DD
        var endDate = $(adminEndDateField).val();     // Should be YYYY-MM-DD
        var today = '';
        if (typeof ems_leave_admin_data !== 'undefined' && ems_leave_admin_data.today) {
            today = ems_leave_admin_data.today;
        } else {
            // Fallback if localization fails, though it shouldn't.
            var d = new Date();
            today = d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
            // console.warn("EmManSys: ems_leave_admin_data.today not localized. Using client-generated date.");
        }


        // Set min attribute for start date to today
        if (!$(adminStartDateField).attr('min') || $(adminStartDateField).attr('min') < today) {
             $(adminStartDateField).attr('min', today);
        }
        // If jQuery UI Datepicker is attached to start date, update its minDate
        if ($.fn.datepicker && $(adminStartDateField).hasClass('hasDatepicker')) {
            try {
                $(adminStartDateField).datepicker("option", "minDate", today);
            } catch (e) { /* console.warn("Error setting minDate on admin start datepicker.", e); */ }
        }
        
        // If start date is in the past, reset it
        if (startDate && startDate < today) {
            $(adminStartDateField).val(today);
            startDate = today; // Update current value for immediate use
        }
        
        // Set min attribute for end date based on start date or today
        var minEndDate = startDate ? startDate : today;
        $(adminEndDateField).attr('min', minEndDate);

        // If jQuery UI Datepicker is attached to end date, update its minDate
        if ($.fn.datepicker && $(adminEndDateField).hasClass('hasDatepicker')) {
            try {
                $(adminEndDateField).datepicker("option", "minDate", minEndDate);
            } catch (e) { /* console.warn("Error setting minDate on admin end datepicker.", e); */ }
        }

        // If end date is already set and is before new start date (or today if start date is empty), reset it
        if (endDate && endDate < minEndDate) {
            $(adminEndDateField).val(minEndDate); // Or $(adminEndDateField).val('');
        }
    }

    if ($(adminStartDateField).length && $(adminEndDateField).length) {
        validateLeaveDates(); // Initial validation

        $(adminStartDateField).on('change input', function() { // Use 'input' for immediate feedback
            validateLeaveDates();
        });

        $(adminEndDateField).on('change input', function() {
            // Simple re-check: if end date is before start date after change
            var startDateVal = $(adminStartDateField).val();
            var endDateVal = $(this).val();
            if (startDateVal && endDateVal && endDateVal < startDateVal) {
                $(this).val(startDateVal); // Reset to start date
            }
        });
    }

    // Prevent form submission if dates are invalid (more robust client-side)
    // This targets the main post form in admin for the 'leave_request' CPT
    $('form#post').on('submit', function(e){
        if (!$('body').hasClass('post-type-leave_request')) {
            return; // Not our form CPT
        }

        if ($(adminStartDateField).length && $(adminEndDateField).length) {
            var startDate = $(adminStartDateField).val();
            var endDate = $(adminEndDateField).val();
            var today = (typeof ems_leave_admin_data !== 'undefined' && ems_leave_admin_data.today) ? ems_leave_admin_data.today : new Date().toISOString().split('T')[0];


            if (startDate && startDate < today) {
                alert( (typeof wp !== 'undefined' && wp.i18n && wp.i18n.__) ? wp.i18n.__('Start date cannot be in the past.', 'emmansys') : 'Start date cannot be in the past.' );
                e.preventDefault();
                $(adminStartDateField).focus();
                return false;
            }
            if (startDate && endDate && endDate < startDate) {
                 alert( (typeof wp !== 'undefined' && wp.i18n && wp.i18n.__) ? wp.i18n.__('End date cannot be earlier than the start date.', 'emmansys') : 'End date cannot be earlier than the start date.' );
                e.preventDefault();
                $(adminEndDateField).focus();
                return false;
            }
        }
    });
});
