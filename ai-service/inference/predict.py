from datetime import datetime, timezone
import importlib
import json
import os
from pathlib import Path
import sys

import cv2
import numpy as np
import torch

try:
    from ultralytics import YOLO as UltralyticsYOLO  # type: ignore[import-not-found]
except Exception:
    UltralyticsYOLO = None

from models.densenet import build_densenet_classifier
from models.hybrid_model import HybridLungCancerModel
from models.resnet import build_resnet_classifier
from preprocessing.loader import preprocess_for_model
from preprocessing.scan_validation import assess_scan_quality, validate_scan_input
from utils.config import (
    CHECKPOINT_DIR,
    CALIBRATION_FILE,
    CLASS_NAMES,
    HEATMAP_DIR,
    IMAGE_SIZE,
    MALIGNANCY_THRESHOLD_DIAGNOSTIC,
    MALIGNANCY_THRESHOLD_SCREENING,
    MODEL_VERSION,
    QUALITY_GATE_MIN_SCORE,
    TEMPERATURE_SCALING,
)
from utils.visualization import generate_ct_viewer_assets, generate_explanation_maps, generate_model_comparison_visuals

_hybrid_model: HybridLungCancerModel | None = None
_resnet_model: torch.nn.Module | None = None
_densenet_model: torch.nn.Module | None = None
_yolov8_model = None
_keras_hf_model = None
_calibration_profile: dict | None = None


def _import_keras_module():
    os.environ.setdefault('KERAS_BACKEND', 'jax')

    try:
        return importlib.import_module('keras')
    except Exception:
        os.environ['KERAS_BACKEND'] = 'torch'
        sys.modules.pop('keras', None)
        return importlib.import_module('keras')


def get_model_statuses() -> dict:
    yolov8_available = UltralyticsYOLO is not None
    keras_hf_available = True
    keras_note = 'Histopathological model'

    try:
        _import_keras_module()
    except Exception:
        keras_hf_available = False
        keras_note = 'Keras backend unavailable in runtime environment'

    return {
        'hybrid': {
            'label': 'Hybrid',
            'status': 'ready',
            'available': True,
            'modalities': ['xray', 'ct'],
        },
        'resnet': {
            'label': 'ResNet',
            'status': 'ready',
            'available': True,
            'modalities': ['xray', 'ct'],
        },
        'densenet': {
            'label': 'DenseNet',
            'status': 'ready',
            'available': True,
            'modalities': ['xray', 'ct'],
        },
        'yolov8': {
            'label': 'YOLOv8',
            'status': 'ready' if yolov8_available else 'unavailable',
            'available': yolov8_available,
            'modalities': ['xray'],
            'note': 'Chest X-ray only',
        },
        'kerashf': {
            'label': 'KerasHF',
            'status': 'ready' if keras_hf_available else 'unavailable',
            'available': keras_hf_available,
            'modalities': ['xray', 'ct'],
            'note': keras_note,
        },
    }


def _estimate_finding_location(mask_tensor: torch.Tensor) -> str:
    mask = mask_tensor.squeeze().detach().cpu().numpy()
    peak = float(mask.max())
    if peak < 0.25:
        return 'No focal high-activation region'

    threshold = max(0.45, peak * 0.6)
    binary = mask >= threshold

    if not binary.any():
        y_idx, x_idx = divmod(int(mask.argmax()), mask.shape[1])
    else:
        ys, xs = binary.nonzero()
        y_idx = int(ys.mean())
        x_idx = int(xs.mean())

    x_norm = x_idx / max(mask.shape[1] - 1, 1)
    y_norm = y_idx / max(mask.shape[0] - 1, 1)

    laterality = 'Left Hemithorax (image space)' if x_norm < 0.5 else 'Right Hemithorax (image space)'

    if y_norm < 0.33:
        zone = 'Upper Lung Zone'
    elif y_norm < 0.66:
        zone = 'Mid Lung Zone'
    else:
        zone = 'Lower Lung Zone'

    return f'{laterality}, {zone}'


