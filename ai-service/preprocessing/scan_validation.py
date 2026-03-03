from pathlib import Path

import cv2
import numpy as np
import pydicom


CHEST_KEYWORDS = (
    'chest',
    'thorax',
    'lung',
    'pulmonary',
    'toraks',
)

XRAY_MODALITIES = {'CR', 'DX', 'DR', 'XR'}


def _contains_chest_keyword(value: str | None) -> bool:
    if not value:
        return False
    text = value.lower()
    return any(keyword in text for keyword in CHEST_KEYWORDS)


def _validate_dicom_for_modality(image_path: Path, modality: str) -> None:
    dataset = pydicom.dcmread(str(image_path), stop_before_pixels=True, force=True)

    dicom_modality = str(getattr(dataset, 'Modality', '')).upper()
    body_part = str(getattr(dataset, 'BodyPartExamined', '') or '')
    study_description = str(getattr(dataset, 'StudyDescription', '') or '')
    series_description = str(getattr(dataset, 'SeriesDescription', '') or '')

    chest_context = any(
        _contains_chest_keyword(value)
        for value in [body_part, study_description, series_description]
    )

    if modality == 'ct':
        if dicom_modality != 'CT':
            raise ValueError('Uploaded file is not a CT DICOM study (Modality tag must be CT).')
        if not chest_context:
            raise ValueError('CT image does not appear to be a chest/thorax study based on DICOM metadata.')
        return

    if modality == 'xray':
        if dicom_modality not in XRAY_MODALITIES:
            raise ValueError('Uploaded file is not a chest X-ray DICOM study (expected CR/DX/DR/XR).')
        if not chest_context:
            raise ValueError('X-ray image does not appear to be chest-related based on DICOM metadata.')


def _validate_non_dicom_xray(image_path: Path, modality: str) -> None:
    image = cv2.imread(str(image_path), cv2.IMREAD_COLOR)
    if image is None:
        raise ValueError('Unable to read uploaded image file.')

    height, width = image.shape[:2]
    if height < 128 or width < 128:
        raise ValueError('Image resolution is too low for chest scan analysis.')

    b, g, r = cv2.split(image)
    channel_diff = float(
        np.mean(np.abs(r.astype(np.float32) - g.astype(np.float32)))
        + np.mean(np.abs(g.astype(np.float32) - b.astype(np.float32)))
    )
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    contrast = float(np.std(gray))
    hsv = cv2.cvtColor(image, cv2.COLOR_BGR2HSV)
    mean_saturation = float(np.mean(hsv[:, :, 1]))

    edges = cv2.Canny(gray, 50, 150)
    edge_density = float(np.count_nonzero(edges)) / float(edges.size)

    height_f = float(height)
    width_f = float(width)
    aspect_ratio = width_f / max(height_f, 1.0)

    if modality == 'ct':
        raise ValueError('CT uploads must be DICOM (.dcm) chest CT images.')

    if channel_diff > 14 or mean_saturation > 30:
        raise ValueError('Image appears colorized or non-radiographic; expected grayscale chest X-ray image.')
    if contrast < 20:
        raise ValueError('Image contrast is too low for valid chest X-ray interpretation.')
    if edge_density < 0.01 or edge_density > 0.22:
        raise ValueError('Image texture pattern is not consistent with chest X-ray structure.')
    if aspect_ratio < 0.55 or aspect_ratio > 1.35:
        raise ValueError('Image shape is not consistent with standard chest X-ray framing.')


def validate_scan_input(image_path: str | Path, modality: str) -> None:
    selected_modality = (modality or 'xray').strip().lower()
    if selected_modality not in {'xray', 'ct'}:
        raise ValueError('Unsupported modality. Please choose either xray or ct.')

    path = Path(image_path)
    if not path.exists() or not path.is_file():
        raise ValueError('Uploaded file is missing or inaccessible.')

    if path.suffix.lower() == '.dcm':
        _validate_dicom_for_modality(path, selected_modality)
        return

    _validate_non_dicom_xray(path, selected_modality)
