import torch
import numpy as np
from sklearn.metrics import accuracy_score, classification_report, fbeta_score

from models.hybrid_model import HybridLungCancerModel


def _expected_calibration_error(probabilities: np.ndarray, labels: np.ndarray, bins: int = 10) -> float:
    bin_edges = np.linspace(0.0, 1.0, bins + 1)
    ece = 0.0

    for index in range(bins):
        lower = bin_edges[index]
        upper = bin_edges[index + 1]
        in_bin = (probabilities >= lower) & (probabilities < upper)
        if not np.any(in_bin):
            continue

        bin_acc = np.mean(labels[in_bin] == (probabilities[in_bin] >= 0.5).astype(np.int64))
        bin_conf = np.mean(probabilities[in_bin])
        ece += (np.sum(in_bin) / len(probabilities)) * abs(bin_acc - bin_conf)

    return float(ece)


def _best_threshold(probabilities: np.ndarray, labels: np.ndarray, beta: float) -> float:
    best_t = 0.5
    best_s = -1.0
    for threshold in np.arange(0.2, 0.81, 0.02):
        preds = (probabilities >= threshold).astype(np.int64)
        score = fbeta_score(labels, preds, beta=beta, zero_division=0)
        if score > best_s:
            best_s = float(score)
            best_t = float(threshold)
    return round(best_t, 4)


def run_dummy_evaluation() -> dict:
    model = HybridLungCancerModel()
    model.eval()

    x = torch.randn(12, 3, 224, 224)
    y_true = torch.randint(0, 2, (12,)).numpy()

    with torch.no_grad():
        logits = model(x).logits
        y_pred = torch.argmax(logits, dim=1).numpy()
        y_prob = torch.softmax(logits, dim=1)[:, 1].numpy()

    diagnostic_threshold = _best_threshold(y_prob, y_true, beta=1.0)
    screening_threshold = _best_threshold(y_prob, y_true, beta=2.0)

    report = {
        "accuracy": float(accuracy_score(y_true, y_pred)),
        "classification_report": classification_report(y_true, y_pred, output_dict=True),
        "calibration": {
            "ece": _expected_calibration_error(y_prob, y_true, bins=10),
            "diagnostic_threshold": diagnostic_threshold,
            "screening_threshold": screening_threshold,
        },
    }
    return report


if __name__ == "__main__":
    metrics = run_dummy_evaluation()
    print(metrics)
