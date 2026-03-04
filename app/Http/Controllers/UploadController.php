<?php

namespace App\Http\Controllers;

use App\Exceptions\AIServiceException;
use App\Models\Patient;
use App\Models\Prediction;
use App\Models\Scan;
use App\Services\AIService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    public function create(AIService $aiService)
    {
        $patients = Patient::query()
            ->whereHas('scans')
            ->orderBy('full_name')
            ->get(['id', 'medical_record_number', 'full_name', 'date_of_birth', 'sex']);

        $modelStatus = $aiService->modelStatus();

        return view('scans.upload', [
            'patients' => $patients,
            'modelStatus' => $modelStatus,
        ]);
    }

    public function store(Request $request, AIService $aiService): RedirectResponse
    {
        $validated = $request->validate([
            'patient_mode' => ['required', 'in:existing,new'],
            'patient_id' => ['required_if:patient_mode,existing', 'nullable', 'integer', 'exists:patients,id'],
            'medical_record_number' => ['required_if:patient_mode,new', 'nullable', 'string', 'max:50'],
            'full_name' => ['required_if:patient_mode,new', 'nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'sex' => ['nullable', 'in:male,female,other'],
            'modality' => ['required', 'in:xray,ct'],
            'operating_mode' => ['required', 'in:diagnostic,screening'],
            'selected_model' => ['required', 'in:hybrid,resnet,densenet,yolov8,kerashf'],
            'dataset_source' => ['nullable', 'string', 'max:255'],
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,dcm,tif,tiff', 'max:10240'],
        ]);

        if (($validated['selected_model'] ?? '') === 'yolov8' && ($validated['modality'] ?? '') !== 'xray') {
            return back()
                ->withInput()
                ->withErrors(['selected_model' => 'YOLOv8 model currently supports Chest X-ray modality only.']);
        }

        if ($validated['patient_mode'] === 'existing') {
            $patient = Patient::query()->findOrFail((int) $validated['patient_id']);
        } else {
            $patient = Patient::firstOrCreate(
                ['medical_record_number' => $validated['medical_record_number']],
                [
                    'full_name' => $validated['full_name'],
                    'date_of_birth' => $validated['date_of_birth'] ?? null,
                    'sex' => $validated['sex'] ?? null,
                ]
            );
        }

        $storedPath = $request->file('image')->store('', 'medical_images');

        $scan = Scan::create([
            'patient_id' => $patient->id,
            'uploaded_by' => $request->user()?->id,
            'modality' => $validated['modality'],
            'dataset_source' => $validated['dataset_source'] ?? null,
            'original_filename' => $request->file('image')->getClientOriginalName(),
            'storage_path' => $storedPath,
            'status' => 'uploaded',
        ]);

        $absoluteImagePath = Storage::disk('medical_images')->path($storedPath);
        try {
            $result = $aiService->predict(
                $absoluteImagePath,
                $validated['modality'],
                $validated['dataset_source'] ?? '',
                $validated['selected_model'],
                $validated['operating_mode']
            );
        } catch (AIServiceException $exception) {
            $scan->update(['status' => 'failed']);

            return back()
                ->withInput()
                ->withErrors(['image' => $exception->getMessage()]);
        }

        $currentVolume = isset($result['tumor_volume_mm3']) ? (float) $result['tumor_volume_mm3'] : null;
        $evaluatedAt = now();

        $previousPrediction = Prediction::query()
            ->whereHas('scan', fn ($query) => $query->where('patient_id', $patient->id))
            ->latest('evaluated_at')
            ->latest('id')
            ->first();

        $growthRatePercent = null;
        if ($previousPrediction && ! is_null($currentVolume)) {
            $previousVolume = $previousPrediction->tumor_volume_mm3;
            if (! is_null($previousVolume) && (float) $previousVolume > 0) {
                $growthRatePercent = round((($currentVolume - (float) $previousVolume) / (float) $previousVolume) * 100, 2);
            }
        }

        $prediction = Prediction::create([
            'scan_id' => $scan->id,
            'predicted_label' => $result['prediction'],
            'probability' => $result['probability'],
            'heatmap_path' => $result['heatmap'],
            'cancer_stage' => $result['cancer_stage'] ?? null,
            'confidence_reasoning' => $result['confidence_reasoning'] ?? null,
            'ct_viewer' => $result['ct_viewer'] ?? null,
            'finding_location' => $result['finding_location'],
            'severity_score' => $result['severity_score'],
            'confidence_band' => $result['confidence_band'],
            'region_confidence_score' => $result['region_confidence_score'] ?? null,
            'explanation_maps' => $result['explanation_maps'] ?? null,
            'nodule_diameter_mm' => $result['nodule_diameter_mm'] ?? null,
            'nodule_area_px' => $result['nodule_area_px'] ?? null,
            'tumor_area_mm2' => $result['tumor_area_mm2'] ?? null,
            'tumor_volume_mm3' => $result['tumor_volume_mm3'] ?? null,
            'growth_rate_percent' => $growthRatePercent,
            'nodule_burden_percent' => $result['nodule_burden_percent'] ?? null,
            'raw_response' => $result['raw'],
            'model_version' => $result['model_version'] ?? strtoupper($validated['selected_model']),
            'evaluated_at' => $evaluatedAt,
        ]);

        $scan->update(['status' => 'predicted']);

        return redirect()->route('predictions.show', $prediction)->with('status', 'Prediction completed successfully.');
    }
}
