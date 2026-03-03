from pathlib import Path
from typing import Annotated

from aiofiles import tempfile as aio_tempfile
from fastapi import FastAPI, File, Form, HTTPException, UploadFile
from fastapi.responses import FileResponse, JSONResponse

from inference.predict import get_model_statuses, infer_image
from utils.config import HEATMAP_DIR

app = FastAPI(title="Lung Cancer AI Service", version="1.0.0")


@app.get("/health")
def health() -> dict:
    return {"status": "ok"}


@app.get("/models/status")
def model_status() -> dict:
    return {
        'models': get_model_statuses(),
    }


@app.get("/heatmaps/{filename}", responses={404: {"description": "Heatmap not found"}})
def get_heatmap(filename: str) -> FileResponse:
    heatmap_path = (HEATMAP_DIR / filename).resolve()

    if heatmap_path.parent != HEATMAP_DIR.resolve() or not heatmap_path.exists():
        raise HTTPException(status_code=404, detail="Heatmap not found")

    return FileResponse(path=heatmap_path, media_type="image/png")


@app.post("/predict", responses={422: {"description": "Unsupported or invalid scan input"}, 500: {"description": "Prediction pipeline failed"}})
async def predict(
    file: Annotated[UploadFile, File(...)],
    modality: Annotated[str, Form()] = 'xray',
    dataset_source: Annotated[str, Form()] = '',
    selected_model: Annotated[str, Form()] = 'hybrid',
) -> JSONResponse:
    suffix = Path(file.filename or "scan.png").suffix or ".png"
    temp_path = None

    try:
        async with aio_tempfile.NamedTemporaryFile(mode='wb', delete=False, suffix=suffix) as temp:
            content = await file.read()
            await temp.write(content)
            temp_path = temp.name

        result = infer_image(
            temp_path,
            modality=modality,
            dataset_source=dataset_source,
            selected_model=selected_model,
        )
        return JSONResponse(content={
            "prediction": result["prediction"],
            "probability": result["probability"],
            "model_comparisons": result["model_comparisons"],
            "model_visuals": result["model_visuals"],
            "model_version": result["model_version"],
            "heatmap": result["heatmap"],
            "explanation_maps": result["explanation_maps"],
            "region_confidence_score": result["region_confidence_score"],
            "cancer_stage": result["cancer_stage"],
            "confidence_reasoning": result["confidence_reasoning"],
            "ct_viewer": result["ct_viewer"],
            "dataset_source": result["dataset_source"],
            "finding_location": result["finding_location"],
            "severity_score": result["severity_score"],
            "confidence_band": result["confidence_band"],
            "nodule_diameter_mm": result["nodule_diameter_mm"],
            "nodule_area_px": result["nodule_area_px"],
            "tumor_area_mm2": result["tumor_area_mm2"],
            "tumor_volume_mm3": result["tumor_volume_mm3"],
            "nodule_burden_percent": result["nodule_burden_percent"],
        })
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc
    except Exception as exc:
        raise HTTPException(status_code=500, detail=str(exc)) from exc
    finally:
        try:
            if temp_path is not None:
                Path(temp_path).unlink(missing_ok=True)
        except Exception:
            pass
