from pathlib import Path

import cv2
import numpy as np
import pydicom
import torch
from pydicom.multival import MultiValue

from utils.config import CT_LUNG_WINDOW_CENTER, CT_LUNG_WINDOW_WIDTH, CT_TARGET_SPACING_MM


def _first_value(value, default: float) -> float:
    if isinstance(value, MultiValue):
        return float(value[0])
    if value is None:
        return default
    return float(value)


def _resample_to_spacing_2d(image_hu: np.ndarray, pixel_spacing: tuple[float, float], target_spacing: float = 1.0) -> np.ndarray:
    row_spacing = max(float(pixel_spacing[0]), 1e-6)
    col_spacing = max(float(pixel_spacing[1]), 1e-6)

    scale_y = row_spacing / target_spacing
    scale_x = col_spacing / target_spacing

    new_height = max(int(round(image_hu.shape[0] * scale_y)), 64)
    new_width = max(int(round(image_hu.shape[1] * scale_x)), 64)

    return cv2.resize(image_hu, (new_width, new_height), interpolation=cv2.INTER_CUBIC)


def _window_hu(image_hu: np.ndarray, center: float, width: float) -> np.ndarray:
    half = max(width / 2.0, 1.0)
    lower = center - half
    upper = center + half
    windowed = np.clip(image_hu, lower, upper)
    return (windowed - lower) / (upper - lower)


def _estimate_lung_mask(windowed: np.ndarray) -> np.ndarray:
    gray = np.uint8(np.clip(windowed, 0.0, 1.0) * 255)
    blurred = cv2.GaussianBlur(gray, (5, 5), 0)

    body_mask = (blurred > 18).astype(np.uint8)
    body_mask = cv2.morphologyEx(body_mask, cv2.MORPH_CLOSE, np.ones((7, 7), dtype=np.uint8), iterations=2)

    air_like = (blurred < 175).astype(np.uint8)
    candidate = cv2.bitwise_and(air_like, body_mask)

    num_labels, labels, stats, _ = cv2.connectedComponentsWithStats(candidate, connectivity=8)
    if num_labels <= 1:
        return body_mask.astype(np.uint8)

    kept = np.zeros_like(candidate)
    components: list[tuple[int, int]] = []
    for label in range(1, num_labels):
        area = int(stats[label, cv2.CC_STAT_AREA])
        if area < 120:
            continue
        components.append((area, label))

    if not components:
        return body_mask.astype(np.uint8)

    components.sort(reverse=True)
    for _, label in components[:2]:
        kept[labels == label] = 1

    kept = cv2.morphologyEx(kept, cv2.MORPH_OPEN, np.ones((5, 5), dtype=np.uint8), iterations=1)
    kept = cv2.morphologyEx(kept, cv2.MORPH_CLOSE, np.ones((7, 7), dtype=np.uint8), iterations=2)

    if np.count_nonzero(kept) < 200:
        return body_mask.astype(np.uint8)

    return kept.astype(np.uint8)


def _load_dicom_with_windowing(image_path: str | Path) -> tuple[np.ndarray, dict]:
    dataset = pydicom.dcmread(str(image_path), force=True)
    pixel_array = dataset.pixel_array.astype(np.float32)

    slope = float(getattr(dataset, 'RescaleSlope', 1.0))
    intercept = float(getattr(dataset, 'RescaleIntercept', 0.0))
    hu_image = (pixel_array * slope) + intercept

    raw_spacing = getattr(dataset, 'PixelSpacing', [CT_TARGET_SPACING_MM[0], CT_TARGET_SPACING_MM[1]])
    row_spacing = _first_value(raw_spacing, CT_TARGET_SPACING_MM[0])
    col_spacing = _first_value(raw_spacing[1] if isinstance(raw_spacing, (list, tuple, MultiValue)) and len(raw_spacing) > 1 else raw_spacing, CT_TARGET_SPACING_MM[1])
    hu_resampled = _resample_to_spacing_2d(hu_image, (row_spacing, col_spacing), target_spacing=CT_TARGET_SPACING_MM[0])

    window_center = _first_value(getattr(dataset, 'WindowCenter', None), CT_LUNG_WINDOW_CENTER)
    window_width = max(_first_value(getattr(dataset, 'WindowWidth', None), CT_LUNG_WINDOW_WIDTH), 1.0)

    lung_windowed = _window_hu(hu_resampled, window_center, window_width)
    lung_mask = _estimate_lung_mask(lung_windowed)
    masked_windowed = lung_windowed * lung_mask

    if np.count_nonzero(lung_mask) < 120:
        masked_windowed = lung_windowed
        lung_mask = np.ones_like(lung_windowed, dtype=np.uint8)

    grayscale = np.uint8(np.clip(masked_windowed, 0.0, 1.0) * 255)
    clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8))
    enhanced = clahe.apply(grayscale)
    rgb = cv2.cvtColor(enhanced, cv2.COLOR_GRAY2RGB)

    metadata = {
        'pixel_spacing_mm': [row_spacing, col_spacing],
        'target_spacing_mm': list(CT_TARGET_SPACING_MM),
        'window_center': float(window_center),
        'window_width': float(window_width),
        'lung_mask_coverage': float(np.count_nonzero(lung_mask)) / float(max(lung_mask.size, 1)),
        'hu_mean': float(np.mean(hu_resampled)),
        'hu_std': float(np.std(hu_resampled)),
    }

    return rgb, metadata


def load_image(image_path: str | Path) -> tuple[np.ndarray, dict]:
    path = Path(image_path)

    if path.suffix.lower() == '.dcm':
        return _load_dicom_with_windowing(path)

    image = cv2.imread(str(image_path), cv2.IMREAD_COLOR)
    if image is None:
        raise ValueError(f"Unable to read image: {image_path}")
    image = cv2.cvtColor(image, cv2.COLOR_BGR2RGB)

    gray = cv2.cvtColor(image, cv2.COLOR_RGB2GRAY)
    metadata = {
        'contrast_std': float(np.std(gray)),
        'mean_intensity': float(np.mean(gray)),
    }
    return image, metadata


def preprocess_for_model(image_path: str | Path, image_size: int = 224) -> tuple[torch.Tensor, np.ndarray, dict]:
    image, metadata = load_image(image_path)
    resized = cv2.resize(image, (image_size, image_size), interpolation=cv2.INTER_AREA)
    normalized = resized.astype(np.float32) / 255.0

    mean = np.array([0.485, 0.456, 0.406], dtype=np.float32)
    std = np.array([0.229, 0.224, 0.225], dtype=np.float32)
    normalized = (normalized - mean) / std

    tensor = torch.from_numpy(normalized.transpose(2, 0, 1)).unsqueeze(0)
    return tensor, resized, metadata
