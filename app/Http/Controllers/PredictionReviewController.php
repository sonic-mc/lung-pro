<?php

namespace App\Http\Controllers;

use App\Models\Prediction;
use App\Models\PredictionComment;
use App\Models\PredictionTwoPassReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PredictionReviewController extends Controller
{
    public function saveGroundTruth(Request $request, Prediction $prediction): RedirectResponse
    {
        $validated = $request->validate([
            'ground_truth_label' => ['required', 'in:Malignant,Benign'],
            'ground_truth_source' => ['nullable', 'string', 'max:255'],
        ]);

        $prediction->update([
            'ground_truth_label' => $validated['ground_truth_label'],
            'ground_truth_source' => $validated['ground_truth_source'] ?? null,
            'ground_truth_recorded_at' => now(),
        ]);

        return back()->with('status', 'Ground truth label saved.');
    }

    public function saveFeedback(Request $request, Prediction $prediction): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:accept,reject'],
            'review_comment' => ['nullable', 'string', 'max:5000'],
            'annotations' => ['nullable', 'string'],
        ]);

        $annotationPayload = null;
        if (! empty($validated['annotations'])) {
            $decoded = json_decode($validated['annotations'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $annotationPayload = $decoded;
            }
        }

        $prediction->feedback()->updateOrCreate(
            ['prediction_id' => $prediction->id],
            [
                'decision' => $validated['decision'],
                'review_comment' => $validated['review_comment'] ?? null,
                'annotations' => $annotationPayload,
                'reviewed_by' => $request->user()?->id,
                'reviewed_at' => now(),
            ]
        );

        return back()->with('status', 'Radiologist feedback saved.');
    }

    public function addComment(Request $request, Prediction $prediction): RedirectResponse
    {
        $validated = $request->validate([
            'comment' => ['required', 'string', 'max:5000'],
        ]);

        PredictionComment::create([
            'prediction_id' => $prediction->id,
            'user_id' => $request->user()?->id,
            'comment' => $validated['comment'],
        ]);

        return back()->with('status', 'Comment posted.');
    }

    public function saveTwoPassReview(Request $request, Prediction $prediction): RedirectResponse
    {
        $validated = $request->validate([
            'baseline_label' => ['required', 'in:Malignant,Benign'],
            'baseline_confidence' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'baseline_time_seconds' => ['nullable', 'integer', 'min:1', 'max:7200'],
            'assisted_label' => ['required', 'in:Malignant,Benign'],
            'assisted_confidence' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'assisted_time_seconds' => ['nullable', 'integer', 'min:1', 'max:7200'],
            'overlooked_nodule_recovered' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        PredictionTwoPassReview::query()->updateOrCreate(
            [
                'prediction_id' => $prediction->id,
                'reviewer_id' => $request->user()?->id,
            ],
            [
                'baseline_label' => $validated['baseline_label'],
                'baseline_confidence' => $validated['baseline_confidence'] ?? null,
                'baseline_time_seconds' => $validated['baseline_time_seconds'] ?? null,
                'assisted_label' => $validated['assisted_label'],
                'assisted_confidence' => $validated['assisted_confidence'] ?? null,
                'assisted_time_seconds' => $validated['assisted_time_seconds'] ?? null,
                'overlooked_nodule_recovered' => (bool) ($validated['overlooked_nodule_recovered'] ?? false),
                'notes' => $validated['notes'] ?? null,
                'completed_at' => now(),
            ]
        );

        return back()->with('status', 'Two-pass radiologist review saved.');
    }

    public function twoPassDashboard()
    {
        $reviews = PredictionTwoPassReview::query()
            ->with(['prediction.feedback', 'prediction.scan.patient', 'reviewer'])
            ->latest('completed_at')
            ->latest('id')
            ->get();

        $summary = $this->initializeTwoPassSummary($reviews->count());

        $recent = [];

        foreach ($reviews as $review) {
            [$truthLabel, $baselineCorrect, $assistedCorrect] = $this->resolveTwoPassCorrectness($review);
            $delta = $this->applyTwoPassOutcome($summary, $baselineCorrect, $assistedCorrect);
            $this->applyTwoPassEfficiency($summary, $review);

            if (count($recent) < 100) {
                $recent[] = $this->formatRecentTwoPassReview($review, $truthLabel, $delta);
            }
        }

        $this->finalizeTwoPassSummary($summary);

        return view('predictions.two-pass', [
            'summary' => $summary,
            'recent' => $recent,
        ]);
    }

    private function initializeTwoPassSummary(int $totalReviews): array
    {
        return [
            'total_reviews' => $totalReviews,
            'improved' => 0,
            'worsened' => 0,
            'unchanged' => 0,
            'baseline_correct' => 0,
            'assisted_correct' => 0,
            'overlooked_recovered' => 0,
            'time_pairs' => 0,
            'baseline_time_sum' => 0,
            'assisted_time_sum' => 0,
            'confidence_pairs' => 0,
            'baseline_confidence_sum' => 0,
            'assisted_confidence_sum' => 0,
        ];
    }

    private function resolveTwoPassCorrectness($review): array
    {
        $truthLabel = $this->referenceTruthLabel($review->prediction);
        $baselineCorrect = ! is_null($truthLabel) && $review->baseline_label === $truthLabel;
        $assistedCorrect = ! is_null($truthLabel) && $review->assisted_label === $truthLabel;

        return [$truthLabel, $baselineCorrect, $assistedCorrect];
    }

    private function applyTwoPassOutcome(array &$summary, bool $baselineCorrect, bool $assistedCorrect): string
    {
        if ($baselineCorrect) {
            $summary['baseline_correct']++;
        }
        if ($assistedCorrect) {
            $summary['assisted_correct']++;
        }

        if (! $baselineCorrect && $assistedCorrect) {
            $summary['improved']++;

            return 'Improved';
        }

        if ($baselineCorrect && ! $assistedCorrect) {
            $summary['worsened']++;

            return 'Worsened';
        }

        $summary['unchanged']++;

        return 'Unchanged';
    }

    private function applyTwoPassEfficiency(array &$summary, $review): void
    {
        if ($review->overlooked_nodule_recovered) {
            $summary['overlooked_recovered']++;
        }

        if (! is_null($review->baseline_time_seconds) && ! is_null($review->assisted_time_seconds)) {
            $summary['time_pairs']++;
            $summary['baseline_time_sum'] += (int) $review->baseline_time_seconds;
            $summary['assisted_time_sum'] += (int) $review->assisted_time_seconds;
        }

        if (! is_null($review->baseline_confidence) && ! is_null($review->assisted_confidence)) {
            $summary['confidence_pairs']++;
            $summary['baseline_confidence_sum'] += (float) $review->baseline_confidence;
            $summary['assisted_confidence_sum'] += (float) $review->assisted_confidence;
        }
    }

    private function formatRecentTwoPassReview($review, ?string $truthLabel, string $delta): array
    {
        return [
            'prediction_id' => $review->prediction_id,
            'patient' => $review->prediction->scan->patient->full_name ?? '-',
            'mrn' => $review->prediction->scan->patient->medical_record_number ?? '-',
            'model' => $this->modelNameFromVersion($review->prediction->model_version),
            'baseline_label' => $review->baseline_label,
            'assisted_label' => $review->assisted_label,
            'baseline_time_seconds' => $review->baseline_time_seconds,
            'assisted_time_seconds' => $review->assisted_time_seconds,
            'truth_label' => $truthLabel,
            'delta' => $delta,
            'overlooked_recovered' => (bool) $review->overlooked_nodule_recovered,
            'reviewer' => $review->reviewer?->name ?? 'Radiology Team',
            'completed_at' => $review->completed_at,
        ];
    }

    private function finalizeTwoPassSummary(array &$summary): void
    {
        $total = max((int) $summary['total_reviews'], 1);
        $summary['baseline_accuracy'] = round(($summary['baseline_correct'] / $total) * 100, 2);
        $summary['assisted_accuracy'] = round(($summary['assisted_correct'] / $total) * 100, 2);
        $summary['accuracy_gain'] = round($summary['assisted_accuracy'] - $summary['baseline_accuracy'], 2);
        $summary['overlooked_recovered_rate'] = round(($summary['overlooked_recovered'] / $total) * 100, 2);

        $timePairs = max((int) $summary['time_pairs'], 1);
        $summary['avg_baseline_time_seconds'] = round($summary['baseline_time_sum'] / $timePairs, 2);
        $summary['avg_assisted_time_seconds'] = round($summary['assisted_time_sum'] / $timePairs, 2);
        $summary['time_reduction_percent'] = $summary['avg_baseline_time_seconds'] > 0
            ? round((($summary['avg_baseline_time_seconds'] - $summary['avg_assisted_time_seconds']) / $summary['avg_baseline_time_seconds']) * 100, 2)
            : 0.0;

        $confidencePairs = max((int) $summary['confidence_pairs'], 1);
        $summary['avg_baseline_confidence'] = round($summary['baseline_confidence_sum'] / $confidencePairs, 2);
        $summary['avg_assisted_confidence'] = round($summary['assisted_confidence_sum'] / $confidencePairs, 2);
        $summary['confidence_gain'] = round($summary['avg_assisted_confidence'] - $summary['avg_baseline_confidence'], 2);
    }

    private function referenceTruthLabel(Prediction $prediction): ?string
    {
        $truthLabel = null;

        $groundTruth = (string) ($prediction->ground_truth_label ?? '');
        if (in_array($groundTruth, ['Malignant', 'Benign'], true)) {
            $truthLabel = $groundTruth;
        } else {
            $predictedLabel = (string) ($prediction->predicted_label ?? '');
            $decision = $prediction->feedback?->decision;

            if ($predictedLabel !== '' && in_array($decision, ['accept', 'reject'], true)) {
                if ($decision === 'accept') {
                    $truthLabel = $predictedLabel;
                } else {
                    $truthLabel = $predictedLabel === 'Malignant' ? 'Benign' : 'Malignant';
                }
            }
        }

        return $truthLabel;
    }

    private function modelNameFromVersion(?string $modelVersion): string
    {
        $value = strtolower((string) $modelVersion);
        $name = 'Hybrid';

        if (Str::contains($value, 'resnet')) {
            $name = 'ResNet';
        } elseif (Str::contains($value, 'densenet')) {
            $name = 'DenseNet';
        } elseif (Str::contains($value, 'yolov8') || Str::contains($value, 'yolo')) {
            $name = 'YOLOv8';
        } elseif (Str::contains($value, 'kerashf') || Str::contains($value, 'keras')) {
            $name = 'KerasHF';
        }

        return $name;
    }
}