def _confidence_band(max_probability: float) -> str:
    if max_probability >= 0.85:
        return 'High'
    if max_probability >= 0.70:
        return 'Moderate'
    return 'Low'


def _nodule_growth_metrics(mask_tensor: torch.Tensor) -> dict:
    mask = mask_tensor.squeeze().detach().cpu().numpy()
    threshold = 0.55
    binary = (mask >= threshold).astype('uint8')

    area_px = float(binary.sum())
    total_px = float(binary.shape[0] * binary.shape[1])
    burden_percent = round((area_px / total_px) * 100, 2) if total_px > 0 else 0.0

    if area_px <= 0:
        diameter_px = 0.0
    else:
        diameter_px = float((4 * area_px / 3.141592653589793) ** 0.5)

    pixel_spacing_mm = 0.7
    diameter_mm = round(diameter_px * pixel_spacing_mm, 2)
    area_mm2 = round(area_px * (pixel_spacing_mm ** 2), 2)
    radius_mm = diameter_mm / 2
    volume_mm3 = round((4 / 3) * 3.141592653589793 * (radius_mm ** 3), 2) if diameter_mm > 0 else 0.0

    return {
        'nodule_diameter_mm': diameter_mm,
        'nodule_area_px': round(area_px, 2),
        'tumor_area_mm2': area_mm2,
        'tumor_volume_mm3': volume_mm3,
        'nodule_burden_percent': burden_percent,
    }


def _predict_cancer_stage(label: str, severity_score: float, nodule_diameter_mm: float) -> str:
    if label == 'Benign' and severity_score < 35:
        return 'Stage 0 (Low Suspicion)'
    if nodule_diameter_mm < 10 and severity_score < 55:
        return 'Stage IA'
    if nodule_diameter_mm < 20 and severity_score < 70:
        return 'Stage IB'
    if nodule_diameter_mm < 30 and severity_score < 80:
        return 'Stage IIA'
    if severity_score < 90:
        return 'Stage III'
    return 'Stage IV (High Suspicion)'


def _build_confidence_reasoning(
    confidence_band: str,
    region_confidence_score: float,
    model_comparisons: list[dict],
    dataset_source: str,
) -> str:
    result_counts: dict[str, int] = {}
    for row in model_comparisons:
        result = row.get('result')
        if isinstance(result, str):
            result_counts[result] = result_counts.get(result, 0) + 1

    consensus = 'No consensus data'
    if result_counts:
        top_count = max(result_counts.values())
        consensus = f"{top_count}/{len(model_comparisons)} models agree"

    return (
        f"Confidence band is {confidence_band}; region confidence score is {region_confidence_score:.2f}/100; "
        f"ensemble consensus: {consensus}; dataset source: {dataset_source or 'Not specified'}."
    )


def _load_checkpoint_if_exists(model: torch.nn.Module, checkpoint_name: str) -> torch.nn.Module:
    checkpoint_path = CHECKPOINT_DIR / checkpoint_name
    if checkpoint_path.exists():
        state = torch.load(checkpoint_path, map_location="cpu")
        model.load_state_dict(state)
    model.eval()
    return model


def _get_hybrid_model() -> HybridLungCancerModel:
    global _hybrid_model
    if _hybrid_model is not None:
        return _hybrid_model

    model = HybridLungCancerModel()
    _hybrid_model = _load_checkpoint_if_exists(model, "hybrid_model.pt")
    return _hybrid_model


def _get_resnet_model() -> torch.nn.Module:
    global _resnet_model
    if _resnet_model is not None:
        return _resnet_model

    model = build_resnet_classifier(num_classes=2)
    _resnet_model = _load_checkpoint_if_exists(model, "resnet_model.pt")
    return _resnet_model


def _get_densenet_model() -> torch.nn.Module:
    global _densenet_model
    if _densenet_model is not None:
        return _densenet_model

    model = build_densenet_classifier(num_classes=2)
    _densenet_model = _load_checkpoint_if_exists(model, "densenet_model.pt")
    return _densenet_model


