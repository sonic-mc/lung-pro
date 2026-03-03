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
        string $selectedModel = 'hybrid'
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
                ]);
        } catch (ConnectionException $exception) {
            throw new AIServiceException('AI service is unavailable. Please ensure FastAPI is running on '.config('services.ai_service.base_url').'.', previous: $exception);
        }

        $this->ensureSuccessfulResponse($response);

        $payload = $response->json();

        return [
            'prediction' => $payload['prediction'] ?? 'Unknown',
            'probability' => (float) ($payload['probability'] ?? 0),
            'model_comparisons' => is_array($payload['model_comparisons'] ?? null) ? $payload['model_comparisons'] : [],
            'model_visuals' => is_array($payload['model_visuals'] ?? null) ? $payload['model_visuals'] : null,
            'model_version' => $payload['model_version'] ?? strtoupper($selectedModel),
            'heatmap' => $payload['heatmap'] ?? null,
            'cancer_stage' => $payload['cancer_stage'] ?? null,
            'confidence_reasoning' => $payload['confidence_reasoning'] ?? null,
            'ct_viewer' => is_array($payload['ct_viewer'] ?? null) ? $payload['ct_viewer'] : null,
            'dataset_source' => $payload['dataset_source'] ?? $datasetSource,
            'finding_location' => $payload['finding_location'] ?? null,
            'severity_score' => isset($payload['severity_score']) ? (float) $payload['severity_score'] : null,
            'confidence_band' => $payload['confidence_band'] ?? null,
            'region_confidence_score' => isset($payload['region_confidence_score']) ? (float) $payload['region_confidence_score'] : null,
            'explanation_maps' => is_array($payload['explanation_maps'] ?? null) ? $payload['explanation_maps'] : null,
            'nodule_diameter_mm' => isset($payload['nodule_diameter_mm']) ? (float) $payload['nodule_diameter_mm'] : null,
            'nodule_area_px' => isset($payload['nodule_area_px']) ? (float) $payload['nodule_area_px'] : null,
            'tumor_area_mm2' => isset($payload['tumor_area_mm2']) ? (float) $payload['tumor_area_mm2'] : null,
            'tumor_volume_mm3' => isset($payload['tumor_volume_mm3']) ? (float) $payload['tumor_volume_mm3'] : null,
            'nodule_burden_percent' => isset($payload['nodule_burden_percent']) ? (float) $payload['nodule_burden_percent'] : null,
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
