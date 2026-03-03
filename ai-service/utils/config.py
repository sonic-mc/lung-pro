from pathlib import Path

BASE_DIR = Path(__file__).resolve().parents[1]
ARTIFACTS_DIR = BASE_DIR / "artifacts"
CHECKPOINT_DIR = ARTIFACTS_DIR / "checkpoints"
HEATMAP_DIR = ARTIFACTS_DIR / "heatmaps"

MODEL_VERSION = "hybrid-v1"
CLASS_NAMES = ["Benign", "Malignant"]
IMAGE_SIZE = 224

for directory in (ARTIFACTS_DIR, CHECKPOINT_DIR, HEATMAP_DIR):
    directory.mkdir(parents=True, exist_ok=True)
