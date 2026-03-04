<?php

namespace App\Services;

use App\Exceptions\AIServiceException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class AIService
{
    public function modelStatus(): array
    {
        try {
            $response = Http::baseUrl(config('services.ai_service.base_url'))
                ->timeout(4)
                ->get('/models/status');

            if (! $response->successful()) {
                return $this->defaultModelStatus();
            }

            $models = $response->json('models');

            return is_array($models) ? $models : $this->defaultModelStatus();
        } catch (\Throwable) {
            return $this->defaultModelStatus();
        }
    }

    public function predict(
        string $absoluteImagePath,
        string $modality = 'xray',
        string $datasetSource = '',
        string $selectedModel = 'hybrid',
        string $operatingMode = 'diagnostic'
    ): array
    {
        if (! is_file($absoluteImagePath)) {
            throw new AIServiceException('Image file not found for AI inference.');
        }

        try {
            $response = Http::baseUrl(config('services.ai_service.base_url'))
                ->timeout(config('services.ai_service.timeout'))
                ->attach('file', fopen($absoluteImagePath, 'r'), basename($absoluteImagePath))
                ->post('/predict', [
                    'modality' => $modality,
                    'dataset_source' => $datasetSource,
                    'selected_model' => $selectedModel,
                    'operating_mode' => $operatingMode,
                ]);
        } catch (ConnectionException $exception) {
            throw new AIServiceException('AI service is unavailable. Please ensure FastAPI is running on '.config('services.ai_service.base_url').'.', previous: $exception);
        }

        $this->ensureSuccessfulResponse($response);

        $payload = $response->json();

        return $this->mapPredictionPayload($payload, $selectedModel, $datasetSource, $operatingMode);
    }

    private function floatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private function arrayOrNull(mixed $value): ?array
    {
        return is_array($value) ? $value : null;
    }

    private function arrayOrEmpty(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private function mapPredictionPayload(array $payload, string $selectedModel, string $datasetSource, string $operatingMode): array
    {
        return [
            'prediction' => $payload['prediction'] ?? 'Unknown',
            'probability' => (float) ($payload['probability'] ?? 0),
            'malignancy_probability' => $this->floatOrNull($payload['malignancy_probability'] ?? null),
            'operating_mode' => $payload['operating_mode'] ?? $operatingMode,
            'malignancy_threshold' => $this->floatOrNull($payload['malignancy_threshold'] ?? null),
            'model_comparisons' => $this->arrayOrEmpty($payload['model_comparisons'] ?? null),
            'model_visuals' => $this->arrayOrNull($payload['model_visuals'] ?? null),
            'model_version' => $payload['model_version'] ?? strtoupper($selectedModel),
            'heatmap' => $payload['heatmap'] ?? null,
            'cancer_stage' => $payload['cancer_stage'] ?? null,
            'confidence_reasoning' => $payload['confidence_reasoning'] ?? null,
            'ct_viewer' => $this->arrayOrNull($payload['ct_viewer'] ?? null),
            'dataset_source' => $payload['dataset_source'] ?? $datasetSource,
            'finding_location' => $payload['finding_location'] ?? null,
            'severity_score' => $this->floatOrNull($payload['severity_score'] ?? null),
            'confidence_band' => $payload['confidence_band'] ?? null,
            'region_confidence_score' => $this->floatOrNull($payload['region_confidence_score'] ?? null),
            'top_suspicious_regions' => $this->arrayOrEmpty($payload['top_suspicious_regions'] ?? null),
            'lesion_quantification' => $this->arrayOrNull($payload['lesion_quantification'] ?? null),
            'quality_gate' => $this->arrayOrNull($payload['quality_gate'] ?? null),
            'preprocessing_metadata' => $this->arrayOrNull($payload['preprocessing_metadata'] ?? null),
            'explanation_maps' => $this->arrayOrNull($payload['explanation_maps'] ?? null),
            'nodule_diameter_mm' => $this->floatOrNull($payload['nodule_diameter_mm'] ?? null),
            'nodule_area_px' => $this->floatOrNull($payload['nodule_area_px'] ?? null),
            'tumor_area_mm2' => $this->floatOrNull($payload['tumor_area_mm2'] ?? null),
            'tumor_volume_mm3' => $this->floatOrNull($payload['tumor_volume_mm3'] ?? null),
            'nodule_burden_percent' => $this->floatOrNull($payload['nodule_burden_percent'] ?? null),
            'temperature_scaling' => $this->floatOrNull($payload['temperature_scaling'] ?? null),
            'raw' => $payload,
        ];
    }

    private function ensureSuccessfulResponse(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $detail = $response->json('detail');
        $reason = is_string($detail) && $detail !== ''
            ? $detail
            : 'AI service request failed with status '.$response->status();

        throw new AIServiceException($reason);
    }

    private function defaultModelStatus(): array
    {
        return [
            'hybrid' => [
                'label' => 'Hybrid',
                'status' => 'ready',
                'available' => true,
                'modalities' => ['xray', 'ct'],
            ],
            'resnet' => [
                'label' => 'ResNet',
                'status' => 'ready',
                'available' => true,
                'modalities' => ['xray', 'ct'],
            ],
            'densenet' => [
                'label' => 'DenseNet',
                'status' => 'ready',
                'available' => true,
                'modalities' => ['xray', 'ct'],
            ],
            'yolov8' => [
                'label' => 'YOLOv8',
                'status' => 'unknown',
                'available' => true,
                'modalities' => ['xray'],
                'note' => 'Chest X-ray only',
            ],
            'kerashf' => [
                'label' => 'KerasHF',
                'status' => 'unknown',
                'available' => true,
                'modalities' => ['xray', 'ct'],
                'note' => 'Histopathological model',
            ],
        ];
    }
}
