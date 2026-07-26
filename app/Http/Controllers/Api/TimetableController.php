<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Section;
use App\Models\Timetable;
use App\Models\TimetableSlot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TimetableController extends Controller
{
    public function index(Request $request)
    {
        $branchId = $request->branch_id ?: auth()->user()->branch_id;

        return Timetable::with(['group', 'periodSet', 'creator', 'updater'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->latest()
            ->get();
    }

    public function store(Request $request)
    {
        $data = $this->validateHeader($request);

        $timetable = DB::transaction(function () use ($data) {
            $timetable = Timetable::create([
                ...$data,
                'is_active' => $data['is_active'] ?? true,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $this->generateSlots($timetable);

            return $timetable;
        });

        return $timetable->load(['group', 'periodSet']);
    }

    public function show($id)
    {
        return Timetable::with(['group.classes', 'periodSet', 'creator', 'updater'])->findOrFail($id);
    }

    /**
     * Lightweight meta edit — title/active flag only. Changing period_set_id,
     * date_from/date_to, or school_time_from goes through regenerate() instead,
     * so an admin can rename/deactivate a Timetable without accidentally
     * rebuilding (and re-time-stamping) the whole grid.
     */
    public function update(Request $request, $id)
    {
        $timetable = Timetable::findOrFail($id);

        $data = $request->validate([
            'title' => 'required|string',
            'is_active' => 'required|boolean',
        ]);

        $timetable->update([...$data, 'updated_by' => auth()->id()]);

        return $timetable->load(['group', 'periodSet']);
    }

    /**
     * Re-run generation after a period_set/date/school_time_from change (or on demand).
     * Preserves existing class_subject_id/teacher_id/timetable_activity_id assignments.
     */
    public function regenerate(Request $request, $id)
    {
        $timetable = Timetable::findOrFail($id);

        $data = $request->validate([
            'timetable_group_id' => 'sometimes|exists:timetable_groups,id',
            'period_set_id' => 'sometimes|exists:timetable_period_sets,id',
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date|after_or_equal:date_from',
            'school_time_from' => 'sometimes|date_format:H:i',
            'title' => 'sometimes|string',
        ]);

        DB::transaction(function () use ($timetable, $data) {
            if (!empty($data)) {
                $timetable->update([...$data, 'updated_by' => auth()->id()]);
            }

            $this->generateSlots($timetable->fresh());
        });

        return $timetable->fresh(['group', 'periodSet', 'slots']);
    }

    public function destroy($id)
    {
        Timetable::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Timetable deleted',
        ]);
    }

    /**
     * The editable Period Allocation grid: every slot for this Timetable, grouped
     * for the frontend by section then day then period.
     */
    public function slots($id)
    {
        $timetable = Timetable::with('group.classes')->findOrFail($id);

        $slots = $timetable->slots()
            ->with(['section.schoolClass', 'classSubject.teacher', 'teacher', 'activity'])
            ->orderBy('section_id')
            ->orderBy('day_of_week')
            ->orderBy('period_number')
            ->get();

        return response()->json([
            'timetable' => $timetable,
            'slots' => $slots,
        ]);
    }

    /**
     * Report #1: one printable page for a single day, one row per Section this
     * Timetable covers, columns = every period with its computed time range.
     */
    public function printDaily($id, $day)
    {
        $timetable = Timetable::with('group')->findOrFail($id);

        $bySection = TimetableSlot::where('timetable_id', $timetable->id)
            ->where('day_of_week', $day)
            ->with(['section.schoolClass', 'classSubject', 'teacher', 'activity'])
            ->get()
            ->groupBy('section_id')
            ->map(function ($sectionSlots) {
                $section = $sectionSlots->first()->section;

                return [
                    'section_id' => $section->id,
                    'level_section_label' => trim(($section->schoolClass->name ?? '') . ' ' . $section->name),
                    'periods' => $sectionSlots->sortBy('period_number')->values()->map(fn ($slot) => [
                        'period_number' => $slot->period_number,
                        'time_label' => substr($slot->start_time, 0, 5) . ' - ' . substr($slot->end_time, 0, 5),
                        'subject_label' => $slot->classSubject->subject_name ?? $slot->activity->name ?? null,
                        'teacher_name' => $slot->teacher->name ?? null,
                    ]),
                ];
            })
            ->values();

        return response()->json([
            'timetable' => $timetable,
            'group_name' => $timetable->group->name,
            'day_of_week' => (int) $day,
            'sections' => $bySection,
        ]);
    }

    /**
     * Report #2: a teacher's full weekly schedule, aggregated across every active
     * Timetable/Group they appear in — not scoped to a single Group. Teachers may
     * only ever request their own (self-scoped below, same pattern as
     * DiaryController::canReadDiary).
     */
    public function teacherPrint($teacherId)
    {
        $user = auth()->user();

        if ((int) $user->user_role === 4) {
            $teacherId = $user->id;
        }

        $teacher = User::findOrFail($teacherId);

        $slotsByDay = TimetableSlot::where('teacher_id', $teacherId)
            ->whereHas('timetable', fn ($q) => $q->where('is_active', true))
            ->with(['section.schoolClass', 'classSubject', 'activity', 'timetable.periodSet.slots'])
            ->get()
            ->groupBy('day_of_week');

        $days = [];

        foreach (range(1, 7) as $day) {
            $daySlots = ($slotsByDay->get($day) ?? collect())->sortBy('period_number')->values();

            // Total periods that day, taken from the first relevant Timetable's Periods
            // template — a reasonable approximation for the common single-group case.
            // A teacher spanning multiple concurrent groups on the same day would need
            // a richer "school day" concept than the schema currently models.
            $totalPeriods = $daySlots->isNotEmpty()
                ? $daySlots->first()->timetable->periodSet->slots->where('day_of_week', $day)->count()
                : 0;

            $days[] = [
                'day_of_week' => $day,
                'periods' => $daySlots->map(fn ($slot) => [
                    'period_number' => $slot->period_number,
                    'time_label' => substr($slot->start_time, 0, 5) . ' - ' . substr($slot->end_time, 0, 5),
                    'subject_label' => $slot->classSubject->subject_name ?? $slot->activity->name ?? null,
                    'level_section_label' => trim(($slot->section->schoolClass->name ?? '') . ' ' . ($slot->section->name ?? '')),
                ]),
                'free_count' => max(0, $totalPeriods - $daySlots->count()),
            ];
        }

        return response()->json([
            'teacher' => ['id' => $teacher->id, 'name' => $teacher->name],
            'days' => $days,
        ]);
    }

    private function validateHeader(Request $request): array
    {
        return $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'timetable_group_id' => 'required|exists:timetable_groups,id',
            'period_set_id' => 'required|exists:timetable_period_sets,id',
            'title' => 'required|string',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'school_time_from' => 'required|date_format:H:i',
            'is_active' => 'sometimes|boolean',
        ]);
    }

    /**
     * Computes start/end clock times for every Section under the Timetable's Group,
     * for every day present in the chosen Periods template, by cumulatively summing
     * that day's period durations starting from school_time_from. Existing
     * class_subject_id/teacher_id/timetable_activity_id assignments are preserved on
     * upsert; slots whose (day, period) no longer exists in the period set are removed.
     *
     * Public (not just used internally by store()/regenerate()) so seeders/tests can
     * generate slots for a Timetable without going through HTTP.
     */
    public function generateSlots(Timetable $timetable): void
    {
        $timetable->loadMissing('group.classes', 'periodSet.slots');

        $classIds = $timetable->group->classes->pluck('id');

        $sections = Section::whereIn('school_class_id', $classIds)
            ->where('branch_id', $timetable->branch_id)
            ->get();

        $slotsByDay = $timetable->periodSet->slots
            ->groupBy('day_of_week')
            ->map(fn ($daySlots) => $daySlots->sortBy('period_number')->values());

        $validKeys = [];

        foreach ($sections as $section) {
            foreach ($slotsByDay as $day => $periodDurations) {
                // Carbon::parse (not createFromFormat) because school_time_from can be
                // 'H:i' here (fresh in-memory model right after store(), before any
                // DB round-trip normalizes it) or 'H:i:s' (after ->fresh()/regenerate()).
                $cursor = Carbon::parse($timetable->school_time_from);

                foreach ($periodDurations as $periodDuration) {
                    $start = $cursor->copy();
                    $end = $cursor->copy()->addMinutes($periodDuration->duration_minutes);

                    $timetable->slots()->updateOrCreate(
                        [
                            'section_id' => $section->id,
                            'day_of_week' => $day,
                            'period_number' => $periodDuration->period_number,
                        ],
                        [
                            'start_time' => $start->format('H:i:s'),
                            'end_time' => $end->format('H:i:s'),
                        ]
                    );

                    $validKeys[] = "{$section->id}-{$day}-{$periodDuration->period_number}";
                    $cursor = $end;
                }
            }
        }

        $timetable->slots()->get()->each(function ($slot) use ($validKeys) {
            $key = "{$slot->section_id}-{$slot->day_of_week}-{$slot->period_number}";

            if (!in_array($key, $validKeys, true)) {
                $slot->delete();
            }
        });
    }
}
