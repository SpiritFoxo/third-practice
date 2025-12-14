@extends('layouts.app')

@section('content')
<style>
    .leaflet-control-attribution { display: none !important; }
</style>

<div class="container py-5">
    
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
        <h1 class="h2 mb-0 text-primary">Мониторинг МКС</h1>
        <div class="text-muted">
            Последнее обновление: 
            <span class="fw-bold">{{ $last['FetchedAt'] ?? '—' }} UTC</span>
        </div>
    </div>

    @if(empty($last) && empty($trend))
        <div class="alert alert-warning text-center" role="alert">
            Нет актуальных данных о МКС.
        </div>
    @endif

    {{--- 1. КАРТОЧКИ: ПОСЛЕДНИЙ СНИМОК ---}}
    <h3 class="mb-3 text-secondary">Текущее состояние</h3>
    <div class="row g-4 mb-5">
        
        {{-- Карточки с анимацией hover-card --}}
        <div class="col-md-3 col-sm-6">
            <div class="card h-100 border-start border-4 border-danger shadow-sm hover-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-speedometer2 text-danger fs-3 me-3"></i>
                        <div>
                            <h6 class="card-title text-uppercase text-muted mb-1 small">Скорость</h6>
                            <p class="h4 mb-0 fw-bold">
                                {{ number_format($last['Payload']['velocity'] ?? 0, 0, '', ' ') }}
                                <small class="text-muted fs-6">км/ч</small>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="card h-100 border-start border-4 border-info shadow-sm hover-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-globe-americas text-info fs-3 me-3"></i>
                        <div>
                            <h6 class="card-title text-uppercase text-muted mb-1 small">Высота</h6>
                            <p class="h4 mb-0 fw-bold">
                                {{ number_format($last['Payload']['altitude'] ?? 0, 1, '.', ' ') }}
                                <small class="text-muted fs-6">км</small>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            @php
                $visibility = $last['Payload']['visibility'] ?? 'unknown';
                $visColor = match($visibility) {
                    'daylight' => 'warning',
                    'eclipsed' => 'dark',
                    default => 'secondary'
                };
            @endphp
            <div class="card h-100 border-start border-4 border-{{ $visColor }} shadow-sm hover-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-eye text-{{ $visColor }} fs-3 me-3"></i>
                        <div>
                            <h6 class="card-title text-uppercase text-muted mb-1 small">Видимость</h6>
                            <p class="h4 mb-0 fw-bold text-uppercase">{{ $visibility }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="card h-100 border-start border-4 border-primary shadow-sm hover-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-bullseye text-primary fs-3 me-3"></i>
                        <div>
                            <h6 class="card-title text-uppercase text-muted mb-1 small">Покрытие</h6>
                            <p class="h4 mb-0 fw-bold">
                                {{ number_format($last['Payload']['footprint'] ?? 0, 0, '', ' ') }}
                                <small class="text-muted fs-6">км</small>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <hr>
    
    {{--- 2. КАРТА И ТРЕНД ---}}
    <h3 class="mb-3 text-secondary">Траектория и Тренд</h3>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card shadow-sm h-100 hover-card">
                <div class="card-header bg-white py-2">
                    <h5 class="mb-0">Позиция МКС</h5>
                </div>
                <div id="issMap" style="height: 400px; width: 100%; background: #eee;"></div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm h-100 hover-card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0"><i class="bi bi-graph-up me-2"></i>Тренд движения</h5>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Скорость (Trend)
                            <span class="fw-bold text-danger">{{ number_format($trend['velocity_kmh'] ?? 0, 0, '', ' ') }} км/ч</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Смещение
                            <span class="fw-bold text-primary">{{ number_format($trend['delta_km'] ?? 0, 3, '.', ' ') }} км</span>
                        </li>
                         <li class="list-group-item d-flex justify-content-between align-items-center">
                            Движение
                            <span class="badge {{ ($trend['movement'] ?? false) ? 'bg-success' : 'bg-danger' }} rounded-pill">
                                {{ ($trend['movement'] ?? false) ? 'ДА' : 'НЕТ' }}
                            </span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Конечная точка
                            <span class="fw-bold">{{ round($trend['to_lon'] ?? 0, 2) }} / {{ round($trend['to_lat'] ?? 0, 2) }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <hr>

    {{--- 3. API и ССЫЛКИ ---}}
    <h3 class="mb-3 text-secondary">API и Навигация</h3>
    <div class="row g-4">
        <div class="col-md-12">
            <div class="card shadow-sm hover-card">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1">Текущая позиция МКС</h6>
                        <code class="d-block">{{ $base }}/last</code>
                    </div>
                    <a class="btn btn-primary btn-lg btn-hover" href="/osdr">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Перейти к OSDR
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Leaflet Script --}}
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const lat = {{ $trend['to_lat'] ?? 0 }};
        const lon = {{ $trend['to_lon'] ?? 0 }};
        const hasData = {{ isset($trend['to_lat']) ? 'true' : 'false' }};

        if (hasData) {
            var map = L.map('issMap').setView([lat, lon], 3); 
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            L.marker([lat, lon]).addTo(map)
                .bindPopup('ISS Destination').openPopup();

            const fromLat = {{ $trend['from_lat'] ?? 0 }};
            const fromLon = {{ $trend['from_lon'] ?? 0 }};

            L.marker([fromLat, fromLon], { icon: L.divIcon({className: 'custom-div-icon', html: '<i class="bi bi-pin-map text-secondary fs-4"></i>'}) })
                .addTo(map);

            L.polyline([[fromLat, fromLon], [lat, lon]], {color: 'red', dashArray: '5, 5'}).addTo(map);
        } else {
            document.getElementById('issMap').innerHTML = '<div class="d-flex align-items-center justify-content-center h-100 text-muted">Нет координат</div>';
        }
    });
</script>
@endsection