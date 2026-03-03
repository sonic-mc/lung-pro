from pathlib import Path

import cv2
import numpy as np
import pydicom
import torch
from pydicom.multival import MultiValue


def _first_value(value, default: float) -> float:
    if isinstance(value, MultiValue):
        return float(value[0])
    if value is None:
        return default
    return float(value)


def _load_dicom_with_windowing(image_path: str | Path) -> np.ndarray:
    dataset = pydicom.dcmread(str(image_path), force=True)
    pixel_array = dataset.pixel_array.astype(np.float32)

    slope = float(getattr(dataset, 'RescaleSlope', 1.0))
    intercept = float(getattr(dataset, 'RescaleIntercept', 0.0))
    hu_image = (pixel_array * slope) + intercept

    window_center = _first_value(getattr(dataset, 'WindowCenter', None), float(np.median(hu_image)))
    window_width = max(_first_value(getattr(dataset, 'WindowWidth', None), float(np.ptp(hu_image))), 1.0)

    lower = window_center - (window_width / 2)
    upper = window_center + (window_width / 2)
    windowed = np.clip(hu_image, lower, upper)
    windowed = (windowed - lower) / (upper - lower)

    grayscale = np.uint8(windowed * 255)
    clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8))
    enhanced = clahe.apply(grayscale)
    rgb = cv2.cvtColor(enhanced, cv2.COLOR_GRAY2RGB)
    return rgb


def load_image(image_path: str | Path) -> np.ndarray:
    path = Path(image_path)

    if path.suffix.lower() == '.dcm':
        return _load_dicom_with_windowing(path)

    image = cv2.imread(str(image_path), cv2.IMREAD_COLOR)
    if image is None:
        raise ValueError(f"Unable to read image: {image_path}")
    image = cv2.cvtColor(image, cv2.COLOR_BGR2RGB)
    return image


def preprocess_for_model(image_path: str | Path, image_size: int = 224) -> tuple[torch.Tensor, np.ndarray]:
    image = load_image(image_path)
    resized = cv2.resize(image, (image_size, image_size), interpolation=cv2.INTER_AREA)
    normalized = resized.astype(np.float32) / 255.0

    mean = np.array([0.485, 0.456, 0.406], dtype=np.float32)
    std = np.array([0.229, 0.224, 0.225], dtype=np.float32)
    normalized = (normalized - mean) / std

    tensor = torch.from_numpy(normalized.transpose(2, 0, 1)).unsqueeze(0)
    return tensor, resized
