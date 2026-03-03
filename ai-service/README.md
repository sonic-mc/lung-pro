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

## Example Response
```json
{
  "prediction": "Benign",
  "probability": 0.9231,
  "heatmap": "gradcam_20260302_120000_123456.png"
}
```
