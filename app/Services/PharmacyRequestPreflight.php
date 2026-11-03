<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Solo lectura: no traduce estados ni rellena metadatos históricos. */
class PharmacyRequestPreflight
{
    public function inspect(): array
    {
        $conflicts = [];
        $warnings = [];
        $total = 0;
        DB::table('pharmacy_requests')->orderBy('id')->chunkById(250, function ($rows) use (&$conflicts, &$warnings, &$total): void {
            $reviewers = DB::table('users')->whereIn('id', $rows->pluck('reviewed_by')->filter()->unique())->pluck('id')->all();
            $pharmacies = DB::table('pharmacies')->whereIn('id', $rows->pluck('pharmacy_id')->filter()->unique())->pluck('id')->all();
            foreach ($rows as $row) {
                $total++;
                $reasons = [];
                if (! in_array($row->type, ['alta', 'baja'], true)) {
                    $reasons[] = 'invalid_type';
                }
                if (! in_array($row->status, ['pending', 'approved', 'rejected'], true)) {
                    $reasons[] = 'invalid_status';
                }
                if ($row->status === 'pending' && ($row->reviewed_by !== null || $row->reviewed_at !== null)) {
                    $reasons[] = 'pending_with_review_metadata';
                }
                if (in_array($row->status, ['approved', 'rejected'], true) && ($row->reviewed_by === null || $row->reviewed_at === null)) {
                    $reasons[] = 'resolved_without_reviewer_or_date';
                }
                if ($row->reviewed_by !== null && ! in_array($row->reviewed_by, $reviewers)) {
                    $reasons[] = 'missing_reviewer';
                }
                if ($row->reviewed_at !== null && ! $this->validTimestamp($row->reviewed_at)) {
                    $reasons[] = 'invalid_review_date';
                }
                if ($row->status === 'rejected' && $row->review_note === null) {
                    $reasons[] = 'rejection_without_note';
                }
                if ($row->status === 'rejected' && $row->review_note !== null && mb_strlen(trim($row->review_note)) < 10) {
                    $warnings[] = ['id' => $row->id, 'reason' => 'rejection_note_shorter_than_current_rule'];
                }
                if ($row->type === 'baja' && ($row->pharmacy_id === null || ! in_array($row->pharmacy_id, $pharmacies))) {
                    $reasons[] = 'baja_without_existing_pharmacy';
                }
                if ($reasons !== []) {
                    $conflicts[] = ['id' => $row->id, 'reasons' => $reasons];
                }
            }
        });

        return ['read_only' => true, 'checked' => $total, 'can_migrate' => $conflicts === [], 'conflicts' => $conflicts, 'warnings' => $warnings];
    }

    private function validTimestamp(string $value): bool
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2}):(\d{2})(?:\.\d+)?$/D', $value, $m)) {
            return false;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) && (int) $m[4] < 24 && (int) $m[5] < 60 && (int) $m[6] < 60;
    }
}
