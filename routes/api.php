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
use App\Http\Controllers\Api\QbSubjectController;
use App\Http\Controllers\Api\QbTopicController;
use App\Http\Controllers\Api\LessonPlanSubjectController;
use App\Http\Controllers\Api\QbQuestionController;
use App\Http\Controllers\Api\StudentAttendanceController;
use App\Http\Controllers\Api\StaffAttendanceController;
use App\Http\Controllers\Api\DashboardController;
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
    Route::apiResource('diaries', DiaryController::class);

    // extra filters
    Route::get('teacher-diaries', [DiaryController::class, 'teacherDiaries']);
    Route::get('class-diaries', [DiaryController::class, 'classDiaries']);

    Route::patch('diaries/{id}/status', [DiaryController::class, 'updateStatus']);
    Route::patch('section-diaries/status', [DiaryController::class, 'updateFullStatus']);


    Route::apiResource('syllabus', SyllabusController::class);

    Route::prefix('syllabus')->group(function () {

        Route::get('admin', [SyllabusController::class, 'adminIndex']);

        Route::post('status', [SyllabusController::class, 'updateStatus']);

        Route::post('bulk-status', [SyllabusController::class, 'updateFullStatus']);

        Route::post('edit', [SyllabusController::class, 'editSyllabus']);

        Route::post('delete-single', [SyllabusController::class, 'singleDelete']);

        Route::post('view', [SyllabusController::class, 'syllabusesView']);
    });

    //lesson plan and questions bank

      // ── QB Subjects ──────────────────────────────────────────────
    Route::apiResource('qb-subjects', QbSubjectController::class);

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

});

