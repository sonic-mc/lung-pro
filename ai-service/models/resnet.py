from torch import nn
from torchvision import models


def build_resnet_classifier(num_classes: int = 2) -> nn.Module:
    try:
        model = models.resnet18(weights=models.ResNet18_Weights.DEFAULT)
    except Exception:
        model = models.resnet18(weights=None)
    model.fc = nn.Linear(model.fc.in_features, num_classes)
    return model
