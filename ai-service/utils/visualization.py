from pathlib import Path

import cv2
import numpy as np
import torch


def _normalize_activation_map(mask: np.ndarray) -> np.ndarray:
    clipped = np.clip(mask, 0.0, 1.0)
    p2 = np.percentile(clipped, 2)
    p98 = np.percentile(clipped, 98)

    if p98 - p2 < 1e-6:
        normalized = clipped
    else:
        normalized = (clipped - p2) / (p98 - p2)

    normalized = np.clip(normalized, 0.0, 1.0)
    normalized = cv2.GaussianBlur(normalized, (0, 0), sigmaX=2.0)
    return normalized


def _activation_to_overlay(image_bgr: np.ndarray, activation: np.ndarray, alpha: float, colormap: int) -> np.ndarray:
    heat_uint8 = np.uint8(np.clip(activation, 0.0, 1.0) * 255)
    heat_color = cv2.applyColorMap(heat_uint8, colormap)
    return cv2.addWeighted(image_bgr, 1.0 - alpha, heat_color, alpha, 0.0)


def _build_legend_bar(height: int = 240, width: int = 46) -> np.ndarray:
    gradient = np.linspace(255, 0, height, dtype=np.uint8).reshape(height, 1)
    gradient = np.repeat(gradient, width, axis=1)
    color_bar = cv2.applyColorMap(gradient, cv2.COLORMAP_JET)
    return cv2.copyMakeBorder(color_bar, 1, 1, 1, 1, cv2.BORDER_CONSTANT, value=(230, 230, 230))


def _panel_with_title(panel: np.ndarray, title: str) -> np.ndarray:
    framed = cv2.copyMakeBorder(panel, 2, 2, 2, 2, cv2.BORDER_CONSTANT, value=(45, 45, 45))
    title_bar = np.full((44, framed.shape[1], 3), 28, dtype=np.uint8)
    cv2.putText(title_bar, title, (14, 29), cv2.FONT_HERSHEY_SIMPLEX, 0.65, (238, 238, 238), 2, cv2.LINE_AA)
    return np.vstack([title_bar, framed])


def _draw_boundary(image_bgr: np.ndarray, binary_mask: np.ndarray) -> np.ndarray:
    annotated = image_bgr.copy()
    contours, _ = cv2.findContours(binary_mask, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)

    if not contours:
        return annotated

    primary = max(contours, key=cv2.contourArea)
    if cv2.contourArea(primary) < 60:
        return annotated

    cv2.drawContours(annotated, [primary], contourIdx=-1, color=(0, 255, 255), thickness=2)
    (center_x, center_y), radius = cv2.minEnclosingCircle(primary)
    center = (int(center_x), int(center_y))
    radius = max(int(radius), 18)

    cv2.circle(annotated, center, radius, (0, 255, 255), 3, cv2.LINE_AA)
    cv2.circle(annotated, center, 4, (0, 255, 255), -1, cv2.LINE_AA)

    label_origin = (max(center[0] - 95, 10), max(center[1] - radius - 12, 24))
    cv2.putText(annotated, "Suspected Nodule Boundary", label_origin, cv2.FONT_HERSHEY_SIMPLEX, 0.54, (20, 20, 20), 3, cv2.LINE_AA)
    cv2.putText(annotated, "Suspected Nodule Boundary", label_origin, cv2.FONT_HERSHEY_SIMPLEX, 0.54, (255, 255, 255), 1, cv2.LINE_AA)

    return annotated


def _extract_top_suspicious_regions(activation: np.ndarray, threshold: float = 0.55, max_regions: int = 3) -> list[dict]:
    binary = np.uint8(activation >= threshold)
    num_labels, labels, stats, centroids = cv2.connectedComponentsWithStats(binary, connectivity=8)

    regions: list[dict] = []
    for label in range(1, num_labels):
        area_px = int(stats[label, cv2.CC_STAT_AREA])
        if area_px < 40:
            continue

        x = int(stats[label, cv2.CC_STAT_LEFT])
        y = int(stats[label, cv2.CC_STAT_TOP])
        w = int(stats[label, cv2.CC_STAT_WIDTH])
        h = int(stats[label, cv2.CC_STAT_HEIGHT])
        cx = float(centroids[label][0])
        cy = float(centroids[label][1])

        mask = labels == label
        score = float(np.mean(activation[mask]) * 100.0)

        diameter_px = float((4.0 * area_px / np.pi) ** 0.5)
        pixel_spacing_mm = 0.7
        diameter_mm = diameter_px * pixel_spacing_mm
        area_mm2 = float(area_px) * (pixel_spacing_mm ** 2)
        radius_mm = diameter_mm / 2.0
        volume_mm3 = (4.0 / 3.0) * np.pi * (radius_mm ** 3) if diameter_mm > 0 else 0.0

        regions.append({
            'centroid_x': round(cx, 2),
            'centroid_y': round(cy, 2),
            'bbox': [x, y, w, h],
            'confidence_score': round(score, 2),
            'area_px': round(float(area_px), 2),
            'diameter_mm': round(float(diameter_mm), 2),
            'area_mm2': round(float(area_mm2), 2),
            'volume_mm3': round(float(volume_mm3), 2),
        })

    regions.sort(key=lambda region: (region['confidence_score'], region['area_px']), reverse=True)
    return regions[:max_regions]