def _get_yolov8_model():
    global _yolov8_model
    if _yolov8_model is not None:
        return _yolov8_model

    if UltralyticsYOLO is None:
        raise ValueError('Ultralytics is not installed. Please install requirements before using YOLOv8 model.')

    model_repo = 'keremberke/yolov8m-chest-xray-classification'

    if hasattr(UltralyticsYOLO, 'from_pretrained'):
        _yolov8_model = UltralyticsYOLO.from_pretrained(model_repo)
    else:
        _yolov8_model = UltralyticsYOLO(model_repo)

    return _yolov8_model


def _map_yolo_probs_to_binary(probabilities: np.ndarray, names: dict | list | None) -> torch.Tensor:
    probs = np.asarray(probabilities, dtype=np.float32).flatten()
    if probs.size == 0:
        return torch.tensor([0.5, 0.5], dtype=torch.float32)

    normalized = probs / max(float(probs.sum()), 1e-8)

    labels: list[str] = []
    if isinstance(names, dict):
        labels = [str(names.get(i, '')).lower() for i in range(len(normalized))]
    elif isinstance(names, list):
        labels = [str(item).lower() for item in names[: len(normalized)]]
    else:
        labels = [''] * len(normalized)

    benign_tokens = ('benign', 'normal', 'negative', 'healthy')
    malignant_tokens = ('malignant', 'cancer', 'carcinoma', 'tumor', 'mass')

    benign_idx = next((i for i, label in enumerate(labels) if any(token in label for token in benign_tokens)), None)
    malignant_idx = next((i for i, label in enumerate(labels) if any(token in label for token in malignant_tokens)), None)

    if benign_idx is None and normalized.size == 2:
        benign_idx = 0
    if malignant_idx is None and normalized.size == 2:
        malignant_idx = 1

    if benign_idx is None:
        benign_idx = int(np.argmax(normalized))
    if malignant_idx is None:
        malignant_candidates = [i for i in range(len(normalized)) if i != benign_idx]
        malignant_idx = malignant_candidates[0] if malignant_candidates else benign_idx

    benign_prob = float(normalized[benign_idx])
    malignant_prob = float(normalized[malignant_idx])
    denom = benign_prob + malignant_prob
    if denom <= 1e-8:
        return torch.tensor([0.5, 0.5], dtype=torch.float32)

    return torch.tensor([benign_prob / denom, malignant_prob / denom], dtype=torch.float32)


def _infer_yolov8_probs(image_path: str | Path) -> torch.Tensor:
    model = _get_yolov8_model()
    results = model.predict(source=str(image_path), verbose=False)
    if not results:
        return torch.tensor([0.5, 0.5], dtype=torch.float32)

    probs_obj = getattr(results[0], 'probs', None)
    if probs_obj is None or getattr(probs_obj, 'data', None) is None:
        return torch.tensor([0.5, 0.5], dtype=torch.float32)

    probs_np = probs_obj.data.detach().cpu().numpy().astype(np.float32)
    names = getattr(results[0], 'names', None)
    return _map_yolo_probs_to_binary(probs_np, names)


def _get_keras_hf_model():
    global _keras_hf_model
    if _keras_hf_model is not None:
        return _keras_hf_model

    keras = _import_keras_module()
    _keras_hf_model = keras.saving.load_model('hf://ahmEdimrann/Histopathological-Lung-and-Colon-Cancer-Dectection')
    return _keras_hf_model


def _infer_keras_hf_probs(image_rgb: np.ndarray) -> torch.Tensor:
    model = _get_keras_hf_model()
    resized = cv2.resize(image_rgb, (224, 224), interpolation=cv2.INTER_CUBIC).astype(np.float32) / 255.0
    batch = np.expand_dims(resized, axis=0)

    prediction = model.predict(batch, verbose=0)
    values = np.asarray(prediction, dtype=np.float32).squeeze()

    if values.ndim == 0:
        malignant = float(np.clip(values.item(), 0.0, 1.0))
        return torch.tensor([1.0 - malignant, malignant], dtype=torch.float32)

    flat = values.flatten()
    if flat.size >= 2:
        benign = float(max(flat[0], 0.0))
        malignant = float(max(flat[1], 0.0))
        denom = benign + malignant
        if denom <= 1e-8:
            return torch.tensor([0.5, 0.5], dtype=torch.float32)
        return torch.tensor([benign / denom, malignant / denom], dtype=torch.float32)

    single = float(np.clip(flat[0], 0.0, 1.0)) if flat.size == 1 else 0.5
    return torch.tensor([1.0 - single, single], dtype=torch.float32)


