jQuery(document).ready(function($) {
    var adminStartDateField = '#ems_leave_start_date'; 
    var adminEndDateField = '#ems_leave_end_date';     

    // Function to validate dates
    function validateLeaveDates() {
        var startDate = $(adminStartDateField).val(); 
        var endDate = $(adminEndDateField).val();     
        var today = '';
        var canSelectPast = false; // Default to no past dates

        if (typeof ems_leave_admin_data !== 'undefined') {
            today = ems_leave_admin_data.today || new Date().toISOString().split('T')[0];
            canSelectPast = ems_leave_admin_data.can_select_past_dates === true; // Check the new flag
        } else {
            var d = new Date();
            today = d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
            // console.warn("EmManSys: ems_leave_admin_data not fully localized. Using client-generated date and restricting past dates.");
        }

        var minStartDateForPicker = today;
        if (canSelectPast) {
            minStartDateForPicker = null; // Allow any past date for admins
            $(adminStartDateField).removeAttr('min'); // Remove min attribute if admin
        } else {
            // Set min attribute for start date to today for non-admins or if flag not set
            if (!$(adminStartDateField).attr('min') || $(adminStartDateField).attr('min') < today) {
                 $(adminStartDateField).attr('min', today);
            }
            // If start date is in the past (e.g. due to browser autofill), reset it for non-admins
            if (startDate && startDate < today) {
                $(adminStartDateField).val(today);
                startDate = today; 
            }
        }
        
        // If jQuery UI Datepicker is attached to start date, update its minDate
        if ($.fn.datepicker && $(adminStartDateField).hasClass('hasDatepicker')) {
            try {
                $(adminStartDateField).datepicker("option", "minDate", minStartDateForPicker);
            } catch (e) { /* console.warn("Error setting minDate on admin start datepicker.", e); */ }
        }
        
        // Set min attribute for end date based on start date or today (if start date is empty AND admin cannot select past)
        var minEndDate;
        if (startDate) {
            minEndDate = startDate;
        } else {
            minEndDate = canSelectPast ? null : today; // If admin can select past and start is empty, end date can also be past
        }

        if(minEndDate){
            $(adminEndDateField).attr('min', minEndDate);
        } else {
            $(adminEndDateField).removeAttr('min');
        }


        // If jQuery UI Datepicker is attached to end date, update its minDate
        if ($.fn.datepicker && $(adminEndDateField).hasClass('hasDatepicker')) {
            try {
                $(adminEndDateField).datepicker("option", "minDate", minEndDate);
            } catch (e) { /* console.warn("Error setting minDate on admin end datepicker.", e); */ }
        }

        // If end date is already set and is before new start date (or today if start date is empty for non-admins), reset it
        if (endDate && minEndDate && endDate < minEndDate) {
            $(adminEndDateField).val(minEndDate); 
        }
    }

    if ($(adminStartDateField).length && $(adminEndDateField).length) {
        validateLeaveDates(); // Initial validation

        $(adminStartDateField).on('change input', function() { 
            validateLeaveDates();
        });

        $(adminEndDateField).on('change input', function() {
            var startDateVal = $(adminStartDateField).val();
            var endDateVal = $(this).val();
            if (startDateVal && endDateVal && endDateVal < startDateVal) {
                $(this).val(startDateVal); 
            }
        });
    }

    $('form#post').on('submit', function(e){
        if (!$('body').hasClass('post-type-leave_request')) {
            return; 
        }

        if ($(adminStartDateField).length && $(adminEndDateField).length) {
            var startDate = $(adminStartDateField).val();
            var endDate = $(adminEndDateField).val();
            var today = (typeof ems_leave_admin_data !== 'undefined' && ems_leave_admin_data.today) ? ems_leave_admin_data.today : new Date().toISOString().split('T')[0];
            var canSelectPastForSubmit = (typeof ems_leave_admin_data !== 'undefined' && ems_leave_admin_data.can_select_past_dates === true);


            if (!canSelectPastForSubmit && startDate && startDate < today) { // Only enforce for non-admins
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
