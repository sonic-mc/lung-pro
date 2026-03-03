import torch
from sklearn.metrics import accuracy_score, classification_report

from models.hybrid_model import HybridLungCancerModel


def run_dummy_evaluation() -> dict:
    model = HybridLungCancerModel()
    model.eval()

    x = torch.randn(12, 3, 224, 224)
    y_true = torch.randint(0, 2, (12,)).numpy()

    with torch.no_grad():
        logits = model(x).logits
        y_pred = torch.argmax(logits, dim=1).numpy()

    report = {
        "accuracy": float(accuracy_score(y_true, y_pred)),
        "classification_report": classification_report(y_true, y_pred, output_dict=True),
    }
    return report


if __name__ == "__main__":
    metrics = run_dummy_evaluation()
    print(metrics)
