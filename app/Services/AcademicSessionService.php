<?php

namespace App\Services;

use App\Models\AcademicSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AcademicSessionService
{
    public function list()
    {
        return AcademicSession::orderBy('start_date', 'desc')->get();
    }

    public function create(array $data): AcademicSession
    {
        $session = AcademicSession::create($data);

        Log::info('Academic session created', ['session_id' => $session->id]);

        return $session;
    }

    public function update(AcademicSession $session, array $data): AcademicSession
    {
        $session->update($data);

        Log::info('Academic session updated', ['session_id' => $session->id]);

        return $session->fresh();
    }

    public function activate(AcademicSession $session): AcademicSession
    {
        DB::transaction(function () use ($session) {
            // Deactivate all other sessions
            AcademicSession::where('id', '!=', $session->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $session->update(['is_active' => true]);
        });

        Log::info('Academic session activated', ['session_id' => $session->id]);

        return $session->fresh();
    }
}
