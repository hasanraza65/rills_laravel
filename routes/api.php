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
use App\Http\Controllers\Api\UserPermissionController;
use App\Http\Controllers\Api\TimetableGroupController;
use App\Http\Controllers\Api\TimetableActivityController;
use App\Http\Controllers\Api\TimetablePeriodSetController;
use App\Http\Controllers\Api\TimetableController;
use App\Http\Controllers\Api\TimetableSlotController;
use App\Http\Controllers\Api\ExamGroupController;
use App\Http\Controllers\Api\ExamGroupExamController;
use App\Http\Controllers\Api\ExamScheduleController;
use App\Http\Controllers\Api\ExamSubjectGroupController;
use App\Http\Controllers\Api\ExamStudentSubjectController;
use App\Http\Controllers\Api\ExamMarkController;
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

    // Split (rather than apiResource) so each verb can carry its own
    // permission:branches,<action> check — index/show=view, store=create,
    // update=edit, destroy=delete.
    Route::get('branch',          [BranchController::class, 'index'])->middleware('permission:branches,view');
    Route::get('branch/{id}',     [BranchController::class, 'show'])->middleware('permission:branches,view');
    Route::post('branch',         [BranchController::class, 'store'])->middleware('permission:branches,create');
    Route::put('branch/{id}',     [BranchController::class, 'update'])->middleware('permission:branches,edit');
    Route::patch('branch/{id}',   [BranchController::class, 'update'])->middleware('permission:branches,edit');
    Route::delete('branch/{id}',  [BranchController::class, 'destroy'])->middleware('permission:branches,delete');

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
        Route::get('section',    [StudentAttendanceController::class, 'sectionView'])->middleware('permission:student_attendance,view');
        Route::post('mark',      [StudentAttendanceController::class, 'mark'])->middleware('permission:student_attendance,create');
        Route::get('report',     [StudentAttendanceController::class, 'report'])->middleware('permission:student_attendance,view');
        Route::get('parent-view',[StudentAttendanceController::class, 'parentView'])->middleware('permission:student_attendance,view');
        Route::get('my-children',[StudentAttendanceController::class, 'myChildren'])->middleware('permission:student_attendance,view');
        Route::get('summary',    [StudentAttendanceController::class, 'summary'])->middleware('permission:student_attendance,view');
    });

    // =====================
    // STAFF ATTENDANCE
    // =====================
    Route::prefix('attendance/staff')->group(function () {
        Route::get('branch-view',[StaffAttendanceController::class, 'branchView'])->middleware('permission:staff_attendance,view');
        Route::post('mark',      [StaffAttendanceController::class, 'mark'])->middleware('permission:staff_attendance,create');
        Route::get('report',     [StaffAttendanceController::class, 'report'])->middleware('permission:staff_attendance,view');
        Route::get('summary',    [StaffAttendanceController::class, 'summary'])->middleware('permission:staff_attendance,view');
    });

    // =====================
    // TIME TABLE
    // =====================
    Route::prefix('timetable-groups')->group(function () {
        Route::get('/',        [TimetableGroupController::class, 'index'])->middleware('permission:timetable,view');
        Route::get('/{id}',    [TimetableGroupController::class, 'show'])->middleware('permission:timetable,view');
        Route::post('/',       [TimetableGroupController::class, 'store'])->middleware('permission:timetable,create');
        Route::put('/{id}',    [TimetableGroupController::class, 'update'])->middleware('permission:timetable,edit');
        Route::delete('/{id}', [TimetableGroupController::class, 'destroy'])->middleware('permission:timetable,delete');
    });

    Route::prefix('timetable-activities')->group(function () {
        Route::get('/',        [TimetableActivityController::class, 'index'])->middleware('permission:timetable,view');
        Route::post('/',       [TimetableActivityController::class, 'store'])->middleware('permission:timetable,create');
        Route::put('/{id}',    [TimetableActivityController::class, 'update'])->middleware('permission:timetable,edit');
        Route::delete('/{id}', [TimetableActivityController::class, 'destroy'])->middleware('permission:timetable,delete');
    });

    Route::prefix('timetable-period-sets')->group(function () {
        Route::get('/',        [TimetablePeriodSetController::class, 'index'])->middleware('permission:timetable,view');
        Route::get('/{id}',    [TimetablePeriodSetController::class, 'show'])->middleware('permission:timetable,view');
        Route::post('/',       [TimetablePeriodSetController::class, 'store'])->middleware('permission:timetable,create');
        Route::put('/{id}',    [TimetablePeriodSetController::class, 'update'])->middleware('permission:timetable,edit');
        Route::delete('/{id}', [TimetablePeriodSetController::class, 'destroy'])->middleware('permission:timetable,delete');
    });

    Route::prefix('timetables')->group(function () {
        Route::get('/',                 [TimetableController::class, 'index'])->middleware('permission:timetable,view');
        Route::get('/teacher-busy-slots', [TimetableController::class, 'teacherBusySlots'])->middleware('permission:timetable,view');
        Route::get('/{id}',             [TimetableController::class, 'show'])->middleware('permission:timetable,view');
        Route::post('/',                [TimetableController::class, 'store'])->middleware('permission:timetable,create');
        Route::put('/{id}',             [TimetableController::class, 'update'])->middleware('permission:timetable,edit');
        Route::post('/{id}/regenerate', [TimetableController::class, 'regenerate'])->middleware('permission:timetable,edit');
        Route::delete('/{id}',          [TimetableController::class, 'destroy'])->middleware('permission:timetable,delete');
        Route::get('/{id}/slots',       [TimetableController::class, 'slots'])->middleware('permission:timetable,view');
        Route::put('/{id}/slots/{slotId}', [TimetableSlotController::class, 'update'])->middleware('permission:timetable,edit');
        Route::get('/{id}/print/{day}',    [TimetableController::class, 'printDaily'])->middleware('permission:timetable,view');
    });

    Route::get('teacher-timetable/{teacherId}', [TimetableController::class, 'teacherPrint'])->middleware('permission:timetable,view');

    // =====================
    // EXAMINATION
    // =====================
    Route::prefix('exam-groups')->group(function () {
        Route::get('/',        [ExamGroupController::class, 'index'])->middleware('permission:examination,view');
        Route::post('/',       [ExamGroupController::class, 'store'])->middleware('permission:examination,create');
        Route::put('/{id}',    [ExamGroupController::class, 'update'])->middleware('permission:examination,edit');
        Route::delete('/{id}', [ExamGroupController::class, 'destroy'])->middleware('permission:examination,delete');
    });

    Route::prefix('exam-group-exams')->group(function () {
        Route::get('/',        [ExamGroupExamController::class, 'index'])->middleware('permission:examination,view');
        Route::post('/',       [ExamGroupExamController::class, 'store'])->middleware('permission:examination,create');
        Route::put('/{id}',    [ExamGroupExamController::class, 'update'])->middleware('permission:examination,edit');
        Route::delete('/{id}', [ExamGroupExamController::class, 'destroy'])->middleware('permission:examination,delete');
    });

    Route::prefix('exam-schedules')->group(function () {
        Route::get('/',          [ExamScheduleController::class, 'index'])->middleware('permission:examination,view');
        Route::post('/bulk-save', [ExamScheduleController::class, 'bulkSave'])->middleware('permission:examination,create');
        Route::delete('/{id}',   [ExamScheduleController::class, 'destroy'])->middleware('permission:examination,delete');
    });

    Route::prefix('exam-subject-groups')->group(function () {
        Route::get('/',  [ExamSubjectGroupController::class, 'index'])->middleware('permission:examination,view');
        Route::post('/', [ExamSubjectGroupController::class, 'store'])->middleware('permission:examination,create');
    });

    Route::prefix('exam-student-subjects')->group(function () {
        Route::post('/lookup',           [ExamStudentSubjectController::class, 'lookup'])->middleware('permission:examination,view');
        Route::post('/bulk-assign',      [ExamStudentSubjectController::class, 'bulkAssign'])->middleware('permission:examination,create');
        Route::put('/{studentId}',       [ExamStudentSubjectController::class, 'setForStudent'])->middleware('permission:examination,edit');
    });

    Route::prefix('exam-marks')->group(function () {
        Route::get('/',                    [ExamMarkController::class, 'index'])->middleware('permission:examination,view');
        Route::get('/marksheet',           [ExamMarkController::class, 'marksheet'])->middleware('permission:examination,view');
        Route::get('/student/{studentId}', [ExamMarkController::class, 'forStudent'])->middleware('permission:examination,view');
        Route::post('/bulk-save',          [ExamMarkController::class, 'bulkSave'])->middleware('permission:examination,create');
        Route::post('/bulk-save-for-student', [ExamMarkController::class, 'bulkSaveForStudent'])->middleware('permission:examination,create');
    });

    // =====================
    // ROLES & PERMISSIONS
    // =====================
    Route::get('roles/modules',                    [RoleController::class, 'modules']);
    Route::get('roles/{id}/permissions',           [RoleController::class, 'permissions']);
    Route::put('roles/{id}/permissions',           [RoleController::class, 'updatePermissions']);
    Route::apiResource('roles', RoleController::class);

    // =====================
    // PER-USER PERMISSION OVERRIDES
    // =====================
    Route::get('users/{id}/permissions', [UserPermissionController::class, 'show']);
    Route::put('users/{id}/permissions', [UserPermissionController::class, 'update']);

    // =====================
    // STAFF MANAGEMENT
    // =====================
    Route::apiResource('staff', StaffController::class);

});

