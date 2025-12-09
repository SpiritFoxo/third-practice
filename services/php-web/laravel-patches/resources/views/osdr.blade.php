@extends('layouts.app')

@section('content')
<div class="container py-4">
    
    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
        <h3 class="mb-0 text-primary">NASA Open Science Data Repository (OSDR)</h3>
        <a href="/iss" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Назад к МКС
        </a>
    </div>

    {{-- Секция фильтрации --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="text-muted small">
            Источник: <code>{{ $src }}</code>
        </div>
        
        <div class="btn-group btn-group-sm" role="group" aria-label="Limit filter">
            <span class="btn btn-light text-muted">Лимит:</span>
            {{-- Кнопки для смены лимита --}}
            @foreach([20, 50, 100] as $l)
                <a href="?limit={{ $l }}" class="btn {{ (isset($currentLimit) && $currentLimit == $l) ? 'btn-primary' : 'btn-outline-secondary' }}">
                    {{ $l }}
                </a>
            @endforeach
        </div>
    </div>
    
    {{-- Таблица данных OSDR --}}
    <div class="table-responsive shadow-sm rounded">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 50px;">ID</th>
                    <th style="width: 150px;">Dataset ID</th>
                    <th style="min-width: 300px;">Заголовок / Имя</th>
                    <th>REST URL</th>
                    <th>Статус</th>
                    <th style="width: 150px;">Обновлено</th>
                    <th style="width: 150px;">Создано</th>
                    <th style="width: 80px;">Raw</th>
                </tr>
            </thead>
            <tbody>
            @forelse($items as $row)
                @php
                    // Используем Carbon для красивого форматирования дат
                    $updated = $row['updated_at'] ?? $row['UpdatedAt'] ?? null;
                    $inserted = $row['inserted_at'] ?? $row['InsertedAt'] ?? null;
                    $status = $row['status'] ?? $row['Status'] ?? 'N/A';
                    $statusColor = match(strtolower($status)) {
                        'new' => 'info',
                        'processed' => 'success',
                        'error' => 'danger',
                        default => 'secondary'
                    };
                @endphp
                <tr>
                    <td class="small text-muted">{{ $row['id'] ?? $row['ID'] ?? '—' }}</td>
                    <td><code class="fw-bold">{{ $row['dataset_id'] ?? '—' }}</code></td>
                    <td title="{{ $row['title'] ?? '—' }}" style="max-width:420px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                        {{ $row['title'] ?? '—' }}
                    </td>
                    <td>
                        @if(!empty($row['rest_url']))
                            <a href="{{ $row['rest_url'] }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary py-0">открыть <i class="bi bi-box-arrow-up-right"></i></a>
                        @else — @endif
                    </td>
                    <td>
                         <span class="badge bg-{{ $statusColor }} text-uppercase">{{ $status }}</span>
                    </td>
                    <td class="small">
                        @if($updated)
                            {{ \Carbon\Carbon::parse($updated)->diffForHumans() }}
                            <div class="text-muted" title="{{ $updated }}">{{ \Carbon\Carbon::parse($updated)->format('Y-m-d H:i') }}</div>
                        @else — @endif
                    </td>
                    <td class="small">
                        @if($inserted)
                            {{ \Carbon\Carbon::parse($inserted)->format('Y-m-d H:i') }}
                        @else — @endif
                    </td>
                    <td>
                        <button class="btn btn-outline-secondary btn-sm py-0" data-bs-toggle="collapse" data-bs-target="#raw-{{ $loop->iteration }}">JSON</button>
                    </td>
                </tr>
                {{-- Строка с RAW JSON --}}
                <tr class="collapse" id="raw-{{ $loop->iteration }}">
                    <td colspan="8" class="bg-light p-2">
                        <pre class="mb-0 small" style="max-height:260px;overflow:auto; white-space: pre-wrap; word-wrap: break-word;">{{ json_encode($row['raw'] ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) }}</pre>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">Нет данных или ошибка запроса</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection