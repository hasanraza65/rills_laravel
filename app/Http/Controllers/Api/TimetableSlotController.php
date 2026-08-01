<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassSubject;
use App\Models\TimetableSlot;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TimetableSlotController extends Controller
{
    /**
     * The per-cell "Add" action: assign an academic subject or a non-teaching activity
     * to one period cell, with the teacher picked independently (any staff member
     * except Super Admin), or clear it by sending both ids as null.
     */
    public function update(Request $request, $timetableId, $slotId)
    {
        $slot = TimetableSlot::where('timetable_id', $timetableId)->findOrFail($slotId);

        $data = $request->validate([
            'class_subject_id' => 'nullable|exists:class_subjects,id',
            'timetable_activity_id' => 'nullable|exists:timetable_activities,id',
            'teacher_id' => 'nullable|exists:users,id',
        ]);

        if (!empty($data['class_subject_id']) && !empty($data['timetable_activity_id'])) {
            throw ValidationException::withMessages([
                'subject' => 'Choose either a subject or an activity for this period, not both.',
            ]);
        }

        if (!empty($data['class_subject_id'])) {
            $classSubject = ClassSubject::findOrFail($data['class_subject_id']);

            if ((int) $classSubject->section_id !== (int) $slot->section_id) {
                throw ValidationException::withMessages([
                    'class_subject_id' => 'This subject is not assigned to this section.',
                ]);
            }
        }

        if (!empty($data['teacher_id'])) {
            $this->assertTeacherFree($slot, (int) $data['teacher_id']);
        }

        $slot->update([
            'class_subject_id' => $data['class_subject_id'] ?? null,
            'timetable_activity_id' => $data['timetable_activity_id'] ?? null,
            'teacher_id' => $data['teacher_id'] ?? null,
        ]);

        return $slot->load(['classSubject.teacher', 'teacher', 'activity', 'section']);
    }

    /**
     * Campus-wide, exact-time-overlap conflict check: a teacher cannot be assigned to
     * two slots whose computed start/end overlap on the same day, within overlapping
     * date ranges, across ANY active Timetable/Group in the branch — not just the same
     * period number, since two Groups can have different period-length structures.
     */
    private function assertTeacherFree(TimetableSlot $slot, int $teacherId): void
    {
        $timetable = $slot->timetable;

        $conflict = TimetableSlot::where('teacher_id', $teacherId)
            ->where('day_of_week', $slot->day_of_week)
            ->where('id', '!=', $slot->id)
            ->where('start_time', '<', $slot->end_time)
            ->where('end_time', '>', $slot->start_time)
            ->whereHas('timetable', function ($q) use ($timetable) {
                $q->where('branch_id', $timetable->branch_id)
                    ->where('is_active', true)
                    ->where('date_from', '<=', $timetable->date_to)
                    ->where('date_to', '>=', $timetable->date_from);
            })
            ->with(['timetable.group', 'section'])
            ->first();

        if ($conflict) {
            $sectionLabel = $conflict->section?->name ?? "Section #{$conflict->section_id}";
            $groupLabel = $conflict->timetable?->group?->name ?? 'another timetable';

            throw ValidationException::withMessages([
                'teacher_id' => "This teacher is already assigned to {$sectionLabel} on this day from "
                    . substr($conflict->start_time, 0, 5) . ' to ' . substr($conflict->end_time, 0, 5)
                    . " in \"{$groupLabel}\".",
            ]);
        }
    }
}
