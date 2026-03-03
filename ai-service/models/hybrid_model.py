from dataclasses import dataclass

import torch
from torch import nn

from .densenet import build_densenet_classifier
from .unet import UNet


@dataclass
class HybridOutput:
    segmentation_mask: torch.Tensor
    logits: torch.Tensor


class HybridLungCancerModel(nn.Module):
    def __init__(self, classifier_name: str = "densenet", num_classes: int = 2):
        super().__init__()
        self.segmenter = UNet(in_channels=3, out_channels=1)
        self.classifier = build_densenet_classifier(num_classes=num_classes)
        self.classifier_name = classifier_name

    def forward(self, x: torch.Tensor) -> HybridOutput:
        raw_mask = self.segmenter(x)
        mask = torch.sigmoid(raw_mask)
        masked_input = x * mask
        logits = self.classifier(masked_input)
        return HybridOutput(segmentation_mask=mask, logits=logits)
