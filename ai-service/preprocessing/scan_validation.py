from pathlib import Path

import cv2
import numpy as np
import pydicom

from utils.config import QUALITY_GATE_MIN_CT_STD, QUALITY_GATE_MIN_SCORE, QUALITY_GATE_MIN_XRAY_CONTRAST


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


def _quality_error(reason: str) -> dict:
    return {
        'reliable': False,
        'score': 0.0,
        'reasons': [reason],
    }


def _quality_from_dicom(path: Path) -> dict:
    dataset = pydicom.dcmread(str(path), force=True)
    pixel = dataset.pixel_array.astype(np.float32)
    slope = float(getattr(dataset, 'RescaleSlope', 1.0))
    intercept = float(getattr(dataset, 'RescaleIntercept', 0.0))
    hu = (pixel * slope) + intercept

    hu_std = float(np.std(hu))
    hu_range = float(np.ptp(hu))
    shape_score = 1.0 if min(hu.shape[:2]) >= 256 else 0.5
    contrast_score = min(hu_std / max(QUALITY_GATE_MIN_CT_STD, 1.0), 1.0)
    range_score = min(hu_range / 800.0, 1.0)
    score = round(float((0.4 * contrast_score) + (0.35 * range_score) + (0.25 * shape_score)), 4)

    reasons: list[str] = []
    if hu_std < QUALITY_GATE_MIN_CT_STD:
        reasons.append('CT contrast variability is low; study may be too noisy/flat for reliable AI interpretation.')
    if hu_range < 350:
        reasons.append('CT dynamic range is low; suspected clipping or low-quality acquisition.')
    if min(hu.shape[:2]) < 256:
        reasons.append('CT spatial resolution is low for robust lesion localization.')

    return {
        'score': score,
        'reasons': reasons,
        'metrics': {
            'hu_std': hu_std,
            'hu_range': hu_range,
            'height': float(hu.shape[0]),
            'width': float(hu.shape[1]),
        },
    }


def _quality_from_xray(path: Path) -> dict:
    image = cv2.imread(str(path), cv2.IMREAD_COLOR)
    if image is None:
        return _quality_error('Unable to read uploaded image file.')

    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    height, width = gray.shape[:2]
    contrast = float(np.std(gray))
    mean_intensity = float(np.mean(gray))
    edges = cv2.Canny(gray, 50, 150)
    edge_density = float(np.count_nonzero(edges)) / float(max(edges.size, 1))

    shape_score = 1.0 if min(height, width) >= 256 else 0.6
    contrast_score = min(contrast / max(QUALITY_GATE_MIN_XRAY_CONTRAST, 1.0), 1.0)
    edge_score = 1.0 - min(abs(edge_density - 0.07) / 0.07, 1.0)
    score = round(float((0.4 * contrast_score) + (0.3 * edge_score) + (0.3 * shape_score)), 4)

    reasons: list[str] = []
    if contrast < QUALITY_GATE_MIN_XRAY_CONTRAST:
        reasons.append('X-ray contrast appears too low for reliable interpretation.')
    if edge_density < 0.01 or edge_density > 0.22:
        reasons.append('X-ray texture pattern is outside expected chest radiography range.')
    if min(height, width) < 256:
        reasons.append('X-ray resolution is low for robust AI analysis.')

    return {
        'score': score,
        'reasons': reasons,
        'metrics': {
            'contrast_std': contrast,
            'mean_intensity': mean_intensity,
            'edge_density': edge_density,
            'height': float(height),
            'width': float(width),
        },
    }


def assess_scan_quality(image_path: str | Path, modality: str) -> dict:
    selected_modality = (modality or 'xray').strip().lower()
    path = Path(image_path)

    if not path.exists() or not path.is_file():
        return _quality_error('Uploaded file is missing or inaccessible.')

    if path.suffix.lower() == '.dcm':
        try:
            details = _quality_from_dicom(path)

        except Exception as exc:
            return _quality_error(f'Unable to parse DICOM pixels for quality assessment: {exc}')
    else:
        details = _quality_from_xray(path)
        if details.get('reliable') is False:
            return details

    score = float(details.get('score', 0.0))
    reasons = details.get('reasons', [])
    metrics = details.get('metrics', {})

    return {
        'reliable': score >= QUALITY_GATE_MIN_SCORE,
        'score': score,
        'reasons': reasons,
        'metrics': metrics,
        'modality': selected_modality,
    }
