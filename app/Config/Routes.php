<?php namespace Config;

/**
 * --------------------------------------------------------------------
 * URI Routing
 * --------------------------------------------------------------------
 * This file lets you re-map URI requests to specific controller functions.
 *
 * Typically there is a one-to-one relationship between a URL string
 * and its corresponding controller class/method. The segments in a
 * URL normally follow this pattern:
 *
 *    example.com/class/method/id
 *
 * In some instances, however, you may want to remap this relationship
 * so that a different class/function is called than the one
 * corresponding to the URL.
 */

// Create a new instance of our RouteCollection class.
$routes = Services::routes(true);

// Load the system's routing file first, so that the app and ENVIRONMENT
// can override as needed.
if (file_exists(SYSTEMPATH . 'Config/Routes.php'))
{
	require SYSTEMPATH . 'Config/Routes.php';
}

/**
 * --------------------------------------------------------------------
 * Router Setup
 * --------------------------------------------------------------------
 * The RouteCollection object allows you to modify the way that the
 * Router works, by acting as a holder for it's configuration settings.
 * The following methods can be called on the object to modify
 * the default operations.
 *
 *    $routes->defaultNamespace()
 *
 * Modifies the namespace that is added to a controller if it doesn't
 * already have one. By default this is the global namespace (\).
 *
 *    $routes->defaultController()
 *
 * Changes the name of the class used as a controller when the route
 * points to a folder instead of a class.
 *
 *    $routes->defaultMethod()
 *
 * Assigns the method inside the controller that is ran when the
 * Router is unable to determine the appropriate method to run.
 *
 *    $routes->setAutoRoute()
 *
 * Determines whether the Router will attempt to match URIs to
 * Controllers when no specific route has been defined. If false,
 * only routes that have been defined here will be available.
 */
$routes->setDefaultNamespace('App\Controllers');
$routes->setDefaultController('Home');
$routes->setDefaultMethod('index');
$routes->setTranslateURIDashes(false);
$routes->set404Override();
$routes->setAutoRoute(true);

/**
 * --------------------------------------------------------------------
 * Route Definitions
 * --------------------------------------------------------------------
 */

// We get a performance increase by specifying the default
// route since we don't have to scan directories.
$routes->get('/', 'Home::index');
$routes->get('/login', 'Home::login');
$routes->get('/messaging/employees', 'Home::messaging_employees');
$routes->get('/messaging/parents', 'Home::messaging_parents');
$routes->get('/messaging/history', 'Home::messaging_history');
$routes->add('/student-cards', 'Home::student_cards');
$routes->add('/student-photo', 'Home::student_photo');
$routes->add('/staff-cards', 'Home::staff_cards');
$routes->get('/register-student', 'Home::add_student');
$routes->get('/get-single-package/(:any)', 'Admin::get_single_package/$1');
$routes->get('/get-skl-package/(:any)', 'Admin::get_school_package/$1');
$routes->get('/admin', 'Admin::index');
$routes->add('/users', 'Admin::users');
$routes->add('/packages', 'Admin::packages');
$routes->get('/admin/platform_fees', 'Admin::platform_fees');
$routes->post('/admin/save_platform_fees', 'Admin::save_platform_fees');
$routes->get('/admin/level_clearance', 'Admin::level_clearance');
$routes->post('/admin/save_level_clearance', 'Admin::save_level_clearance');
$routes->post('/admin/save_master_central_posts', 'Admin::save_master_central_posts');
$routes->get('/admin/school_groups', 'Admin::school_groups');
$routes->post('/admin/save_school_group', 'Admin::save_school_group');
$routes->post('/admin/seed_wisdom_master', 'Admin::seed_wisdom_master');
$routes->get('/admin/export_wisdom_credentials', 'Admin::export_wisdom_credentials');
$routes->add('/extra_sms', 'Admin::extra_sms');
$routes->add('/schools', 'Admin::schools');
$routes->add('/add-school', 'Admin::add_school');
$routes->get('/edit-school/(:num)', 'Admin::edit_school/$1');
$routes->post('/admin/share_school_access', 'Admin::share_school_access');
$routes->get('/admin/academic_structure', 'Admin::academic_structure');
$routes->get('/admin/getAcademicStructure/(:num)', 'Admin::getAcademicStructure/$1');
$routes->post('/admin/saveAcademicFaculty', 'Admin::saveAcademicFaculty');
$routes->post('/admin/saveAcademicDepartment', 'Admin::saveAcademicDepartment');
$routes->post('/admin/saveAcademicLevel', 'Admin::saveAcademicLevel');
$routes->post('/admin/deleteAcademicNode', 'Admin::deleteAcademicNode');
$routes->add('/admin/(:any)', 'Admin::$1');
$routes->get('/classes', 'Home::add_classes');
$routes->get('/course-category', 'Home::course_category');
$routes->get('/staff-report/monthly', 'Home::staff_monthly_report');
$routes->get('/student-report/inout/monthly', 'Home::student_inout_monthly_report');
$routes->get('/student-report/course/monthly', 'Home::student_course_report');
$routes->get('/student-report/course/summary', 'Home::student_course_summary_report');
$routes->get('/student-report/daily/class', 'Home::student_class_daily_report');
$routes->get('/student-report/daily/all', 'Home::student_daily_report');
$routes->get('/student-report/daily/details', 'Home::student_details_daily_report');
$routes->get('/student-report/boarding/all', 'Home::student_boarding_report');
$routes->get('/student-report/boarding/details', 'Home::student_details_boarding_report');

