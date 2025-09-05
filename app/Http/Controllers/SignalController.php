<?php

namespace App\Http\Controllers;

use App\Models\Signal;
use App\Models\SignalLeg;
use App\Jobs\ProcessSignalJob;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SignalController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'signal_id' => ['required', 'uuid'],
            'created_at' => ['required', 'date'],
            'ttl_ms' => ['required', 'integer', 'min:1'],
            'legs' => ['required', 'array', 'min:1'],
            'legs.*.Exchange' => ['required', 'string'],
            'legs.*.market' => ['required', 'string'],
            'legs.*.Side' => ['required', 'string'],
            'legs.*.Price' => ['required', 'numeric'],
            'legs.*.Qty' => ['required', 'numeric'],
            'legs.*.time_in_force' => ['required', 'string'],
            'constraints' => ['sometimes', 'array'],
        ]);

        if (Carbon::parse($data['created_at'])->addMilliseconds($data['ttl_ms'])->isPast()) {
            throw ValidationException::withMessages(['ttl_ms' => 'Signal expired']);
        }

        $signal = Signal::find($data['signal_id']);
        if ($signal) {
            return response()->json($signal->load('legs'));
        }

        DB::transaction(function () use ($data) {
            $signal = Signal::create([
                'id' => $data['signal_id'],
                'created_at' => $data['created_at'],
                'ttl_ms' => $data['ttl_ms'],
                'constraints' => $data['constraints'] ?? null,
                'status' => 'PENDING',
                'source' => 'api',
            ]);

            foreach ($data['legs'] as $leg) {
                SignalLeg::create([
                    'signal_id' => $signal->id,
                    'exchange' => $leg['Exchange'],
                    'market' => $leg['market'],
                    'side' => strtolower($leg['Side']),
                    'price' => $leg['Price'],
                    'qty' => $leg['Qty'],
                    'time_in_force' => $leg['time_in_force'],
                ]);
            }
        });
        $signal = Signal::with('legs')->find($data['signal_id']);
        ProcessSignalJob::dispatch($signal->id);

        return response()->json($signal, 201);
    }
}