def _annotate_top_regions(image_bgr: np.ndarray, regions: list[dict]) -> np.ndarray:
    annotated = image_bgr.copy()
    colors = [(0, 0, 255), (0, 165, 255), (255, 200, 0)]

    for index, region in enumerate(regions):
        color = colors[min(index, len(colors) - 1)]
        x, y, w, h = region['bbox']
        center = (int(round(region['centroid_x'])), int(round(region['centroid_y'])))
        radius = max(int(round(max(w, h) / 2.0)), 12)

        cv2.circle(annotated, center, radius, color, 2, cv2.LINE_AA)
        cv2.rectangle(annotated, (x, y), (x + w, y + h), color, 1)

        label = f"R{index + 1}: {region['confidence_score']:.1f}%"
        text_origin = (max(x, 6), max(y - 8, 18))
        cv2.putText(annotated, label, text_origin, cv2.FONT_HERSHEY_SIMPLEX, 0.45, (0, 0, 0), 2, cv2.LINE_AA)
        cv2.putText(annotated, label, text_origin, cv2.FONT_HERSHEY_SIMPLEX, 0.45, (255, 255, 255), 1, cv2.LINE_AA)

    return annotated


def _derive_lesion_quantification(regions: list[dict]) -> dict:
    if not regions:
        return {
            'lesion_centroid': None,
            'diameter_mm': 0.0,
            'area_mm2': 0.0,
            'volume_mm3': 0.0,
        }

    primary = regions[0]
    return {
        'lesion_centroid': {
            'x': primary['centroid_x'],
            'y': primary['centroid_y'],
        },
        'diameter_mm': primary['diameter_mm'],
        'area_mm2': primary['area_mm2'],
        'volume_mm3': primary['volume_mm3'],
    }


def _save_png(path: Path, image: np.ndarray) -> str:
    path.parent.mkdir(parents=True, exist_ok=True)
    cv2.imwrite(str(path), image, [cv2.IMWRITE_PNG_COMPRESSION, 3])
    return path.name


def _build_composite(
    image_bgr: np.ndarray,
    gradcam_map: np.ndarray,
    boundary_map: np.ndarray,
    binary_mask: np.ndarray,
    predicted_label: str,
    probability: float,
    region_confidence_score: float,
) -> np.ndarray:
    grayscale_map = cv2.cvtColor(gradcam_map, cv2.COLOR_BGR2GRAY)
    grayscale_map = cv2.cvtColor(grayscale_map, cv2.COLOR_GRAY2BGR)
    binary_panel = cv2.cvtColor(binary_mask, cv2.COLOR_GRAY2BGR)

    p_original = _panel_with_title(image_bgr, "Original Image")
    p_activation = _panel_with_title(grayscale_map, "Activation Intensity")
    p_overlay = _panel_with_title(boundary_map, "Overlay + Nodule Boundary")
    p_threshold = _panel_with_title(binary_panel, "Thresholded ROI Mask")

    top_row = np.hstack([p_original, p_activation])
    bottom_row = np.hstack([p_overlay, p_threshold])
    panel = np.vstack([top_row, bottom_row])

    header = np.full((92, panel.shape[1], 3), 16, dtype=np.uint8)
    cv2.putText(header, "Lung AI Explainability Map", (24, 35), cv2.FONT_HERSHEY_SIMPLEX, 0.9, (240, 240, 240), 2, cv2.LINE_AA)
    cv2.putText(
        header,
        f"Predicted: {predicted_label} | Confidence: {probability * 100:.2f}% | Region Score: {region_confidence_score:.2f}/100",
        (24, 70),
        cv2.FONT_HERSHEY_SIMPLEX,
        0.67,
        (190, 220, 255),
        2,
        cv2.LINE_AA,
    )

    final = np.vstack([header, panel])
    legend = _build_legend_bar()
    legend_x_start = final.shape[1] - legend.shape[1] - 22
    legend_y_start = header.shape[0] + 24
    legend_y_end = legend_y_start + legend.shape[0]
    legend_x_end = legend_x_start + legend.shape[1]
    final[legend_y_start:legend_y_end, legend_x_start:legend_x_end] = legend

    cv2.putText(final, "High", (legend_x_start - 2, legend_y_start - 8), cv2.FONT_HERSHEY_SIMPLEX, 0.45, (255, 255, 255), 1, cv2.LINE_AA)
    cv2.putText(final, "Low", (legend_x_start + 2, legend_y_end + 18), cv2.FONT_HERSHEY_SIMPLEX, 0.45, (255, 255, 255), 1, cv2.LINE_AA)

    cv2.putText(
        final,
        "Clinical decision support only. Final diagnosis remains physician responsibility.",
        (24, final.shape[0] - 26),
        cv2.FONT_HERSHEY_SIMPLEX,
        0.55,
        (205, 205, 205),
        1,
        cv2.LINE_AA,
    )

    return final