$routes->get('/staff-report/individual', 'Home::staff_individual_report');
$routes->get('/staff-report/all', 'Home::staffs_in_out_attendance_reports');
$routes->get('/system-report/fees/?(:any)', 'Home::feesReport/$1');
$routes->add('/class-deliberation', 'Home::classDeliberation');
$routes->add('/application/?(:any)', 'Home::studentApplication/$1');
$routes->add('/student-marks/?(:any)', 'Home::global_student_marks/$1');
$routes->get('/extra-fees/?(:any)', 'Home::multiple_extra_fees_records/$1');
$routes->add('api', 'Api::index');
$routes->add('api/(:any)', 'Api::$1');
// ✅ Add this line before the wildcard
$routes->get('home/testEmail', 'Home::testEmail');
$routes->get('switch-school/(:num)', 'Home::switch_school_context/$1');
$routes->get('reset-school', 'Home::reset_school_context');
$routes->post('share_staff_access', 'Home::share_staff_access');

// Parent visiting (must be before (:any) catch-all)
$routes->get('parent_visiting/assign', 'Home::parent_visiting_assign');
$routes->get('parent_visiting/verify', 'Home::parent_visiting_verify');
$routes->get('parent_visiting/report', 'Home::parent_visiting_report');
$routes->get('parent_visiting/cards', 'Home::parent_visiting_cards');
$routes->match(['get', 'post'], 'generate_visitor_cards', 'Home::generate_visitor_cards');
$routes->get('get_visitors/(:num)/(:num)/(:num)', 'Home::get_visitors/$1/$2/$3');
$routes->post('parent_visiting/lookup_card', 'Home::parent_visiting_lookup_card');
$routes->post('parent_visiting/save_settings', 'Home::parent_visiting_save_settings');
$routes->post('parent_visiting/save_visitor', 'Home::parent_visiting_save_visitor');
$routes->post('parent_visiting/delete_visitor', 'Home::parent_visiting_delete_visitor');
$routes->post('parent_visiting/assign_card', 'Home::parent_visiting_assign_card');
$routes->post('parent_visiting/scan', 'Home::parent_visiting_scan');
$routes->get('parent_visiting/students', 'Home::parent_visiting_students_json');
$routes->get('parent_visiting/student_visitors/(:num)', 'Home::parent_visiting_student_visitors/$1');

