@extends('layouts.app')

@section('title', 'Lung AI - Upload Scan')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Lung Cancer Detection Platform</h1>
        <a href="{{ route('predictions.index') }}" class="btn btn-outline-primary">Prediction Dashboard</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            <form action="{{ route('scans.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                @php
                    $selectedPatientMode = old('patient_mode', ($patients->isNotEmpty() ? 'existing' : 'new'));
                    $yoloStatus = $modelStatus['yolov8'] ?? [];
                    $yoloAvailable = (bool) ($yoloStatus['available'] ?? true);
                    $yoloStatusText = (string) ($yoloStatus['status'] ?? 'unknown');
                    $yoloBadgeClass = $yoloAvailable ? 'text-bg-success' : 'text-bg-danger';
                    $kerasStatus = $modelStatus['kerashf'] ?? [];
                    $kerasAvailable = (bool) ($kerasStatus['available'] ?? true);
                    $kerasStatusText = (string) ($kerasStatus['status'] ?? 'unknown');
                    $kerasBadgeClass = $kerasAvailable ? 'text-bg-success' : 'text-bg-danger';
                @endphp
                <div class="row g-3">
                    <div class="col-12">
                        <p class="form-label d-block mb-1">Patient Selection</p>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="patient_mode" id="patient_mode_existing" value="existing" @checked($selectedPatientMode === 'existing') {{ $patients->isEmpty() ? 'disabled' : '' }}>
                            <label class="form-check-label" for="patient_mode_existing">Existing Patient</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="patient_mode" id="patient_mode_new" value="new" @checked($selectedPatientMode === 'new')>
                            <label class="form-check-label" for="patient_mode_new">New Patient</label>
                        </div>
                        @if ($patients->isEmpty())
                            <div class="form-text">No existing patient with previous uploads found yet. Please create a new patient.</div>
                        @endif
                    </div>
                    <div class="col-12" id="existing_patient_fields">
                        <label class="form-label" for="patient_id">Select Existing Patient</label>
                        <select id="patient_id" name="patient_id" class="form-select">
                            <option value="">Select patient</option>
                            @foreach ($patients as $patient)
                                <option value="{{ $patient->id }}" @selected((string) old('patient_id') === (string) $patient->id)>
                                    {{ $patient->full_name }} (MRN: {{ $patient->medical_record_number }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6" id="new_patient_mrn_field">
                        <label class="form-label" for="medical_record_number">Medical Record Number</label>
                        <input id="medical_record_number" type="text" name="medical_record_number" class="form-control" value="{{ old('medical_record_number') }}" required>
                    </div>
                    <div class="col-md-6" id="new_patient_name_field">
                        <label class="form-label" for="full_name">Patient Full Name</label>
                        <input id="full_name" type="text" name="full_name" class="form-control" value="{{ old('full_name') }}" required>
                    </div>
                    <div class="col-md-4" id="new_patient_dob_field">
                        <label class="form-label" for="date_of_birth">Date of Birth</label>
                        <input id="date_of_birth" type="date" name="date_of_birth" class="form-control" value="{{ old('date_of_birth') }}">
                    </div>
                    <div class="col-md-4" id="new_patient_sex_field">
                        <label class="form-label" for="sex">Sex</label>
                        <select id="sex" name="sex" class="form-select">
                            <option value="">Select</option>
                            <option value="male" @selected(old('sex') === 'male')>Male</option>
                            <option value="female" @selected(old('sex') === 'female')>Female</option>
                            <option value="other" @selected(old('sex') === 'other')>Other</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="modality">Modality</label>
                        <select id="modality" name="modality" class="form-select" required>
                            <option value="xray" @selected(old('modality') === 'xray')>Chest X-ray</option>
                            <option value="ct" @selected(old('modality') === 'ct')>CT Scan</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label mb-0" for="selected_model">Primary AI Model</label>
                            <div class="d-flex gap-1">
                                <span class="badge {{ $yoloBadgeClass }}">YOLOv8: {{ ucfirst($yoloStatusText) }}</span>
                                <span class="badge {{ $kerasBadgeClass }}">KerasHF: {{ ucfirst($kerasStatusText) }}</span>
                            </div>
                        </div>
                        <select id="selected_model" name="selected_model" class="form-select" required>
                            <option value="hybrid" @selected(old('selected_model', 'hybrid') === 'hybrid')>Hybrid (U-Net + CNN)</option>
                            <option value="resnet" @selected(old('selected_model') === 'resnet')>ResNet</option>
                            <option value="densenet" @selected(old('selected_model') === 'densenet')>DenseNet</option>
                            <option value="yolov8" @selected(old('selected_model') === 'yolov8') {{ $yoloAvailable ? '' : 'disabled' }}>YOLOv8 (Chest X-ray Classification)</option>
                            <option value="kerashf" @selected(old('selected_model') === 'kerashf') {{ $kerasAvailable ? '' : 'disabled' }}>KerasHF (HF Histopathological Model)</option>
                        </select>
                        <div class="form-text">YOLOv8 is available for Chest X-ray only. KerasHF availability depends on Keras backend/runtime.</div>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="dataset_source">Hospital / Dataset Source</label>
                        <input id="dataset_source" type="text" name="dataset_source" class="form-control" value="{{ old('dataset_source') }}" placeholder="e.g., Hospital A, LIDC-IDRI, NSCLC-Radiomics">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="image">Medical Image</label>
                        <input id="image" type="file" name="image" class="form-control" accept=".jpg,.jpeg,.png,.dcm,.tif,.tiff" required>
                    </div>
                    <div class="col-12 text-end">
                        <button class="btn btn-primary" type="submit">Upload and Predict</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const existingMode = document.getElementById('patient_mode_existing');
        const newMode = document.getElementById('patient_mode_new');
        const existingPatientFields = document.getElementById('existing_patient_fields');
        const newPatientMnrField = document.getElementById('new_patient_mrn_field');
        const newPatientNameField = document.getElementById('new_patient_name_field');
        const newPatientDobField = document.getElementById('new_patient_dob_field');
        const newPatientSexField = document.getElementById('new_patient_sex_field');

        const patientId = document.getElementById('patient_id');
        const medicalRecordNumber = document.getElementById('medical_record_number');
        const fullName = document.getElementById('full_name');
        const modality = document.getElementById('modality');
        const selectedModel = document.getElementById('selected_model');
        const yoloServerAvailable = @json($yoloAvailable);
        const kerasServerAvailable = @json($kerasAvailable);

        function updatePatientFields() {
            const useExisting = existingMode && existingMode.checked && !existingMode.disabled;

            existingPatientFields.style.display = useExisting ? '' : 'none';
            newPatientMnrField.style.display = useExisting ? 'none' : '';
            newPatientNameField.style.display = useExisting ? 'none' : '';
            newPatientDobField.style.display = useExisting ? 'none' : '';
            newPatientSexField.style.display = useExisting ? 'none' : '';

            if (patientId) {
                patientId.required = useExisting;
            }
            if (medicalRecordNumber) {
                medicalRecordNumber.required = !useExisting;
            }
            if (fullName) {
                fullName.required = !useExisting;
            }
        }

        if (existingMode) {
            existingMode.addEventListener('change', updatePatientFields);
        }
        if (newMode) {
            newMode.addEventListener('change', updatePatientFields);
        }

        function syncModelModalityConstraints() {
            if (!modality || !selectedModel) {
                return;
            }

            const yoloOption = selectedModel.querySelector('option[value="yolov8"]');
            const kerasOption = selectedModel.querySelector('option[value="kerashf"]');
            const ctMode = modality.value === 'ct';

            if (yoloOption) {
                yoloOption.disabled = ctMode || !yoloServerAvailable;
            }

            if (kerasOption) {
                kerasOption.disabled = !kerasServerAvailable;
            }

            if ((ctMode || !yoloServerAvailable) && selectedModel.value === 'yolov8') {
                selectedModel.value = 'hybrid';
            }

            if (!kerasServerAvailable && selectedModel.value === 'kerashf') {
                selectedModel.value = 'hybrid';
            }
        }

        if (modality) {
            modality.addEventListener('change', syncModelModalityConstraints);
        }

        updatePatientFields();
        syncModelModalityConstraints();
    });
</script>
@endsection
