import random

import cv2
import numpy as np


def random_augment(image: np.ndarray) -> np.ndarray:
    augmented = image.copy()

    if random.random() > 0.5:
        augmented = cv2.flip(augmented, 1)

    if random.random() > 0.5:
        alpha = random.uniform(0.85, 1.15)
        beta = random.uniform(-12, 12)
        augmented = cv2.convertScaleAbs(augmented, alpha=alpha, beta=beta)

    if random.random() > 0.7:
        augmented = cv2.GaussianBlur(augmented, (3, 3), sigmaX=0.5)

    return augmented