// Asset Management (Phase 1+)
$routes->get('asset_management/dashboard', 'AssetManagement::dashboard');
$routes->get('asset_management/assets', 'AssetManagement::assets');
$routes->get('asset_management/asset_view/(:num)', 'AssetManagement::asset_view/$1');
$routes->post('asset_management/save_asset', 'AssetManagement::save_asset');
$routes->post('asset_management/archive_asset', 'AssetManagement::archive_asset');
$routes->get('asset_management/locations', 'AssetManagement::locations');
$routes->post('asset_management/save_location', 'AssetManagement::save_location');
$routes->post('asset_management/archive_location', 'AssetManagement::archive_location');
$routes->post('asset_management/restore_location', 'AssetManagement::restore_location');
$routes->get('asset_management/categories', 'AssetManagement::categories');
$routes->post('asset_management/save_category', 'AssetManagement::save_category');
$routes->post('asset_management/archive_category', 'AssetManagement::archive_category');
$routes->post('asset_management/save_category_field', 'AssetManagement::save_category_field');
$routes->get('asset_management/category_fields/(:num)', 'AssetManagement::category_fields/$1');
$routes->get('asset_management/settings', 'AssetManagement::settings');
$routes->post('asset_management/save_settings', 'AssetManagement::save_settings');
$routes->get('asset_management/placeholder/(:segment)', 'AssetManagement::placeholder/$1');

// Phase 2+ import/export
$routes->get('asset_management/import', 'AssetManagement::import');
$routes->get('asset_management/download_import_template', 'AssetManagement::download_import_template');
$routes->post('asset_management/upload_import', 'AssetManagement::upload_import');
$routes->get('asset_management/import_preview/(:num)', 'AssetManagement::import_preview/$1');
$routes->post('asset_management/commit_import', 'AssetManagement::commit_import');
$routes->get('asset_management/export_assets', 'AssetManagement::export_assets');

// Phase 3 circulation
$routes->get('asset_management/assignments', 'AssetManagement::assignments');
$routes->post('asset_management/save_assignment', 'AssetManagement::save_assignment');
$routes->post('asset_management/end_assignment', 'AssetManagement::end_assignment');
$routes->get('asset_management/checkout', 'AssetManagement::checkout');
$routes->post('asset_management/scan_person', 'AssetManagement::scan_person');
$routes->post('asset_management/scan_asset', 'AssetManagement::scan_asset');
$routes->post('asset_management/do_checkout', 'AssetManagement::do_checkout');
$routes->post('asset_management/do_checkin', 'AssetManagement::do_checkin');
$routes->post('asset_management/renew_loan', 'AssetManagement::renew_loan');

// Phase 4 operations
$routes->get('asset_management/transfers', 'AssetManagement::transfers');
$routes->post('asset_management/save_transfer', 'AssetManagement::save_transfer');
$routes->post('asset_management/transfer_action', 'AssetManagement::transfer_action');
$routes->get('asset_management/maintenance', 'AssetManagement::maintenance');
$routes->post('asset_management/save_maintenance', 'AssetManagement::save_maintenance');
$routes->post('asset_management/maintenance_status', 'AssetManagement::maintenance_status');
$routes->get('asset_management/inspections', 'AssetManagement::inspections');
$routes->post('asset_management/save_inspection', 'AssetManagement::save_inspection');
$routes->get('asset_management/incidents', 'AssetManagement::incidents');
$routes->post('asset_management/save_incident', 'AssetManagement::save_incident');
$routes->get('asset_management/audits', 'AssetManagement::audits');
$routes->post('asset_management/save_audit', 'AssetManagement::save_audit');
$routes->get('asset_management/audit_view/(:num)', 'AssetManagement::audit_view/$1');
$routes->post('asset_management/audit_scan', 'AssetManagement::audit_scan');
$routes->post('asset_management/close_audit', 'AssetManagement::close_audit');
$routes->get('asset_management/disposals', 'AssetManagement::disposals');
$routes->post('asset_management/save_disposal', 'AssetManagement::save_disposal');
$routes->post('asset_management/disposal_action', 'AssetManagement::disposal_action');

