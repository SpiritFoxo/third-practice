@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRS9BMY="
      crossorigin=""/>

<style>
    .leaflet-control-attribution {
        display: none !important;
    }
</style>

<div class="container py-4">
    
    {{-- Заголовок --}}
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
        <div>
            <h1 class="h2 mb-0">Панель управления</h1>
            <small class="text-muted">Мониторинг МКС и дальнего космоса</small>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.location.reload()">
            <i class="bi bi-arrow-clockwise"></i> Обновить
        </button>
    </div>

    {{-- СЕКЦИЯ 1: МКС и ТРЕНД --}}
    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <div class="row g-3">

                {{-- Скорость --}}
                <div class="col-6">
                    <div class="card h-100 border-primary shadow-sm">
                        <div class="card-body text-center p-3">
                            <h6 class="text-muted text-uppercase small mb-2">Скорость (Trend)</h6>
                            <div class="fs-5 fw-bold text-primary">
                                {{ isset($trend['velocity_kmh']) ? number_format($trend['velocity_kmh'], 0, '', ' ') : '—' }}
                            </div>
                            <small class="text-muted">км/ч</small>
                        </div>
                    </div>
                </div>

                {{-- Смещение --}}
                <div class="col-6">
                    <div class="card h-100 border-info shadow-sm">
                        <div class="card-body text-center p-3">
                            <h6 class="text-muted text-uppercase small mb-2">Смещение</h6>
                            <div class="fs-5 fw-bold text-info">
                                {{ isset($trend['delta_km']) ? number_format($trend['delta_km'], 1, '.', '') : '—' }}
                            </div>
                            <small class="text-muted">км за ~{{ isset($trend['dt_sec']) ? round($trend['dt_sec']/60) : 0 }} мин</small>
                        </div>
                    </div>
                </div>

                {{-- Координаты --}}
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <small class="d-block text-muted">Широта (Lat)</small>
                                <span class="fw-bold">{{ isset($iss['payload']['latitude']) ? round($iss['payload']['latitude'], 4) : '—' }}</span>
                            </div>
                            <div class="vr"></div>
                            <div>
                                <small class="d-block text-muted">Долгота (Lon)</small>
                                <span class="fw-bold">{{ isset($iss['payload']['longitude']) ? round($iss['payload']['longitude'], 4) : '—' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        {{-- Карта --}}
        <div class="col-lg-8">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white py-2">
                    <h6 class="mb-0">Карта позиции (To: {{ $trend['to_time'] ?? 'N/A' }})</h6>
                </div>

                <div id="issMap" style="height: 300px; width: 100%; background: #eee;"></div>
            </div>
        </div>
    </div>

    {{-- СЕКЦИЯ 2: АСТРОНОМИЯ и JWST --}}
    <div class="row g-4">
        
        {{-- Астро-события --}}
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Астро-события</h5>
                    <small class="text-muted">Ближайшие события для текущей локации</small>
                </div>

                <ul class="list-group list-group-flush">
                    @forelse ($astroEvents as $event)
                        <li class="list-group-item d-flex justify-content-between align-items-start">
                            <div class="ms-2 me-auto">
                                <div class="fw-bold">{{ $event->name }}</div>

                                @php
                                    $safeDate = $event->date
                                        ? \Carbon\Carbon::parse($event->date)->setTimezone(config('app.timezone'))
                                        : null;
                                @endphp

                                @if ($safeDate)
                                    <small class="text-muted">{{ $safeDate->format('d.m H:i') }}</small>
                                @else
                                    <small class="text-muted">Дата не указана</small>
                                @endif
                            </div>
                            <span class="badge bg-secondary rounded-pill">Event</span>
                        </li>
                    @empty
                        <li class="list-group-item text-center text-muted py-4">
                            Нет данных или сервис недоступен
                        </li>
                    @endforelse
                </ul>
            </div>
        </div>

        {{-- JWST --}}
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Лента Webb Telescope</h5>
                    <span class="badge bg-primary">Live Feed</span>
                </div>

                <div class="card-body">
                    <div class="row g-3">
                        @forelse($jwstItems as $item)
                            <div class="col-md-4 col-sm-6">
                                <div class="card h-100 border-0 shadow-sm">
                                    <img src="{{ $item['url'] }}" class="card-img-top rounded"
                                         alt="JWST" style="height:150px; object-fit:cover;">
                                    <div class="card-body p-2">
                                        <p class="card-text small text-truncate">{{ $item['title'] }}</p>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-12 text-center text-muted">
                                Не удалось загрузить изображения JWST.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

{{-- Скрипты Leaflet --}}
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
        crossorigin=""></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const lat = {{ $trend['to_lat'] ?? 0 }};
    const lon = {{ $trend['to_lon'] ?? 0 }};
    const hasData = {{ isset($trend['to_lat']) ? 'true' : 'false' }};

    if (hasData) {
        var map = L.map('issMap').setView([lat, lon], 3);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: ''
        }).addTo(map);

        L.marker([lat, lon]).addTo(map)
            .bindPopup('ISS Position<br>Lat: ' + lat + '<br>Lon: ' + lon)
            .openPopup();

    } else {
        document.getElementById('issMap').innerHTML =
            '<div class="d-flex align-items-center justify-content-center h-100 text-muted">Нет координат для отображения карты</div>';
    }
});
</script>

@endsection