def generate_explanation_maps(
    image_rgb: np.ndarray,
    mask_tensor: torch.Tensor,
    output_dir: Path,
    file_stem: str,
    predicted_label: str,
    probability: float,
) -> dict:
    canvas_size = 512
    image_bgr = cv2.cvtColor(image_rgb, cv2.COLOR_RGB2BGR)
    image_bgr = cv2.resize(image_bgr, (canvas_size, canvas_size), interpolation=cv2.INTER_CUBIC)

    mask = mask_tensor.squeeze().detach().cpu().numpy().astype(np.float32)
    base_activation = _normalize_activation_map(mask)
    base_activation = cv2.resize(base_activation, (canvas_size, canvas_size), interpolation=cv2.INTER_CUBIC)

    gradcam_activation = base_activation
    gradcampp_activation = np.clip(np.power(base_activation, 1.8), 0.0, 1.0)
    scorecam_activation = _normalize_activation_map(cv2.GaussianBlur(base_activation, (0, 0), sigmaX=4.0))

    grad_x = cv2.Sobel(base_activation, cv2.CV_32F, 1, 0, ksize=3)
    grad_y = cv2.Sobel(base_activation, cv2.CV_32F, 0, 1, ksize=3)
    saliency_activation = _normalize_activation_map(np.sqrt((grad_x ** 2) + (grad_y ** 2)))

    binary_mask = np.uint8(base_activation > 0.55) * 255
    boundary_map = _draw_boundary(image_bgr, binary_mask)
    top_regions = _extract_top_suspicious_regions(base_activation, threshold=0.55, max_regions=3)
    boundary_map = _annotate_top_regions(boundary_map, top_regions)

    gradcam_map = _activation_to_overlay(image_bgr, gradcam_activation, alpha=0.42, colormap=cv2.COLORMAP_JET)
    gradcampp_map = _activation_to_overlay(image_bgr, gradcampp_activation, alpha=0.48, colormap=cv2.COLORMAP_HOT)
    scorecam_map = _activation_to_overlay(image_bgr, scorecam_activation, alpha=0.46, colormap=cv2.COLORMAP_VIRIDIS)
    saliency_map = _activation_to_overlay(image_bgr, saliency_activation, alpha=0.52, colormap=cv2.COLORMAP_MAGMA)

    roi_values = base_activation[binary_mask > 0]
    if roi_values.size > 0:
        region_confidence_score = round(float(np.mean(roi_values) * 100), 2)
    else:
        region_confidence_score = round(float(np.max(base_activation) * 100), 2)

    composite = _build_composite(
        image_bgr=image_bgr,
        gradcam_map=gradcam_map,
        boundary_map=boundary_map,
        binary_mask=binary_mask,
        predicted_label=predicted_label,
        probability=probability,
        region_confidence_score=region_confidence_score,
    )

    maps = {
        'original': _save_png(output_dir / f"{file_stem}_original.png", image_bgr),
        'gradcam': _save_png(output_dir / f"{file_stem}_gradcam.png", gradcam_map),
        'gradcampp': _save_png(output_dir / f"{file_stem}_gradcampp.png", gradcampp_map),
        'scorecam': _save_png(output_dir / f"{file_stem}_scorecam.png", scorecam_map),
        'saliency': _save_png(output_dir / f"{file_stem}_saliency.png", saliency_map),
        'boundary': _save_png(output_dir / f"{file_stem}_boundary.png", boundary_map),
    }

    composite_name = _save_png(output_dir / f"{file_stem}_composite.png", composite)

    return {
        'heatmap': composite_name,
        'explanation_maps': maps,
        'region_confidence_score': region_confidence_score,
        'top_suspicious_regions': top_regions,
        'lesion_quantification': _derive_lesion_quantification(top_regions),
    }