// Phase 5–6 reports + AI
$routes->get('asset_management/reports', 'AssetManagement::reports');
$routes->post('asset_management/run_depreciation', 'AssetManagement::run_depreciation');
$routes->get('asset_management/export_report_csv', 'AssetManagement::export_report_csv');
$routes->post('asset_management/ai_suggest_category', 'AssetManagement::ai_suggest_category');
$routes->post('asset_management/ai_detect_duplicates', 'AssetManagement::ai_detect_duplicates');

// Budget & Cash Flow
$routes->get('budget/dashboard', 'BudgetCashflow::dashboard');
$routes->get('budget/periods', 'BudgetCashflow::periods');
$routes->post('budget/save_period', 'BudgetCashflow::save_period');
$routes->get('budget/settings', 'BudgetCashflow::settings');
$routes->post('budget/save_settings', 'BudgetCashflow::save_settings');
$routes->get('budget/templates', 'BudgetCashflow::templates');
$routes->get('budget/download_official_template', 'BudgetCashflow::download_official_template');
$routes->post('budget/install_official_template', 'BudgetCashflow::install_official_template');
$routes->post('budget/upload_template', 'BudgetCashflow::upload_template');
$routes->post('budget/activate_template', 'BudgetCashflow::activate_template');
$routes->get('budget/prepare', 'BudgetCashflow::prepare');
$routes->post('budget/create_budget', 'BudgetCashflow::create_budget');
$routes->get('budget/edit_budget/(:num)', 'BudgetCashflow::edit_budget/$1');
$routes->post('budget/save_budget_lines', 'BudgetCashflow::save_budget_lines');
$routes->post('budget/save_budget_setup', 'BudgetCashflow::save_budget_setup');
$routes->post('budget/submit_budget', 'BudgetCashflow::submit_budget');
$routes->post('budget/add_budget_line', 'BudgetCashflow::add_budget_line');
$routes->post('budget/delete_budget_line', 'BudgetCashflow::delete_budget_line');
$routes->post('budget/move_budget_line', 'BudgetCashflow::move_budget_line');
$routes->post('budget/reorder_budget_lines', 'BudgetCashflow::reorder_budget_lines');
$routes->get('budget/budget_review', 'BudgetCashflow::budget_review');
$routes->post('budget/budget_action', 'BudgetCashflow::budget_action');
$routes->post('budget/delete_budget', 'BudgetCashflow::delete_budget');
$routes->get('budget/approved_budgets', 'BudgetCashflow::approved_budgets');
$routes->get('budget/requests', 'BudgetCashflow::requests');
$routes->get('budget/cash_requests', 'BudgetCashflow::cash_requests');
$routes->get('budget/cash_request_form', 'BudgetCashflow::cash_request_form');
$routes->get('budget/cash_request_form/(:num)', 'BudgetCashflow::cash_request_form/$1');
$routes->post('budget/save_cash_request', 'BudgetCashflow::save_cash_request');
$routes->get('budget/cash_request_view/(:num)', 'BudgetCashflow::cash_request_view/$1');
$routes->post('budget/cash_request_action', 'BudgetCashflow::cash_request_action');
$routes->get('budget/pending_actions', 'BudgetCashflow::pending_actions');
$routes->get('budget/procurement_review', 'BudgetCashflow::procurement_review');
$routes->get('budget/budget_availability_review', 'BudgetCashflow::budget_availability_review');
$routes->get('budget/final_approval', 'BudgetCashflow::final_approval');
$routes->get('budget/payments', 'BudgetCashflow::payments');
$routes->post('budget/record_payment', 'BudgetCashflow::record_payment');
$routes->get('budget/filing', 'BudgetCashflow::filing');
$routes->post('budget/confirm_receipt', 'BudgetCashflow::confirm_receipt');
$routes->get('budget/reports', 'BudgetCashflow::reports');
$routes->get('budget/audit_trail', 'BudgetCashflow::audit_trail');
$routes->post('budget/scan_session_start', 'BudgetCashflow::scan_session_start');
$routes->get('budget/scan_session_poll', 'BudgetCashflow::scan_session_poll');
$routes->post('budget/fill_budget_from_excel', 'BudgetCashflow::fill_budget_from_excel');
$routes->post('budget/fill_school_fees_income', 'BudgetCashflow::fill_school_fees_income');
$routes->post('budget/reset_budget_empty_amounts', 'BudgetCashflow::reset_budget_empty_amounts');
$routes->get('budget/download_term_template', 'BudgetCashflow::download_term_template');
$routes->post('budget/reset_term_budget', 'BudgetCashflow::reset_term_budget');
$routes->get('budget/dashboard_ai_json', 'BudgetCashflow::dashboard_ai_json');
$routes->get('budget/get_budget_lines_json/(:num)', 'BudgetCashflow::get_budget_lines_json/$1');
$routes->get('budget/cash_request_document/(:num)', 'BudgetCashflow::cash_request_document/$1');
$routes->get('budget/badge_counts_json', 'BudgetCashflow::badge_counts_json');

