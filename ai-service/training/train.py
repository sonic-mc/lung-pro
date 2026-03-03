from pathlib import Path

import torch
from torch import nn, optim

from models.hybrid_model import HybridLungCancerModel
from utils.config import CHECKPOINT_DIR


def run_dummy_training(epochs: int = 1) -> Path:
    model = HybridLungCancerModel()
    criterion = nn.CrossEntropyLoss()
    optimizer = optim.Adam(model.parameters(), lr=1e-4, weight_decay=1e-5)

    model.train()
    for _ in range(epochs):
        x = torch.randn(4, 3, 224, 224)
        y = torch.randint(0, 2, (4,))
        output = model(x)
        loss = criterion(output.logits, y)

        optimizer.zero_grad()
        loss.backward()
        optimizer.step()

    checkpoint_path = CHECKPOINT_DIR / "hybrid_model.pt"
    torch.save(model.state_dict(), checkpoint_path)
    return checkpoint_path


if __name__ == "__main__":
    path = run_dummy_training(epochs=1)
    print(f"Saved checkpoint to: {path}")
