<?php

namespace App\Services;

use App\Models\Prediction;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PredictionAnalyticsService
{
    public function buildComparisonWorkspaceData(Collection $predictions): array
    {
        $primaryStats = [
            'Hybrid' => $this->emptyPrimaryStats(),
            'ResNet' => $this->emptyPrimaryStats(),
            'DenseNet' => $this->emptyPrimaryStats(),
            'YOLOv8' => $this->emptyPrimaryStats(),
            'KerasHF' => $this->emptyPrimaryStats(),
        ];

        $comparisonStats = [
            'Hybrid' => $this->emptyComparisonStats(),
            'ResNet' => $this->emptyComparisonStats(),
            'DenseNet' => $this->emptyComparisonStats(),
            'YOLOv8' => $this->emptyComparisonStats(),
            'KerasHF' => $this->emptyComparisonStats(),
        ];

        $recentCases = [];

        foreach ($predictions as $prediction) {
            $primaryModel = $this->resolvePrimaryModelName($prediction->model_version);
            $modelComparisons = $this->normalizedModelComparisons($prediction);

            $this->accumulatePrimaryStats($primaryStats, $prediction, $primaryModel);
            $this->accumulateComparisonStats($comparisonStats, $prediction, $modelComparisons);

            if (count($recentCases) < 20) {
                $recentCases[] = [
                    'id' => $prediction->id,
                    'patient' => $prediction->scan->patient->full_name ?? '-',
                    'mrn' => $prediction->scan->patient->medical_record_number ?? '-',
                    'primary_model' => $primaryModel,
                    'final_label' => $prediction->predicted_label,
                    'final_probability' => (float) $prediction->probability,
                    'feedback' => $prediction->feedback?->decision,
                    'comparisons' => $modelComparisons,
                ];
            }
        }

        return [
            'primaryStats' => $this->finalizePrimaryStats($primaryStats),
            'comparisonStats' => $this->finalizeComparisonStats($comparisonStats),
            'recentCases' => $recentCases,
        ];
    }

    public function buildAuditWorkspaceData(Collection $predictions): array
    {
        $summary = [
            'total' => 0,
            'reviewed' => 0,
            'unreviewed' => 0,
            'proxy_tp' => 0,
            'proxy_tn' => 0,
            'proxy_fp' => 0,
            'proxy_fn' => 0,
        ];

        $modelStats = [
            'Hybrid' => ['total' => 0, 'reviewed' => 0, 'proxy_fp' => 0, 'proxy_fn' => 0, 'proxy_tp' => 0, 'proxy_tn' => 0],
            'ResNet' => ['total' => 0, 'reviewed' => 0, 'proxy_fp' => 0, 'proxy_fn' => 0, 'proxy_tp' => 0, 'proxy_tn' => 0],
            'DenseNet' => ['total' => 0, 'reviewed' => 0, 'proxy_fp' => 0, 'proxy_fn' => 0, 'proxy_tp' => 0, 'proxy_tn' => 0],
            'YOLOv8' => ['total' => 0, 'reviewed' => 0, 'proxy_fp' => 0, 'proxy_fn' => 0, 'proxy_tp' => 0, 'proxy_tn' => 0],
            'KerasHF' => ['total' => 0, 'reviewed' => 0, 'proxy_fp' => 0, 'proxy_fn' => 0, 'proxy_tp' => 0, 'proxy_tn' => 0],
        ];

        $flaggedCases = [];

        foreach ($predictions as $prediction) {
            $summary['total']++;

            $primaryModel = $this->resolvePrimaryModelName($prediction->model_version);
            if (isset($modelStats[$primaryModel])) {
                $modelStats[$primaryModel]['total']++;
            }

            $bucket = $this->resolveAuditBucket($prediction);
            $summary[$bucket]++;

            if ($bucket !== 'unreviewed') {
                $summary['reviewed']++;
                if (isset($modelStats[$primaryModel])) {
                    $modelStats[$primaryModel]['reviewed']++;
                    $modelStats[$primaryModel][$bucket]++;
                }
            }

            if (($bucket === 'proxy_fp' || $bucket === 'proxy_fn') && count($flaggedCases) < 100) {
                $flaggedCases[] = [
                    'id' => $prediction->id,
                    'patient' => $prediction->scan->patient->full_name ?? '-',
                    'mrn' => $prediction->scan->patient->medical_record_number ?? '-',
                    'model' => $primaryModel,
                    'modality' => strtoupper((string) ($prediction->scan->modality ?? '-')),
                    'bucket' => $bucket,
                    'predicted_label' => $prediction->predicted_label,
                    'probability' => (float) $prediction->probability,
                    'severity_score' => $prediction->severity_score,
                    'finding_location' => $prediction->finding_location,
                    'feedback_comment' => $prediction->feedback?->review_comment,
                    'evaluated_at' => $prediction->evaluated_at,
                ];
            }
        }

        $summary['unreviewed'] = max($summary['total'] - $summary['reviewed'], 0);

        $reviewed = max($summary['reviewed'], 1);
        $summary['proxy_fp_rate'] = round(($summary['proxy_fp'] / $reviewed) * 100, 2);
        $summary['proxy_fn_rate'] = round(($summary['proxy_fn'] / $reviewed) * 100, 2);

        foreach ($modelStats as $model => $row) {
            $modelReviewed = max((int) $row['reviewed'], 1);
            $modelStats[$model]['proxy_fp_rate'] = round(((int) $row['proxy_fp'] / $modelReviewed) * 100, 2);
            $modelStats[$model]['proxy_fn_rate'] = round(((int) $row['proxy_fn'] / $modelReviewed) * 100, 2);
        }

        return [
            'summary' => $summary,
            'modelStats' => $modelStats,
            'flaggedCases' => $flaggedCases,
        ];
    }

    public function buildStatisticsWorkspaceData(Collection $predictions): array
    {
        $models = ['Hybrid', 'ResNet', 'DenseNet', 'YOLOv8', 'KerasHF'];
        $metrics = [];
        $correctnessByModel = [
            'Hybrid' => [],
            'ResNet' => [],
            'DenseNet' => [],
            'YOLOv8' => [],
            'KerasHF' => [],
        ];

        foreach ($models as $model) {
            $metrics[$model] = $this->emptyClassificationMetrics();
        }

        $reviewedCount = 0;

        foreach ($predictions as $prediction) {
            $truthLabel = $this->resolveReferenceTruthLabel($prediction);
            if ($truthLabel === null) {
                continue;
            }

            $reviewedCount++;
            $comparisonMap = $this->modelComparisonMap($prediction);

            foreach ($models as $model) {
                $predictedLabel = data_get($comparisonMap, $model.'.result');
                if (! is_string($predictedLabel) || $predictedLabel === '') {
                    continue;
                }

                $isCorrect = $predictedLabel === $truthLabel;
                $correctnessByModel[$model][] = $isCorrect;

                $this->accumulateClassificationMetrics($metrics[$model], $predictedLabel, $truthLabel);
            }
        }

        foreach ($models as $model) {
            $metrics[$model] = $this->finalizeClassificationMetrics($metrics[$model]);
        }

        $pairs = [];
        for ($i = 0; $i < count($models); $i++) {
            for ($j = $i + 1; $j < count($models); $j++) {
                $pairs[] = ['a' => $models[$i], 'b' => $models[$j]];
            }
        }

        $pairwise = [];
        foreach ($pairs as $pair) {
            $pairwise[] = $this->computeMcNemarPair(
                $pair['a'],
                $pair['b'],
                $correctnessByModel[$pair['a']],
                $correctnessByModel[$pair['b']]
            );
        }

        return [
            'reviewedCount' => $reviewedCount,
            'metrics' => $metrics,
            'pairwise' => $pairwise,
        ];
    }

    private function emptyPrimaryStats(): array
    {
        return [
            'cases' => 0,
            'malignant' => 0,
            'benign' => 0,
            'accepted' => 0,
            'rejected' => 0,
            'probability_sum' => 0.0,
        ];
    }

    private function emptyComparisonStats(): array
    {
        return [
            'cases' => 0,
            'malignant' => 0,
            'benign' => 0,
            'agreement_with_primary' => 0,
            'probability_sum' => 0.0,
        ];
    }

    private function resolvePrimaryModelName(?string $modelVersion): string
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

    private function finalizePrimaryStats(array $stats): array
    {
        foreach ($stats as $modelName => $row) {
            $cases = max((int) $row['cases'], 1);
            $feedbackTotal = (int) $row['accepted'] + (int) $row['rejected'];
            $stats[$modelName]['avg_probability'] = round(((float) $row['probability_sum'] / $cases) * 100, 2);
            $stats[$modelName]['malignant_rate'] = round(((int) $row['malignant'] / $cases) * 100, 2);
            $stats[$modelName]['acceptance_rate'] = $feedbackTotal > 0
                ? round(((int) $row['accepted'] / $feedbackTotal) * 100, 2)
                : null;
        }

        return $stats;
    }

    private function finalizeComparisonStats(array $stats): array
    {
        foreach ($stats as $modelName => $row) {
            $cases = max((int) $row['cases'], 1);
            $stats[$modelName]['avg_probability'] = round(((float) $row['probability_sum'] / $cases) * 100, 2);
            $stats[$modelName]['malignant_rate'] = round(((int) $row['malignant'] / $cases) * 100, 2);
            $stats[$modelName]['agreement_rate'] = round(((int) $row['agreement_with_primary'] / $cases) * 100, 2);
        }

        return $stats;
    }

    private function accumulatePrimaryStats(array &$primaryStats, Prediction $prediction, string $primaryModel): void
    {
        if (! isset($primaryStats[$primaryModel])) {
            return;
        }

        $primaryStats[$primaryModel]['cases']++;
        $primaryStats[$primaryModel]['probability_sum'] += (float) $prediction->probability;

        if (($prediction->predicted_label ?? '') === 'Malignant') {
            $primaryStats[$primaryModel]['malignant']++;
        } else {
            $primaryStats[$primaryModel]['benign']++;
        }

        $decision = $prediction->feedback?->decision;
        if ($decision === 'accept') {
            $primaryStats[$primaryModel]['accepted']++;
        }
        if ($decision === 'reject') {
            $primaryStats[$primaryModel]['rejected']++;
        }
    }

    private function accumulateComparisonStats(array &$comparisonStats, Prediction $prediction, array $modelComparisons): void
    {
        foreach ($modelComparisons as $entry) {
            $modelName = data_get($entry, 'model');
            if (! is_string($modelName) || ! isset($comparisonStats[$modelName])) {
                continue;
            }

            $comparisonStats[$modelName]['cases']++;
            $comparisonStats[$modelName]['probability_sum'] += (float) data_get($entry, 'probability', 0);

            $result = (string) data_get($entry, 'result', '');
            if ($result === 'Malignant') {
                $comparisonStats[$modelName]['malignant']++;
            } elseif ($result === 'Benign') {
                $comparisonStats[$modelName]['benign']++;
            }

            if ($result !== '' && $result === $prediction->predicted_label) {
                $comparisonStats[$modelName]['agreement_with_primary']++;
            }
        }
    }

    private function normalizedModelComparisons(Prediction $prediction): array
    {
        $modelComparisons = data_get($prediction->raw_response, 'model_comparisons', []);

        return is_array($modelComparisons) ? $modelComparisons : [];
    }

    private function resolveAuditBucket(Prediction $prediction): string
    {
        $truthLabel = $this->resolveReferenceTruthLabel($prediction);
        $bucket = 'unreviewed';

        if (! in_array($truthLabel, ['Malignant', 'Benign'], true)) {
            return $bucket;
        }

        $predictedMalignant = ($prediction->predicted_label ?? '') === 'Malignant';
        $truthMalignant = $truthLabel === 'Malignant';

        if ($predictedMalignant && $truthMalignant) {
            $bucket = 'proxy_tp';
        } elseif (! $predictedMalignant && ! $truthMalignant) {
            $bucket = 'proxy_tn';
        } elseif ($predictedMalignant) {
            $bucket = 'proxy_fp';
        } else {
            $bucket = 'proxy_fn';
        }

        return $bucket;
    }

    private function resolveReferenceTruthLabel(Prediction $prediction): ?string
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

    private function modelComparisonMap(Prediction $prediction): array
    {
        $rows = $this->normalizedModelComparisons($prediction);
        $byModel = [];

        foreach ($rows as $row) {
            $modelName = data_get($row, 'model');
            if (! is_string($modelName) || $modelName === '') {
                continue;
            }

            $byModel[$modelName] = $row;
        }

        return $byModel;
    }

    private function emptyClassificationMetrics(): array
    {
        return [
            'tp' => 0,
            'tn' => 0,
            'fp' => 0,
            'fn' => 0,
            'support' => 0,
        ];
    }

    private function accumulateClassificationMetrics(array &$metrics, string $predictedLabel, string $truthLabel): void
    {
        $metrics['support']++;

        if ($predictedLabel === 'Malignant' && $truthLabel === 'Malignant') {
            $metrics['tp']++;
            return;
        }

        if ($predictedLabel === 'Malignant' && $truthLabel === 'Benign') {
            $metrics['fp']++;
            return;
        }

        if ($predictedLabel === 'Benign' && $truthLabel === 'Malignant') {
            $metrics['fn']++;
            return;
        }

        if ($predictedLabel === 'Benign' && $truthLabel === 'Benign') {
            $metrics['tn']++;
        }
    }

    private function finalizeClassificationMetrics(array $metrics): array
    {
        $support = max((int) $metrics['support'], 1);
        $tp = (int) $metrics['tp'];
        $tn = (int) $metrics['tn'];
        $fp = (int) $metrics['fp'];
        $fn = (int) $metrics['fn'];

        $precisionDen = max($tp + $fp, 1);
        $recallDen = max($tp + $fn, 1);
        $f1Den = max((2 * $tp) + $fp + $fn, 1);

        $metrics['accuracy'] = round((($tp + $tn) / $support) * 100, 2);
        $metrics['precision'] = round(($tp / $precisionDen) * 100, 2);
        $metrics['recall'] = round(($tp / $recallDen) * 100, 2);
        $metrics['f1'] = round(((2 * $tp) / $f1Den) * 100, 2);

        return $metrics;
    }

    private function computeMcNemarPair(string $modelA, string $modelB, array $correctA, array $correctB): array
    {
        $n = min(count($correctA), count($correctB));
        $b = 0;
        $c = 0;

        for ($index = 0; $index < $n; $index++) {
            $aCorrect = (bool) ($correctA[$index] ?? false);
            $bCorrect = (bool) ($correctB[$index] ?? false);

            if ($aCorrect && ! $bCorrect) {
                $b++;
            }
            if (! $aCorrect && $bCorrect) {
                $c++;
            }
        }

        $discordant = $b + $c;
        $chiSquare = 0.0;
        $pValue = null;
        if ($discordant > 0) {
            $chiSquare = ((abs($b - $c) - 1) ** 2) / $discordant;
            $pValue = $this->chiSquareDf1PValue($chiSquare);
        }

        return [
            'pair' => $modelA.' vs '.$modelB,
            'discordant' => $discordant,
            'a_better' => $b,
            'b_better' => $c,
            'chi_square' => round($chiSquare, 4),
            'p_value' => is_null($pValue) ? null : round($pValue, 6),
            'significant' => ! is_null($pValue) && $pValue < 0.05,
        ];
    }

    private function chiSquareDf1PValue(float $chiSquare): float
    {
        if ($chiSquare <= 0) {
            return 1.0;
        }

        $x = sqrt($chiSquare / 2);

        return $this->erfcApprox($x);
    }

    private function erfcApprox(float $x): float
    {
        $z = abs($x);
        $t = 1 / (1 + 0.5 * $z);
        $r = $t * exp(
            -$z * $z
            - 1.26551223
            + $t * (
                1.00002368
                + $t * (
                    0.37409196
                    + $t * (
                        0.09678418
                        + $t * (
                            -0.18628806
                            + $t * (
                                0.27886807
                                + $t * (
                                    -1.13520398
                                    + $t * (
                                        1.48851587
                                        + $t * (
                                            -0.82215223
                                            + $t * 0.17087277
                                        )
                                    )
                                )
                            )
                        )
                    )
                )
            )
        );

        return $x >= 0 ? $r : 2 - $r;
    }
}
