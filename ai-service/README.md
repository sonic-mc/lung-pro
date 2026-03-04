# AI Service (FastAPI + PyTorch)

This service provides deep-learning inference for lung cancer detection.

## Features
- Hybrid segmentation + classification workflow
- U-Net segmentation mask generation
- CNN classifier (DenseNet / ResNet scaffold)
- Ultralytics YOLOv8 chest X-ray classification support (`keremberke/yolov8m-chest-xray-classification`)
- Keras HuggingFace model support (`ahmEdimrann/Histopathological-Lung-and-Colon-Cancer-Dectection`)
- Grad-CAM style heatmap artifact output
- REST endpoint: `POST /predict`

## Setup
```bash
python -m venv .venv
.venv\\Scripts\\activate
pip install -r requirements.txt
```

## Run API
```bash
uvicorn api.main:app --host 0.0.0.0 --port 8001 --reload
```

## Train / Evaluate (scaffold)
```bash
python training/train.py
python training/evaluate.py
```

Training now includes:
- Focal loss + weighted cross-entropy blend for class imbalance
- Hard negative replay mining for false-positive reduction
- Calibration artifact export to `artifacts/calibration/thresholds.json` containing:
  - `temperature_scaling`
  - `malignancy_threshold_diagnostic`
  - `malignancy_threshold_screening`

Inference automatically loads this artifact when available.

## Example Response
```json
{
  "prediction": "Benign",
  "probability": 0.9231,
  "heatmap": "gradcam_20260302_120000_123456.png"
}
```

## Lung Cancer AI System Flow (With Visual Output Stage)

1. User uploads CT scan or X-ray (`POST /predict`) with modality and metadata.
2. Image preprocessing prepares model input (resize, normalize, denoise).
3. Segmentation stage (hybrid path) produces tumor activation mask and location cues.
4. Classification stage (ResNet / DenseNet / Hybrid / optional YOLOv8 / KerasHF) returns probabilities.
5. Explainability stage generates Grad-CAM and related saliency maps.
6. **Visualization stage** overlays original scan + heatmap + optional segmentation boundary.
7. Severity and risk features are computed (`severity_score`, nodule burden and size metrics).
8. Confidence band is determined (`High` / `Moderate` / `Low`) with reasoning text.
9. Final payload returns annotated assets + diagnostic values for UI rendering.
10. Report layer consumes the same payload for clinician documentation.

### What the clinician sees in the UI

- Left panel: Original scan
- Center panel: AI-highlighted scan (heatmap toggle ON/OFF, optional mask overlay)
- Right panel: Diagnostic results (prediction, probability, severity score, confidence band, finding location)

### Core workflow sequence

User Uploads Scan
→ Image Preprocessing
→ Segmentation Model
→ Classification Model
→ Heatmap Generation
→ **Visualization (affected areas shown)**
→ Severity & Confidence Analysis
→ Diagnostic Report
→ Radiologist Review
