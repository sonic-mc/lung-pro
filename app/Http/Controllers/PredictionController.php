<?php

namespace App\Http\Controllers;

use App\Models\Prediction;
use App\Models\Scan;
use App\Services\PredictionAnalyticsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PredictionController extends Controller
{
    public function index()
    {
        $predictions = Prediction::query()
            ->with(['scan.patient', 'feedback'])
            ->latest()
            ->paginate(15);

        $totalPredictions = Prediction::query()->count();
        $groundTruthCount = Prediction::query()
            ->whereIn('ground_truth_label', ['Malignant', 'Benign'])
            ->count();
        $groundTruthCoverage = $totalPredictions > 0
            ? round(($groundTruthCount / $totalPredictions) * 100, 2)
            : 0.0;

        $malignantCount = Prediction::query()
            ->where('predicted_label', 'Malignant')
            ->count();
        $benignCount = Prediction::query()
            ->where('predicted_label', 'Benign')
            ->count();
        $weeklyPredictions = Prediction::query()
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
        $averageRiskPercent = (float) Prediction::query()->avg('probability') * 100;

        $ctCount = Prediction::query()
            ->whereHas('scan', fn ($query) => $query->where('modality', 'ct'))
            ->count();
        $xrayCount = Prediction::query()
            ->whereHas('scan', fn ($query) => $query->where('modality', 'xray'))
            ->count();

        $resnetCount = Prediction::query()
            ->where('model_version', 'like', '%resnet%')
            ->count();
        $densenetCount = Prediction::query()
            ->where('model_version', 'like', '%densenet%')
            ->count();
        $yolov8Count = Prediction::query()
            ->where('model_version', 'like', '%yolov8%')
            ->count();
        $kerashfCount = Prediction::query()
            ->where('model_version', 'like', '%kerashf%')
            ->count();
        $hybridCount = max($totalPredictions - ($resnetCount + $densenetCount + $yolov8Count + $kerashfCount), 0);

        $recentActivities = Prediction::query()
            ->with(['scan.patient', 'feedback'])
            ->latest()
            ->limit(6)
            ->get();

        $weeklyTrendRows = Prediction::query()
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->get(['created_at', 'predicted_label']);

        $trendLabels = [];
        $trendUploads = [];
        $trendMalignantRate = [];

        for ($daysAgo = 6; $daysAgo >= 0; $daysAgo--) {
            $day = now()->subDays($daysAgo);
            $dayKey = $day->format('Y-m-d');
            $dayRows = $weeklyTrendRows->filter(
                fn ($row) => optional($row->created_at)?->format('Y-m-d') === $dayKey
            );

            $uploads = $dayRows->count();
            $malignant = $dayRows->filter(
                fn ($row) => (string) $row->predicted_label === 'Malignant'
            )->count();

            $trendLabels[] = $day->format('M d');
            $trendUploads[] = $uploads;
            $trendMalignantRate[] = $uploads > 0
                ? round(($malignant / $uploads) * 100, 2)
                : 0.0;
        }

        return view('predictions.index', compact(
            'predictions',
            'totalPredictions',
            'groundTruthCount',
            'groundTruthCoverage',
            'malignantCount',
            'benignCount',
            'weeklyPredictions',
            'averageRiskPercent',
            'ctCount',
            'xrayCount',
            'resnetCount',
            'densenetCount',
            'yolov8Count',
            'kerashfCount',
            'hybridCount',
            'recentActivities',
            'trendLabels',
            'trendUploads',
            'trendMalignantRate'
        ));
    }

    public function show(Prediction $prediction)
    {
        $prediction->load('scan.patient', 'feedback', 'comments.user', 'twoPassReviews.reviewer');

        return view('predictions.show', compact('prediction'));
    }

    public function comparison(PredictionAnalyticsService $analytics)
    {
        $predictions = Prediction::query()
            ->with(['scan.patient', 'feedback'])
            ->latest('evaluated_at')
            ->latest('id')
            ->get();

        $workspace = $analytics->buildComparisonWorkspaceData($predictions);

        $totalPredictions = $predictions->count();
        $groundTruthCount = $predictions
            ->filter(fn ($prediction) => in_array((string) $prediction->ground_truth_label, ['Malignant', 'Benign'], true))
            ->count();
        $groundTruthCoverage = $totalPredictions > 0
            ? round(($groundTruthCount / $totalPredictions) * 100, 2)
            : 0.0;

        return view('predictions.comparison', [
            'primaryStats' => $workspace['primaryStats'],
            'comparisonStats' => $workspace['comparisonStats'],
            'recentCases' => $workspace['recentCases'],
            'totalPredictions' => $totalPredictions,
            'groundTruthCount' => $groundTruthCount,
            'groundTruthCoverage' => $groundTruthCoverage,
        ]);
    }

    public function audit(PredictionAnalyticsService $analytics)
    {
        $predictions = Prediction::query()
            ->with(['scan.patient', 'feedback'])
            ->latest('evaluated_at')
            ->latest('id')
            ->get();

        $workspace = $analytics->buildAuditWorkspaceData($predictions);

        return view('predictions.audit', [
            'summary' => $workspace['summary'],
            'modelStats' => $workspace['modelStats'],
            'flaggedCases' => $workspace['flaggedCases'],
        ]);
    }

    public function statistics(Request $request, PredictionAnalyticsService $analytics)
    {
        $predictions = Prediction::query()
            ->with(['scan.patient', 'feedback'])
            ->latest('evaluated_at')
            ->latest('id')
            ->get();

        $workspace = $analytics->buildStatisticsWorkspaceData($predictions);

        $totalPredictions = $predictions->count();
        $groundTruthCount = $predictions
            ->filter(fn ($prediction) => in_array((string) $prediction->ground_truth_label, ['Malignant', 'Benign'], true))
            ->count();
        $groundTruthCoverage = $totalPredictions > 0
            ? round(($groundTruthCount / $totalPredictions) * 100, 2)
            : 0.0;

        $selectedRange = (string) $request->query('range', 'all');
        $allowedRanges = ['all', '7', '30', '90'];
        if (! in_array($selectedRange, $allowedRanges, true)) {
            $selectedRange = 'all';
        }

        $rangeDays = $selectedRange === 'all' ? null : (int) $selectedRange;
        $rangeStart = is_null($rangeDays) ? null : now()->subDays($rangeDays - 1)->startOfDay();

        $chronological = $predictions
            ->filter(function ($prediction) use ($rangeStart) {
                if (is_null($rangeStart)) {
                    return true;
                }

                $timestamp = $prediction->evaluated_at ?? $prediction->created_at;

                return ! is_null($timestamp) && $timestamp->greaterThanOrEqualTo($rangeStart);
            })
            ->sortBy(fn ($prediction) => $prediction->evaluated_at ?? $prediction->created_at)
            ->groupBy(fn ($prediction) => optional($prediction->evaluated_at ?? $prediction->created_at)?->format('Y-m-d'));

        $trendLabels = [];
        $truthCoverageTrend = [];
        $reviewedVolumeTrend = [];
        $cumulativeTotal = 0;
        $cumulativeGroundTruth = 0;

        foreach ($chronological as $date => $rows) {
            $label = $date ?: 'Unknown';
            $dailyTotal = $rows->count();
            $dailyGroundTruth = $rows
                ->filter(fn ($prediction) => in_array((string) $prediction->ground_truth_label, ['Malignant', 'Benign'], true))
                ->count();
            $dailyReviewed = $rows
                ->filter(function ($prediction) {
                    if (in_array((string) $prediction->ground_truth_label, ['Malignant', 'Benign'], true)) {
                        return true;
                    }

                    $decision = $prediction->feedback?->decision;

                    return in_array($decision, ['accept', 'reject'], true);
                })
                ->count();

            $cumulativeTotal += $dailyTotal;
            $cumulativeGroundTruth += $dailyGroundTruth;

            $trendLabels[] = $label;
            $truthCoverageTrend[] = $cumulativeTotal > 0
                ? round(($cumulativeGroundTruth / $cumulativeTotal) * 100, 2)
                : 0.0;
            $reviewedVolumeTrend[] = $dailyReviewed;
        }

        return view('predictions.statistics', [
            'reviewedCount' => $workspace['reviewedCount'],
            'metrics' => $workspace['metrics'],
            'pairwise' => $workspace['pairwise'],
            'totalPredictions' => $totalPredictions,
            'groundTruthCount' => $groundTruthCount,
            'groundTruthCoverage' => $groundTruthCoverage,
            'trendLabels' => $trendLabels,
            'truthCoverageTrend' => $truthCoverageTrend,
            'reviewedVolumeTrend' => $reviewedVolumeTrend,
            'selectedRange' => $selectedRange,
        ]);
    }

    public function exportStatistics(Request $request, PredictionAnalyticsService $analytics): StreamedResponse
    {
        $predictions = Prediction::query()
            ->with(['scan.patient', 'feedback'])
            ->latest('evaluated_at')
            ->latest('id')
            ->get();

        $workspace = $analytics->buildStatisticsWorkspaceData($predictions);

        $totalPredictions = $predictions->count();
        $groundTruthCount = $predictions
            ->filter(fn ($prediction) => in_array((string) $prediction->ground_truth_label, ['Malignant', 'Benign'], true))
            ->count();
        $groundTruthCoverage = $totalPredictions > 0
            ? round(($groundTruthCount / $totalPredictions) * 100, 2)
            : 0.0;

        $selectedRange = (string) $request->query('range', 'all');
        $allowedRanges = ['all', '7', '30', '90'];
        if (! in_array($selectedRange, $allowedRanges, true)) {
            $selectedRange = 'all';
        }

        $filename = 'statistics_export_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use (
            $workspace,
            $selectedRange,
            $totalPredictions,
            $groundTruthCount,
            $groundTruthCoverage
        ) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['Statistics Export']);
            fputcsv($out, ['Generated At', now()->toDateTimeString()]);
            fputcsv($out, ['Trend Range', $selectedRange === 'all' ? 'All time' : 'Last '.$selectedRange.' days']);
            fputcsv($out, ['Total Predictions', $totalPredictions]);
            fputcsv($out, ['Ground Truth Labeled', $groundTruthCount]);
            fputcsv($out, ['Truth Coverage (%)', $groundTruthCoverage]);
            fputcsv($out, ['Reviewed Cases Used in Statistics', $workspace['reviewedCount']]);
            fputcsv($out, []);

            fputcsv($out, ['Per-Model Metrics']);
            fputcsv($out, ['Model', 'Support', 'Accuracy (%)', 'Precision (%)', 'Recall (%)', 'F1-score (%)', 'TP', 'TN', 'FP', 'FN']);
            foreach ($workspace['metrics'] as $model => $metric) {
                fputcsv($out, [
                    $model,
                    $metric['support'] ?? 0,
                    $metric['accuracy'] ?? 0,
                    $metric['precision'] ?? 0,
                    $metric['recall'] ?? 0,
                    $metric['f1'] ?? 0,
                    $metric['tp'] ?? 0,
                    $metric['tn'] ?? 0,
                    $metric['fp'] ?? 0,
                    $metric['fn'] ?? 0,
                ]);
            }

            fputcsv($out, []);
            fputcsv($out, ['Pairwise McNemar Tests']);
            fputcsv($out, ['Pair', 'Discordant', 'A Better', 'B Better', 'Chi-square', 'p-value', 'Significant (p<0.05)']);
            foreach ($workspace['pairwise'] as $test) {
                fputcsv($out, [
                    $test['pair'] ?? 'N/A',
                    $test['discordant'] ?? 0,
                    $test['a_better'] ?? 0,
                    $test['b_better'] ?? 0,
                    $test['chi_square'] ?? 0,
                    $test['p_value'] ?? 'N/A',
                    ! empty($test['significant']) ? 'Yes' : 'No',
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function report(Prediction $prediction)
    {
        $prediction->load('scan.patient');

        $reportData = $this->buildReportData($prediction);

        return view('predictions.report', [
            'prediction' => $prediction,
            'reportData' => $reportData,
        ]);
    }

    public function downloadPdfReport(Prediction $prediction): Response
    {
        $prediction->load('scan.patient');
        $reportData = $this->buildReportData($prediction);

        $pdf = Pdf::loadView('reports.diagnostic-pdf', [
            'prediction' => $prediction,
            'reportData' => $reportData,
            'hospitalName' => env('HOSPITAL_NAME', 'LungCare Medical Center'),
            'hospitalTagline' => env('HOSPITAL_TAGLINE', 'AI-Assisted Thoracic Imaging Unit'),
        ])->setPaper('a4');

        $timestamp = now()->format('Ymd_His');
        $mrn = $prediction->scan->patient->medical_record_number ?? 'unknown';

        return $pdf->download("diagnostic_report_mrn_{$mrn}_{$timestamp}.pdf");
    }

    public function apiResult(Scan $scan): JsonResponse
    {
        $scan->load('prediction', 'patient');

        return response()->json([
            'scan_id' => $scan->id,
            'patient' => $scan->patient?->full_name,
            'dataset_source' => $scan->dataset_source,
            'prediction' => $scan->prediction?->predicted_label,
            'probability' => $scan->prediction?->probability,
            'model_comparisons' => data_get($scan->prediction?->raw_response, 'model_comparisons', []),
            'heatmap' => $scan->prediction?->heatmap_path,
            'cancer_stage' => $scan->prediction?->cancer_stage,
            'confidence_reasoning' => $scan->prediction?->confidence_reasoning,
            'ct_viewer' => $scan->prediction?->ct_viewer,
            'finding_location' => $scan->prediction?->finding_location,
            'severity_score' => $scan->prediction?->severity_score,
            'confidence_band' => $scan->prediction?->confidence_band,
            'region_confidence_score' => $scan->prediction?->region_confidence_score,
            'explanation_maps' => $scan->prediction?->explanation_maps,
            'nodule_diameter_mm' => $scan->prediction?->nodule_diameter_mm,
            'nodule_area_px' => $scan->prediction?->nodule_area_px,
            'tumor_area_mm2' => $scan->prediction?->tumor_area_mm2,
            'tumor_volume_mm3' => $scan->prediction?->tumor_volume_mm3,
            'growth_rate_percent' => $scan->prediction?->growth_rate_percent,
            'nodule_burden_percent' => $scan->prediction?->nodule_burden_percent,
        ]);
    }

    private function buildReportData(Prediction $prediction): array
    {
        $modelComparisons = data_get($prediction->raw_response, 'model_comparisons', []);
        $modelVisuals = data_get($prediction->raw_response, 'model_visuals', []);
        $modelOverlays = is_array($modelVisuals) ? data_get($modelVisuals, 'overlays', []) : [];
        $modelComparisonPanel = is_array($modelVisuals) ? data_get($modelVisuals, 'comparison_panel') : null;

        $findingsSummary = [
            'Primary classification: '.$prediction->predicted_label,
            'Predicted probability: '.number_format((float) $prediction->probability * 100, 2).'%',
            'Finding location: '.($prediction->finding_location ?? 'N/A'),
            'Cancer stage estimate: '.($prediction->cancer_stage ?? 'N/A'),
            'Severity score: '.(! is_null($prediction->severity_score) ? number_format((float) $prediction->severity_score, 2).'/100' : 'N/A'),
            'Region confidence: '.(! is_null($prediction->region_confidence_score) ? number_format((float) $prediction->region_confidence_score, 2).'/100' : 'N/A'),
            'Nodule diameter estimate: '.(! is_null($prediction->nodule_diameter_mm) ? number_format((float) $prediction->nodule_diameter_mm, 2).' mm' : 'N/A'),
            'Tumor area estimate: '.(! is_null($prediction->tumor_area_mm2) ? number_format((float) $prediction->tumor_area_mm2, 2).' mm²' : 'N/A'),
            'Tumor volume estimate: '.(! is_null($prediction->tumor_volume_mm3) ? number_format((float) $prediction->tumor_volume_mm3, 2).' mm³' : 'N/A'),
            'Growth rate: '.(! is_null($prediction->growth_rate_percent) ? number_format((float) $prediction->growth_rate_percent, 2).'%' : 'N/A'),
        ];

        $aiExplanation = [
            'Consensus band: '.($prediction->confidence_band ?? 'N/A'),
            'Confidence reasoning: '.($prediction->confidence_reasoning ?? 'N/A'),
            'Explainability maps: '.implode(', ', array_keys($prediction->explanation_maps ?? [])),
            'Model comparisons available: '.(is_array($modelComparisons) ? count($modelComparisons) : 0),
        ];

        $recommendation = 'Routine radiologist review advised.';
        if ($prediction->predicted_label === 'Malignant' || ((float) $prediction->severity_score >= 70)) {
            $recommendation = 'High-risk pattern detected. Recommend urgent specialist review, confirmatory CT protocol, and tissue diagnosis pathway as clinically indicated.';
        } elseif ((float) $prediction->probability >= 0.55) {
            $recommendation = 'Intermediate risk. Recommend short-interval follow-up imaging and multidisciplinary radiology review.';
        }

        return [
            'findingsSummary' => $findingsSummary,
            'aiExplanation' => $aiExplanation,
            'recommendation' => $recommendation,
            'modelComparisons' => is_array($modelComparisons) ? $modelComparisons : [],
            'modelVisuals' => [
                'comparison_panel' => is_string($modelComparisonPanel) ? $modelComparisonPanel : null,
                'overlays' => is_array($modelOverlays) ? $modelOverlays : [],
            ],
            'datasetSource' => $prediction->scan->dataset_source,
        ];
    }
}
