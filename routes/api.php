<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\VisitorController;
use App\Http\Controllers\Api\SchoolClassController;
use App\Http\Controllers\Api\SectionController;
use App\Http\Controllers\Api\ParentProfileController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\FeeHeadController;
use App\Http\Controllers\Api\TempAddKeyController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ClassSubjectController;
use App\Http\Controllers\Api\DiaryController;
use App\Http\Controllers\Api\SyllabusController;
use App\Http\Controllers\Api\QbTopicController;
use App\Http\Controllers\Api\LessonPlanSubjectController;
use App\Http\Controllers\Api\QbQuestionController;
use App\Http\Controllers\Api\StudentAttendanceController;
use App\Http\Controllers\Api\StaffAttendanceController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\StaffController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/dashboard/overview', [DashboardController::class, 'overview']);

    Route::apiResource('visitors', VisitorController::class);

    Route::apiResource('classes', SchoolClassController::class);

    Route::apiResource('sections', SectionController::class);

    Route::get('/sections-by-class/{id}', [SectionController::class, 'sectionsByClass']);

    Route::apiResource('parents', ParentProfileController::class);

    Route::apiResource('students', StudentController::class);

    Route::get('/feeheads_by_section/{id}', [FeeHeadController::class, 'feeHeadsBySection']);

    Route::apiResource('fee-head', FeeHeadController::class);

    Route::get('/students_by_parent/{id}', [StudentController::class, 'studentsByParent']);

    Route::apiResource('temp-add-keys', TempAddKeyController::class);

    Route::post('/verify_admission_key', [AuthController::class, 'checkTempKey']);

    Route::apiResource('branch', BranchController::class);

    Route::prefix('invoices')->group(function () {
        Route::post('/create', [InvoiceController::class, 'store']);
        Route::get('/', [InvoiceController::class, 'index']);
        Route::get('/{id}', [InvoiceController::class, 'show']);
        Route::delete('/{id}', [InvoiceController::class, 'destroy']);
    });

    Route::post('/payments/pay', [PaymentController::class, 'pay']);
    Route::get('/wallet/{parentId}', [PaymentController::class, 'wallet']);

    // =====================
    // CLASS SUBJECTS
    // =====================
    Route::apiResource('class-subjects', ClassSubjectController::class);

    Route::post('get-subjects', [ClassSubjectController::class, 'getSubjects']);

    // =====================
    // DIARIES
    // =====================
    // index() is role-scoped and takes section_id / class_subject_id / date / topic
    // filters, which is what the removed teacher-diaries and class-diaries routes
    // were for. They pointed at methods that never existed.
    Route::apiResource('diaries', DiaryController::class);

    Route::patch('diaries/{id}/status', [DiaryController::class, 'updateStatus']);


    // index() is role-scoped and takes section_id / subject_id / date filters,
    // which is what the removed admin/bulk-status/edit/delete-single/view routes
    // were for. `edit` pointed at a method that never existed.
    Route::apiResource('syllabus', SyllabusController::class);

    Route::patch('syllabus/{id}/status', [SyllabusController::class, 'updateStatus']);

    //lesson plan and questions bank

    // Subjects are now reused from /class-subjects — see ClassSubjectController.

    // ── QB Topics ────────────────────────────────────────────────
    Route::apiResource('qb-topics', QbTopicController::class);
    Route::put('qb-topics/{id}/lesson-plan', [QbTopicController::class, 'updateLessonPlan']);

    // ── QB Questions ─────────────────────────────────────────────
    Route::apiResource('qb-questions', QbQuestionController::class);

    // ── Lesson Plan ──────────────────────────────────────────────
    // Admin: view teachers + manage assignments
    Route::get('lesson-plan/teachers',                          [LessonPlanSubjectController::class, 'index']);
    Route::get('lesson-plan/teachers/{teacherId}/subjects',     [LessonPlanSubjectController::class, 'teacherSubjects']);
    Route::get('lesson-plan/teachers/{teacherId}/topics',       [LessonPlanSubjectController::class, 'teacherTopics']);
    Route::post('lesson-plan/teachers/{teacherId}/subjects',    [LessonPlanSubjectController::class, 'assignSubjects']);

    // Teacher: my lesson plan + topic toggle
    Route::get('lesson-plan/my',                                [LessonPlanSubjectController::class, 'myLessonPlan']);
    Route::post('lesson-plan/topic-status',                     [LessonPlanSubjectController::class, 'topicDoneStatus']);

    // =====================
    // STUDENT ATTENDANCE
    // =====================
    Route::prefix('attendance/students')->group(function () {
        Route::get('section',    [StudentAttendanceController::class, 'sectionView']);
        Route::post('mark',      [StudentAttendanceController::class, 'mark']);
        Route::get('report',     [StudentAttendanceController::class, 'report']);
        Route::get('parent-view',[StudentAttendanceController::class, 'parentView']);
        Route::get('my-children',[StudentAttendanceController::class, 'myChildren']);
        Route::get('summary',    [StudentAttendanceController::class, 'summary']);
    });

    // =====================
    // STAFF ATTENDANCE
    // =====================
    Route::prefix('attendance/staff')->group(function () {
        Route::get('branch-view',[StaffAttendanceController::class, 'branchView']);
        Route::post('mark',      [StaffAttendanceController::class, 'mark']);
        Route::get('report',     [StaffAttendanceController::class, 'report']);
        Route::get('summary',    [StaffAttendanceController::class, 'summary']);
    });

    // =====================
    // ROLES & PERMISSIONS
    // =====================
    Route::get('roles/modules',                    [RoleController::class, 'modules']);
    Route::get('roles/{id}/permissions',           [RoleController::class, 'permissions']);
    Route::put('roles/{id}/permissions',           [RoleController::class, 'updatePermissions']);
    Route::apiResource('roles', RoleController::class);

    // =====================
    // STAFF MANAGEMENT
    // =====================
    Route::apiResource('staff', StaffController::class);

});