def _image_edge_saliency(image_rgb: np.ndarray, size: int = 224) -> np.ndarray:
    gray = cv2.cvtColor(image_rgb, cv2.COLOR_RGB2GRAY)
    gray = cv2.resize(gray, (size, size), interpolation=cv2.INTER_CUBIC).astype(np.float32) / 255.0
    grad_x = cv2.Sobel(gray, cv2.CV_32F, 1, 0, ksize=3)
    grad_y = cv2.Sobel(gray, cv2.CV_32F, 0, 1, ksize=3)
    edges = np.sqrt((grad_x ** 2) + (grad_y ** 2))
    return _normalize_saliency_map(edges)


def _collect_optional_model_probs(modality: str, image_path: str | Path, image_rgb: np.ndarray) -> tuple[dict[str, torch.Tensor], dict[str, str]]:
    optional_probs: dict[str, torch.Tensor] = {}
    optional_errors: dict[str, str] = {}

    if modality == 'xray':
        try:
            optional_probs['yolov8'] = _infer_yolov8_probs(image_path)
        except Exception as exc:
            optional_errors['yolov8'] = str(exc)

    try:
        optional_probs['kerashf'] = _infer_keras_hf_probs(image_rgb)
    except Exception as exc:
        optional_errors['kerashf'] = str(exc)

    return optional_probs, optional_errors


def _normalize_selected_model_key(selected_model: str) -> str:
    key = (selected_model or 'hybrid').strip().lower()
    if key.startswith('yolo'):
        return 'yolov8'

    return key


def _resolve_selected_output(
    selected_model_key: str,
    modality: str,
    model_outputs: dict[str, tuple[str, torch.Tensor]],
    optional_errors: dict[str, str],
) -> tuple[str, torch.Tensor]:
    if selected_model_key == 'yolov8' and modality != 'xray':
        raise ValueError('YOLOv8 model supports chest X-ray modality only.')

    if selected_model_key in {'yolov8', 'kerashf'} and selected_model_key not in model_outputs:
        detail = optional_errors.get(selected_model_key) or f'{selected_model_key} model is currently unavailable.'
        raise ValueError(f'{selected_model_key.upper()} inference failed: {detail}')

    return model_outputs.get(selected_model_key, model_outputs['hybrid'])


def _temperature_scale_probs(probs: torch.Tensor, temperature: float = TEMPERATURE_SCALING) -> torch.Tensor:
    temperature = max(float(temperature), 1e-3)
    safe = torch.clamp(probs, min=1e-6)
    logits = torch.log(safe)
    calibrated = torch.softmax(logits / temperature, dim=0)
    return calibrated


def _load_calibration_profile() -> dict:
    global _calibration_profile
    if _calibration_profile is not None:
        return _calibration_profile

    default_profile = {
        'temperature_scaling': TEMPERATURE_SCALING,
        'malignancy_threshold_diagnostic': MALIGNANCY_THRESHOLD_DIAGNOSTIC,
        'malignancy_threshold_screening': MALIGNANCY_THRESHOLD_SCREENING,
    }

    if not CALIBRATION_FILE.exists():
        _calibration_profile = default_profile
        return _calibration_profile

    try:
        with CALIBRATION_FILE.open('r', encoding='utf-8') as handle:
            artifact = json.load(handle)

        _calibration_profile = {
            'temperature_scaling': float(artifact.get('temperature_scaling', TEMPERATURE_SCALING)),
            'malignancy_threshold_diagnostic': float(artifact.get('malignancy_threshold_diagnostic', MALIGNANCY_THRESHOLD_DIAGNOSTIC)),
            'malignancy_threshold_screening': float(artifact.get('malignancy_threshold_screening', MALIGNANCY_THRESHOLD_SCREENING)),
        }
    except Exception:
        _calibration_profile = default_profile

    return _calibration_profile


