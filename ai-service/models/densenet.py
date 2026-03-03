from torch import nn
from torchvision import models


def build_densenet_classifier(num_classes: int = 2) -> nn.Module:
    try:
        model = models.densenet121(weights=models.DenseNet121_Weights.DEFAULT)
    except Exception:
        model = models.densenet121(weights=None)
    model.classifier = nn.Linear(model.classifier.in_features, num_classes)
    return model
