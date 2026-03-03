<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use Illuminate\Http\Request;

class PatientHistoryController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $patients = Patient::query()
            ->with(['scans.prediction'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('full_name', 'like', '%'.$search.'%')
                        ->orWhere('medical_record_number', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('full_name')
            ->paginate(15)
            ->withQueryString();

        $patients->getCollection()->transform(function (Patient $patient) {
            $predictions = $patient->scans
                ->pluck('prediction')
                ->filter()
                ->values();

            $latest = $predictions
                ->sortByDesc(fn ($prediction) => $prediction->evaluated_at ?? $prediction->created_at)
                ->first();

            $patient->setAttribute('prediction_count', $predictions->count());
            $patient->setAttribute('latest_prediction', $latest);

            return $patient;
        });

        return view('patients.index', [
            'patients' => $patients,
            'search' => $search,
        ]);
    }

    public function show(Patient $patient)
    {
        $predictions = $patient->scans()
            ->with(['prediction.feedback', 'prediction.scan'])
            ->get()
            ->pluck('prediction')
            ->filter()
            ->sortBy(fn ($prediction) => $prediction->evaluated_at ?? $prediction->created_at)
            ->values();

        $latest = $predictions->last();
        $previous = $predictions->count() > 1 ? $predictions->slice(-2, 1)->first() : null;

        $labels = $predictions
            ->map(fn ($p) => optional($p->evaluated_at ?? $p->created_at)?->format('Y-m-d H:i'))
            ->values();

        $probabilitySeries = $predictions
            ->map(fn ($p) => round((float) $p->probability * 100, 2))
            ->values();

        $diameterSeries = $predictions
            ->map(fn ($p) => is_null($p->nodule_diameter_mm) ? null : round((float) $p->nodule_diameter_mm, 2))
            ->values();

        $volumeSeries = $predictions
            ->map(fn ($p) => is_null($p->tumor_volume_mm3) ? null : round((float) $p->tumor_volume_mm3, 2))
            ->values();

        $growthRateSeries = $predictions
            ->map(fn ($p) => is_null($p->growth_rate_percent) ? null : round((float) $p->growth_rate_percent, 2))
            ->values();

        $timeline = $predictions->sortByDesc(fn ($p) => $p->evaluated_at ?? $p->created_at)->values();

        return view('patients.history', [
            'patient' => $patient,
            'latest' => $latest,
            'previous' => $previous,
            'timeline' => $timeline,
            'labels' => $labels,
            'probabilitySeries' => $probabilitySeries,
            'diameterSeries' => $diameterSeries,
            'volumeSeries' => $volumeSeries,
            'growthRateSeries' => $growthRateSeries,
        ]);
    }
}