def _ensemble_probs(
    base_probs: dict[str, torch.Tensor],
    modality: str,
    optional_probs: dict[str, torch.Tensor],
) -> torch.Tensor:
    calibration = _load_calibration_profile()
    temperature = float(calibration.get('temperature_scaling', TEMPERATURE_SCALING))

    weighted_probs: list[tuple[torch.Tensor, float]] = [
        (base_probs['hybrid'], 0.45),
        (base_probs['resnet'], 0.25),
        (base_probs['densenet'], 0.25),
    ]

    if modality == 'xray' and 'yolov8' in optional_probs:
        weighted_probs.append((optional_probs['yolov8'], 0.15))

    total_weight = sum(weight for _, weight in weighted_probs)
    merged = torch.zeros_like(weighted_probs[0][0])
    for probs, weight in weighted_probs:
        merged = merged + (probs * weight)

    if total_weight <= 1e-6:
        return torch.tensor([0.5, 0.5], dtype=torch.float32)

    merged = merged / total_weight
    return _temperature_scale_probs(merged, temperature=temperature)


def _operating_threshold(operating_mode: str) -> float:
    calibration = _load_calibration_profile()

    if operating_mode == 'screening':
        return float(calibration.get('malignancy_threshold_screening', MALIGNANCY_THRESHOLD_SCREENING))
    return float(calibration.get('malignancy_threshold_diagnostic', MALIGNANCY_THRESHOLD_DIAGNOSTIC))


def _build_saliency_maps(
    resnet_model: torch.nn.Module,
    densenet_model: torch.nn.Module,
    hybrid_model: HybridLungCancerModel,
    tensor: torch.Tensor,
    resnet_probs: torch.Tensor,
    densenet_probs: torch.Tensor,
    hybrid_probs: torch.Tensor,
    optional_probs: dict[str, torch.Tensor],
    image_rgb: np.ndarray,
) -> dict[str, np.ndarray]:
    resnet_target = int(torch.argmax(resnet_probs).item())
    densenet_target = int(torch.argmax(densenet_probs).item())
    hybrid_target = int(torch.argmax(hybrid_probs).item())

    saliency_maps = {
        'resnet': _input_saliency_map(resnet_model, tensor, resnet_target, is_hybrid=False),
        'densenet': _input_saliency_map(densenet_model, tensor, densenet_target, is_hybrid=False),
        'hybrid': _input_saliency_map(hybrid_model, tensor, hybrid_target, is_hybrid=True),
    }

    if 'kerashf' in optional_probs:
        saliency_maps['kerashf'] = _image_edge_saliency(image_rgb, size=IMAGE_SIZE)

    return saliency_maps


def _prediction_from_probs(model_name: str, probs: torch.Tensor) -> dict:
    model_pred_idx = int(torch.argmax(probs).item())
    model_probability = float(probs[model_pred_idx].item())
    sorted_probs = torch.sort(probs, descending=True).values
    confidence_margin = float((sorted_probs[0] - sorted_probs[1]).item()) if sorted_probs.numel() > 1 else model_probability

    return {
        'model': model_name,
        'result': CLASS_NAMES[model_pred_idx],
        'probability': round(model_probability, 4),
        'confidence_margin': round(confidence_margin, 4),
    }


def _normalize_saliency_map(values: np.ndarray) -> np.ndarray:
    values = np.maximum(values, 0)
    min_value = float(values.min()) if values.size > 0 else 0.0
    max_value = float(values.max()) if values.size > 0 else 0.0

    if max_value - min_value < 1e-8:
        return np.zeros_like(values, dtype=np.float32)

    return ((values - min_value) / (max_value - min_value)).astype(np.float32)


