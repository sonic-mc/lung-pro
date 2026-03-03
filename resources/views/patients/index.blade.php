@extends('layouts.app')

@section('title', 'Patients')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h1 class="h3 mb-1">Patients</h1>
            <p class="text-muted mb-0">Select a patient to open full history and progression analytics.</p>
        </div>
        <a href="{{ route('scans.create') }}" class="btn btn-primary">New Upload</a>
    </div>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('patients.index') }}" class="row g-2 align-items-center">
                <div class="col-md-8 col-lg-6">
                    <input
                        type="text"
                        name="search"
                        value="{{ $search }}"
                        class="form-control"
                        placeholder="Search by patient name or MRN"
                    >
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-primary">Search</button>
                </div>
                @if ($search !== '')
                    <div class="col-auto">
                        <a href="{{ route('patients.index') }}" class="btn btn-outline-secondary">Clear</a>
                    </div>
                @endif
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Patient</th>
                        <th>MRN</th>
                        <th>Sex</th>
                        <th>DOB</th>
                        <th>Predictions</th>
                        <th>Latest Result</th>
                        <th>Latest Probability</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($patients as $patient)
                        @php
                            $latest = $patient->latest_prediction;
                        @endphp
                        <tr>
                            <td class="fw-semibold">{{ $patient->full_name }}</td>
                            <td>{{ $patient->medical_record_number }}</td>
                            <td>{{ strtoupper((string) $patient->sex ?: '-') }}</td>
                            <td>{{ optional($patient->date_of_birth)->format('Y-m-d') ?? '-' }}</td>
                            <td>{{ $patient->prediction_count }}</td>
                            <td>
                                @if ($latest)
                                    <span class="badge {{ $latest->predicted_label === 'Malignant' ? 'text-bg-danger' : 'text-bg-success' }}">
                                        {{ $latest->predicted_label }}
                                    </span>
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
                            </td>
                            <td>
                                @if ($latest)
                                    {{ number_format((float) $latest->probability * 100, 2) }}%
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('patients.history', $patient) }}" class="btn btn-sm btn-outline-primary">View History</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">No patients found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3 d-flex justify-content-end">
        {{ $patients->links() }}
    </div>
</div>
@endsection
