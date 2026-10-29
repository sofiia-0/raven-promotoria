<?php

return [
    'disk' => env('VISIT_EVIDENCE_DISK', 'visit_evidence'),
    'max_files' => 10,
    'max_file_bytes' => 10 * 1024 * 1024,
    'max_visit_bytes' => 40 * 1024 * 1024,
    'max_staged_files' => 20,
    'max_staged_bytes' => 80 * 1024 * 1024,
    'staged_hours' => 24,
    'zip_max_entries' => 200,
    'zip_max_expanded_bytes' => 100 * 1024 * 1024,
    'zip_max_ratio' => 100,
];
