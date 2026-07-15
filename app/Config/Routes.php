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
$routes->add('/extra_sms', 'Admin::extra_sms');
$routes->add('/schools', 'Admin::schools');
$routes->add('/add-school', 'Admin::add_school');
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