def _input_saliency_map(model: torch.nn.Module, tensor: torch.Tensor, target_index: int, is_hybrid: bool = False) -> np.ndarray:
    model.zero_grad(set_to_none=True)

    with torch.enable_grad():
        local_input = tensor.detach().clone().requires_grad_(True)
        output = model(local_input)
        logits = output.logits if is_hybrid else output
        score = logits[0, target_index]
        score.backward()
        gradients = local_input.grad.detach().abs().mean(dim=1).squeeze(0).cpu().numpy()

    return _normalize_saliency_map(gradients)



def infer_image(
    image_path: str | Path,
    modality: str = 'xray',
    dataset_source: str = '',
    selected_model: str = 'hybrid',
    operating_mode: str = 'diagnostic',
) -> dict:
    validate_scan_input(image_path, modality)

    normalized_mode = (operating_mode or 'diagnostic').strip().lower()
    if normalized_mode not in {'diagnostic', 'screening'}:
        normalized_mode = 'diagnostic'

    quality_gate = assess_scan_quality(image_path, modality)
    if not quality_gate['reliable']:
        reasons = quality_gate.get('reasons') or ['Quality gate failed.']
        joined = '; '.join(str(reason) for reason in reasons)
        raise ValueError(f'Cannot reliably evaluate this scan (quality score {quality_gate.get("score", 0.0):.2f} < {QUALITY_GATE_MIN_SCORE:.2f}). {joined}')

    hybrid_model = _get_hybrid_model()
    resnet_model = _get_resnet_model()
    densenet_model = _get_densenet_model()

    tensor, resized_rgb, preprocessing_metadata = preprocess_for_model(image_path, image_size=IMAGE_SIZE)

    with torch.no_grad():
        output = hybrid_model(tensor)

        hybrid_probs = torch.softmax(output.logits, dim=1).squeeze(0)
        resnet_probs = torch.softmax(resnet_model(tensor), dim=1).squeeze(0)
        densenet_probs = torch.softmax(densenet_model(tensor), dim=1).squeeze(0)

    optional_probs, optional_errors = _collect_optional_model_probs(modality, image_path, resized_rgb)

    model_comparisons = [
        _prediction_from_probs('ResNet', resnet_probs),
        _prediction_from_probs('DenseNet', densenet_probs),
        _prediction_from_probs('Hybrid', hybrid_probs),
    ]

    ensemble_probs_preview = _ensemble_probs(
        base_probs={
            'resnet': resnet_probs,
            'densenet': densenet_probs,
            'hybrid': hybrid_probs,
        },
        modality=modality,
        optional_probs=optional_probs,
    )
    model_comparisons.append(_prediction_from_probs('Ensemble', ensemble_probs_preview))

    if 'yolov8' in optional_probs:
        model_comparisons.append(_prediction_from_probs('YOLOv8', optional_probs['yolov8']))
    if 'kerashf' in optional_probs:
        model_comparisons.append(_prediction_from_probs('KerasHF', optional_probs['kerashf']))

    selected_model_key = _normalize_selected_model_key(selected_model)

    model_outputs = {
        'resnet': ('ResNet', resnet_probs),
        'densenet': ('DenseNet', densenet_probs),
        'hybrid': ('Hybrid', hybrid_probs),
    }

    ensemble_probs = ensemble_probs_preview
    model_outputs['ensemble'] = ('Ensemble', ensemble_probs)

    if 'yolov8' in optional_probs:
        model_outputs['yolov8'] = ('YOLOv8', optional_probs['yolov8'])
    if 'kerashf' in optional_probs:
        model_outputs['kerashf'] = ('KerasHF', optional_probs['kerashf'])

    selected_model_name, selected_probs = _resolve_selected_output(
        selected_model_key,
        modality,
        model_outputs,
        optional_errors,
    )

    malignant_probability = float(selected_probs[1].item()) if selected_probs.numel() > 1 else float(selected_probs.max().item())
    threshold = _operating_threshold(normalized_mode)
    pred_idx = 1 if malignant_probability >= threshold else 0
    label = CLASS_NAMES[pred_idx]
    probability = malignant_probability if label == 'Malignant' else 1.0 - malignant_probability

    finding_location = _estimate_finding_location(output.segmentation_mask)
    severity_score = round(malignant_probability * 100, 2)
    confidence_band = _confidence_band(probability)
    growth_metrics = _nodule_growth_metrics(output.segmentation_mask)

    timestamp = datetime.now(timezone.utc).strftime("%Y%m%d_%H%M%S_%f")

    saliency_maps = _build_saliency_maps(
        resnet_model,
        densenet_model,
        hybrid_model,
        tensor,
        resnet_probs,
        densenet_probs,
        hybrid_probs,
        optional_probs,
        resized_rgb,
    )

    model_visuals = generate_model_comparison_visuals(
        resized_rgb,
        saliency_maps=saliency_maps,
        model_predictions=model_comparisons,
        output_dir=HEATMAP_DIR,
        file_stem=f"models_{timestamp}",
    )

    xai_bundle = generate_explanation_maps(
        resized_rgb,
        output.segmentation_mask,
        output_dir=HEATMAP_DIR,
        file_stem=f"xai_{timestamp}",
        predicted_label=label,
        probability=probability,
    )

    ct_viewer = None if modality != 'ct' else generate_ct_viewer_assets(
        resized_rgb,
        output.segmentation_mask,
        output_dir=HEATMAP_DIR,
        file_stem=f"ct_{timestamp}",
        depth=24,
    )

    cancer_stage = _predict_cancer_stage(label, severity_score, growth_metrics['nodule_diameter_mm'])
    confidence_reasoning = _build_confidence_reasoning(
        confidence_band=confidence_band,
        region_confidence_score=xai_bundle["region_confidence_score"],
        model_comparisons=model_comparisons,
        dataset_source=dataset_source,
    )

    model_versions = {
        'Hybrid': f'Hybrid-{MODEL_VERSION}',
        'ResNet': 'ResNet-v1',
        'DenseNet': 'DenseNet-v1',
        'Ensemble': 'Ensemble-v1-calibrated',
        'YOLOv8': 'YOLOv8-chest-xray-v1',
        'KerasHF': 'KerasHF-histopath-v1',
    }

    calibration = _load_calibration_profile()

    return {
        "prediction": label,
        "probability": round(probability, 4),
        "malignancy_probability": round(malignant_probability, 4),
        "operating_mode": normalized_mode,
        "malignancy_threshold": round(threshold, 4),
        "model_comparisons": model_comparisons,
        "model_visuals": model_visuals,
        "heatmap": xai_bundle["heatmap"],
        "explanation_maps": xai_bundle["explanation_maps"],
        "region_confidence_score": xai_bundle["region_confidence_score"],
        "top_suspicious_regions": xai_bundle.get("top_suspicious_regions", []),
        "lesion_quantification": xai_bundle.get("lesion_quantification", {}),
        "cancer_stage": cancer_stage,
        "confidence_reasoning": confidence_reasoning,
        "ct_viewer": ct_viewer,
        "dataset_source": dataset_source,
        "quality_gate": quality_gate,
        "preprocessing_metadata": preprocessing_metadata,
        "finding_location": finding_location,
        "severity_score": severity_score,
        "confidence_band": confidence_band,
        "nodule_diameter_mm": growth_metrics['nodule_diameter_mm'],
        "nodule_area_px": growth_metrics['nodule_area_px'],
        "tumor_area_mm2": growth_metrics['tumor_area_mm2'],
        "tumor_volume_mm3": growth_metrics['tumor_volume_mm3'],
        "nodule_burden_percent": growth_metrics['nodule_burden_percent'],
        "model_version": model_versions.get(selected_model_name, f"{selected_model_name}-{MODEL_VERSION}"),
        "temperature_scaling": float(calibration.get('temperature_scaling', TEMPERATURE_SCALING)),
    }
