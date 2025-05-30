EmManSys - Employee Management System
Version: 1.1.0
Author: Your Name
License: GPL v2 or later
Requires WordPress Version: (Specify if known, otherwise assume latest)
Requires PHP Version: (Specify if known, otherwise assume 7.4+)
Requires Plugins: User Role Editor

A WordPress plugin for managing employee records and their leave requests. It allows for creating, editing, and listing employees, as well as a system for employees to request leave and for administrators/managers to approve or reject those requests.

Functionality
Employee Custom Post Type (CPT):

Create, edit, and delete employee records.

Store details such as Full Name, Linked WordPress User, Employee ID, Department, Position, Email, Phone, and Salary.

Custom admin columns for easy viewing of employee details in the WordPress admin list.

Sortable admin columns.

Leave Request Custom Post Type (CPT):

Create, edit, and delete leave requests.

Store details such as the requesting Employee, Leave Type (Vacation, Sick, Personal, Unpaid, Other), Start Date, End Date, Leave Duration (Whole Day, Half Day AM/PM), Reason for Leave, Leave Status (Pending, Approved, Rejected, Cancelled by Employee), and Admin Notes.

Automatic generation of leave request titles.

Custom admin columns for managing leave requests.

Sortable admin columns.

User Profile Integration:

Employees (linked to an Employee CPT record and with appropriate permissions) can submit leave requests directly from their WordPress user profile page.

Employees can view their leave request history on their profile page.

Permission Management:

Relies on the "User Role Editor" plugin for granular control over who can perform specific actions.

Defines custom capabilities (e.g., edit_employee, publish_employees, approve_leave_requests, submit_profile_leave_request) that administrators can assign to user roles.

Shortcode:

[list_employees]: Displays a list of employees on the front end. Supports count and department attributes.

Validation:

Server-side validation for leave request dates (start date not in the past, end date not before start date).

Client-side JavaScript validation for leave request forms (admin and profile) to enhance user experience by preventing invalid date submissions.

Installation and Setup
Download the Plugin: Obtain the emmansys plugin files.

Upload to WordPress:

Log in to your WordPress admin dashboard.

Navigate to Plugins > Add New.

Click the Upload Plugin button at the top.

Choose the emmansys.zip file (if it's zipped) or upload the emmansys folder to the /wp-content/plugins/ directory via FTP/SFTP.

Install Dependency:

Crucial Step: Before activating EmManSys, you MUST install and activate the User Role Editor plugin. You can find it by searching for "User Role Editor" in Plugins > Add New.

Activate EmManSys:

Once "User Role Editor" is active, go to Plugins > Installed Plugins.

Find "EmManSys" in the list and click Activate.

Configure Permissions (Using User Role Editor):

After activation, navigate to Users > User Role Editor.

You will need to create or edit user roles (e.g., Employee, HR Manager) and assign the custom capabilities provided by EmManSys.

Key EmManSys Capabilities include (but are not limited to):

edit_employee, read_employee, delete_employee, edit_employees, edit_others_employees, publish_employees

edit_leave_request, read_leave_request, delete_leave_request, edit_leave_requests, edit_others_leave_requests, publish_leave_requests, approve_leave_requests (typically for managers)

submit_profile_leave_request, view_own_profile_leave_history (typically for employees)

Assign these capabilities as needed to your defined roles. For example:

An Employee role might get read_employee (if they can view their own record, though this plugin doesn't explicitly provide a frontend view for single employees beyond the list shortcode), submit_profile_leave_request, and view_own_profile_leave_history.

An HR Manager role would likely get all or most of the edit_others_employees, publish_employees, edit_others_leave_requests, and approve_leave_requests capabilities.

Link WordPress Users to Employee Records:

Go to Employees > All Employees.

Edit an employee record (or create a new one).

In the "Employee Details" meta box, use the "Linked WordPress User" dropdown to associate the employee record with an existing WordPress user account. This is necessary for employees to submit leave from their profile.

Version History
1.1.0

Added "Leave Duration" (Whole Day, Half Day AM/PM) option to Leave Request forms (admin and profile).

Updated meta box, save functions, and display columns/history to include duration.

Added client-side and server-side date validation for Start Date and End Date.

Start Date cannot be in the past.

End Date cannot be earlier than Start Date.

Enqueued JavaScript for date validation (admin-leave-validation.js, profile-leave-validation.js).

1.0.9

Added dependency on "User Role Editor" plugin.

Removed internal role/capability creation. Plugin now relies on admin to set up roles (e.g., ems_employee, ems_manager) and assign capabilities using User Role Editor.

CPTs still define their necessary capabilities, which are then managed externally.

Added admin notice if "User Role Editor" is not active.

1.0.8

(Initially) Introduced Role Management module (later superseded by User Role Editor dependency in 1.0.9).

Defined custom roles: ems_employee and ems_manager.

Assigned specific capabilities for Employee and Leave Request CPTs.

Integrated current_user_can() checks for CPT access and actions.

Roles and capabilities added on activation, removed on deactivation.

Corrected admin column display bug for Employees list (header duplication).

1.0.7

Removed standard "Title" field and main "Visual Editor" from Employee CPT edit screen.

Added "Employee Full Name" meta field, used to programmatically set post_title.

Removed standard "Title" field and main "Visual Editor" from Leave Request CPT edit screen.

Title for Leave Requests is auto-generated (e.g., "Leave: Employee Name (Start Date to End Date)").

Added "Reason for Leave" textarea meta field.

Conditional display of "Employee" dropdown in Leave Request meta box:

Administrators/Managers see dropdown.

Other users see their linked employee details as read-only (if submitting for themselves, or if viewing a request they made).

1.0.6

Prefixed custom admin column keys with ems_ to prevent conflicts and fix header duplication.

1.0.5

Changed "Employee Name" field in Leave Request meta box to a dropdown populated from Employee CPT.

Auto-populates Employee Name and linked WP User ID on leave request based on selection.

Leave submission from profile page now requires user to be linked to an Employee CPT record.

1.0.4

Added "Linked WordPress User" dropdown to Employee CPT meta box.

Employee records can now be directly tagged to a WordPress user account.

Displayed linked user in the "All Employees" admin list.

1.0.3

Added "File a Leave" functionality to the user's WordPress profile page.

Users can submit leave requests from their profile.

Users can view their leave request history on their profile.

Leave Request CPT now stores _leave_user_id.

1.0.2

Added "File for Leave" module (Leave Request Custom Post Type).

Meta fields for Leave Type, Start Date, End Date, Reason, Status, Admin Notes.

Custom admin columns for Leave Requests.

1.0.1

Updated plugin Text Domain to emmansys for consistency.

1.0.0

Initial plugin setup.

Created "Employee" Custom Post Type (CPT).

Meta fields for Employee ID, Department, Position, Email, Phone, Salary.

Custom admin columns for Employee CPT.

Basic [list_employees] shortcode.