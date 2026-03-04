from pathlib import Path
import json

import numpy as np
import torch
from torch import nn, optim
import torch.nn.functional as F
from sklearn.metrics import fbeta_score

from models.hybrid_model import HybridLungCancerModel
from utils.config import (
    CALIBRATION_FILE,
    CALIBRATION_GRID_MAX,
    CALIBRATION_GRID_MIN,
    CALIBRATION_GRID_STEP,
    CHECKPOINT_DIR,
    FOCAL_LOSS_ALPHA,
    FOCAL_LOSS_GAMMA,
    HARD_NEGATIVE_MIN_CONFIDENCE,
)


class FocalLoss(nn.Module):
    def __init__(self, alpha: float = FOCAL_LOSS_ALPHA, gamma: float = FOCAL_LOSS_GAMMA):
        super().__init__()
        self.alpha = float(alpha)
        self.gamma = float(gamma)

    def forward(self, logits: torch.Tensor, targets: torch.Tensor) -> torch.Tensor:
        ce_loss = F.cross_entropy(logits, targets, reduction='none')
        pt = torch.exp(-ce_loss)

        alpha_weight = torch.where(
            targets == 1,
            torch.full_like(targets, self.alpha, dtype=torch.float32),
            torch.full_like(targets, 1.0 - self.alpha, dtype=torch.float32),
        )
        alpha_weight = alpha_weight.to(logits.device)

        loss = alpha_weight * ((1.0 - pt) ** self.gamma) * ce_loss
        return loss.mean()


def _compute_class_weights(targets: torch.Tensor) -> torch.Tensor:
    targets_np = targets.detach().cpu().numpy().astype(np.int64)
    classes, counts = np.unique(targets_np, return_counts=True)
    if len(classes) < 2:
        return torch.tensor([1.0, 1.0], dtype=torch.float32)

    total = float(np.sum(counts))
    weights = np.zeros(2, dtype=np.float32)
    for cls, count in zip(classes, counts):
        weights[int(cls)] = total / max(float(count) * 2.0, 1.0)

    weights = np.clip(weights, 0.5, 4.0)
    return torch.tensor(weights, dtype=torch.float32)


def _fit_temperature(logits: torch.Tensor, labels: torch.Tensor) -> float:
    with torch.no_grad():
        probs = torch.softmax(logits, dim=1)
    eps = 1e-6
    probs = torch.clamp(probs, min=eps)
    log_probs = torch.log(probs)

    best_temp = 1.0
    best_nll = float('inf')

    for temperature in np.linspace(0.7, 2.0, num=40):
        scaled = torch.softmax(log_probs / float(temperature), dim=1)
        nll = F.nll_loss(torch.log(torch.clamp(scaled, min=eps)), labels).item()
        if nll < best_nll:
            best_nll = nll
            best_temp = float(temperature)

    return round(best_temp, 4)


def _find_best_threshold(probabilities: np.ndarray, labels: np.ndarray, beta: float = 1.0) -> float:
    best_threshold = 0.5
    best_score = -1.0

    for threshold in np.arange(CALIBRATION_GRID_MIN, CALIBRATION_GRID_MAX + 1e-6, CALIBRATION_GRID_STEP):
        preds = (probabilities >= threshold).astype(np.int64)
        score = fbeta_score(labels, preds, beta=beta, zero_division=0)
        if score > best_score:
            best_score = float(score)
            best_threshold = float(threshold)

    return round(best_threshold, 4)


def _export_calibration_artifact(logits: torch.Tensor, labels: torch.Tensor) -> dict:
    with torch.no_grad():
        probs = torch.softmax(logits, dim=1)[:, 1].detach().cpu().numpy()
        y_true = labels.detach().cpu().numpy().astype(np.int64)

    temperature = _fit_temperature(logits.detach(), labels.detach())
    diagnostic_threshold = _find_best_threshold(probs, y_true, beta=1.0)
    screening_threshold = _find_best_threshold(probs, y_true, beta=2.0)

    artifact = {
        'temperature_scaling': temperature,
        'malignancy_threshold_diagnostic': diagnostic_threshold,
        'malignancy_threshold_screening': screening_threshold,
        'calibration_size': int(len(y_true)),
    }

    CALIBRATION_FILE.parent.mkdir(parents=True, exist_ok=True)
    with CALIBRATION_FILE.open('w', encoding='utf-8') as handle:
        json.dump(artifact, handle, indent=2)

    return artifact


def _mine_hard_negative_batch(model: HybridLungCancerModel, pool_x: torch.Tensor, pool_y: torch.Tensor) -> tuple[torch.Tensor, torch.Tensor]:
    model.eval()
    with torch.no_grad():
        logits = model(pool_x).logits
        probs = torch.softmax(logits, dim=1)[:, 1]

    negative_mask = pool_y == 0
    hard_negative_mask = negative_mask & (probs >= HARD_NEGATIVE_MIN_CONFIDENCE)

    if not torch.any(hard_negative_mask):
        return pool_x[:0], pool_y[:0]

    return pool_x[hard_negative_mask], pool_y[hard_negative_mask]


def run_dummy_training(epochs: int = 2) -> Path:
    model = HybridLungCancerModel()
    focal_criterion = FocalLoss()
    optimizer = optim.Adam(model.parameters(), lr=1e-4, weight_decay=1e-5)

    hard_negative_x = torch.empty((0, 3, 224, 224), dtype=torch.float32)
    hard_negative_y = torch.empty((0,), dtype=torch.long)

    calibration_logits: list[torch.Tensor] = []
    calibration_labels: list[torch.Tensor] = []

    model.train()
    for _ in range(epochs):
        x = torch.randn(12, 3, 224, 224)
        y = torch.randint(0, 2, (12,))

        if hard_negative_x.numel() > 0:
            x = torch.cat([x, hard_negative_x], dim=0)
            y = torch.cat([y, hard_negative_y], dim=0)

        class_weights = _compute_class_weights(y)
        weighted_ce = nn.CrossEntropyLoss(weight=class_weights)

        output = model(x)
        ce_loss = weighted_ce(output.logits, y)
        focal_loss = focal_criterion(output.logits, y)
        loss = (0.55 * ce_loss) + (0.45 * focal_loss)

        optimizer.zero_grad()
        loss.backward()
        optimizer.step()

        mined_x, mined_y = _mine_hard_negative_batch(model, x.detach(), y.detach())
        hard_negative_x = mined_x
        hard_negative_y = mined_y

        calibration_logits.append(output.logits.detach().cpu())
        calibration_labels.append(y.detach().cpu())

    if calibration_logits and calibration_labels:
        _export_calibration_artifact(
            torch.cat(calibration_logits, dim=0),
            torch.cat(calibration_labels, dim=0),
        )

    checkpoint_path = CHECKPOINT_DIR / "hybrid_model.pt"
    torch.save(model.state_dict(), checkpoint_path)
    return checkpoint_path


if __name__ == "__main__":
    path = run_dummy_training(epochs=1)
    print(f"Saved checkpoint to: {path}")