$routes->get('timetable/dashboard', 'TimetableManagement::dashboard');
$routes->post('timetable/save_slots', 'TimetableManagement::save_slots');
$routes->post('timetable/save_special_times', 'TimetableManagement::save_special_times');
$routes->post('timetable/check_move', 'TimetableManagement::check_move');
$routes->post('timetable/move_entry', 'TimetableManagement::move_entry');
$routes->get('timetable/preview/(:num)', 'TimetableManagement::preview_grid/$1');
$routes->post('timetable/generate', 'TimetableManagement::generate');
$routes->get('timetable/class/(:num)', 'TimetableManagement::class_timetable/$1');
$routes->get('timetable/teacher/(:num)', 'TimetableManagement::teacher_timetable/$1');
$routes->get('timetable/print_class/(:num)', 'TimetableManagement::print_class/$1');
$routes->get('timetable/print_teacher/(:num)', 'TimetableManagement::print_teacher/$1');
$routes->get('timetable/pdf_all_classes', 'TimetableManagement::pdf_all_classes');
$routes->get('timetable/pdf_all_teachers', 'TimetableManagement::pdf_all_teachers');

$routes->add('(:any)', 'Home::$1');
$routes->get('/home/editRegno/(:num)', 'Home::editRegno/$1');
$routes->post('/home/updateRegno/(:num)', 'Home::updateRegno/$1');
$routes->post('/home/updateRegno', 'Home::updateRegno');
$routes->get('assign-card', 'Home::assign_card');
$routes->post('discipline_card_scan', 'Api::discipline_card_scan');
$routes->post('permission_card_scan', 'Api::permission_card_scan');
$routes->get('pages/reports/print_permission/(:num)', 'Home::print_permission/$1');
$routes->get('attendance-card', 'Home::attendanceCard');
$routes->post('scan-card', 'Home::scanCard');
$routes->get('scan-card', 'Home::scanCard'); // testing



/**
 * --------------------------------------------------------------------
 * Additional Routing
 * --------------------------------------------------------------------
 *
 * There will often be times that you need additional routing and you
 * need to it be able to override any defaults in this file. Environment
 * based routes is one such time. require() additional route files here
 * to make that happen.
 *
 * You will have access to the $routes object within that file without
 * needing to reload it.
 */
if (file_exists(APPPATH . 'Config/' . ENVIRONMENT . '/Routes.php'))
{
	require APPPATH . 'Config/' . ENVIRONMENT . '/Routes.php';
}