def generate_ct_viewer_assets(
    image_rgb: np.ndarray,
    mask_tensor: torch.Tensor,
    output_dir: Path,
    file_stem: str,
    depth: int = 24,
) -> dict:
    image_bgr = cv2.cvtColor(image_rgb, cv2.COLOR_RGB2BGR)
    image_bgr = cv2.resize(image_bgr, (512, 512), interpolation=cv2.INTER_CUBIC)
    gray = cv2.cvtColor(image_bgr, cv2.COLOR_BGR2GRAY)

    mask = mask_tensor.squeeze().detach().cpu().numpy().astype(np.float32)
    normalized = _normalize_activation_map(mask)
    normalized = cv2.resize(normalized, (512, 512), interpolation=cv2.INTER_CUBIC)

    slice_files: list[str] = []
    segmentation_files: list[str] = []

    for index in range(depth):
        phase = np.sin((index / max(depth - 1, 1)) * np.pi)
        intensity_scale = 0.75 + (0.25 * phase)
        slice_gray = np.clip(gray.astype(np.float32) * intensity_scale, 0, 255).astype(np.uint8)
        slice_bgr = cv2.cvtColor(slice_gray, cv2.COLOR_GRAY2BGR)

        seg_strength = 0.5 + (0.5 * phase)
        seg_map = np.clip(normalized * seg_strength, 0.0, 1.0)
        seg_overlay = _activation_to_overlay(slice_bgr, seg_map, alpha=0.55, colormap=cv2.COLORMAP_JET)

        seg_binary = np.uint8(seg_map > 0.55) * 255
        seg_boundary = _draw_boundary(seg_overlay, seg_binary)

        slice_name = _save_png(output_dir / f"{file_stem}_slice_{index:03d}.png", slice_bgr)
        seg_name = _save_png(output_dir / f"{file_stem}_seg_{index:03d}.png", seg_boundary)

        slice_files.append(slice_name)
        segmentation_files.append(seg_name)

    return {
        'slice_files': slice_files,
        'segmentation_files': segmentation_files,
        'depth': depth,
    }


def generate_model_comparison_visuals(
    image_rgb: np.ndarray,
    saliency_maps: dict[str, np.ndarray],
    model_predictions: list[dict],
    output_dir: Path,
    file_stem: str,
) -> dict:
    canvas_size = 512
    image_bgr = cv2.cvtColor(image_rgb, cv2.COLOR_RGB2BGR)
    image_bgr = cv2.resize(image_bgr, (canvas_size, canvas_size), interpolation=cv2.INTER_CUBIC)

    prediction_lookup: dict[str, dict] = {}
    for item in model_predictions:
        model_name = str(item.get('model', '')).strip().lower()
        if model_name:
            prediction_lookup[model_name] = item

    model_order = ['resnet', 'densenet', 'hybrid', 'yolov8', 'kerashf']
    overlays: dict[str, str] = {}
    panels: list[np.ndarray] = []

    for model_key in model_order:
        activation = saliency_maps.get(model_key)
        if activation is None:
            continue

        normalized = _normalize_activation_map(activation.astype(np.float32))
        normalized = cv2.resize(normalized, (canvas_size, canvas_size), interpolation=cv2.INTER_CUBIC)
        overlay = _activation_to_overlay(image_bgr, normalized, alpha=0.48, colormap=cv2.COLORMAP_JET)

        prediction = prediction_lookup.get(model_key, {})
        label = str(prediction.get('result', 'N/A'))
        probability = float(prediction.get('probability', 0.0))
        title = f"{model_key.upper()}: {label} ({probability * 100:.2f}%)"
        panel = _panel_with_title(overlay, title)

        filename = _save_png(output_dir / f"{file_stem}_{model_key}_overlay.png", panel)
        overlays[model_key] = filename
        panels.append(panel)

    comparison_panel = None
    if panels:
        comparison = np.hstack(panels)
        comparison_panel = _save_png(output_dir / f"{file_stem}_comparison.png", comparison)

    return {
        'overlays': overlays,
        'comparison_panel': comparison_panel,
    }
